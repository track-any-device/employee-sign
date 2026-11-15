<?php

namespace Tests\Feature\Devices;

use App\Models\AttendanceEvent;
use App\Models\Device;
use App\Models\Employee;
use App\Models\SyncVersion;
use App\Support\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    public function test_device_only_sees_its_own_tenant(): void
    {
        [, $token] = $this->pairedDevice(tenantId: 7);

        $ours = Employee::factory()->create(['all_devices' => true]); // null tenant
        SyncVersion::create(['employee_id' => $ours->id, 'op' => 'upsert']);

        app(CurrentTenant::class)->set(7);
        $theirs = Employee::factory()->create(['all_devices' => true]);
        SyncVersion::create(['employee_id' => $theirs->id, 'op' => 'upsert']);
        $this->resetTenant();

        $ids = collect($this->getJson('/api/device/v1/employees/changes', $this->deviceHeaders($token))->assertOk()->json('changes'))
            ->pluck('employee.id');

        $this->assertEquals([$theirs->id], $ids->all());
    }

    public function test_attendance_cannot_reference_another_tenants_employee(): void
    {
        [, $token] = $this->pairedDevice(tenantId: 7);
        $foreign = Employee::factory()->create(); // null tenant

        $this->postJson('/api/device/v1/attendance', ['events' => [
            ['device_event_id' => 1, 'employee_id' => $foreign->id, 'method' => 'face'],
        ]], $this->deviceHeaders($token))->assertOk()->assertJsonPath('accepted', [1]);

        $event = AttendanceEvent::withoutGlobalScopes()->sole();
        $this->assertNull($event->employee_id);
        $this->assertSame(7, (int) $event->tenant_id);
        $this->assertSame($foreign->id, $event->raw['employee_id']); // kept for later review
    }

    public function test_web_user_cannot_see_other_tenants_devices_or_employees(): void
    {
        [$foreignDevice] = $this->pairedDevice(tenantId: 7);
        $foreignEmployee = Employee::factory()->forTenant(7)->create();
        $own = Device::factory()->create(['name' => 'Ours']);

        $user = $this->verifiedUser();
        $this->actingAs($user)->get(route('devices.show', $foreignDevice))->assertNotFound();
        $this->actingAs($user)->delete(route('employees.destroy', $foreignEmployee))->assertNotFound();
        $this->actingAs($user)->get(route('devices.index'))
            ->assertInertia(fn ($page) => $page->has('devices', 1)->where('devices.0.id', $own->id));
    }

    public function test_other_tenants_command_result_is_not_found(): void
    {
        [$foreign] = $this->pairedDevice(tenantId: 7);
        [, $token] = $this->pairedDevice();
        $command = $foreign->commands()->create(['name' => 'reboot', 'status' => 'sent', 'issued_at' => now(), 'expires_at' => now()->addHour()]);

        $this->postJson("/api/device/v1/commands/{$command->id}/result", ['status' => 'ok'], $this->deviceHeaders($token))->assertNotFound();
    }
}
