<?php

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEventAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission = 'view'): Response
    {
        $event = $request->route('event');

        if (!$event instanceof Event) {
            $event = Event::find($request->route('event'));
        }

        if (!$event) {
            abort(404, 'Événement non trouvé.');
        }

        $user = $request->user();

        if (!$user) {
            abort(401, 'Authentification requise.');
        }

        $hasAccess = match ($permission) {
            'view' => $event->canBeViewedBy($user),
            'edit', 'update' => $event->canBeEditedBy($user),
            'delete' => $event->user_id === $user->id,
            default => false,
        };

        if (!$hasAccess) {
            abort(403, 'Vous n\'avez pas accès à cet événement.');
        }

        return $next($request);
    }
}
