<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\UserSignIns;
use App\Traits\HasApiScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

class PasswordController extends Controller
{
    use HasApiScopes;

    /**
     * Change the authenticated user's password.
     */
    #[OA\Post(
        path: '/api/v2/auth/change-password',
        summary: 'Change user password',
        description: 'Change the authenticated user\'s password. All existing API tokens, OAuth grants and stored sessions end, and registered mobile devices are removed with their passkey and biometric keys; API keys stay. The response carries a new token.',
        operationId: 'changePassword',
        tags: ['Authentication'],
        security: [['sanctum' => []]],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['current_password', 'new_password', 'new_password_confirmation'], properties: [
        new OA\Property(property: 'current_password', type: 'string', format: 'password', example: 'oldpassword123'),
        new OA\Property(property: 'new_password', type: 'string', format: 'password', example: 'newpassword123'),
        new OA\Property(property: 'new_password_confirmation', type: 'string', format: 'password', example: 'newpassword123'),
        ]))
    )]
    #[OA\Response(
        response: 200,
        description: 'Password changed successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Password changed successfully'),
        new OA\Property(property: 'new_token', type: 'string', example: '1|laravel_sanctum_token...'),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(ref: '#/components/schemas/Error')
    )]
    #[OA\Response(
        response: 401,
        description: 'Unauthorized - Invalid current password'
    )]
    public function changePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'new_password'     => ['required', 'string', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();

        // Type assertion for PHPStan
        if (! $user instanceof User) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        // Verify current password
        if (! Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The provided password is incorrect.'],
            ]);
        }

        // Update password
        $user->update([
            'password' => Hash::make($validated['new_password']),
        ]);

        // End every other sign-in (API tokens, OAuth grants, stored
        // sessions, mobile devices); API keys stay. The caller gets a new
        // token below.
        UserSignIns::endAllExcept($user);

        // Create a new token with proper expiration
        $newToken = $this->createTokenWithScopes($user, $request->header('User-Agent', 'Unknown Device'));

        return response()->json([
            'message'   => 'Password changed successfully',
            'new_token' => $newToken,
        ], 200);
    }
}
