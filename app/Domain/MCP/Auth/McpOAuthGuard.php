<?php

declare(strict_types=1);

namespace App\Domain\MCP\Auth;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Passport\Token;
use Symfony\Component\HttpFoundation\Response;

final class McpOAuthGuard
{
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        $bearer = $request->bearerToken();
        if ($bearer === null || $bearer === '') {
            return $this->unauthenticated();
        }

        $user = Auth::guard('api')->user();
        if ($user === null || ! is_callable([$user, 'token'])) {
            return $this->unauthenticated();
        }

        // Passport's `api` guard returns a user with the resolved access token
        // attached via HasApiTokens::withAccessToken(). `token()` returns the
        // concrete `Token` model — we need a `Token` (not just any
        // ScopeAuthorizable) so we can check `revoked` and forward the model
        // to downstream MCP handlers.
        /** @var Token|null $token */
        $token = $user->token();
        if (! $token instanceof Token || $token->revoked) {
            return $this->unauthenticated();
        }

        if ($requiredScope !== null && ! $token->can($requiredScope)) {
            return new JsonResponse([
                'jsonrpc' => '2.0',
                'error'   => [
                    'code'    => -32000,
                    'message' => 'INSUFFICIENT_SCOPE',
                    'data'    => [
                        'required' => $requiredScope,
                        'granted'  => $token->scopes,
                    ],
                ],
                'id' => null,
            ], 403);
        }

        $request->attributes->set('mcp.token', $token);

        // Existing internal tool implementations (TransferTool, AccountBalanceTool,
        // RampStartTool, …) call Auth::user() with no guard argument — that
        // resolves the *default* guard, which is `web` (session) and returns null
        // under a stateless bearer-token request. Switch the default to `api` for
        // the remainder of this request so those tools see the authenticated user
        // without each having to know about the MCP-specific guard.
        Auth::shouldUse('api');

        return $next($request);
    }

    private function unauthenticated(): JsonResponse
    {
        $resourceMetadata = rtrim((string) config('mcp.resource_uri'), '/') . '/.well-known/oauth-protected-resource';
        // Strip header-injection / quote-break characters in case MCP_RESOURCE_URI
        // is operator-misconfigured. RFC 9728 examples are bare https URLs anyway.
        $resourceMetadata = str_replace(['"', "\r", "\n"], '', $resourceMetadata);

        $response = new JsonResponse([
            'jsonrpc' => '2.0',
            'error'   => [
                'code'    => -32001,
                'message' => 'UNAUTHENTICATED',
            ],
            'id' => null,
        ], 401);

        $response->headers->set('WWW-Authenticate', 'Bearer resource_metadata="' . $resourceMetadata . '"');

        return $response;
    }
}
