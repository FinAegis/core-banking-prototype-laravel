<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Privacy;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

/**
 * Non-custodial RAILGUN on-device engine bootstrap (Phase 1).
 *
 * The mobile app runs @railgun-community/wallet on-device and holds all keys.
 * This endpoint hands the device the parameters it needs to point at OUR
 * self-hosted infra:
 *   - poi_node_urls           → startRailgunEngine(..., poiNodeURLs)
 *   - networks[].fallback_provider_config → loadProvider(config, networkName)
 *   - artifact_base_url        → the app's OWN ArtifactStore.get() implementation
 *                                fetches mirrored artifacts from here on a cache
 *                                miss. NOTE: the v9 SDK hardcodes its IPFS gateway
 *                                and does NOT consume this — it is honored only by
 *                                app-side ArtifactStore code, not startRailgunEngine.
 *
 * NOT returned (device-constructed): the LevelDB instance, the ArtifactStore, and
 * shouldDebug — those are positional startRailgunEngine args the device supplies.
 *
 * Shapes mirror the SDK exactly (FallbackProviderJsonConfig, NetworkName values,
 * TXIDVersion). No secrets are returned: RPC URLs must be key-safe, and any URL
 * that appears to embed a credential is dropped defensively (see rpcUrlIsUnsafe).
 */
class RailgunEngineConfigController extends Controller
{
    /**
     * Canonical RAILGUN network metadata. NetworkName values match the SDK enum
     * (@railgun-community/shared-models): note bsc → "BNB_Chain".
     *
     * @var array<string, array{name: string, chain_id: int}>
     */
    private const NETWORK_META = [
        'ethereum' => ['name' => 'Ethereum', 'chain_id' => 1],
        'polygon'  => ['name' => 'Polygon', 'chain_id' => 137],
        'arbitrum' => ['name' => 'Arbitrum', 'chain_id' => 42161],
        'bsc'      => ['name' => 'BNB_Chain', 'chain_id' => 56],
    ];

    #[OA\Get(
        path: '/api/v1/privacy/engine-config',
        operationId: 'getRailgunEngineConfig',
        summary: 'On-device RAILGUN engine bootstrap config',
        description: 'Returns the parameters the on-device RAILGUN engine needs to point at our self-hosted infra (POI node, artifact mirror, RPC). No secrets; RPC URLs are key-safe.',
        security: [['sanctum' => []]],
        tags: ['Privacy'],
        responses: [new OA\Response(response: 200, description: 'Engine bootstrap config')],
    )]
    public function __invoke(Request $request): JsonResponse
    {
        /** @var array<int, string> $enabled */
        $enabled = (array) config('privacy.railgun.networks', []);
        /** @var array<string, string> $rpcUrls */
        $rpcUrls = (array) config('privacy.railgun.engine.rpc_urls', []);

        $networks = [];

        foreach ($enabled as $key) {
            $meta = self::NETWORK_META[$key] ?? null;
            $rpcUrl = trim((string) ($rpcUrls[$key] ?? ''));

            // Omit networks we can't actually serve a provider for — the device
            // would otherwise build a loadProvider config with an empty URL.
            if ($meta === null || $rpcUrl === '') {
                continue;
            }

            // Defense-in-depth: this URL is returned to every authenticated
            // client, so a provider API key embedded in it would leak. Drop the
            // network (graceful, same as an empty URL) rather than serve a key.
            if ($this->rpcUrlIsUnsafe($rpcUrl)) {
                Log::warning('RAILGUN engine-config: dropping RPC URL that appears to embed a credential', ['network' => $key]);

                continue;
            }

            $networks[] = [
                'key'          => $key,
                'network_name' => $meta['name'],
                'chain_id'     => $meta['chain_id'],
                // Directly consumable by loadProvider() — FallbackProviderJsonConfig.
                'fallback_provider_config' => [
                    'chainId'   => $meta['chain_id'],
                    'providers' => [[
                        'provider'        => $rpcUrl,
                        'priority'        => 1,
                        'weight'          => 2,
                        'maxLogsPerBatch' => 1,
                        'stallTimeout'    => 2500,
                    ]],
                ],
            ];
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'wallet_source'        => (string) config('privacy.railgun.engine.wallet_source', 'zelta'),
                'txid_version'         => (string) config('privacy.railgun.engine.txid_version', 'V2_PoseidonMerkle'),
                'use_native_artifacts' => (bool) config('privacy.railgun.engine.use_native_artifacts', true),
                'artifact_base_url'    => (string) config('privacy.railgun.engine.artifact_base_url', ''),
                'poi_node_urls'        => array_values((array) config('privacy.railgun.engine.poi_node_urls', [])),
                'broadcaster_enabled'  => (bool) config('privacy.railgun.engine.broadcaster_enabled', true),
                'networks'             => $networks,
            ],
        ]);
    }

    /**
     * Heuristic guard against an operator misconfiguring a credential-bearing RPC
     * URL (the value is served to every client). High precision, low false-positive:
     * matches a credential query param, or an Alchemy/Infura-style key embedded in
     * a /v2|/v3 path segment. A key-safe proxy like https://rpc.zelta.app/polygon
     * passes cleanly.
     */
    private function rpcUrlIsUnsafe(string $url): bool
    {
        if (preg_match('/[?&](api[-_]?key|apikey|key|token|secret|auth|access[-_]?token)=/i', $url) === 1) {
            return true;
        }

        // /v2/<long opaque token> or /v3/<...> — the canonical provider key-in-path.
        return preg_match('~/v[23]/[A-Za-z0-9_-]{24,}~', $url) === 1;
    }
}
