<?php

declare(strict_types=1);

namespace App\GraphQL\Queries\X402;

use App\Domain\X402\Models\X402Payment;
use App\Support\MachinePaymentScope;
use Illuminate\Support\Facades\Auth;

class X402PaymentQuery
{
    /**
     * @param  null  $_
     * @param  array<string, mixed>  $args
     */
    public function __invoke($_, array $args): ?X402Payment
    {
        // The caller's team's payment (any payment for administrators).
        /** @var X402Payment|null */
        return MachinePaymentScope::teamRecords(X402Payment::query(), Auth::user())->find($args['id']);
    }
}
