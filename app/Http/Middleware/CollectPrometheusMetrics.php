<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Prometheus\Facades\Prometheus;
use Symfony\Component\HttpFoundation\Response;

class CollectPrometheusMetrics
{
    /**
     * Handle an incoming request.
     *
     * Collecte les métriques HTTP pour Prometheus :
     * - Nombre de requêtes par route et méthode HTTP
     * - Durée des requêtes
     * - Erreurs HTTP (4xx et 5xx)
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

            // Compter les requêtes totales
            Prometheus::addCounter('http_requests_total')
                ->help('Nombre total de requêtes HTTP')
                ->label('method', $method)
                ->label('route', $this->sanitizeRoute($route))
                ->label('status', (string) $statusCode)
                ->inc();

            // Enregistrer la durée
            Prometheus::addHistogram('http_request_duration_seconds')
                ->help('Durée des requêtes HTTP en secondes')
                ->label('method', $method)
                ->label('route', $this->sanitizeRoute($route))
                ->observe($duration);

            // Compter les erreurs
            if ($statusCode >= 400) {
                Prometheus::addCounter('http_errors_total')
                    ->help('Nombre total d\'erreurs HTTP')
                    ->label('method', $method)
                    ->label('route', $this->sanitizeRoute($route))
                    ->label('status', (string) $statusCode)
                    ->inc();
            }

            return $response;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            // Enregistrer l'erreur
            Prometheus::addCounter('http_errors_total')
                ->help('Nombre total d\'erreurs HTTP')
                ->label('method', $method)
                ->label('route', $this->sanitizeRoute($route))
                ->label('status', '500')
                ->inc();

            Prometheus::addCounter('http_requests_total')
                ->help('Nombre total de requêtes HTTP')
                ->label('method', $method)
                ->label('route', $this->sanitizeRoute($route))
                ->label('status', '500')
                ->inc();

            throw $e;
        }
    }

    /**
     * Nettoie le nom de la route pour éviter les caractères problématiques dans les labels Prometheus.
     */
    private function sanitizeRoute(string $route): string
    {
        // Remplacer les caractères spéciaux par des underscores
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $route);
    }
}
