<?php

namespace App\Http\Controllers;

use App\Domain\Account\Models\Account;
use App\Models\ApiKey;
use App\Models\User;
use App\Support\DecimalString;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'API Keys',
    description: 'API key management for developers'
)]
class ApiKeyController extends Controller
{
        #[OA\Get(
            path: '/api-keys',
            operationId: 'aPIKeysIndex',
            tags: ['API Keys'],
            summary: 'List API keys',
            description: 'Returns the API keys management page',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        #[OA\Get(
            path: '/api-keys/create',
            operationId: 'aPIKeysCreate',
            tags: ['API Keys'],
            summary: 'Show create API key form',
            description: 'Shows the form to create a new API key',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function create()
    {
        $accounts = $this->ownAccounts(Auth::user());

        return view('api-keys.create', compact('accounts'));
    }

        #[OA\Post(
            path: '/api-keys',
            operationId: 'aPIKeysStore',
            tags: ['API Keys'],
            summary: 'Create a new API key',
            description: 'Creates a new API key',
            security: [['sanctum' => []]]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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
            ] + $this->moneyControlRules(Auth::user()),
            $this->moneyControlMessages(),
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
            ] + $this->moneyControls($validated)
        );

        // Store the API key in session to show it once
        session()->flash('new_api_key', $result['plain_key']);

        return redirect()->route('api-keys.show', $result['api_key'])
            ->with('success', 'API key created successfully. Please copy it now as it won\'t be shown again.');
    }

        #[OA\Get(
            path: '/api-keys/{id}',
            operationId: 'aPIKeysShow',
            tags: ['API Keys'],
            summary: 'Show API key details',
            description: 'Returns details of a specific API key',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        $pinnedAccounts = $this->ownAccounts(Auth::user())
            ->whereIn('uuid', $apiKey->pinnedAccountUuids())
            ->values();

        return view('api-keys.show', compact('apiKey', 'stats', 'recentLogs', 'pinnedAccounts'));
    }

        #[OA\Get(
            path: '/api-keys/{id}/edit',
            operationId: 'aPIKeysEdit',
            tags: ['API Keys'],
            summary: 'Show edit API key form',
            description: 'Shows the form to edit an API key',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    public function edit(ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $accounts = $this->ownAccounts(Auth::user());

        return view('api-keys.edit', compact('apiKey', 'accounts'));
    }

        #[OA\Put(
            path: '/api-keys/{id}',
            operationId: 'aPIKeysUpdate',
            tags: ['API Keys'],
            summary: 'Update an API key',
            description: 'Updates an existing API key',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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
            ] + $this->moneyControlRules(Auth::user()),
            $this->moneyControlMessages(),
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
            ] + $this->moneyControls($validated)
        );

        return redirect()->route('api-keys.show', $apiKey)
            ->with('success', 'API key updated successfully.');
    }

        #[OA\Delete(
            path: '/api-keys/{id}',
            operationId: 'aPIKeysDestroy',
            tags: ['API Keys'],
            summary: 'Revoke an API key',
            description: 'Revokes and deletes an API key',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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

        #[OA\Post(
            path: '/api-keys/{id}/regenerate',
            operationId: 'aPIKeysRegenerate',
            tags: ['API Keys'],
            summary: 'Regenerate an API key',
            description: 'Regenerates the secret for an API key',
            security: [['sanctum' => []]],
            parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
            ]
        )]
    #[OA\Response(
        response: 201,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
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
                // The new key keeps the old key's money controls.
                'transfer_limit'        => $apiKey->transfer_limit,
                'daily_limit'           => $apiKey->daily_limit,
                'allowed_account_uuids' => $apiKey->allowed_account_uuids,
            ]
        );

        // Store the API key in session to show it once
        session()->flash('new_api_key', $result['plain_key']);

        return redirect()->route('api-keys.show', $result['api_key'])
            ->with('success', 'API key regenerated successfully. Please copy the new key as it won\'t be shown again.');
    }

    /**
     * The owner's accounts, for the account picker.
     *
     * @return Collection<int, Account>
     */
    private function ownAccounts(?User $user): Collection
    {
        if (! $user instanceof User) {
            return collect();
        }

        return Account::query()
            ->where('user_uuid', $user->uuid)
            ->orderBy('name')
            ->get(['uuid', 'name']);
    }

    /**
     * Validation for the money limits and the account picker. Limits are
     * optional (empty = the standard limit), positive, at most two decimals
     * and not above the ceiling; picked accounts must be the owner's.
     *
     * @return array<string, mixed>
     */
    private function moneyControlRules(?User $user): array
    {
        $limitRule = function (string $attribute, mixed $value, Closure $fail): void {
            $amount = is_string($value) && preg_match('/^\d{1,15}(\.\d{1,2})?$/', $value) === 1
                ? DecimalString::fromNumeric($value, 2)
                : null;

            if ($amount === null) {
                $fail('Enter the limit as a number with no more than two digits after the decimal point, for example 250 or 250.50.');

                return;
            }

            if (bccomp($amount, '0', 2) <= 0) {
                $fail('The limit must be more than zero.');

                return;
            }

            $ceiling = ApiKey::limitCeiling();
            if (bccomp($amount, $ceiling, 2) > 0) {
                $fail('The limit cannot be more than ' . ApiKey::displayAmount($ceiling) . '.');
            }
        };

        $ownUuids = $this->ownAccounts($user)->pluck('uuid')->map(fn ($uuid): string => (string) $uuid)->all();

        return [
            'transfer_limit'          => ['nullable', $limitRule],
            'daily_limit'             => ['nullable', $limitRule],
            'allowed_account_uuids'   => ['nullable', 'array'],
            'allowed_account_uuids.*' => [
                'string',
                function (string $attribute, mixed $value, Closure $fail) use ($ownUuids): void {
                    if (! in_array($value, $ownUuids, true)) {
                        $fail('You can only choose your own accounts.');
                    }
                },
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function moneyControlMessages(): array
    {
        return [
            'allowed_account_uuids.array'    => 'You can only choose your own accounts.',
            'allowed_account_uuids.*.string' => 'You can only choose your own accounts.',
        ];
    }

    /**
     * The money controls to store, from validated input.
     *
     * @param  array<string, mixed>  $validated
     * @return array{transfer_limit: string|null, daily_limit: string|null, allowed_account_uuids: list<string>|null}
     */
    private function moneyControls(array $validated): array
    {
        $limit = fn (mixed $value): ?string => DecimalString::fromNumeric($value, 2);

        $accounts = array_values(array_unique(array_filter(
            (array) ($validated['allowed_account_uuids'] ?? []),
            fn ($uuid): bool => is_string($uuid) && $uuid !== '',
        )));

        return [
            'transfer_limit'        => $limit($validated['transfer_limit'] ?? null),
            'daily_limit'           => $limit($validated['daily_limit'] ?? null),
            'allowed_account_uuids' => $accounts === [] ? null : $accounts,
        ];
    }
}
