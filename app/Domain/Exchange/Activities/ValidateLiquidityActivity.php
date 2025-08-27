<?php

namespace App\Domain\Exchange\Activities;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Domain\Exchange\Projections\LiquidityPool as PoolProjection;
use App\Domain\Exchange\ValueObjects\LiquidityAdditionInput;
use App\Domain\Exchange\ValueObjects\LiquidityRemovalInput;
use Brick\Math\BigDecimal;
use DomainException;
use InvalidArgumentException;
use Workflow\Activity;

class ValidateLiquidityActivity extends Activity
{
    public function execute($input): array
    {
        if ($input instanceof LiquidityAdditionInput) {
            return $this->validateAddition($input);
        } elseif ($input instanceof LiquidityRemovalInput) {
            return $this->validateRemoval($input);
        }

        throw new InvalidArgumentException('Invalid input type for liquidity validation');
    }

    private function validateAddition(LiquidityAdditionInput $input): array
    {
        // Validate account exists
        $account = Account::findOrFail($input->providerId);

        // Check KYC status through user relationship
        if (! $account->user || $account->user->kyc_status !== 'approved') {
            throw new DomainException('Account must have KYC approval to provide liquidity');
        }

        // Validate pool exists
        /** @var PoolProjection|null $pool */
        $pool = PoolProjection::where('pool_id', $input->poolId)->first();

        if (! $pool) {
            throw new DomainException('Liquidity pool not found');
        }

        if (! $pool->is_active) {
            throw new DomainException('Liquidity pool is not active');
        }

        // Validate currencies match
        if (
            $pool->base_currency !== $input->baseCurrency
            || $pool->quote_currency !== $input->quoteCurrency
        ) {
            throw new DomainException('Currency mismatch with pool');
        }

        // Validate sufficient balances
        $baseBalance = AccountBalance::where('account_id', $input->providerId)
            ->where('currency_code', $input->baseCurrency)
            ->first();

        $quoteBalance = AccountBalance::where('account_id', $input->providerId)
            ->where('currency_code', $input->quoteCurrency)
            ->first();

        if (! $baseBalance || BigDecimal::of($baseBalance->available_balance)->isLessThan($input->baseAmount)) {
            throw new DomainException("Insufficient {$input->baseCurrency} balance");
        }

        if (! $quoteBalance || BigDecimal::of($quoteBalance->available_balance)->isLessThan($input->quoteAmount)) {
            throw new DomainException("Insufficient {$input->quoteCurrency} balance");
        }

        // Validate amounts maintain pool ratio (within 1% tolerance)
        if (BigDecimal::of($pool->total_shares)->isGreaterThan(0)) {
            $poolRatio = BigDecimal::of($pool->base_reserve)->dividedBy($pool->quote_reserve, 18);
            $inputRatio = BigDecimal::of($input->baseAmount)->dividedBy($input->quoteAmount, 18);

            $deviation = $poolRatio->minus($inputRatio)->abs()
                ->dividedBy($poolRatio, 18)
                ->multipliedBy(100);

            if ($deviation->isGreaterThan(1)) {
                throw new DomainException('Input amounts deviate too much from pool ratio');
            }
        }

        return [
            'valid'      => true,
            'account_id' => $account->id,
            'pool'       => $pool->toArray(),
        ];
    }

    private function validateRemoval(LiquidityRemovalInput $input): array
    {
        /** @var \App\Domain\Liquidity\Models\LiquidityPool|null $pool */
        $pool = null;
        // Validate pool exists
        /** @var \Illuminate\Database\Eloquent\Model|null $$pool */
        $$pool = PoolProjection::where('pool_id', $input->poolId)->first();

        if (! $pool) {
            throw new DomainException('Liquidity pool not found');
        }

        // Validate provider has shares
        $provider = $pool->providers()
            ->where('provider_id', $input->providerId)
            ->first();

        if (! $provider) {
            throw new DomainException('Provider not found in pool');
        }

        if (BigDecimal::of($provider->shares)->isLessThan($input->shares)) {
            throw new DomainException('Insufficient shares');
        }

        return [
            'valid'           => true,
            'pool'            => $pool->toArray(),
            'provider_shares' => $provider->shares,
        ];
    }
}
