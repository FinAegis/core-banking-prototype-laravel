<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Domain\Mobile\Services\BiometricJWTService;
use App\Domain\User\Exceptions\RegistrationNotAllowedException;
use App\Domain\User\Services\ApiRegistrationGate;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\HasApiScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Authentication',
    description: 'User authentication and registration endpoints'
)]
class RegisterController extends Controller
{
    use HasApiScopes;

    public function __construct(
        private readonly BiometricJWTService $biometricJWTService,
        private readonly ApiRegistrationGate $registrationGate,
    ) {
    }

    /**
     * Register a new user.
     *
     *
     * @throws ValidationException
     */
    #[OA\Post(
        path: '/api/auth/register',
        summary: 'Register a new user',
        description: 'Create a new user account with email and password',
        operationId: 'register',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name', 'email', 'password', 'password_confirmation'], properties: [
        new OA\Property(property: 'name', type: 'string', example: 'John Doe', description: 'User\'s full name'),
        new OA\Property(property: 'email', type: 'string', format: 'email', example: 'john@example.com', description: 'User\'s email address'),
        new OA\Property(property: 'password', type: 'string', format: 'password', example: 'password123', description: 'User\'s password (min 8 characters)'),
        new OA\Property(property: 'password_confirmation', type: 'string', format: 'password', example: 'password123', description: 'Password confirmation'),
        new OA\Property(property: 'is_business_customer', type: 'boolean', example: false, description: 'Whether the user is a business customer'),
        new OA\Property(property: 'invitation_token', type: 'string', nullable: true, description: 'Required while sign-up is invite-only (REGISTRATION_API_INVITE_ONLY=true): the token from the invitation link sent to this email address'),
        ]))
    )]
    #[OA\Response(
        response: 201,
        description: 'User registered successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'user', type: 'object', properties: [
        new OA\Property(property: 'id', type: 'integer', example: 1),
        new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
        new OA\Property(property: 'email', type: 'string', example: 'john@example.com'),
        new OA\Property(property: 'email_verified_at', type: 'string', nullable: true, example: null),
        ]),
        new OA\Property(property: 'access_token', type: 'string', example: '1|aBcDeFgHiJkLmNoPqRsTuVwXyZ...'),
        new OA\Property(property: 'refresh_token', type: 'string', example: '2|rEfReShToKeNhErE...'),
        new OA\Property(property: 'token_type', type: 'string', example: 'Bearer'),
        new OA\Property(property: 'expires_in', type: 'integer', nullable: true, example: 86400, description: 'Access token expiration time in seconds'),
        new OA\Property(property: 'refresh_expires_in', type: 'integer', nullable: true, example: 2592000, description: 'Refresh token expiration time in seconds'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'message', type: 'string', example: 'The given data was invalid.'),
        new OA\Property(property: 'errors', type: 'object', properties: [
        new OA\Property(property: 'email', type: 'array', items: new OA\Items(type: 'string', example: 'The email has already been taken.')),
        ]),
        ])
    )]
    #[OA\Response(
        response: 403,
        description: 'Registration closed (ERR_REG_001, REGISTRATION_API_ENABLED=false) or invite-only without a pending invitation for this email and its invitation_token (ERR_REG_002)',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: false),
        new OA\Property(property: 'error', type: 'object', properties: [
        new OA\Property(property: 'code', type: 'string', example: 'ERR_REG_001'),
        new OA\Property(property: 'message', type: 'string', example: 'New account registration is currently closed.'),
        new OA\Property(property: 'reason', type: 'string', example: 'REGISTRATION_CLOSED'),
        ]),
        ])
    )]
    public function register(Request $request): JsonResponse
    {
        // REGISTRATION_API_ENABLED: refuse before validation so a closed
        // endpoint does not reveal which email addresses are taken.
        try {
            $this->registrationGate->ensureOpen();
        } catch (RegistrationNotAllowedException $e) {
            return $e->toResponse();
        }

        $validated = $request->validate(
            [
                'name'                 => ['required', 'string', 'max:255'],
                'email'                => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password'             => ['required', 'string', 'min:8', 'confirmed'],
                'is_business_customer' => ['sometimes', 'boolean'],
                'device_attestation'   => ['nullable', 'string', 'max:4096'],
                'device_type'          => ['nullable', 'string', 'in:ios,android'],
                'invitation_token'     => ['nullable', 'string', 'max:255'],
            ]
        );

        // Verify device attestation if provided and enabled
        if ($request->filled('device_attestation') && config('mobile.attestation.enabled')) {
            $verified = $this->biometricJWTService->verifyDeviceAttestation(
                $request->input('device_attestation'),
                $request->input('device_type', 'android')
            );
            if (! $verified) {
                return response()->json(['success' => false, 'message' => 'Device attestation failed'], 403);
            }
        }

        // Use the same CreateNewUser action as Fortify for consistency
        // Pass isApiRegistration=true to bypass the Fortify web registration gate
        $creator = new CreateNewUser();
        try {
            $user = $creator->create(
                [
                    'name'                  => $validated['name'],
                    'email'                 => $validated['email'],
                    'password'              => $validated['password'],
                    'password_confirmation' => $request->password_confirmation,
                    'is_business_customer'  => $validated['is_business_customer'] ?? false,
                    'invitation_token'      => $validated['invitation_token'] ?? null,
                    'terms'                 => true, // For API, we assume terms are accepted
                ],
                isApiRegistration: true,
            );
        } catch (RegistrationNotAllowedException $e) {
            return $e->toResponse();
        }

        // Create access/refresh token pair
        $tokenPair = $this->createTokenPair($user, 'api-token');

        return response()->json(
            [
                'success' => true,
                'data'    => [
                    'user'               => $user,
                    'access_token'       => $tokenPair['access_token'],
                    'refresh_token'      => $tokenPair['refresh_token'],
                    'token_type'         => 'Bearer',
                    'expires_in'         => $tokenPair['expires_in'],
                    'refresh_expires_in' => $tokenPair['refresh_expires_in'],
                ],
            ],
            201
        );
    }
}
