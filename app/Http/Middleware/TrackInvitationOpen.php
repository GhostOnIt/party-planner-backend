<?php

namespace App\Http\Middleware;

use App\Models\Invitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackInvitationOpen
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        if ($token) {
            $invitation = Invitation::where('token', $token)->first();

            if ($invitation && !$invitation->opened_at) {
                $invitation->update(['opened_at' => now()]);
            }
        }

        return $next($request);
    }
}
