<?php

namespace Tests\Feature\Access;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RouteAuditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function gsk_079_every_route_requires_authentication_or_is_an_explicit_public_route(): void
    {
        $public = (array) config('gsk.public_routes', []);
        $offenders = [];

        foreach (Route::getRoutes() as $route) {
            $uri = ltrim($route->uri(), '/');
            $uri = $uri === '' ? '/' : $uri;
            if (in_array($uri, $public, true)) {
                continue;
            }
            $requiresAuth = false;
            foreach ($route->gatherMiddleware() as $middleware) {
                if (is_string($middleware) && (str_starts_with($middleware, 'auth') || str_contains($middleware, 'Authenticate'))) {
                    $requiresAuth = true;
                    break;
                }
            }
            if (! $requiresAuth) {
                $offenders[] = implode('|', $route->methods()) . ' ' . $route->uri();
            }
        }

        $this->assertSame([], $offenders, "Routes that are neither authenticated nor allow-listed:\n" . implode("\n", $offenders));
    }

    #[Test]
    public function the_public_route_allow_list_has_no_wildcard_or_unexpected_api_entries(): void
    {
        $public = (array) config('gsk.public_routes', []);

        foreach ($public as $uri) {
            $this->assertStringNotContainsString('*', $uri);
        }
        $api = array_values(array_filter($public, fn (string $u) => str_starts_with($u, 'api/')));
        sort($api);
        $this->assertSame(['api/v1/health', 'api/v1/welfare-cases'], $api, 'Adding a public API route needs a documented decision.');
    }
}
