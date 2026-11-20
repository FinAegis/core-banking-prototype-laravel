<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Banking;

use App\Domain\Banking\Services\BankTransferService;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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

        // The user who initiated the transfer, or an operator. Another user's
        // transfer gets the same answer as an unknown id, so the response does
        // not reveal whether the id exists.
        $owned = DB::table('bank_transfers')
            ->where('id', $transferId)
            ->where('user_uuid', $user->uuid)
            ->exists();

        if (! $owned && ! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            return $this->notFound($transferId);
        }

        $status = $this->bankTransferService->getStatus($transferId);

        if ($status['status'] === 'not_found') {
            return $this->notFound($transferId);
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

    /**
     * @return array{transfer_id: string, status: string, message: string}
     */
    private function notFound(string $transferId): array
    {
        return [
            'transfer_id' => $transferId,
            'status'      => 'not_found',
            'message'     => 'Transfer not found.',
        ];
    }
}
