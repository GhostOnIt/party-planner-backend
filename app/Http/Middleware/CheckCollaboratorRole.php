<?php

namespace App\Http\Middleware;

use App\Enums\CollaboratorRole;
use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckCollaboratorRole
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     * @param  string  ...$roles  Allowed roles (owner, editor, viewer)
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
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

        // Owner always has access
        if ($event->user_id === $user->id) {
            return $next($request);
        }

        // Check collaborator role
        $collaborator = $event->collaborators()
            ->where('user_id', $user->id)
            ->first();

        if (!$collaborator) {
            abort(403, 'Vous n\'êtes pas collaborateur de cet événement.');
        }

        // Check if collaborator has one of the required roles
        if (!empty($roles) && !in_array($collaborator->role, $roles)) {
            abort(403, 'Vous n\'avez pas les permissions nécessaires.');
        }

        // Check if invitation was accepted
        if (!$collaborator->isAccepted()) {
            abort(403, 'Vous devez d\'abord accepter l\'invitation.');
        }

        return $next($request);
    }
}
