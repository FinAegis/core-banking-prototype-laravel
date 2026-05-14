<?php

/**
 * IapReceiptPseudonymiser — Plan B Slice 2 Backend-Q7 α.
 *
 * Called from the GdprController erasure walk. Pseudonymises (not deletes)
 * `iap_receipts` rows so post-erasure store webhooks (REFUND, RENEWAL) can
 * still be matched to the original-but-anonymised subscription via the
 * HMAC-SHA256 hash column. Raw originalTransactionId is nulled.
 *
 * `IAP_RECEIPT_PEPPER` cannot be rotated cleanly — once a receipt is scrubbed,
 * the raw value is gone and re-hashing is impossible. Operator runbook covers
 * the manual ops resolution path on suspected compromise.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.9
 * @see docs/BACKEND_HANDOVER_PLAN_B_REVIEW_DELTAS.md Q7 α
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Iap;

use App\Domain\Compliance\Models\AuditLog;
use App\Domain\Subscription\Models\IapReceipt;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

final class IapReceiptPseudonymiser
{
    /**
     * Walk every `iap_receipts` row belonging to $user and pseudonymise.
     *
     * Returns the count of rows scrubbed.
     */
    public function pseudonymise(User $user, string $requestId): int
    {
        $pepper = (string) config('subscription.iap.receipt_pepper', '');
        if ($pepper === '') {
            // In production / staging an empty pepper would permanently destroy
            // the receipt linkage: pseudonymise() nulls the raw
            // originalTransactionId and stores a NULL hash, while fingerprint()
            // (called from post-erasure webhooks) hashes against an empty key —
            // the two never match. We refuse to proceed so the operator catches
            // the missing config before any user data is wiped.
            if (app()->environment('production', 'staging')) {
                Log::error('iap.pseudonymise.pepper_missing', [
                    'user_id'    => $user->id,
                    'request_id' => $requestId,
                ]);

                throw new RuntimeException(
                    'IAP_RECEIPT_PEPPER must be configured before GDPR erasure runs. '
                    . 'Pseudonymising with an empty pepper permanently breaks '
                    . 'post-erasure webhook lookups (Backend-Q7 α).',
                );
            }

            Log::warning('iap.pseudonymise.pepper_missing', [
                'user_id'    => $user->id,
                'request_id' => $requestId,
                'env'        => app()->environment(),
            ]);
        }

        $scrubbed = 0;

        DB::transaction(function () use ($user, $requestId, $pepper, &$scrubbed): void {
            $rows = IapReceipt::query()
                ->where('user_id', $user->id)
                ->whereNull('scrubbed_at')
                ->lockForUpdate()
                ->get();

            foreach ($rows as $row) {
                $rawId = (string) ($row->original_transaction_id ?? '');

                $hash = $rawId !== '' && $pepper !== ''
                    ? hash_hmac('sha256', $rawId, $pepper)
                    : null;

                $row->fill([
                    'user_id'                      => null,
                    'original_transaction_id'      => null,
                    'original_transaction_id_hash' => $hash,
                    'apple_app_account_token'      => null,
                    'google_obfuscated_account_id' => null,
                    'receipt_blob'                 => null,
                    'scrubbed_at'                  => now(),
                ]);
                $row->save();

                $scrubbed++;

                $this->writeAuditRow($user, $row->id, $requestId);
            }
        });

        return $scrubbed;
    }

    /**
     * Compute the HMAC the post-erasure webhook lookup needs. Used by Apple
     * and Google webhook controllers when the raw lookup misses (the row was
     * already scrubbed).
     */
    public function fingerprint(string $rawIdOrToken): string
    {
        return hash_hmac(
            'sha256',
            $rawIdOrToken,
            (string) config('subscription.iap.receipt_pepper', ''),
        );
    }

    private function writeAuditRow(User $user, int $rowId, string $requestId): void
    {
        try {
            AuditLog::log(
                'gdpr.iap_receipt_pseudonymised',
                $user,
                null,
                null,
                [
                    'row_id'     => $rowId,
                    'request_id' => $requestId,
                    'table'      => 'iap_receipts',
                ],
                'gdpr,compliance,iap',
            );
        } catch (Throwable $e) {
            // AuditLog failure is not fatal — pseudonymisation already
            // succeeded. Log and continue.
            Log::warning('iap.pseudonymise.audit_log_failed', [
                'user_id' => $user->id,
                'row_id'  => $rowId,
                'error'   => $e->getMessage(),
            ]);
        }
    }
}
