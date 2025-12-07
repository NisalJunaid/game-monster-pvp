<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class BroadcastingAuthRouteTest extends TestCase
{
    public function test_broadcasting_auth_route_exists_and_restricts_guests(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this->post('/broadcasting/auth');

        $this->assertContains($response->getStatusCode(), [302, 401, 403]);
    }
}
