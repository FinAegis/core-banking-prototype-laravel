<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Agent;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Binds the agent in the route to the authenticated agent.
 *
 * For routes such as /agents/{did}/payments the {did} segment names the
 * agent acting (the payment sender, the inbox owner, the profile being
 * changed). auth.agent authenticates the caller; this middleware, which
 * must run after it, answers 403 unless the authenticated agent's DID is
 * the one in the route, so an agent can only act as itself.
 */
class EnsureAgentMatchesRouteDid
{
    /**
     * @param  Closure(Request): (Response)  $next
     * @param  string  $parameter  route parameter holding the acting agent's DID
     */
    public function handle(Request $request, Closure $next, string $parameter = 'did'): Response
    {
        $agent = $request->attributes->get('agent');

        if (! $agent instanceof Agent) {
            return response()->json([
                'error'   => 'Unauthorized',
                'message' => 'Agent authentication required',
                'code'    => 'AGENT_NOT_AUTHENTICATED',
            ], 401);
        }

        $routeDid = $request->route($parameter);

        if (! is_string($routeDid) || $routeDid === '' || ! hash_equals((string) $agent->did, $routeDid)) {
            Log::warning('Agent attempted to act as another agent', [
                'agent_id'  => $agent->agent_id,
                'did'       => $agent->did,
                'route_did' => is_string($routeDid) ? $routeDid : null,
                'path'      => $request->path(),
            ]);

            return response()->json([
                'error'   => 'Forbidden',
                'message' => 'The authenticated agent can only act as itself',
                'code'    => 'AGENT_DID_MISMATCH',
            ], 403);
        }

        return $next($request);
    }
}
