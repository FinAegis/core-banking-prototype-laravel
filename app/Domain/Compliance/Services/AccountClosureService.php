<?php

declare(strict_types=1);

namespace App\Domain\Compliance\Services;

use App\Domain\Compliance\Exceptions\AccountClosureBlockedException;
use App\Models\User;
use App\Support\UserSignIns;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

/**
 * Deletes an account at the holder's request. The app
 * (POST /api/auth/delete-account), the GDPR endpoint and the web profile all
 * use this, so every path erases the same data and keeps the same records
 * (see GdprService::deleteUserData).
 *
 * Order: the erasure runs first. Only after it has succeeded do the
 * sign-ins end. When the erasure throws, the exception reaches the caller
 * and the user stays signed in, so they can try again instead of being
 * signed out with half a deletion.
 */
class AccountClosureService
{
    public const SOURCE_APP = 'app';

    public const SOURCE_GDPR_API = 'gdpr_api';

    public const SOURCE_WEB = 'web';

    public function __construct(
        private readonly GdprService $gdprService,
    ) {
    }

    /**
     * @throws AccountClosureBlockedException when a rule does not allow the deletion yet; nothing is erased
     * @throws Throwable                      when the erasure fails; the sign-ins are left untouched
     */
    public function close(User $user, string $source, ?string $reason = null): void
    {
        $check = $this->gdprService->canDeleteUserData($user);
        if (! $check['can_delete']) {
            throw new AccountClosureBlockedException(array_values($check['reasons']));
        }

        $this->gdprService->deleteUserData($user, [
            'source'                 => $source,
            'reason'                 => $reason,
            'delete_documents'       => true,
            'anonymize_transactions' => true,
        ]);

        $this->purgeStripeCustomerData($user);

        $this->endSignIns($user);
    }

    /**
     * Ends every way into the account: API tokens, OAuth grants, the
     * "remember me" token, stored sessions, devices and API keys. The
     * password is replaced with an unusable one, so sessions in stores that
     * cannot be listed by user end on their next request through the
     * AuthenticateSession middleware.
     */
    private function endSignIns(User $user): void
    {
        $user->forceFill(['password' => Hash::make(Str::random(64))]);

        UserSignIns::endAll($user);
    }

    /**
     * GDPR Article 17 erasure of the Stripe customer, which Cashier does not
     * delete on its own. Best effort: a Stripe outage or an already deleted
     * customer must not block the local erasure, so the failure is logged.
     */
    private function purgeStripeCustomerData(User $user): void
    {
        if (empty($user->stripe_id)) {
            return;
        }

        try {
            $user->asStripeCustomer()->delete();
        } catch (Throwable $e) {
            Log::warning('gdpr.stripe_customer_delete_failed', [
                'user_id' => $user->id,
                'error'   => $e->getMessage(),
            ]);
        }

        $user->forceFill([
            'stripe_id'    => null,
            'pm_type'      => null,
            'pm_last_four' => null,
        ])->save();
    }
}
