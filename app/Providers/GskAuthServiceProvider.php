<?php

namespace App\Providers;

use App\Actions\Gsk\ResetUserPassword;
use App\Http\Middleware\BlockStaffMfaDisable;
use App\Http\Middleware\EnsureAccountActive;
use App\Http\Middleware\EnsureStaffMfa;
use App\Http\Responses\GenericPasswordResetLinkResponse;
use App\Models\User;
use App\Support\Access\AuditLogger;
use App\Support\Access\SessionRevoker;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

// Wires Laravel Fortify to GSK rules: server-side sessions, staff MFA, generic reset answers, throttling and audit.
// Registered by scripts/bootstrap-laravel.sh in bootstrap/providers.php.
class GskAuthServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, GenericPasswordResetLinkResponse::class);
    }

    public function boot(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);

        // Only active, non-deleted accounts can sign in. The failure is generic so it does not reveal why.
        Fortify::authenticateUsing(function (Request $request) {
            $user = User::query()->where('email', Str::lower((string) $request->input('email')))->first();
            if ($user !== null && $user->isActive() && Hash::check((string) $request->input('password'), $user->password)) {
                return $user;
            }

            return null;
        });

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(5)->by(Str::lower((string) $request->input('email')) . '|' . $request->ip());
        });
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(5)->by((string) $request->session()->get('login.id'));
        });

        foreach ([EnsureAccountActive::class, EnsureStaffMfa::class, BlockStaffMfaDisable::class] as $middleware) {
            Route::pushMiddlewareToGroup('web', $middleware);
        }

        $this->registerAuditListeners();
    }

    private function registerAuditListeners(): void
    {
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->save();
                app(AuditLogger::class)->record('auth.login', $event->user);
            }
        });

        Event::listen(Failed::class, function (Failed $event) {
            $email = (string) ($event->credentials['email'] ?? '');
            // Only a short hash of the email is kept for correlation. The password is never recorded.
            app(AuditLogger::class)->record('auth.login_failed', null, null, 'failure', null, [
                'email_hash' => substr(hash('sha256', Str::lower($email)), 0, 16),
            ]);
        });

        Event::listen(Logout::class, function (Logout $event) {
            app(AuditLogger::class)->record('auth.logout', $event->user instanceof User ? $event->user : null);
        });

        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            if ($event->user instanceof User) {
                app(SessionRevoker::class)->revokeAll($event->user);
                app(AuditLogger::class)->record('auth.password_reset', $event->user);
            }
        });

        // Fortify two-factor events, registered by class name. If a name differs in the installed version the
        // matching audit event is simply not written, and the audit tests will say so.
        $events = [
            'Laravel\Fortify\Events\TwoFactorAuthenticationEnabled' => ['auth.mfa_enabled', 'success'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationConfirmed' => ['auth.mfa_confirmed', 'success'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationDisabled' => ['auth.mfa_disabled', 'success'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationChallenged' => ['auth.mfa_challenged', 'success'],
            'Laravel\Fortify\Events\TwoFactorAuthenticationFailed' => ['auth.mfa_failed', 'failure'],
            'Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided' => ['auth.mfa_code_valid', 'success'],
            'Laravel\Fortify\Events\RecoveryCodeReplayed' => ['auth.mfa_recovery_code_replayed', 'denied'],
            'Laravel\Fortify\Events\RecoveryCodesGenerated' => ['auth.mfa_recovery_codes_generated', 'success'],
        ];
        foreach ($events as $class => [$action, $outcome]) {
            Event::listen($class, function (object $event) use ($action, $outcome) {
                $user = property_exists($event, 'user') ? $event->user : null;
                app(AuditLogger::class)->record($action, $user instanceof User ? $user : null, null, $outcome);
            });
        }
    }
}
