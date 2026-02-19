<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CollectPrometheusMetrics
{
    /**
     * Handle an incoming request.
     *
     * Collecte les métriques HTTP pour Prometheus si disponible.
     * Ignore silencieusement si Prometheus n'est pas configuré (CollectorRegistry absent).
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);
        $route = $request->route()?->getName() ?? $request->path();
        $method = $request->method();

        try {
            $response = $next($request);
            $statusCode = $response->getStatusCode();
            $duration = microtime(true) - $startTime;

            $this->recordMetrics($route, $method, (string) $statusCode, $duration);

            return $response;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;
            $this->recordMetrics($route, $method, '500', $duration);
            throw $e;
        }
    }

    private function recordMetrics(string $route, string $method, string $status, float $duration): void
    {
        try {
            if (!class_exists(\Prometheus\CollectorRegistry::class)) {
                return;
            }
            $registry = app()->make(\Prometheus\CollectorRegistry::class);
            $sanitized = $this->sanitizeRoute($route);

            $registry->getOrRegisterCounter('app', 'http_requests_total', '', ['method', 'route', 'status'])
                ->incBy(1, [$method, $sanitized, $status]);

            $registry->getOrRegisterHistogram('app', 'http_request_duration_seconds', '', ['method', 'route'], [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10])
                ->observe($duration, [$method, $sanitized]);

            if ((int) $status >= 400) {
                $registry->getOrRegisterCounter('app', 'http_errors_total', '', ['method', 'route', 'status'])
                    ->incBy(1, [$method, $sanitized, $status]);
            }
        } catch (\Throwable) {
            // Prometheus non disponible : ignorer silencieusement
        }
    }

    private function sanitizeRoute(string $route): string
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $route);
    }
}
