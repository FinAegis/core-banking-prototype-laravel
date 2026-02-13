<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;

/**
 * @OA\Tag(
 *     name="Settings",
 *     description="Application settings management"
 * )
 */
class SettingsController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/settings",
     *     operationId="settingsIndex",
     *     tags={"Settings"},
     *     summary="Get public settings",
     *     description="Retrieves all public application settings",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(): JsonResponse
    {
        $settings = Setting::where('is_public', true)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value]);

        return response()->json(
            [
                'data' => $settings,
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/settings/group/{group}",
     *     operationId="settingsGroup",
     *     tags={"Settings"},
     *     summary="Get settings by group",
     *     description="Retrieves public settings filtered by group name",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="group", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function group(string $group): JsonResponse
    {
        $settings = Setting::where('group', $group)
            ->where('is_public', true)
            ->get()
            ->mapWithKeys(fn ($setting) => [$setting->key => $setting->value]);

        return response()->json(
            [
                'data' => $settings,
            ]
        );
    }
}
