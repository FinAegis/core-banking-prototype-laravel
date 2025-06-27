<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class SettingsController extends Controller
{
    /**
     * Get public settings
     */
    public function index(): JsonResponse
    {
        $settings = Setting::where('is_public', true)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value]);
        
        return response()->json([
            'data' => $settings,
        ]);
    }
    
    /**
     * Get settings by group
     */
    public function group(string $group): JsonResponse
    {
        $settings = Setting::where('group', $group)
            ->where('is_public', true)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value]);
        
        return response()->json([
            'data' => $settings,
        ]);
    }
}