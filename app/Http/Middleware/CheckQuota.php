<?php

namespace App\Http\Middleware;

use App\Services\QuotaService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckQuota
{
    public function __construct(
        protected QuotaService $quotaService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return response()->json([
                'message' => 'Authentification requise.',
            ], 401);
        }

        // Check if user can create event
        if (!$this->quotaService->canCreateEvent($user)) {
            $quota = $this->quotaService->getCreationsQuota($user);
            
            return response()->json([
                'message' => 'Quota de création d\'événements atteint.',
                'error' => 'quota_exceeded',
                'quota' => $quota,
                'actions' => [
                    'upgrade' => [
                        'label' => 'Passer à un plan supérieur',
                        'url' => '/pricing',
                    ],
                    'topup' => [
                        'label' => 'Acheter des crédits supplémentaires',
                        'url' => '/top-up',
                    ],
                ],
            ], 403);
        }

        return $next($request);
    }
}

