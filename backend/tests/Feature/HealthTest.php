<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthTest extends TestCase
{
    public function test_root_returns_json_status_ok_without_login(): void
    {
        $this->getJson('/')
            ->assertOk()
            ->assertExactJson(['app' => config('app.name'), 'status' => 'ok']);

        $this->get('/')->assertOk()->assertHeader('Content-Type', 'application/json');
    }

    public function test_framework_health_endpoint_is_up(): void
    {
        $this->get('/up')->assertOk();
    }
}
