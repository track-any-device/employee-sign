<?php

namespace Tests\Feature\Devices;

use App\Events\EmployeesChanged;
use App\Models\Employee;
use App\Services\Devices\TemplateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class EmployeeChangesTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    private function changes(string $token, int $cursor = 0, array $query = []): array
    {
        return $this->getJson('/api/device/v1/employees/changes?'.http_build_query(['cursor' => $cursor, ...$query]), $this->deviceHeaders($token))
            ->assertOk()->json();
    }

    private function createEmployee(array $data): Employee
    {
        $user = $this->verifiedUser();
        $this->actingAs($user)->post(route('employees.store'), ['status' => 'active', ...$data])->assertRedirect()->assertSessionHasNoErrors();

        return Employee::where('name', $data['name'])->sole();
    }

    public function test_only_allowed_employees_are_upserted_with_templates(): void
    {
        [$device, $token] = $this->pairedDevice();
        [$other] = $this->pairedDevice(attributes: ['name' => 'Other']);

        $mine = $this->createEmployee(['name' => 'Ayesha', 'code' => 'E1', 'device_ids' => [$device->id]]);
        $everyone = $this->createEmployee(['name' => 'Bilal', 'code' => 'E2', 'all_devices' => true]);
        $notMine = $this->createEmployee(['name' => 'Chand', 'code' => 'E3', 'device_ids' => [$other->id]]);
        TemplateStore::add($mine, $this->faceTemplate(), null);

        $feed = $this->changes($token);
        $byId = collect($feed['changes'])->keyBy('employee.id');

        $this->assertSame('upsert', $byId[$mine->id]['op']);
        $this->assertCount(1, $byId[$mine->id]['templates']);
        $this->assertSame(['id', 'type', 'model_version', 'data_base64', 'sha256'], array_keys($byId[$mine->id]['templates'][0]));
        $this->assertSame('upsert', $byId[$everyone->id]['op']);
        $this->assertSame('delete', $byId[$notMine->id]['op']); // tombstone, no data
        $this->assertSame([], $byId[$notMine->id]['templates']);
        $this->assertFalse($feed['has_more']);

        // Nothing new since the returned cursor.
        $this->assertSame([], $this->changes($token, (int) $feed['cursor'])['changes']);
    }

    public function test_cursor_paginates_and_reports_deletes_and_removal_from_device(): void
    {
        [$device, $token] = $this->pairedDevice();
        $a = $this->createEmployee(['name' => 'A', 'device_ids' => [$device->id]]);
        $b = $this->createEmployee(['name' => 'B', 'device_ids' => [$device->id]]);
        $this->createEmployee(['name' => 'C', 'device_ids' => [$device->id]]);

        $page1 = $this->changes($token, 0, ['limit' => 2]);
        $this->assertCount(2, $page1['changes']);
        $this->assertTrue($page1['has_more']);
        $page2 = $this->changes($token, (int) $page1['cursor'], ['limit' => 2]);
        $this->assertCount(1, $page2['changes']);
        $this->assertFalse($page2['has_more']);
        $cursor = (int) $page2['cursor'];

        $this->actingAs($this->verifiedUser())->delete(route('employees.destroy', $a))->assertRedirect();
        $this->put(route('devices.employees.update', $device), ['employee_ids' => [$this->employeeId('C')]])->assertRedirect();

        $changes = collect($this->changes($token, $cursor)['changes'])->keyBy('employee.id');
        $this->assertSame('delete', $changes[$a->id]['op']);
        $this->assertSame('delete', $changes[$b->id]['op']); // no longer allowed on this device
    }

    public function test_merge_is_sent_as_merge_change(): void
    {
        [$device, $token] = $this->pairedDevice();
        $a = $this->createEmployee(['name' => 'A', 'code' => 'E1', 'device_ids' => [$device->id]]);
        $b = $this->createEmployee(['name' => 'B', 'code' => 'E2', 'device_ids' => [$device->id]]);
        $cursor = (int) $this->changes($token)['cursor'];

        $this->actingAs($this->verifiedUser())->post(route('employees.merge.store'), [
            'survivor_id' => $b->id, 'merged_id' => $a->id, 'take_from_merged' => ['name'],
        ])->assertRedirect();

        $changes = collect($this->changes($token, $cursor)['changes'])->keyBy('employee.id');
        $this->assertSame('merge', $changes[$a->id]['op']);
        $this->assertSame($b->id, $changes[$a->id]['merged_into']);
        $this->assertSame('upsert', $changes[$b->id]['op']);
        $this->assertSame('A', $changes[$b->id]['employee']['name']);
    }

    public function test_types_filter_limits_templates(): void
    {
        [$device, $token] = $this->pairedDevice();
        $e = $this->createEmployee(['name' => 'A', 'device_ids' => [$device->id]]);
        TemplateStore::add($e, $this->faceTemplate(), null);
        TemplateStore::add($e, ['type' => 'fp_zn600', 'model_version' => 'ZN600', 'data_base64' => base64_encode(random_bytes(512))], null);

        $all = $this->changes($token)['changes'][0]['templates'];
        $fp = $this->changes($token, 0, ['types' => 'fp_zn600'])['changes'][0]['templates'];

        $this->assertCount(2, $all);
        $this->assertCount(1, $fp);
        $this->assertSame('fp_zn600', $fp[0]['type']);
    }

    public function test_change_hint_is_sent_to_allowed_devices(): void
    {
        [$device] = $this->pairedDevice();
        [$other] = $this->pairedDevice(attributes: ['name' => 'Other']);
        Event::fake([EmployeesChanged::class]);

        $this->createEmployee(['name' => 'A', 'device_ids' => [$device->id]]);

        Event::assertDispatched(EmployeesChanged::class, fn ($e) => $e->deviceId === $device->id);
        Event::assertNotDispatched(EmployeesChanged::class, fn ($e) => $e->deviceId === $other->id);
    }

    private function employeeId(string $name): string
    {
        return Employee::where('name', $name)->value('id');
    }
}
