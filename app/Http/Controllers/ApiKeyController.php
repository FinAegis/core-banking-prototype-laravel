<?php

namespace App\Http\Controllers;

use App\Models\ApiKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ApiKeyController extends Controller
{
    /**
     * Display API keys dashboard
     */
    public function index()
    {
        $apiKeys = Auth::user()->apiKeys()
            ->withCount(['logs as requests_today' => function ($query) {
                $query->where('created_at', '>=', now()->startOfDay());
            }])
            ->orderBy('created_at', 'desc')
            ->get();
            
        return view('api-keys.index', compact('apiKeys'));
    }

    /**
     * Show the form for creating a new API key
     */
    public function create()
    {
        return view('api-keys.create');
    }

    /**
     * Store a newly created API key
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'required|array',
            'permissions.*' => 'in:read,write,delete,*',
            'expires_in' => 'nullable|in:30,90,365,never',
            'ip_whitelist' => 'nullable|string',
        ]);

        // Process expiration
        $expiresAt = null;
        if ($validated['expires_in'] !== 'never' && !empty($validated['expires_in'])) {
            $expiresAt = now()->addDays((int)$validated['expires_in']);
        }

        // Process IP whitelist
        $allowedIps = null;
        if (!empty($validated['ip_whitelist'])) {
            $allowedIps = array_map('trim', explode("\n", $validated['ip_whitelist']));
            $allowedIps = array_filter($allowedIps); // Remove empty lines
        }

        // Create API key
        $result = ApiKey::createForUser(Auth::user(), [
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'],
            'allowed_ips' => $allowedIps,
            'expires_at' => $expiresAt,
        ]);

        // Store the API key in session to show it once
        session()->flash('new_api_key', $result['plain_key']);

        return redirect()->route('api-keys.show', $result['api_key'])
            ->with('success', 'API key created successfully. Please copy it now as it won\'t be shown again.');
    }

    /**
     * Display the specified API key
     */
    public function show(ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        // Get usage statistics
        $stats = [
            'total_requests' => $apiKey->request_count,
            'requests_today' => $apiKey->logs()->where('created_at', '>=', now()->startOfDay())->count(),
            'requests_this_month' => $apiKey->logs()->where('created_at', '>=', now()->startOfMonth())->count(),
            'avg_response_time' => $apiKey->logs()->where('created_at', '>=', now()->subDays(7))->avg('response_time'),
            'error_rate' => $apiKey->logs()->where('created_at', '>=', now()->subDays(7))->failed()->count() / max($apiKey->logs()->where('created_at', '>=', now()->subDays(7))->count(), 1) * 100,
        ];

        // Get recent logs
        $recentLogs = $apiKey->logs()
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get();

        return view('api-keys.show', compact('apiKey', 'stats', 'recentLogs'));
    }

    /**
     * Show the form for editing the specified API key
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
     * Update the specified API key
     */
    public function update(Request $request, ApiKey $apiKey)
    {
        // Ensure user owns this API key
        if ($apiKey->user_uuid !== Auth::user()->uuid) {
            abort(403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'permissions' => 'required|array',
            'permissions.*' => 'in:read,write,delete,*',
            'ip_whitelist' => 'nullable|string',
        ]);

        // Process IP whitelist
        $allowedIps = null;
        if (!empty($validated['ip_whitelist'])) {
            $allowedIps = array_map('trim', explode("\n", $validated['ip_whitelist']));
            $allowedIps = array_filter($allowedIps);
        }

        $apiKey->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'permissions' => $validated['permissions'],
            'allowed_ips' => $allowedIps,
        ]);

        return redirect()->route('api-keys.show', $apiKey)
            ->with('success', 'API key updated successfully.');
    }

    /**
     * Revoke the specified API key
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
     * Regenerate the specified API key
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
        $result = ApiKey::createForUser(Auth::user(), [
            'name' => $apiKey->name . ' (Regenerated)',
            'description' => $apiKey->description,
            'permissions' => $apiKey->permissions,
            'allowed_ips' => $apiKey->allowed_ips,
            'expires_at' => $apiKey->expires_at,
        ]);

        // Store the API key in session to show it once
        session()->flash('new_api_key', $result['plain_key']);

        return redirect()->route('api-keys.show', $result['api_key'])
            ->with('success', 'API key regenerated successfully. Please copy the new key as it won\'t be shown again.');
    }
}