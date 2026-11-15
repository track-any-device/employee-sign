<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TrustProxiesTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirects_use_https_and_client_ip_behind_the_proxy(): void
    {
        $this->actingAs(User::factory()->create())
            ->withHeaders([
                'X-Forwarded-Proto' => 'https',
                'X-Forwarded-Host' => 'employee-sign.example.com',
                'X-Forwarded-Port' => '443',
                'X-Forwarded-For' => '203.0.113.7',
            ])
            ->post('/logout')
            ->assertRedirect('https://employee-sign.example.com');

        $this->withHeaders(['X-Forwarded-For' => '203.0.113.7'])->get('/up');
        $this->assertSame('203.0.113.7', request()->ip());
    }
}
