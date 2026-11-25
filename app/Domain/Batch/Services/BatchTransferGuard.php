<?php

declare(strict_types=1);

namespace App\Domain\Batch\Services;

use App\Domain\Account\Models\Account;
use App\Domain\Asset\Models\Asset;
use App\Models\User;
use App\Support\DecimalString;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

/**
 * Checks the items of a transfer batch before the batch is created.
 *
 * Each item gets the same checks as a single transfer (POST /api/transfers):
 * both accounts exist and differ, the user owns the source account, neither
 * account is frozen, the asset exists, the amount has no more decimals than
 * the asset has, and the source holds enough money. The balance check adds up
 * every item that takes the same asset from the same account, so two items
 * cannot each pass against a balance that only covers one of them.
 *
 * Batch conversions are refused: the conversion item never moved money and
 * reported a made-up result.
 */
class BatchTransferGuard
{
    /** Item types that move money from one account to another. */
    public const TRANSFER_TYPES = ['transfer', 'payment'];

    /** The largest batch one request may create. */
    public const MAX_ITEMS = 100;

    /**
     * @param  array<mixed>  $items
     * @return list<array{type: string, from_account: string, to_account: string, amount: string, currency: string, description: string|null}>
     *
     * @throws ValidationException when an item is invalid (HTTP 422)
     * @throws AuthorizationException when a source account belongs to someone else (HTTP 403)
     */
    public function validate(User $user, string $type, array $items): array
    {
        Validator::make(
            ['type' => $type, 'items' => $items],
            [
                'type'                 => 'required|string|in:' . implode(',', self::TRANSFER_TYPES),
                'items'                => 'required|array|min:1|max:' . self::MAX_ITEMS,
                'items.*'              => 'required|array',
                'items.*.from_account' => 'required|uuid|exists:accounts,uuid',
                'items.*.to_account'   => 'required|uuid|exists:accounts,uuid|different:items.*.from_account',
                'items.*.amount'       => 'required|numeric|min:0.01',
                'items.*.currency'     => 'required|string|exists:assets,code',
                'items.*.description'  => 'nullable|string|max:255',
            ],
            ['type.in' => 'Batches can only hold transfers and payments.']
        )->validate();

        $userUuid = (string) $user->uuid;
        $errors = [];
        $planned = [];
        $normalized = [];

        foreach ($items as $index => $item) {
            /** @var array<string, mixed> $item */
            $from = $this->account((string) $item['from_account']);
            $to = $this->account((string) $item['to_account']);

            if ($userUuid === '' || ! hash_equals((string) $from->user_uuid, $userUuid)) {
                throw new AuthorizationException('Unauthorized: You can only transfer from your own accounts');
            }

            if ($from->frozen) {
                $errors["items.{$index}.from_account"][] = 'Cannot transfer from frozen account';

                continue;
            }

            if ($to->frozen) {
                $errors["items.{$index}.to_account"][] = 'Cannot transfer to frozen account';

                continue;
            }

            /** @var Asset $asset */
            $asset = Asset::query()->where('code', (string) $item['currency'])->firstOrFail();
            $precision = (int) ($asset->precision ?? 2);
            $minor = DecimalString::toMinorUnitsInt($item['amount'], $precision);

            if ($minor === null || $minor <= 0) {
                $errors["items.{$index}.amount"][] = $precision === 0
                    ? "The amount must be a whole number for {$asset->code}."
                    : "The amount can have at most {$precision} decimals for {$asset->code}.";

                continue;
            }

            $key = "{$from->uuid}|{$asset->code}";
            $planned[$key] = bcadd($planned[$key] ?? '0', (string) $minor, 0);

            if (bccomp($planned[$key], (string) $from->getBalance($asset->code), 0) > 0) {
                $errors["items.{$index}.amount"][] = 'Insufficient funds';

                continue;
            }

            $normalized[] = [
                'type'         => $type,
                'from_account' => (string) $from->uuid,
                'to_account'   => (string) $to->uuid,
                'amount'       => (string) DecimalString::fromNumeric($item['amount'], $precision),
                'currency'     => (string) $asset->code,
                'description'  => isset($item['description']) ? (string) $item['description'] : null,
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $normalized;
    }

    /**
     * Ownership is checked against user_uuid, so the lookup ignores the team
     * scope (as Account::findOwnedBy does); the validator has already
     * confirmed that the account exists.
     */
    private function account(string $uuid): Account
    {
        /** @var Account $account */
        $account = Account::query()->withoutGlobalScope('team')->where('uuid', $uuid)->firstOrFail();

        return $account;
    }
}
