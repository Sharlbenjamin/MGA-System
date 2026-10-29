<?php

namespace Tests\Feature;

use Tests\TestCase;

class ApiInfrastructureSmokeTest extends TestCase
{
    public function test_api_health_returns_json(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson([
                'ok' => true,
                'service' => 'mga-api',
            ])
            ->assertJsonStructure(['ok', 'service', 'env']);
    }

    public function test_login_requires_a_json_payload(): void
    {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonStructure(['message']);
    }

    public function test_login_rejects_an_invalid_email(): void
    {
        $this->postJson('/api/login', [
            'email' => 'not-an-email',
            'password' => 'secret',
        ])->assertStatus(422);
    }

    public function test_get_login_explains_that_post_is_required(): void
    {
        $this->getJson('/api/login')
            ->assertStatus(405)
            ->assertJsonStructure(['message']);
    }

    public function test_lead_and_file_overview_require_authentication(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Auth\AuthenticationException::class,
            $this->getJson('/api/leads/overview')->exception,
        );
        $this->assertInstanceOf(
            \Illuminate\Auth\AuthenticationException::class,
            $this->getJson('/api/files/overview')->exception,
        );
        $this->assertInstanceOf(
            \Illuminate\Auth\AuthenticationException::class,
            $this->postJson('/api/leads/1/send-email')->exception,
        );
    }

    public function test_dashboard_and_push_status_require_authentication(): void
    {
        $this->assertInstanceOf(
            \Illuminate\Auth\AuthenticationException::class,
            $this->getJson('/api/dashboard')->exception,
        );
        $this->assertInstanceOf(
            \Illuminate\Auth\AuthenticationException::class,
            $this->getJson('/api/push-token/status')->exception,
        );
    }
}
