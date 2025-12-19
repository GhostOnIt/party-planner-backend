<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    /**
     * Test the API status endpoint.
     */
    public function test_api_status_returns_successful_response(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertJson([
                'status' => 'operational',
            ]);
    }

    /**
     * Test the health check endpoint.
     */
    public function test_health_check_returns_successful_response(): void
    {
        $response = $this->get('/health');

        $response->assertOk()
            ->assertJson([
                'status' => 'healthy',
            ]);
    }
}
