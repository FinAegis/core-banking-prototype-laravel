<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Models\Plugin;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * Public-facing Plugin Marketplace web controller.
 *
 * Renders the browsable marketplace page with search, category filtering,
 * and plugin detail views. No authentication required for browsing.
 */
class PluginMarketplaceWebController extends Controller
{
    /**
     * Browse the plugin marketplace.
     */
    public function index(Request $request): View
    {
        $validated = $request->validate([
            'search' => 'nullable|string|max:100',
            'status' => 'nullable|string|in:active,inactive,failed',
            'vendor' => 'nullable|string|max:100',
        ]);

        $query = Plugin::query()->orderBy('vendor')->orderBy('name');

        if (! empty($validated['search'])) {
            // Escape LIKE wildcards to prevent pattern injection
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $validated['search']);
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if (! empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }

        if (! empty($validated['vendor'])) {
            $query->byVendor($validated['vendor']);
        }

        $plugins = $query->paginate(12)->withQueryString();

        // Cache stats for 5 minutes to avoid 3 COUNT queries per request
        $stats = Cache::remember('marketplace:stats', 300, fn (): array => [
            'total'   => Plugin::count(),
            'active'  => Plugin::active()->count(),
            'vendors' => Plugin::distinct()->count('vendor'),
        ]);

        $vendors = Cache::remember('marketplace:vendors', 300, fn () => Plugin::select('vendor')
            ->distinct()
            ->orderBy('vendor')
            ->pluck('vendor'));

        return view('marketplace.index', compact('plugins', 'stats', 'vendors'));
    }

    /**
     * View plugin details.
     */
    public function show(string $vendor, string $name): View
    {
        $plugin = Plugin::where('vendor', $vendor)
            ->where('name', $name)
            ->firstOrFail();

        return view('marketplace.show', compact('plugin'));
    }
}
