<?php

namespace Tests\Feature\Devices;

use App\Models\AuditLog;
use App\Models\BiometricConsent;
use App\Models\BiometricTemplate;
use App\Models\Employee;
use App\Services\Devices\TemplateStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class EmployeePushTest extends TestCase
{
    use InteractsWithDevices, RefreshDatabase;

    private function consent(): array
    {
        return ['given_by' => 'HR Manager', 'given_at' => '2026-11-15T09:00:00Z'];
    }

    public function test_push_matches_by_code_creates_new_and_dedupes_templates(): void
    {
        [$device, $token] = $this->pairedDevice();
        $existing = Employee::factory()->create(['code' => 'E100', 'name' => 'Existing']);
        $face = $this->faceTemplate();

        $response = $this->postJson('/api/device/v1/employees/push', [
            'employees' => [
                ['local_id' => 1, 'name' => 'Existing (device)', 'code' => 'E100', 'templates' => [$face, $face]],
                ['local_id' => 2, 'name' => 'New Person', 'code' => null, 'templates' => [$this->faceTemplate()]],
            ],
            'consent' => $this->consent(),
        ], $this->deviceHeaders($token))->assertOk();

        $mapping = collect($response->json('mapping'))->keyBy('local_id');
        $this->assertSame($existing->id, $mapping[1]['employee_id']);
        $this->assertFalse($mapping[1]['created']);
        $this->assertSame(1, $mapping[1]['templates_added']); // duplicate in the same push ignored
        $this->assertTrue($mapping[2]['created']);
        $this->assertSame('Existing', $existing->fresh()->name); // server name wins on match

        $this->assertSame(2, Employee::count());
        $this->assertSame(2, BiometricTemplate::count());
        $this->assertTrue($existing->isAllowedOn($device));

        // Same push again: no new templates.
        $this->postJson('/api/device/v1/employees/push', [
            'employees' => [['local_id' => 1, 'name' => 'x', 'code' => 'E100', 'templates' => [$face]]],
            'consent' => $this->consent(),
        ], $this->deviceHeaders($token))->assertOk()->assertJsonPath('mapping.0.templates_added', 0);
        $this->assertSame(2, BiometricTemplate::count());

        $consent = BiometricConsent::first();
        $this->assertSame('HR Manager', $consent->given_by);
        $this->assertSame($device->id, $consent->device_id);
        $this->assertTrue(AuditLog::where('action', 'employee.consent_upload')->exists());
    }

    public function test_template_data_is_encrypted_at_rest_and_hidden(): void
    {
        [, $token] = $this->pairedDevice();
        $face = $this->faceTemplate();

        $this->postJson('/api/device/v1/employees/push', [
            'employees' => [['local_id' => 1, 'name' => 'A', 'code' => 'E1', 'templates' => [$face]]],
            'consent' => $this->consent(),
        ], $this->deviceHeaders($token))->assertOk();

        $raw = DB::table('biometric_templates')->value('data');
        $this->assertNotSame($face['data_base64'], $raw);
        $this->assertSame($face['data_base64'], BiometricTemplate::first()->data);
        $this->assertArrayNotHasKey('data', BiometricTemplate::first()->toArray());
    }

    public function test_consent_is_required_and_templates_are_validated(): void
    {
        [, $token] = $this->pairedDevice();

        $this->postJson('/api/device/v1/employees/push', [
            'employees' => [['local_id' => 1, 'name' => 'A', 'templates' => [$this->faceTemplate()]]],
        ], $this->deviceHeaders($token))->assertUnprocessable()->assertJsonValidationErrors('consent');

        $this->postJson('/api/device/v1/employees/push', [
            'employees' => [['local_id' => 1, 'name' => 'A', 'templates' => [$this->faceTemplate(random_bytes(100))]]],
            'consent' => $this->consent(),
        ], $this->deviceHeaders($token))->assertUnprocessable()->assertJsonValidationErrors('employees.0.templates.0');

        $bad = $this->faceTemplate();
        $bad['sha256'] = str_repeat('0', 64);
        $this->postJson('/api/device/v1/employees/push', [
            'employees' => [['local_id' => 1, 'name' => 'A', 'templates' => [$bad]]],
            'consent' => $this->consent(),
        ], $this->deviceHeaders($token))->assertUnprocessable();

        $this->assertSame(0, Employee::count());
    }

    public function test_web_employee_list_shows_counts_not_template_data(): void
    {
        $e = Employee::factory()->create();
        TemplateStore::add($e, $this->faceTemplate(), null);

        $this->actingAs($this->verifiedUser())->get(route('employees.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('employees/index')
                ->where('employees.data.0.templates.face_mfn_s8_v1', 1)
                ->missing('employees.data.0.templates.0.data'));
        $this->assertStringNotContainsString(BiometricTemplate::first()->data, $this->get(route('employees.index'))->getContent());
    }
}
