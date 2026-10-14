<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectIfPasswordResetRequired
{
    // Allow profile and logout routes while a password change is required.
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
