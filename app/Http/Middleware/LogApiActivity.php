<?php

namespace App\Http\Middleware;

use App\Jobs\StoreActivityLogJob;
use App\Models\ActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LogApiActivity
{
    /**
     * Routes à exclure du logging automatique (pour éviter les boucles ou le bruit).
     */
    protected array $excludedRoutes = [
        'api/activity-logs/batch',
        'api/admin/activity-logs',
        'api/admin/activity-logs/stats',
        'api/admin/activity',
        'api/user',
        'api/notifications/unread-count',
        'api/notifications/recent',
    ];

    /**
     * Préfixes à exclure.
     */
    protected array $excludedPrefixes = [
        'api/auth/',
        'api/communication/active',
    ];

    /**
     * Champs sensibles à ne jamais loguer.
     */
    protected array $sensitiveFields = [
        'password',
        'password_confirmation',
        'current_password',
        'token',
        'secret',
        'api_key',
        'credit_card',
        'card_number',
        'cvv',
        'otp',
    ];

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $this->logRequest($request, $response);

        return $response;
    }

    /**
     * Log la requête API de manière asynchrone.
     */
    protected function logRequest(Request $request, Response $response): void
    {
        if (!$this->shouldLog($request)) {
            return;
        }

        $user = $request->user();
        if (!$user) {
            return;
        }

        $method = strtoupper($request->method());
        $path = $request->path();
        $statusCode = $response->getStatusCode();

        // Ne loguer que les requêtes réussies ou échouées (pas les 304, etc.)
        if ($statusCode < 200 || $statusCode >= 500) {
            return;
        }

        $action = $this->resolveAction($method);
        $description = $this->buildDescription($method, $path, $statusCode);
        $actorType = $user->isAdmin() ? ActivityLog::ACTOR_ADMIN : ActivityLog::ACTOR_USER;

        $logData = [
            'user_id' => $user->id,
            'actor_type' => $actorType,
            'action' => $action,
            'description' => $description,
            'model_type' => null,
            'model_id' => null,
            'old_values' => null,
            'new_values' => $this->extractRequestData($request, $method),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'source' => ActivityLog::SOURCE_API,
            'page_url' => null,
            'session_id' => null,
            'metadata' => [
                'http_method' => $method,
                'endpoint' => $path,
                'status_code' => $statusCode,
            ],
        ];

        StoreActivityLogJob::dispatch($logData);
    }

    /**
     * Déterminer si la requête doit être loguée.
     */
    protected function shouldLog(Request $request): bool
    {
        $path = $request->path();

        // Exclure les routes spécifiques
        foreach ($this->excludedRoutes as $route) {
            if ($path === $route) {
                return false;
            }
        }

        // Exclure les préfixes
        foreach ($this->excludedPrefixes as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return false;
            }
        }

        // Exclure les requêtes OPTIONS (CORS preflight)
        if ($request->isMethod('OPTIONS')) {
            return false;
        }

        // Exclure les requêtes HEAD
        if ($request->isMethod('HEAD')) {
            return false;
        }

        return true;
    }

    /**
     * Résoudre le type d'action à partir de la méthode HTTP.
     */
    protected function resolveAction(string $method): string
    {
        return match ($method) {
            'GET' => 'api_read',
            'POST' => 'api_create',
            'PUT', 'PATCH' => 'api_update',
            'DELETE' => 'api_delete',
            default => 'api_request',
        };
    }

    /**
     * Construire une description lisible de la requête.
     */
    protected function buildDescription(string $method, string $path, int $statusCode): string
    {
        $cleanPath = preg_replace('/\/\d+/', '/{id}', $path);
        $statusLabel = $statusCode >= 400 ? " (erreur {$statusCode})" : '';

        return "{$method} /{$cleanPath}{$statusLabel}";
    }

    /**
     * Extraire les données pertinentes de la requête (en excluant les champs sensibles).
     */
    protected function extractRequestData(Request $request, string $method): ?array
    {
        // Ne capturer les données que pour les requêtes modificatrices
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'])) {
            return null;
        }

        $data = $request->except($this->sensitiveFields);

        if (empty($data)) {
            return null;
        }

        // Limiter la taille des données capturées
        $encoded = json_encode($data);
        if ($encoded && strlen($encoded) > 5000) {
            return ['_truncated' => true, '_size' => strlen($encoded)];
        }

        return $data;
    }
}
