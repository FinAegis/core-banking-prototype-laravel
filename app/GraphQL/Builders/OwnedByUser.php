<?php

declare(strict_types=1);

namespace App\GraphQL\Builders;

use App\Domain\Account\Models\Account;
use App\Domain\Wallet\Models\MultiSigWalletSigner;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Field builders that limit GraphQL @find / @paginate queries to the rows
 * owned by the authenticated user (same rule as the REST read endpoints:
 * owner only). Used as
 * `@builder(method: "App\\GraphQL\\Builders\\OwnedByUser@userUuid")`, with
 * an optional `value` naming the owner column when it differs from the
 * default. Without an authenticated user every query matches nothing.
 */
final class OwnedByUser
{
    /**
     * Rows whose column (default user_uuid) holds the user's uuid.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function userUuid(Builder $builder, mixed $column = null): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        return $builder->where($this->column($builder, $column, 'user_uuid'), (string) $user->uuid);
    }

    /**
     * Rows whose column (default user_id) holds the user's id.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function userId(Builder $builder, mixed $column = null): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        return $builder->where($this->column($builder, $column, 'user_id'), $user->getKey());
    }

    /**
     * Rows whose column (default account_uuid) holds the uuid of an account
     * the user owns.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function accountUuid(Builder $builder, mixed $column = null): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        return $builder->whereIn($this->column($builder, $column, 'account_uuid'), $this->ownedAccountUuids($user));
    }

    /**
     * Rows whose column (default account_id) holds the id of an account the
     * user owns (exchange orders store it as a string).
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function accountId(Builder $builder, mixed $column = null): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        return $builder->whereIn($this->column($builder, $column, 'account_id'), $this->ownedAccountIds($user));
    }

    /**
     * Custodian transfers from or to an account the user owns.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function custodianTransfer(Builder $builder): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        $table = $builder->getModel()->getTable();
        $accountUuids = $this->ownedAccountUuids($user);

        return $builder->where(function (Builder $query) use ($accountUuids, $table): void {
            $query->whereIn("{$table}.from_account_uuid", $accountUuids)
                ->orWhereIn("{$table}.to_account_uuid", $accountUuids);
        });
    }

    /**
     * Trades where one of the user's accounts is the buyer or the seller.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function trade(Builder $builder): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        $table = $builder->getModel()->getTable();
        $accountIds = $this->ownedAccountIds($user);

        return $builder->where(function (Builder $query) use ($accountIds, $table): void {
            $query->whereIn("{$table}.buyer_account_id", $accountIds)
                ->orWhereIn("{$table}.seller_account_id", $accountIds);
        });
    }

    /**
     * Multi-signature wallets the user owns or is an active signer of.
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function wallet(Builder $builder): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        $table = $builder->getModel()->getTable();
        $userId = $user->getKey();
        $signedWallets = MultiSigWalletSigner::query()
            ->select('multi_sig_wallet_id')
            ->where('user_id', $userId)
            ->where('is_active', true);

        return $builder->where(function (Builder $query) use ($userId, $table, $signedWallets): void {
            // Table-qualified columns go to the base query builder (Eloquent's
            // where() forwards a string column to it unchanged).
            $query->getQuery()->where("{$table}.user_id", $userId);
            $query->orWhereIn("{$table}.id", $signedWallets);
        });
    }

    /**
     * Loan applications of the user (borrower_id holds the user id; some
     * older paths stored the uuid).
     *
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    public function borrower(Builder $builder, mixed $column = null): Builder
    {
        $user = $this->user();
        if ($user === null) {
            return $this->none($builder);
        }

        return $builder->whereIn(
            $this->column($builder, $column, 'borrower_id'),
            [(string) $user->getKey(), (string) $user->uuid],
        );
    }

    private function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * @param  Builder<Model>  $builder
     * @return Builder<Model>
     */
    private function none(Builder $builder): Builder
    {
        return $builder->whereRaw('1 = 0');
    }

    /**
     * @param  Builder<Model>  $builder
     */
    private function column(Builder $builder, mixed $column, string $default): string
    {
        $name = is_string($column) && $column !== '' ? $column : $default;

        return $builder->getModel()->getTable() . '.' . $name;
    }

    /**
     * Resolved to a list (not a subquery) because accounts may live on the
     * tenant connection while the filtered model does not.
     *
     * @return list<string>
     */
    private function ownedAccountUuids(User $user): array
    {
        return array_values(Account::query()
            ->where('user_uuid', (string) $user->uuid)
            ->pluck('uuid')
            ->map(fn (mixed $uuid): string => (string) $uuid)
            ->all());
    }

    /**
     * @return list<string>
     */
    private function ownedAccountIds(User $user): array
    {
        return array_values(Account::query()
            ->where('user_uuid', (string) $user->uuid)
            ->pluck('id')
            ->map(fn (mixed $id): string => (string) $id)
            ->all());
    }
}
