<?php

/**
 * IapReceipt — raw receipt + money triple per ADR-0004.
 *
 * Pseudonymisation (Backend-Q7 α) nulls the personal columns on Article 17
 * erasure but retains tier/amount/period for tax retention. The hash column
 * makes post-erasure webhook matching possible (REFUND / RENEWAL).
 *
 * One row per receipt event: initial purchase + each renewal. Many receipts
 * per `iap_subscription_id`.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-2-iap-design.md §5.2
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int                             $id
 * @property int|null                        $user_id
 * @property string                          $iap_subscription_id
 * @property string                          $store              apple|google
 * @property string|null                     $original_transaction_id
 * @property string|null                     $original_transaction_id_hash
 * @property string|null                     $apple_app_account_token
 * @property string|null                     $google_obfuscated_account_id
 * @property string                          $product_id
 * @property string|null                     $transaction_id
 * @property string|null                     $receipt_blob
 * @property string                          $tier
 * @property int                             $amount_smallest_unit
 * @property int                             $amount_decimals
 * @property string                          $amount_currency
 * @property \Illuminate\Support\Carbon|null $period_starts_at
 * @property \Illuminate\Support\Carbon|null $period_ends_at
 * @property string                          $environment        sandbox|production
 * @property \Illuminate\Support\Carbon|null $scrubbed_at
 * @property int                             $scrubbed_renewal_count
 */
final class IapReceipt extends Model
{
    public const ENV_SANDBOX = 'sandbox';

    public const ENV_PRODUCTION = 'production';

    protected $table = 'iap_receipts';

    protected $fillable = [
        'user_id',
        'iap_subscription_id',
        'store',
        'original_transaction_id',
        'original_transaction_id_hash',
        'apple_app_account_token',
        'google_obfuscated_account_id',
        'product_id',
        'transaction_id',
        'receipt_blob',
        'tier',
        'amount_smallest_unit',
        'amount_decimals',
        'amount_currency',
        'period_starts_at',
        'period_ends_at',
        'environment',
        'scrubbed_at',
        'scrubbed_renewal_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'amount_smallest_unit'   => 'integer',
            'amount_decimals'        => 'integer',
            'period_starts_at'       => 'datetime',
            'period_ends_at'         => 'datetime',
            'scrubbed_at'            => 'datetime',
            'scrubbed_renewal_count' => 'integer',
        ];
    }
}
