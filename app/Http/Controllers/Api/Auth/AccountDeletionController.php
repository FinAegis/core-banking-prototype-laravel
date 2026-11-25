<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Auth;

use App\Domain\Compliance\Exceptions\AccountClosureBlockedException;
use App\Domain\Compliance\Services\AccountClosureService;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;
use Throwable;

class AccountDeletionController extends Controller
{
    public function __construct(
        private readonly AccountClosureService $accountClosure,
    ) {
    }

    /**
     * Delete the authenticated user's account, the way the GDPR deletion
     * endpoint does: personal data is erased, money records are kept and the
     * user row stays as an anonymous entry. Only after the erasure has
     * succeeded are the tokens revoked; when it fails, the tokens stay so the
     * app can try again.
     *
     * POST /auth/delete-account
     */
    #[OA\Post(
        path: '/api/auth/delete-account',
        operationId: 'accountDeletion',
        summary: 'Delete user account',
        description: 'Erases the authenticated user\'s personal data right away and keeps the records the law requires in anonymous form. All tokens are revoked after the erasure has succeeded. When the erasure fails, nothing is revoked and the request can be repeated.',
        tags: ['Account Deletion'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['confirmation'], properties: [
        new OA\Property(property: 'confirmation', type: 'string', example: 'DELETE', description: 'Must be the string \'DELETE\' to confirm account deletion'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Account deleted',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Your account has been deleted.'),
        new OA\Property(property: 'deleted_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthenticated'
    )]
    #[OA\Response(
        response: 409,
        description: 'A rule does not allow the deletion yet (error.code ACCOUNT_DELETION_BLOCKED); nothing was erased and the tokens stay valid. error.reason_codes holds a stable code for each sentence in error.reasons: positive_balance, card_withdrawal_in_progress, identity_check_in_review',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ACCOUNT_DELETION_BLOCKED'),
        new OA\Property(property: 'message', type: 'string', example: 'The account cannot be deleted yet.'),
        new OA\Property(property: 'reasons', type: 'array', items: new OA\Items(type: 'string'), example: ['User has active accounts with positive balance']),
        new OA\Property(property: 'reason_codes', type: 'array', items: new OA\Items(type: 'string', enum: ['positive_balance', 'card_withdrawal_in_progress', 'identity_check_in_review']), example: ['positive_balance']),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error — confirmation field is required and must be \'DELETE\''
    )]
    #[OA\Response(
        response: 500,
        description: 'The erasure failed (error.code ACCOUNT_DELETION_FAILED); the tokens stay valid and the request can be repeated'
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'confirmation' => ['required', 'string', 'in:DELETE'],
        ]);

        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'UNAUTHENTICATED',
                    'message' => 'No authenticated user.',
                ],
            ], 401);
        }

        Log::warning('Account deletion requested', [
            'user_id' => $user->id,
            'ip'      => $request->ip(),
        ]);

        try {
            $this->accountClosure->close($user, AccountClosureService::SOURCE_APP);
        } catch (AccountClosureBlockedException $e) {
            return response()->json([
                'success' => false,
                'error'   => [
                    'code'         => 'ACCOUNT_DELETION_BLOCKED',
                    'message'      => 'The account cannot be deleted yet.',
                    'reasons'      => $e->reasons,
                    'reason_codes' => $e->reasonCodes,
                ],
            ], 409);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'error'   => [
                    'code'    => 'ACCOUNT_DELETION_FAILED',
                    'message' => 'The account could not be deleted. You are still signed in, so you can try again.',
                ],
            ], 500);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'message'    => 'Your account has been deleted.',
                'deleted_at' => now()->toIso8601String(),
            ],
        ]);
    }
}
