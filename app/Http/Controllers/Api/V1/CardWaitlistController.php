<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Domain\CardIssuance\Models\CardWaitlist;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CardWaitlistController extends Controller
{
    /**
     * Register the authenticated user on the card pre-order waitlist.
     *
     * POST /api/v1/cards/waitlist
     * 201 — newly created entry
     * 409 — already on waitlist (returns existing entry)
     */
    public function join(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $existing = CardWaitlist::where('user_id', $user->id)->first();

        if ($existing !== null) {
            return response()->json([
                'id'       => 'wl_' . $existing->id,
                'position' => $existing->position,
                'joinedAt' => $existing->joined_at->toIso8601String(),
            ], 409);
        }

        $entry = DB::transaction(function () use ($user) {
            $position = CardWaitlist::lockForUpdate()->max('position') ?? 0;
            $position++;

            return CardWaitlist::create([
                'user_id'   => $user->id,
                'position'  => $position,
                'joined_at' => now(),
            ]);
        });

        return response()->json([
            'id'       => 'wl_' . $entry->id,
            'position' => $entry->position,
            'joinedAt' => $entry->joined_at->toIso8601String(),
        ], 201);
    }

    /**
     * Return the waitlist status for the authenticated user.
     *
     * GET /api/v1/cards/waitlist/status
     * 200 — joined=true if on waitlist, joined=false otherwise
     */
    public function status(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $entry = CardWaitlist::where('user_id', $user->id)->first();

        if ($entry === null) {
            return response()->json([
                'joined'   => false,
                'position' => null,
                'joinedAt' => null,
            ]);
        }

        return response()->json([
            'joined'   => true,
            'position' => $entry->position,
            'joinedAt' => $entry->joined_at->toIso8601String(),
        ]);
    }
}
