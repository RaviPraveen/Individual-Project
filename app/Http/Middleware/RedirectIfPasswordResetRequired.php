<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfPasswordResetRequired
{
    /**
     * Redirect users who have been flagged for a forced password reset
     * to their profile page so they can set a new password.
     * We skip the redirect for the profile/password routes themselves
     * (and logout) to avoid an infinite loop.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && $user->force_password_reset
            && ! $request->routeIs('profile.*', 'logout')
        ) {
            return redirect()->route('profile.edit')
                ->with('force_reset_notice', 'An administrator has reset your password. Please set a new password before continuing.');
        }

        return $next($request);
    }
}
