<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\AgentProtocol;

use App\Domain\AgentProtocol\Services\AgentAuthenticationService;
use App\Http\Controllers\Controller;
use App\Models\Agent;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'Agent Protocol - Authentication',
    description: 'Agent authentication, API key management, and session handling'
)]
class AgentAuthController extends Controller
{
    public function __construct(
        private readonly AgentAuthenticationService $authService
    ) {
    }

        #[OA\Post(
            path: '/api/agent-protocol/auth/challenge',
            operationId: 'getAuthChallenge',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Get authentication challenge for DID signature verification',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['did'], properties: [
            new OA\Property(property: 'did', type: 'string', example: 'did:finaegis:agent:abc123def456'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Challenge generated successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'challenge', type: 'string'),
        new OA\Property(property: 'nonce', type: 'string'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid DID format'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function getChallenge(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'did' => 'required|string',
        ]);

        try {
            // Generate challenge for the DID
            // Note: DID format validation is not strict here - the authentication
            // step will properly validate the DID when verifying the signature
            $result = $this->authService->generateChallenge($validated['did']);

            return response()->json([
                'success' => true,
                'data'    => $result,
            ]);
        } catch (Exception $e) {
            Log::error('Failed to generate auth challenge', [
                'did'   => $validated['did'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to generate challenge',
                'code'    => 'CHALLENGE_GENERATION_FAILED',
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/auth/did',
            operationId: 'authenticateWithDID',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Authenticate agent using DID signature',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['did', 'signature', 'challenge'], properties: [
            new OA\Property(property: 'did', type: 'string', example: 'did:finaegis:agent:abc123def456'),
            new OA\Property(property: 'signature', type: 'string', example: 'base64_encoded_signature'),
            new OA\Property(property: 'challenge', type: 'string', example: 'base64_encoded_challenge'),
            new OA\Property(property: 'nonce', type: 'string', example: 'random_nonce_string'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Authentication successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'session_token', type: 'string'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'agent', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Authentication failed'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function authenticateWithDID(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'did'       => 'required|string',
            'signature' => 'required|string',
            'challenge' => 'required|string',
            'nonce'     => 'nullable|string',
        ]);

        try {
            $result = $this->authService->authenticateWithDID(
                $validated['did'],
                $validated['signature'],
                $validated['challenge'],
                $validated['nonce'] ?? null
            );

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error'   => $result['error'],
                    'code'    => 'AUTH_FAILED',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'session_token' => $result['session_token'],
                    'expires_at'    => $result['expires_at'],
                    'agent'         => [
                        'agent_id' => $result['agent']->agent_id,
                        'did'      => $result['agent']->did,
                        'name'     => $result['agent']->name,
                        'status'   => $result['agent']->status,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('DID authentication error', [
                'did'   => $validated['did'],
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Authentication error',
                'code'    => 'AUTH_ERROR',
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/auth/api-key',
            operationId: 'authenticateWithApiKey',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Authenticate agent using API key',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['api_key'], properties: [
            new OA\Property(property: 'api_key', type: 'string', example: 'your_64_char_api_key'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Authentication successful',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'session_token', type: 'string'),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'agent', type: 'object'),
        ]),
        ])
    )]
    #[OA\Response(
        response: 401,
        description: 'Invalid API key'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function authenticateWithApiKey(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'api_key' => 'required|string|min:32',
        ]);

        try {
            $result = $this->authService->authenticateWithApiKey($validated['api_key']);

            if (! $result['success']) {
                return response()->json([
                    'success' => false,
                    'error'   => $result['error'],
                    'code'    => 'INVALID_API_KEY',
                ], 401);
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'session_token' => $result['session_token'],
                    'expires_at'    => $result['expires_at'],
                    'agent'         => [
                        'agent_id' => $result['agent']->agent_id,
                        'did'      => $result['agent']->did,
                        'name'     => $result['agent']->name,
                        'status'   => $result['agent']->status,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('API key authentication error', ['error' => $e->getMessage()]);

            return response()->json([
                'success' => false,
                'error'   => 'Authentication error',
                'code'    => 'AUTH_ERROR',
            ], 500);
        }
    }

        #[OA\Post(
            path: '/api/agent-protocol/auth/session/validate',
            operationId: 'validateSession',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Validate an existing session token',
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['session_token'], properties: [
            new OA\Property(property: 'session_token', type: 'string'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Session validation result',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean'),
        new OA\Property(property: 'valid', type: 'boolean'),
        new OA\Property(property: 'data', type: 'object', nullable: true),
        ])
    )]
    public function validateSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
        ]);

        $result = $this->authService->validateSession($validated['session_token']);

        if (! $result['valid']) {
            return response()->json([
                'success' => false,
                'valid'   => false,
                'error'   => $result['error'],
            ], 401);
        }

        return response()->json([
            'success' => true,
            'valid'   => true,
            'data'    => [
                'agent' => [
                    'agent_id' => $result['agent']->agent_id,
                    'did'      => $result['agent']->did,
                    'name'     => $result['agent']->name,
                    'status'   => $result['agent']->status,
                ],
            ],
        ]);
    }

        #[OA\Post(
            path: '/api/agent-protocol/auth/session/revoke',
            operationId: 'revokeSession',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Revoke a session token',
            security: [['agentAuth' => []]],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['session_token'], properties: [
            new OA\Property(property: 'session_token', type: 'string'),
            ]))
        )]
    #[OA\Response(
        response: 200,
        description: 'Session revoked successfully',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'Session revoked'),
        ])
    )]
    public function revokeSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'session_token' => 'required|string',
        ]);

        $this->authService->revokeSession($validated['session_token']);

        return response()->json([
            'success' => true,
            'message' => 'Session revoked',
        ]);
    }

        #[OA\Post(
            path: '/api/agent-protocol/agents/{did}/api-keys',
            operationId: 'generateApiKey',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Generate a new API key for an agent',
            security: [['sanctum' => [], 'agentAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ],
            requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(required: ['name'], properties: [
            new OA\Property(property: 'name', type: 'string', example: 'Production API Key'),
            new OA\Property(property: 'scopes', type: 'array', example: ['payments:read', 'wallet:read'], items: new OA\Items(type: 'string')),
            new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
            ]))
        )]
    #[OA\Response(
        response: 201,
        description: 'API key generated',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'api_key', type: 'string', description: 'Only shown once'),
        new OA\Property(property: 'key_id', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        ]),
        new OA\Property(property: 'warning', type: 'string', example: 'Store this API key securely. It will not be shown again.'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error'
    )]
    public function generateApiKey(Request $request, string $did): JsonResponse
    {
        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'scopes'     => 'nullable|array',
            'scopes.*'   => ['string', Rule::in(array_keys(config('agent_protocol.authentication.scopes', [])))],
            'expires_at' => 'nullable|date|after:now',
        ]);

        try {
            // Find agent by DID
            $agent = Agent::where('did', $did)->first();
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                    'code'    => 'AGENT_NOT_FOUND',
                ], 404);
            }

            $expiresAt = isset($validated['expires_at'])
                ? Carbon::parse($validated['expires_at'])
                : null;

            $result = $this->authService->generateApiKey(
                $agent,
                $validated['name'],
                $validated['scopes'] ?? config('agent_protocol.authentication.default_scopes', []),
                $expiresAt
            );

            Log::info('API key generated for agent', [
                'agent_id' => $agent->agent_id,
                'key_id'   => $result['key_id'],
            ]);

            return response()->json([
                'success' => true,
                'data'    => $result,
                'warning' => 'Store this API key securely. It will not be shown again.',
            ], 201);
        } catch (Exception $e) {
            Log::error('Failed to generate API key', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to generate API key',
                'code'    => 'KEY_GENERATION_FAILED',
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/api-keys',
            operationId: 'listApiKeys',
            tags: ['Agent Protocol - Authentication'],
            summary: 'List all API keys for an agent',
            security: [['sanctum' => [], 'agentAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'List of API keys',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object', properties: [
        new OA\Property(property: 'key_id', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'key_prefix', type: 'string'),
        new OA\Property(property: 'scopes', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'is_active', type: 'boolean'),
        new OA\Property(property: 'last_used_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time', nullable: true),
        ])),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    public function listApiKeys(string $did): JsonResponse
    {
        try {
            // Find agent by DID
            $agent = Agent::where('did', $did)->first();
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                    'code'    => 'AGENT_NOT_FOUND',
                ], 404);
            }

            $keys = $this->authService->listApiKeys($agent->agent_id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'keys' => $keys,
                ],
                'meta' => [
                    'count' => count($keys),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to list API keys', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to list API keys',
            ], 500);
        }
    }

        #[OA\Delete(
            path: '/api/agent-protocol/agents/{did}/api-keys/{keyId}',
            operationId: 'revokeApiKey',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Revoke an API key',
            security: [['sanctum' => [], 'agentAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            new OA\Parameter(name: 'keyId', in: 'path', required: true, description: 'API key ID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'API key revoked',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string', example: 'API key revoked'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent or API key not found'
    )]
    public function revokeApiKey(string $did, string $keyId): JsonResponse
    {
        try {
            // Find agent by DID
            $agent = Agent::where('did', $did)->first();
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                    'code'    => 'AGENT_NOT_FOUND',
                ], 404);
            }

            $revoked = $this->authService->revokeApiKey($keyId, $agent->agent_id);

            if (! $revoked) {
                return response()->json([
                    'success' => false,
                    'error'   => 'API key not found',
                    'code'    => 'KEY_NOT_FOUND',
                ], 404);
            }

            Log::info('API key revoked', [
                'agent_id' => $agent->agent_id,
                'key_id'   => $keyId,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'API key revoked successfully',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to revoke API key', [
                'did'    => $did,
                'key_id' => $keyId,
                'error'  => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to revoke API key',
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/agents/{did}/sessions',
            operationId: 'listAgentSessions',
            tags: ['Agent Protocol - Authentication'],
            summary: 'List active sessions for an agent',
            security: [['sanctum' => [], 'agentAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'List of active sessions',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'array', items: new OA\Items(type: 'object')),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    public function listSessions(string $did): JsonResponse
    {
        try {
            // Find agent by DID
            $agent = Agent::where('did', $did)->first();
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                    'code'    => 'AGENT_NOT_FOUND',
                ], 404);
            }

            $sessions = $this->authService->getActiveSessions($agent->agent_id);

            return response()->json([
                'success' => true,
                'data'    => [
                    'sessions' => $sessions,
                ],
                'meta' => [
                    'count' => count($sessions),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to list sessions', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to list sessions',
            ], 500);
        }
    }

        #[OA\Delete(
            path: '/api/agent-protocol/agents/{did}/sessions',
            operationId: 'revokeAllAgentSessions',
            tags: ['Agent Protocol - Authentication'],
            summary: 'Revoke all sessions for an agent',
            security: [['sanctum' => [], 'agentAuth' => []]],
            parameters: [
            new OA\Parameter(name: 'did', in: 'path', required: true, description: 'Agent DID', schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'All sessions revoked',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'message', type: 'string'),
        new OA\Property(property: 'revoked_count', type: 'integer'),
        ])
    )]
    #[OA\Response(
        response: 404,
        description: 'Agent not found'
    )]
    public function revokeAllSessions(string $did): JsonResponse
    {
        try {
            // Find agent by DID
            $agent = Agent::where('did', $did)->first();
            if (! $agent) {
                return response()->json([
                    'success' => false,
                    'error'   => 'Agent not found',
                    'code'    => 'AGENT_NOT_FOUND',
                ], 404);
            }

            $count = $this->authService->revokeAllSessions($agent->agent_id);

            Log::info('All sessions revoked for agent', [
                'agent_id' => $agent->agent_id,
                'count'    => $count,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All sessions revoked',
                'data'    => [
                    'revoked_count' => $count,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to revoke all sessions', [
                'did'   => $did,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => 'Failed to revoke sessions',
            ], 500);
        }
    }

        #[OA\Get(
            path: '/api/agent-protocol/auth/scopes',
            operationId: 'listAvailableScopes',
            tags: ['Agent Protocol - Authentication'],
            summary: 'List all available OAuth2-style scopes'
        )]
    #[OA\Response(
        response: 200,
        description: 'List of available scopes',
        content: new OA\JsonContent(properties: [
        new OA\Property(property: 'success', type: 'boolean', example: true),
        new OA\Property(property: 'data', type: 'object', properties: [
        new OA\Property(property: 'scopes', type: 'object'),
        new OA\Property(property: 'default_scopes', type: 'array', items: new OA\Items(type: 'string')),
        ]),
        ])
    )]
    public function listScopes(): JsonResponse
    {
        $configScopes = config('agent_protocol.authentication.scopes', []);

        // Transform to array of objects with scope and description
        $scopes = [];
        foreach ($configScopes as $scope => $description) {
            $scopes[] = [
                'scope'       => $scope,
                'description' => $description,
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'scopes'         => $scopes,
                'default_scopes' => config('agent_protocol.authentication.default_scopes', []),
            ],
        ]);
    }
}
