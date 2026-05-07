<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Domain\Auth\Exceptions\PrivyEmailOtpException;
use App\Domain\Auth\Services\PrivyEmailOtpClient;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

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
    public function __construct(
        private readonly PrivyEmailOtpClient $privy,
        private readonly RateLimiter $rateLimiter,
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

        try {
            $this->privy->sendCode($email);
        } catch (PrivyEmailOtpException $e) {
            report($e);

            return back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => __('We could not send the code right now. Please try again.')]);
        }

        return redirect()
            ->route('login', ['email' => $email, 'step' => 'verify'])
            ->with('status', __('We sent a 6-digit code to :email.', ['email' => $email]));
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

        try {
            $resolved = $this->privy->loginWithCode($email, $code);
        } catch (PrivyEmailOtpException $e) {
            report($e);

            return redirect()
                ->route('login', ['email' => $email, 'step' => 'verify'])
                ->withErrors(['code' => __('That code did not work. Try again or request a new one.')]);
        }

        $user = $this->resolveOrCreateUser($resolved['id'], $resolved['email']);

        // Returning a 409 Conflict from a redirect form is awkward; instead we
        // surface a flash error and bounce back to /login. This is the rare
        // case where a Privy email collides with an existing non-Privy account.
        // The mobile flow returns 409 + {error.code: EMAIL_ALREADY_EXISTS}; we
        // mirror the same error.code in the flash session so support can
        // diagnose tickets across both transports without copy-pasting flash
        // strings. Mirrors the alignment ask from the mobile dev review.
        if ($user === null) {
            return redirect()
                ->route('login')
                ->withErrors([
                    'email' => __('An account with this email already exists. Sign in with your existing credentials.'),
                ])
                ->with('error_code', 'EMAIL_ALREADY_EXISTS');
        }

        $this->rateLimiter->clear($throttleKey);

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Match the mobile flow: lookup by privy_user_id; if not found and an
     * unrelated user already owns the email, refuse (account-takeover-shaped);
     * otherwise create. Web signup is intentionally one-step — there's no
     * separate /register surface, the first OTP success either signs you in
     * or signs you up depending on whether the Privy DID is known.
     */
    private function resolveOrCreateUser(string $privyUserId, string $email): ?User
    {
        $user = User::where('privy_user_id', $privyUserId)->first();
        if ($user instanceof User) {
            return $user;
        }

        $existing = User::where('email', $email)->first();
        if ($existing instanceof User) {
            return null;
        }

        return User::create([
            'name'              => 'New User',
            'email'             => $email,
            'password'          => Str::random(64),
            'email_verified_at' => now(),
            'privy_user_id'     => $privyUserId,
            'privy_linked_at'   => now(),
        ]);
    }
}
