<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Payment;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\DataObjects\Money;
use App\Domain\Account\Models\Account;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Domain\Payment\Workflows\TransferWorkflow;
use GraphQL\Error\Error;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;
use Workflow\WorkflowStub;

class InitiatePaymentMutation
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): PaymentTransaction
    {
        if (! config('ledger.direct_funding_enabled')) {
            throw new Error('This operation is not available.');
        }

        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // The caller must own the source account (same rule as REST transfers).
        if (Account::findOwnedBy((string) $args['account_uuid'], $user) === null) {
            throw new AuthorizationException('You are not authorized to use this account.');
        }

        $fromAccountUuid = AccountUuid::fromString($args['account_uuid']);
        $toAccountUuid = AccountUuid::fromString($args['to_account_uuid'] ?? $args['account_uuid']);
        $money = new Money((int) $args['amount']);

        $workflow = WorkflowStub::make(TransferWorkflow::class);
        $workflow->start($fromAccountUuid, $toAccountUuid, $money);

        // Return a read-model record for immediate GraphQL response.
        return PaymentTransaction::create([
            'aggregate_uuid' => Str::uuid()->toString(),
            'account_uuid'   => $args['account_uuid'],
            'type'           => $args['type'],
            'status'         => 'pending',
            'amount'         => $args['amount'],
            'currency'       => $args['currency'],
            'payment_method' => $args['payment_method'] ?? null,
            'reference'      => $args['reference'] ?? Str::uuid()->toString(),
            'initiated_at'   => now(),
        ]);
    }
}
