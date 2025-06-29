<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class GCUController extends Controller
{
    public function index()
    {
        // Fetch real-time composition data from API
        $compositionData = $this->fetchCompositionData();
        
        return view('gcu.index', compact('compositionData'));
    }
    
    protected function fetchCompositionData()
    {
        // Cache the API response for 60 seconds to avoid excessive API calls
        return Cache::remember('gcu_composition', 60, function () {
            try {
                // Use internal API endpoint
                $response = Http::get(url('/api/v2/gcu/composition'));
                
                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Exception $e) {
                \Log::error('Failed to fetch GCU composition data: ' . $e->getMessage());
            }
            
            // Fallback to static config if API fails
            return [
                'composition' => config('platform.gcu.composition'),
                'performance' => [
                    'value' => 1.0000,
                    'change_24h' => 0,
                    'change_7d' => 0,
                    'change_30d' => 0,
                ],
                'last_updated' => now()->toIso8601String(),
            ];
        });
    }
}