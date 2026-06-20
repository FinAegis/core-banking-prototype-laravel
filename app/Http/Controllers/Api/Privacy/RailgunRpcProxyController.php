<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Privacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Non-custodial RAILGUN RPC proxy (Phase 1).
 *
 * The on-device engine reads chain state + syncs the merkle tree over JSON-RPC.
 * To avoid shipping a provider API key to the app, the device is handed a
 * short-lived SIGNED URL (minted by engine-config) that points here; this proxy
 * injects the real upstream URL (key included) server-side and forwards only
 * whitelisted read methods (+ raw-tx broadcast).
 *
 * Auth is the `signed` middleware (the URL is tamper-proof + time-limited) —
 * the SDK's loadProvider takes a plain URL string and cannot inject an
 * Authorization header, so a signed URL is the only workable auth.
 *
 * Security: upstream is config-sourced (no SSRF); method whitelist blocks
 * account/admin/debug methods; throttled; the upstream URL is never echoed.
 */
class RailgunRpcProxyController extends Controller
{
    /**
     * JSON-RPC methods the RAILGUN engine + ethers FallbackProvider legitimately
     * need. Read methods + eth_sendRawTransaction (a signed blob, no key risk).
     *
     * @var list<string>
     */
    private const ALLOWED_METHODS = [
        'eth_chainId', 'net_version', 'eth_blockNumber',
        'eth_getBlockByNumber', 'eth_getBlockByHash',
        'eth_call', 'eth_getLogs', 'eth_getCode',
        'eth_getBalance', 'eth_getTransactionCount',
        'eth_getTransactionByHash', 'eth_getTransactionReceipt',
        'eth_gasPrice', 'eth_estimateGas', 'eth_feeHistory', 'eth_maxPriorityFeePerGas',
        'eth_sendRawTransaction',
    ];

    public function __invoke(Request $request, string $network): JsonResponse
    {
        /** @var array<string, string> $upstreams */
        $upstreams = (array) config('privacy.railgun.engine.rpc_upstream', []);
        $upstream = trim((string) ($upstreams[$network] ?? ''));

        if ($upstream === '') {
            return response()->json([
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32601, 'message' => 'RPC proxy not configured for this network.'],
                'id'      => null,
            ], 404);
        }

        /** @var mixed $payload */
        $payload = $request->json()->all();
        $methods = $this->extractMethods($payload);

        // Reject empty / unparseable bodies (e.g. an empty batch []) — they have
        // no method to whitelist and must not be forwarded to the upstream.
        if ($methods === []) {
            return response()->json([
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32600, 'message' => 'Invalid JSON-RPC request.'],
                'id'      => null,
            ], 400);
        }

        // Reject any non-whitelisted method (handles single + batch requests).
        foreach ($methods as $method) {
            if (! in_array($method, self::ALLOWED_METHODS, true)) {
                Log::warning('RAILGUN RPC proxy: blocked non-whitelisted method', ['network' => $network, 'method' => $method]);

                return response()->json([
                    'jsonrpc' => '2.0',
                    'error'   => ['code' => -32601, 'message' => 'Method not allowed via the RAILGUN RPC proxy.'],
                    'id'      => null,
                ], 403);
            }
        }

        // Audit broadcasts (rare in normal engine operation) against the signed user.
        if (in_array('eth_sendRawTransaction', $methods, true)) {
            Log::info('RAILGUN RPC proxy: forwarding eth_sendRawTransaction', ['network' => $network, 'u' => (string) $request->query('u', '')]);
        }

        try {
            $response = Http::timeout(15)
                ->acceptJson()
                ->asJson()
                ->post($upstream, $payload);
        } catch (Throwable $e) {
            // NEVER log $e->getMessage() — Guzzle embeds the full upstream URL
            // (which contains the provider API key) in connection-error messages.
            Log::error('RAILGUN RPC proxy: upstream request failed', ['network' => $network, 'exception' => $e::class]);

            return response()->json([
                'jsonrpc' => '2.0',
                'error'   => ['code' => -32603, 'message' => 'Upstream RPC error.'],
                'id'      => null,
            ], 502);
        }

        // Pass the provider's JSON-RPC response through verbatim (never the upstream URL).
        return response()->json($response->json(), $response->status());
    }

    /**
     * Collect the JSON-RPC method name(s) from a single or batch request body.
     *
     * @param mixed $payload
     * @return list<string>
     */
    private function extractMethods($payload): array
    {
        $items = (is_array($payload) && array_is_list($payload)) ? $payload : [$payload];
        $methods = [];

        foreach ($items as $item) {
            if (is_array($item) && isset($item['method']) && is_string($item['method'])) {
                $methods[] = $item['method'];
            } else {
                // Malformed/method-less entry — treat as disallowed by returning a
                // sentinel that fails the whitelist check.
                $methods[] = '__invalid__';
            }
        }

        return $methods;
    }
}
