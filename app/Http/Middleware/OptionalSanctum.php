<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authentification optionnelle : si un Bearer token est présent et valide,
 * on définit l'utilisateur sur la requête. Sinon on continue sans utilisateur.
 * Utilisé pour GET /communication/active (dashboard + login).
 */
class OptionalSanctum
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->bearerToken()) {
            try {
                $user = auth('sanctum')->user();
                if ($user) {
                    $request->setUserResolver(fn () => $user);
                }
            } catch (\Throwable) {
                // Token invalide ou expiré : on continue sans utilisateur
            }
        }

        return $next($request);
    }
}
