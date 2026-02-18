<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Prometheus\CollectorRegistry;
use Prometheus\Counter;
use Prometheus\Histogram;
use Symfony\Component\HttpFoundation\Response;

class CollectPrometheusMetrics
{
    protected CollectorRegistry $registry;
    protected ?Counter $httpRequestsCounter = null;
    protected ?Histogram $httpRequestDuration = null;
    protected ?Counter $httpErrorsCounter = null;

    public function __construct(CollectorRegistry $registry)
    {
        $this->registry = $registry;
    }

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
            $this->getHttpRequestsCounter()->incBy(1, [
                'method' => $method,
                'route' => $this->sanitizeRoute($route),
                'status' => (string) $statusCode,
            ]);

            // Enregistrer la durée
            $this->getHttpRequestDuration()->observe($duration, [
                'method' => $method,
                'route' => $this->sanitizeRoute($route),
            ]);

            // Compter les erreurs
            if ($statusCode >= 400) {
                $this->getHttpErrorsCounter()->incBy(1, [
                    'method' => $method,
                    'route' => $this->sanitizeRoute($route),
                    'status' => (string) $statusCode,
                ]);
            }

            return $response;
        } catch (\Throwable $e) {
            $duration = microtime(true) - $startTime;

            // Enregistrer l'erreur
            $this->getHttpErrorsCounter()->incBy(1, [
                'method' => $method,
                'route' => $this->sanitizeRoute($route),
                'status' => '500',
            ]);

            $this->getHttpRequestsCounter()->incBy(1, [
                'method' => $method,
                'route' => $this->sanitizeRoute($route),
                'status' => '500',
            ]);

            throw $e;
        }
    }

    /**
     * Obtient ou crée le counter pour les requêtes HTTP totales.
     */
    protected function getHttpRequestsCounter(): Counter
    {
        if ($this->httpRequestsCounter === null) {
            $this->httpRequestsCounter = $this->registry->getOrRegisterCounter(
                namespace: 'app',
                name: 'http_requests_total',
                help: 'Nombre total de requêtes HTTP',
                labels: ['method', 'route', 'status']
            );
        }

        return $this->httpRequestsCounter;
    }

    /**
     * Obtient ou crée l'histogram pour la durée des requêtes HTTP.
     */
    protected function getHttpRequestDuration(): Histogram
    {
        if ($this->httpRequestDuration === null) {
            $this->httpRequestDuration = $this->registry->getOrRegisterHistogram(
                namespace: 'app',
                name: 'http_request_duration_seconds',
                help: 'Durée des requêtes HTTP en secondes',
                labels: ['method', 'route'],
                buckets: [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1, 2.5, 5, 10]
            );
        }

        return $this->httpRequestDuration;
    }

    /**
     * Obtient ou crée le counter pour les erreurs HTTP.
     */
    protected function getHttpErrorsCounter(): Counter
    {
        if ($this->httpErrorsCounter === null) {
            $this->httpErrorsCounter = $this->registry->getOrRegisterCounter(
                namespace: 'app',
                name: 'http_errors_total',
                help: 'Nombre total d\'erreurs HTTP',
                labels: ['method', 'route', 'status']
            );
        }

        return $this->httpErrorsCounter;
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
