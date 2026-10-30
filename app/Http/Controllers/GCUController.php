<?php

namespace App\Http\Controllers;

use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Log;
use OpenApi\Attributes as OA;

#[OA\Tag(
    name: 'GCU Demo',
    description: 'GCU demo (software demonstration) composition and information'
)]
class GCUController extends Controller
{
        #[OA\Get(
            path: '/gcu',
            operationId: 'gCUTokenIndex',
            tags: ['GCU Demo'],
            summary: 'GCU dashboard',
            description: 'Returns the GCU demo information page'
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
        // Fetch real-time composition data from API
        $compositionData = $this->fetchCompositionData();

        return view('gcu.index', compact('compositionData'));
    }

        #[OA\Get(
            path: '/gcu/composition',
            operationId: 'gCUTokenFetchCompositionData',
            tags: ['GCU Demo'],
            summary: 'Get GCU composition data',
            description: 'Returns the current GCU demo basket composition data'
        )]
    #[OA\Response(
        response: 200,
        description: 'Successful operation'
    )]
    #[OA\Response(
        response: 500,
        description: 'Server error'
    )]
    protected function fetchCompositionData()
    {
        // Cache the API response for 60 seconds to avoid excessive API calls
        return Cache::remember(
            'gcu_composition',
            60,
            function () {
                try {
                    // Use internal API endpoint
                    $response = Http::get(url('/api/v2/gcu/composition'));

                    if ($response->successful()) {
                        return $response->json();
                    }
                } catch (Exception $e) {
                    Log::error('Failed to fetch GCU composition data: ' . $e->getMessage());
                }

                // Fallback to static config if API fails
                return [
                    'composition' => config('platform.gcu.composition'),
                    'performance' => [
                        'value'      => 1.0000,
                        'change_24h' => 0,
                        'change_7d'  => 0,
                        'change_30d' => 0,
                    ],
                    'last_updated' => now()->toIso8601String(),
                ];
            }
        );
    }
}
