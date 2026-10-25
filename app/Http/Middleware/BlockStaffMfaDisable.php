<?php

namespace App\Http\Middleware;

use App\Support\Access\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Staff cannot switch off their own two-factor authentication. An authorised administrator resets it
// through App\Support\Access\MfaAdminService, which audits the reset and needs independent approval for privileged accounts.
class BlockStaffMfaDisable
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('DELETE') && $request->is('user/two-factor-authentication')) {
            $user = $request->user();
            if ($user !== null && $user->isStaff()) {
                app(AuditLogger::class)->record('auth.mfa_disable_blocked', $user, null, 'denied');
                abort(403, 'Staff cannot disable two-factor authentication. Ask an administrator.');
            }
        }

        return $next($request);
    }
}
