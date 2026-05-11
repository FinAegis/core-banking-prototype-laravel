<?php

/**
 * CueController — Plan B Slice 4 cue queue endpoints.
 *
 * GET  /api/v1/me/pending-cues        — list pending cues for authenticated user
 * POST /api/v1/me/cues/{cueId}/dismissed — dismiss a cue (idempotent)
 *
 * Precondition reaping is server-side: cues whose precondition is no longer
 * met are excluded from the response. Mobile does not check preconditions.
 *
 * @see docs/superpowers/specs/2026-05-10-slice-4-cue-queue-design.md §5.3
 */

declare(strict_types=1);

namespace App\Domain\Subscription\Http\Controllers;

use App\Domain\Subscription\Models\Cue;
use App\Domain\Subscription\Services\CuePreconditionInterface;
use App\Domain\Subscription\Services\CueRepository;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class CueController
{
    public function __construct(
        private readonly CueRepository $cueRepository,
    ) {
    }

    /**
     * GET /api/v1/me/pending-cues.
     *
     * Returns pending cues sorted by priority (critical→high→normal) then due_at ASC.
     * Applies server-side precondition reaping per spec §5.3.
     */
    public function pendingCues(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Eager-load Cashier subscription so preconditions can call
        // $user->subscription('default') without N+1 queries.
        $user->load('subscriptions');

        $now = now();

        /** @var \Illuminate\Database\Eloquent\Collection<int, Cue> $cues */
        $cues = Cue::query()
            ->where('user_id', $user->id)
            ->whereNull('dismissed_at')
            ->where('due_at', '<=', $now)
            ->where('expires_at', '>=', $now)
            ->get();

        // Apply server-side precondition reaping.
        $filtered = $cues->filter(function (Cue $cue) use ($user): bool {
            $preconditionClass = $this->cueRepository->preconditionClassFor($cue->kind);

            if ($preconditionClass === null) {
                return true;
            }

            /** @var CuePreconditionInterface $precondition */
            $precondition = new $preconditionClass();

            return $precondition->isMet($user, $cue);
        });

        // Sort: priority order (critical first) then due_at ASC within priority.
        $priorityOrder = ['critical' => 0, 'high' => 1, 'normal' => 2];

        $sorted = $filtered->sortBy(function (Cue $cue) use ($priorityOrder): array {
            return [
                $priorityOrder[$cue->priority] ?? 99,
                $cue->due_at->timestamp,
            ];
        })->values();

        $data = $sorted->map(fn (Cue $cue): array => [
            'id'        => $cue->id,
            'kind'      => $cue->kind,
            'priority'  => $cue->priority,
            'dueAt'     => $cue->due_at->toIso8601ZuluString(),
            'expiresAt' => $cue->expires_at->toIso8601ZuluString(),
            'payload'   => $cue->payload,
        ])->all();

        return response()->json($data);
    }

    /**
     * POST /api/v1/me/cues/{cueId}/dismissed.
     *
     * Idempotent dismiss. Requires Idempotency-Key header (idempotency.required middleware).
     * Returns 200 with cue dismissedAt on success; 200 if already dismissed; 404 if not found.
     */
    public function dismiss(Request $request, string $cueId): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $dismissedAction = $request->input('dismissedAction', 'dismissed');
        if (! in_array($dismissedAction, ['cancelled', 'kept', 'dismissed'], true)) {
            $dismissedAction = 'dismissed';
        }

        /** @var Cue|JsonResponse $result */
        $result = DB::transaction(function () use ($cueId, $user, $dismissedAction): Cue|JsonResponse {
            /** @var Cue|null $cue */
            $cue = Cue::query()
                ->where('id', $cueId)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($cue === null) {
                return ErrorResponse::make('ERR_CUE_001');
            }

            if ($cue->dismissed_at === null) {
                $cue->dismissed_at = now();
                $cue->dismissed_action = $dismissedAction;
                $cue->save();
            }

            return $cue;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        assert($result->dismissed_at !== null);

        return response()->json([
            'id'          => $result->id,
            'dismissedAt' => $result->dismissed_at->toIso8601ZuluString(),
        ]);
    }
}
