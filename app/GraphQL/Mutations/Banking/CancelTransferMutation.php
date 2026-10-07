<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Banking;

use App\Domain\Banking\Services\BankTransferService;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class CancelTransferMutation
{
    public function __construct(
        private readonly BankTransferService $bankTransferService,
    ) {
    }

    /**
     * Cancel an in-progress bank transfer.
     *
     * @param  array<string, mixed>  $args
     * @return array{transfer_id: string, status: string, message: string}
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        $transferId = (string) $args['transfer_id'];

        // Unknown transfers answer not_found.
        $status = $this->bankTransferService->getStatus($transferId);

        if ($status['status'] === 'not_found') {
            return [
                'transfer_id' => $transferId,
                'status'      => 'not_found',
                'message'     => 'Transfer not found.',
            ];
        }

        // The user who initiated the transfer, or an operator.
        $owned = DB::table('bank_transfers')
            ->where('id', $transferId)
            ->where('user_uuid', $user->uuid)
            ->exists();

        if (! $owned && ! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to cancel this transfer.');
        }

        $cancelled = $this->bankTransferService->cancel($transferId, 'Cancelled by user via GraphQL');

        if ($cancelled) {
            return [
                'transfer_id' => $transferId,
                'status'      => 'cancelled',
                'message'     => 'Transfer cancelled successfully.',
            ];
        }

        return [
            'transfer_id' => $transferId,
            'status'      => $status['status'],
            'message'     => 'Transfer cannot be cancelled in its current state.',
        ];
    }
}
