<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Shared\Models\Plugin;
use Illuminate\Http\Request;
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
        $query = Plugin::query()->orderBy('vendor')->orderBy('name');

        // Search by name, vendor, or description
        if ($search = $request->input('search')) {
            $search = (string) $search;
            $query->where(function ($q) use ($search): void {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('vendor', 'like', "%{$search}%")
                    ->orWhere('display_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        // Filter by status
        if ($status = $request->input('status')) {
            $query->where('status', (string) $status);
        }

        // Filter by vendor
        if ($vendor = $request->input('vendor')) {
            $query->byVendor((string) $vendor);
        }

        $plugins = $query->paginate(12)->withQueryString();

        // Get stats for the header
        $stats = [
            'total'   => Plugin::count(),
            'active'  => Plugin::active()->count(),
            'vendors' => Plugin::distinct()->count('vendor'),
        ];

        // Get unique vendors for filter dropdown
        $vendors = Plugin::select('vendor')
            ->distinct()
            ->orderBy('vendor')
            ->pluck('vendor');

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
