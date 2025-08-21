<?php

namespace App\Http\Controllers\Api\Auth;

use App\Actions\Fortify\CreateNewUser;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Traits\HasApiScopes;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * @OA\Tag(
 *     name="Authentication",
 *     description="User authentication and registration endpoints"
 * )
 */
class RegisterController extends Controller
{
    use HasApiScopes;

    /**
     * Register a new user.
     *
     * @OA\Post(
     *     path="/api/auth/register",
     *     summary="Register a new user",
     *     description="Create a new user account with email and password",
     *     operationId="register",
     *     tags={"Authentication"},
     *
     * @OA\RequestBody(
     *         required=true,
     *
     * @OA\JsonContent(
     *             required={"name","email","password","password_confirmation"},
     *
     * @OA\Property(property="name",                  type="string", example="John Doe", description="User's full name"),
     * @OA\Property(property="email",                 type="string", format="email", example="john@example.com", description="User's email address"),
     * @OA\Property(property="password",              type="string", format="password", example="password123", description="User's password (min 8 characters)"),
     * @OA\Property(property="password_confirmation", type="string", format="password", example="password123", description="Password confirmation"),
     * @OA\Property(property="is_business_customer",  type="boolean", example=false, description="Whether the user is a business customer")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=201,
     *         description="User registered successfully",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",               type="string", example="User registered successfully"),
     * @OA\Property(
     *                 property="user",
     *                 type="object",
     * @OA\Property(property="id",                    type="integer", example=1),
     * @OA\Property(property="name",                  type="string", example="John Doe"),
     * @OA\Property(property="email",                 type="string", example="john@example.com"),
     * @OA\Property(property="email_verified_at",     type="string", nullable=true, example=null)
     *             ),
     * @OA\Property(property="access_token",          type="string", example="1|aBcDeFgHiJkLmNoPqRsTuVwXyZ..."),
     * @OA\Property(property="token_type",            type="string", example="Bearer")
     *         )
     *     ),
     *
     * @OA\Response(
     *         response=422,
     *         description="Validation error",
     *
     * @OA\JsonContent(
     *
     * @OA\Property(property="message",               type="string", example="The given data was invalid."),
     * @OA\Property(
     *                 property="errors",
     *                 type="object",
     * @OA\Property(
     *                     property="email",
     *                     type="array",
     *
     * @OA\Items(type="string",                       example="The email has already been taken.")
     *                 )
     *             )
     *         )
     *     )
     * )
     *
     * @throws ValidationException
     */
    public function register(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'name'                 => ['required', 'string', 'max:255'],
                'email'                => ['required', 'string', 'email', 'max:255', 'unique:users'],
                'password'             => ['required', 'string', 'min:8', 'confirmed'],
                'is_business_customer' => ['sometimes', 'boolean'],
            ]
        );

        // Use the same CreateNewUser action as Fortify for consistency
        $creator = new CreateNewUser();
        $user = $creator->create(
            [
                'name'                  => $validated['name'],
                'email'                 => $validated['email'],
                'password'              => $validated['password'],
                'password_confirmation' => $request->password_confirmation,
                'is_business_customer'  => $validated['is_business_customer'] ?? false,
                'terms'                 => true, // For API, we assume terms are accepted
            ]
        );

        // Create a personal access token for the user with appropriate scopes
        $token = $this->createTokenWithScopes($user, 'api-token');

        return response()->json(
            [
                'message' => 'User registered successfully',
                'user'    => [
                    'id'                => $user->id,
                    'name'              => $user->name,
                    'email'             => $user->email,
                    'email_verified_at' => $user->email_verified_at,
                ],
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
            201
        );
    }
}
