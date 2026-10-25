<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Staff must have confirmed two-factor authentication before reaching anything except the pages needed to set it up.
// Candidates and employers are not forced to enrol in this stage.
class EnsureStaffMfa
{
    private const ALLOWED = [
        'login', 'logout', 'forgot-password', 'reset-password', 'reset-password/*', 'two-factor-challenge',
        'user/*', 'email/*', 'account/security',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && $user->isStaff() && $user->two_factor_confirmed_at === null && ! $request->is(...self::ALLOWED)) {
            if ($request->expectsJson()) {
                abort(403, 'Two-factor authentication setup is required.');
            }

            return redirect()->route('account.security')->with('status', 'Set up two-factor authentication to continue.');
        }

        return $next($request);
    }
}
