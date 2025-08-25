<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\IpBlockingService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckBlockedIp
{
    public function __construct(
        private readonly IpBlockingService $ipBlockingService
    ) {
    }

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();

        if ($this->ipBlockingService->isBlocked($ip)) {
            $blockInfo = $this->ipBlockingService->getBlockInfo($ip);

            return response()->json([
                'error'      => 'IP_BLOCKED',
                'message'    => 'Your IP address has been temporarily blocked due to suspicious activity.',
                'reason'     => $blockInfo['reason'] ?? 'Security policy violation',
                'expires_at' => $blockInfo['expires_at'] ?? null,
            ], 403);
        }

        return $next($request);
    }
}
