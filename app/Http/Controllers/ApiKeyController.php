<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * @OA\Tag(
 *     name="API Keys",
 *     description="API key management for developers"
 * )
 */
class ApiKeyController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api-keys",
     *     operationId="aPIKeysIndex",
     *     tags={"API Keys"},
     *     summary="List API keys",
     *     description="Returns the API keys management page",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index()
    {
        $apiKeys = Auth::user()->apiKeys()
            ->withCount(
                ['logs as requests_today' => function ($query) {
                    $query->where('created_at', '>=', now()->startOfDay());
                }]
            )
            ->orderBy('created_at', 'desc')
            ->get();

        return view('api-keys.index', compact('apiKeys'));
    }

    /**
     * @OA\Get(
     *     path="/api-keys/create",
     *     operationId="aPIKeysCreate",
     *     tags={"API Keys"},
     *     summary="Show create API key form",
     *     description="Shows the form to create a new API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function create()
    {
        return view('api-keys.create');
    }

    /**
     * @OA\Post(
     *     path="/api-keys",
     *     operationId="aPIKeysStore",
     *     tags={"API Keys"},
     *     summary="Create a new API key",
     *     description="Creates a new API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request)
    {
        $validated = $request->validate(
            [
                'name'          => 'required|string|max:255',
                'description'   => 'nullable|string|max:1000',
                'permissions'   => 'required|array',
                'permissions.*' => 'in:read,write,delete,*',
                'expires_in'    => 'nullable|in:30,90,365,never',
                'ip_whitelist'  => 'nullable|string',
            ]
        );

        // Process expiration
        $expiresAt = null;
        if ($validated['expires_in'] !== 'never' && ! empty($validated['expires_in'])) {
            $expiresAt = now()->addDays((int) $validated['expires_in']);
        }

        // Process IP whitelist
        $allowedIps = null;
        if (! empty($validated['ip_whitelist'])) {
            $allowedIps = array_map('trim', explode("\n", $validated['ip_whitelist']));
            $allowedIps = array_filter($allowedIps); // Remove empty lines
        }

        // Create API key
        $result = ApiKey::createForUser(
            Auth::user(),
            [
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'permissions' => $validated['permissions'],
                'allowed_ips' => $allowedIps,
                'expires_at'  => $expiresAt,
            ]
        );

        // Store the API key in session to show it once
        session()->flash('new_api_key', $result['plain_key']);

        return redirect()->route('api-keys.show', $result['api_key'])
            ->with('success', 'API key created successfully. Please copy it now as it won\'t be shown again.');
    }

    /**
     * @OA\Get(
     *     path="/api-keys/{id}",
     *     operationId="aPIKeysShow",
     *     tags={"API Keys"},
     *     summary="Show API key details",
     *     description="Returns details of a specific API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        // Get usage statistics
        /** @var \Illuminate\Database\Eloquent\Relations\HasMany<\App\Models\ApiKeyLog, ApiKey> $logsRelation */
        $logsRelation = $apiKey->logs();
        $weekAgoDate = now()->subDays(7);

        $todayLogsCount = $logsRelation->newQuery()->where('created_at', '>=', now()->startOfDay())->count();
        $monthLogsCount = $logsRelation->newQuery()->where('created_at', '>=', now()->startOfMonth())->count();
        $weeklyAvgResponseTime = $logsRelation->newQuery()->where('created_at', '>=', $weekAgoDate)->avg('response_time') ?? 0;
        $weeklyLogsCount = $logsRelation->newQuery()->where('created_at', '>=', $weekAgoDate)->count();
        $weeklyFailedCount = $weeklyLogsCount > 0 ? $logsRelation->newQuery()->where('created_at', '>=', $weekAgoDate)->where('response_code', '>=', 400)->count() : 0;

        $stats = [
            'total_requests'      => $apiKey->request_count,
            'requests_today'      => $todayLogsCount,
            'requests_this_month' => $monthLogsCount,
            'avg_response_time'   => $weeklyAvgResponseTime,
            'error_rate'          => $weeklyLogsCount > 0 ? ($weeklyFailedCount / $weeklyLogsCount * 100) : 0,
        ];

        // Get recent logs
        $recentLogs = $apiKey->logs()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('api-keys.show', compact('apiKey', 'stats', 'recentLogs'));
    }

    /**
     * @OA\Get(
     *     path="/api-keys/{id}/edit",
     *     operationId="aPIKeysEdit",
     *     tags={"API Keys"},
     *     summary="Show edit API key form",
     *     description="Shows the form to edit an API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function edit(ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        return view('api-keys.edit', compact('apiKey'));
    }

    /**
     * @OA\Put(
     *     path="/api-keys/{id}",
     *     operationId="aPIKeysUpdate",
     *     tags={"API Keys"},
     *     summary="Update an API key",
     *     description="Updates an existing API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $validated = $request->validate(
            [
                'name'          => 'required|string|max:255',
                'description'   => 'nullable|string|max:1000',
                'permissions'   => 'required|array',
                'permissions.*' => 'in:read,write,delete,*',
                'ip_whitelist'  => 'nullable|string',
            ]
        );

        // Process IP whitelist
        $allowedIps = null;
        if (! empty($validated['ip_whitelist'])) {
            $allowedIps = array_map('trim', explode("\n", $validated['ip_whitelist']));
            $allowedIps = array_filter($allowedIps);
        }

        $apiKey->update(
            [
                'name'        => $validated['name'],
                'description' => $validated['description'] ?? null,
                'permissions' => $validated['permissions'],
                'allowed_ips' => $allowedIps,
            ]
        );

        return redirect()->route('api-keys.show', $apiKey)
            ->with('success', 'API key updated successfully.');
    }

    /**
     * @OA\Delete(
     *     path="/api-keys/{id}",
     *     operationId="aPIKeysDestroy",
     *     tags={"API Keys"},
     *     summary="Revoke an API key",
     *     description="Revokes and deletes an API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy(ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $apiKey->revoke();

        return redirect()->route('api-keys.index')
            ->with('success', 'API key revoked successfully.');
    }

    /**
     * @OA\Post(
     *     path="/api-keys/{id}/regenerate",
     *     operationId="aPIKeysRegenerate",
     *     tags={"API Keys"},
     *     summary="Regenerate an API key",
     *     description="Regenerates the secret for an API key",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="id", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function regenerate(ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        // Revoke old key
        $apiKey->revoke();

        // Create new key with same settings
        $result = ApiKey::createForUser(
            Auth::user(),
            [
                'name'        => $apiKey->name . ' (Regenerated)',
                'description' => $apiKey->description,
                'permissions' => $apiKey->permissions,
                'allowed_ips' => $apiKey->allowed_ips,
                'expires_at'  => $apiKey->expires_at,
            ]
        );

        // Store the API key in session to show it once
        session()->flash('new_api_key', $result['plain_key']);

        return redirect()->route('api-keys.show', $result['api_key'])
            ->with('success', 'API key regenerated successfully. Please copy the new key as it won\'t be shown again.');
    }
}
