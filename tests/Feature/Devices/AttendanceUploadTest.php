<?php

namespace Tests\Feature\Devices;

use App\Models\AttendanceEvent;
use App\Models\Employee;
use App\Services\EmployeeMerger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AttendanceUploadTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    public function test_upload_is_idempotent(): void
    {
        [$device, $token] = $this->pairedDevice();
        $employee = Employee::factory()->create();

        $events = [
            ['device_event_id' => 1, 'occurred_at' => '2026-11-15T08:00:00Z', 'uptime_s' => 100, 'employee_id' => $employee->id, 'employee_local_id' => 7, 'method' => 'face', 'similarity' => 0.91],
            ['device_event_id' => 2, 'occurred_at' => null, 'uptime_s' => 160, 'employee_id' => null, 'employee_local_id' => 8, 'method' => 'face', 'similarity' => 0.88],
        ];

        $this->postJson('/api/device/v1/attendance', ['events' => $events], $this->deviceHeaders($token))
            ->assertOk()->assertExactJson(['accepted' => [1, 2], 'duplicates' => [], 'rejected' => []]);

        $this->postJson('/api/device/v1/attendance', ['events' => [...$events, ['device_event_id' => 3, 'method' => 'face', 'similarity' => 0.9]]], $this->deviceHeaders($token))
            ->assertOk()->assertExactJson(['accepted' => [3], 'duplicates' => [1, 2], 'rejected' => []]);

        $this->assertSame(3, AttendanceEvent::count());
        $first = AttendanceEvent::where('device_event_id', 1)->sole();
        $this->assertSame($employee->id, $first->employee_id);
        $this->assertSame('2026-11-15 08:00:00', $first->occurred_at->format('Y-m-d H:i:s'));
        $this->assertNull(AttendanceEvent::where('device_event_id', 2)->sole()->occurred_at);
    }

    public function test_invalid_events_are_rejected_individually_and_in_batch_duplicates_reported(): void
    {
        [, $token] = $this->pairedDevice();

        $this->postJson('/api/device/v1/attendance', ['events' => [
            ['device_event_id' => 10, 'method' => 'face', 'similarity' => 0.9],
            ['device_event_id' => 10, 'method' => 'face', 'similarity' => 0.9],
            ['device_event_id' => 11, 'method' => 'iris'],
            ['device_event_id' => 12, 'method' => 'face', 'similarity' => 1.5],
        ]], $this->deviceHeaders($token))
            ->assertOk()
            ->assertJsonPath('accepted', [10])
            ->assertJsonPath('duplicates', [10])
            ->assertJsonPath('rejected.0.id', 11)
            ->assertJsonPath('rejected.1.id', 12);
    }

    public function test_batch_size_is_limited(): void
    {
        [, $token] = $this->pairedDevice();
        $events = array_map(fn ($i) => ['device_event_id' => $i, 'method' => 'face'], range(1, 501));

        $this->postJson('/api/device/v1/attendance', ['events' => $events], $this->deviceHeaders($token))->assertUnprocessable();
    }

    public function test_events_for_a_merged_employee_land_on_the_survivor(): void
    {
        [, $token] = $this->pairedDevice();
        $a = Employee::factory()->create();
        $b = Employee::factory()->create();
        app(EmployeeMerger::class)->merge($b, $a);

        $this->postJson('/api/device/v1/attendance', ['events' => [
            ['device_event_id' => 1, 'employee_id' => $a->id, 'method' => 'face'],
        ]], $this->deviceHeaders($token))->assertOk();

        $this->assertSame($b->id, AttendanceEvent::sole()->employee_id);
    }
}
