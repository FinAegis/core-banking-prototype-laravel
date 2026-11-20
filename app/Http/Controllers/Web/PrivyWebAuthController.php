<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Exceptions\PrivyEmailOtpException;
use App\Domain\Auth\Services\PrivyAccountLinker;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Domain\User\Exceptions\RegistrationNotAllowedException;
use App\Domain\User\Services\ApiRegistrationGate;
use App\Http\Controllers\Concerns\ProvisionsPersonalTeam;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Timebox;

/**
 * Web-facing Privy email-OTP login (and signup) flow.
 *
 * Mirrors the mobile /api/v1/auth/privy-login flow, except:
 *  - We render the OTP form ourselves (no Privy SDK in the browser).
 *  - We call Privy's REST API server-side with our app secret to send +
 *    verify the code, so the response is authoritative without a
 *    separate JWT verification step.
 *  - On success we issue a Laravel session via Auth::login() and redirect
 *    to ->intended('/dashboard'), so the OAuth signup-during-OAuth case
 *    "just works" via the standard Laravel intended-URL machinery.
 *
 * Gated by config('privy.web_login_enabled') (env: MCP_WEB_PRIVY_LOGIN).
 */
final class PrivyWebAuthController extends Controller
{
    use ProvisionsPersonalTeam;

    public function __construct(
        private readonly PrivyEmailOtpClient $privy,
        private readonly RateLimiter $rateLimiter,
        private readonly ApiRegistrationGate $registrationGate,
        private readonly PrivyAccountLinker $linker,
        private readonly Application $app,
        private readonly Timebox $timebox,
    ) {
    }

    /**
     * Step 1: user submitted their email — ask Privy to send an OTP.
     */
    public function sendCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
        ]);
        $email = strtolower((string) $validated['email']);

        $throttleKey = 'privy-web-login:send:' . sha1($email . '|' . (string) $request->ip());
        if ($this->rateLimiter->tooManyAttempts($throttleKey, 5)) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('Too many attempts. Try again in a minute.')]);
        }
        $this->rateLimiter->hit($throttleKey, 60);

        // Every address gets the same answer. The code is requested from
        // Privy only for an address that could sign in or sign up, after the
        // response has been sent.
        if ($this->maySignIn($email)) {
            $this->sendCodeAfterResponse($email);
        }

        return redirect()
            ->route('login', ['email' => $email, 'step' => 'verify'])
            ->with('status', __('If this email can sign in, a 6-digit code is on its way to :email.', ['email' => $email]));
    }

    /**
     * Ask Privy for the code once the response has gone out, so the answer
     * and its timing do not depend on the address. A failed send is
     * reported; the user can request a new code.
     */
    private function sendCodeAfterResponse(string $email): void
    {
        $privy = $this->privy;
        $done = false;

        $this->app->terminating(static function () use ($privy, $email, &$done): void {
            // Terminating callbacks can run more than once per process.
            if ($done) {
                return;
            }

            $done = true;

            try {
                $privy->sendCode($email);
            } catch (PrivyEmailOtpException $e) {
                report($e);
            }
        });
    }

    /**
     * Step 2: user submitted the OTP — verify with Privy, log in / sign up.
     */
    public function verifyCode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email:rfc', 'max:191'],
            'code'  => ['required', 'string', 'min:4', 'max:12'],
        ]);
        $email = strtolower((string) $validated['email']);
        $code = (string) $validated['code'];

        $throttleKey = 'privy-web-login:verify:' . sha1($email . '|' . (string) $request->ip());
        if ($this->rateLimiter->tooManyAttempts($throttleKey, 6)) {
            return redirect()
                ->route('login', ['email' => $email, 'step' => 'verify'])
                ->withErrors(['code' => __('Too many attempts. Request a new code.')]);
        }
        $this->rateLimiter->hit($throttleKey, 600);

        // A refused code takes at least the configured time, whether or not
        // Privy was asked, so the response time does not show which
        // addresses may sign in. A correct code returns at once.
        $minimum = max(0, (int) config('privy.web_verify_min_duration_ms', 1500)) * 1000;

        return $this->timebox->call(
            fn (Timebox $timebox): RedirectResponse => $this->completeVerification($request, $timebox, $email, $code, $throttleKey),
            $minimum,
        );
    }

    private function completeVerification(Request $request, Timebox $timebox, string $email, string $code, string $throttleKey): RedirectResponse
    {
        // No code was sent to such an address (see sendCode()), so the
        // answer is the usual invalid-code error, without a call to Privy.
        if (! $this->maySignIn($email)) {
            return $this->invalidCode($email);
        }

        try {
            $resolved = $this->privy->loginWithCode($email, $code);
        } catch (PrivyEmailOtpException $e) {
            report($e);

            return $this->invalidCode($email);
        }

        // The code was correct: nothing left to hide.
        $timebox->returnEarly();

        try {
            // Returning identity, then an existing account with this email
            // (linked), then a new account under the registration switch.
            $user = $this->linker->resolve($resolved['id'], $resolved['email'], PrivyAccountLinker::FLOW_WEB);
        } catch (RegistrationNotAllowedException $e) {
            // Same switch as the mobile Privy login: an account created here
            // could otherwise sign in on mobile while sign-ups are closed.
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => $e->errorCode === RegistrationNotAllowedException::CODE_INVITATION_REQUIRED
                        ? __('Sign-up is by invitation only. Use the email address your invitation was sent to.')
                        : __('New account registration is currently closed.'),
                ])
                ->with('error_code', $e->errorCode);
        }

        // Returning a 409 Conflict from a redirect form is awkward; instead we
        // surface a flash error and bounce back to /login. This is the rare
        // case where the account with this email cannot be linked
        // automatically: it is linked to a different Privy identity or has
        // confirmed two-factor authentication. The mobile flow returns 409 +
        // {error.code: EMAIL_ALREADY_EXISTS}; we mirror the same error.code in
        // the flash session so support can diagnose tickets across both
        // transports without copy-pasting flash strings.
        if ($user === null) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('This email address belongs to an account that we cannot link automatically. Please contact support.'),
                ])
                ->with('error_code', 'EMAIL_ALREADY_EXISTS');
        }

        // Every team-aware Blade view dereferences Auth::user()->currentTeam,
        // so a teamless user 500s on the dashboard. Provision on every login
        // (not just signup) — this also heals users left teamless by a
        // pre-fix signup that 500'd after the User row was created.
        $this->ensurePersonalTeam($user);

        $this->rateLimiter->clear($throttleKey);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Whether the OTP flow may run for this address: a local account
     * already uses it, or the API registration switch would admit a new
     * account for it (config/registration.php). Otherwise neither step
     * calls Privy, so no Privy-side user or code is created for it.
     */
    private function maySignIn(string $email): bool
    {
        return $this->hasLocalAccount($email) || $this->registrationGate->wouldAdmit($email);
    }

    private function hasLocalAccount(string $email): bool
    {
        if (User::query()->where('email', $email)->exists()) {
            return true;
        }

        return User::query()->whereRaw('LOWER(email) = ?', [$email])->exists();
    }

    private function invalidCode(string $email): RedirectResponse
    {
        return redirect()
            ->route('login', ['email' => $email, 'step' => 'verify'])
            ->withErrors(['code' => __('That code did not work. Try again or request a new one.')]);
    }
}
