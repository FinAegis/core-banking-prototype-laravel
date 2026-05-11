<?php

/**
 * MarketingOptOutController — Plan B Slice 4 marketing opt-out endpoint.
 *
 * POST /api/v1/me/marketing-opt-out
 *
 * Sets users.pro_marketing_opt_out. PECR/ePrivacy compliance.
 * The pro_trial_reminder_d1 delayed job checks this flag at fire time.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.8
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class MarketingOptOutController
{
    /**
     * POST /api/v1/me/marketing-opt-out.
     *
     * Body: { "optOut": true | false }
     * Response: 200 { "proMarketingOptOut": bool }
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $optOut = (bool) $request->input('optOut', true);

        $user->forceFill(['pro_marketing_opt_out' => $optOut])->save();

        return response()->json(['proMarketingOptOut' => $optOut]);
    }
}
