<?php

declare(strict_types=1);

namespace App\GraphQL\Mutations\Commerce;

use App\Domain\Commerce\Enums\TokenType;
use App\Domain\Commerce\Services\SoulboundTokenService;
use App\Support\StaffRoles;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Facades\Auth;
use Nuwave\Lighthouse\Exceptions\AuthorizationException;

final class IssueSoulboundTokenMutation
{
    public function __construct(
        private readonly SoulboundTokenService $soulboundTokenService,
    ) {
    }

    /**
     * @param  array<string, mixed>  $args
     * @return array<string, mixed>
     */
    public function __invoke(mixed $rootValue, array $args): array
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if (! $user) {
            throw new AuthenticationException('Unauthenticated.');
        }

        // Soulbound tokens are issued and revoked by the platform.
        if (! StaffRoles::userHasAny($user, StaffRoles::OPERATORS)) {
            throw new AuthorizationException('You are not authorized to manage soulbound tokens.');
        }

        $metadata = isset($args['metadata']) ? (json_decode($args['metadata'], true) ?: []) : [];
        $tokenType = TokenType::from($args['token_type']);

        $token = $this->soulboundTokenService->issueToken(
            $tokenType,
            $args['recipient_address'],
            $metadata,
        );

        return [
            'token_hash' => $token->getTokenHash(),
            'token_type' => $args['token_type'],
            'subject'    => $token->recipientId,
            'issuer'     => $token->issuerId,
            'issued_at'  => $token->issuedAt->format('c'),
            'is_valid'   => $token->isValid(),
        ];
    }
}
