<?php

namespace App\Http\Middleware;

use App\Support\Access\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

// Ends the session of any account that is no longer active (suspended, deleted) on the next web request.
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user !== null && ! $user->isActive()) {
            app(AuditLogger::class)->record('auth.inactive_session_ended', $user, null, 'denied');
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors(['email' => 'This account cannot be used. Contact GSK support.']);
        }

        return $next($request);
    }
}
