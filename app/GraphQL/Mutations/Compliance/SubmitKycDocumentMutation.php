<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Compliance;

use App\Domain\Compliance\Models\KycVerification;
use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

class SubmitKycDocumentMutation
{
    public function __construct(
        private readonly KycService $kycService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     */
    public function __invoke(mixed $rootValue, array $args): KycVerification
    {
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Users submit their own documents; compliance staff may submit for
        // another user.
        if ((string) $args['user_id'] !== (string) $user->id && ! StaffRoles::userHasAny($user, StaffRoles::COMPLIANCE)) {
            throw new AuthorizationException('You are not authorized to submit KYC documents for this user.');
        }

        /** @var User $targetUser */
        $targetUser = User::findOrFail($args['user_id']);

        $this->kycService->submitKyc($targetUser, $args['documents'] ?? []);

        // Return a read-model record for the GraphQL response.
        return KycVerification::create([
            'user_id'          => $args['user_id'],
            'type'             => $args['type'],
            'document_type'    => $args['document_type'],
            'document_country' => $args['document_country'],
            'status'           => 'pending',
            'started_at'       => now(),
        ]);
    }
}
