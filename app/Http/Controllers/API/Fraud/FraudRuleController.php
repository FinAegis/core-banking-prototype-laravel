<?php

namespace App\Http\Controllers\API\Fraud;

use App\Domain\Fraud\Models\FraudRule;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * @OA\Tag(
 *     name="Fraud Rules",
 *     description="Fraud rule management, testing, import/export"
 * )
 */
class FraudRuleController extends Controller
{
    /**
     * @OA\Get(
     *     path="/api/v2/fraud/rules",
     *     operationId="fraudRulesIndex",
     *     tags={"Fraud Rules"},
     *     summary="List fraud rules",
     *     description="Returns paginated list of fraud rules with filters",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate(
            [
                'category'    => 'nullable|in:velocity,pattern,amount,geography,device,behavior',
                'severity'    => 'nullable|in:low,medium,high,critical',
                'is_active'   => 'nullable|boolean',
                'is_blocking' => 'nullable|boolean',
                'search'      => 'nullable|string|max:100',
            ]
        );

        $query = FraudRule::query();

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('severity')) {
            $query->where('severity', $request->severity);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('is_blocking')) {
            $query->where('is_blocking', $request->boolean('is_blocking'));
        }

        if ($request->search) {
            $searchTerm = $request->search;
            $query->where(
                function ($q) use ($searchTerm) {
                    $q->where('name', 'like', "%{$searchTerm}%")
                        ->orWhere('code', 'like', "%{$searchTerm}%")
                        ->orWhere('description', 'like', "%{$searchTerm}%");
                }
            );
        }

        $rules = $query->orderBy('severity', 'desc')
            ->orderBy('base_score', 'desc')
            ->paginate($request->per_page ?? 20);

        return response()->json($rules);
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/rules/{rule}",
     *     operationId="fraudRulesShow",
     *     tags={"Fraud Rules"},
     *     summary="Get fraud rule details",
     *     description="Returns detailed information about a fraud rule",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function show(string $ruleId): JsonResponse
    {
        $rule = FraudRule::findOrFail($ruleId);

        return response()->json(
            [
                'rule'        => $rule,
                'performance' => $rule->getPerformanceMetrics(),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/rules",
     *     operationId="fraudRulesStore",
     *     tags={"Fraud Rules"},
     *     summary="Create fraud rule",
     *     description="Creates a new fraud detection rule",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate(
            [
                'name'            => 'required|string|max:255|unique:fraud_rules',
                'code'            => 'required|string|max:50|unique:fraud_rules',
                'description'     => 'required|string',
                'category'        => 'required|in:velocity,pattern,amount,geography,device,behavior',
                'severity'        => 'required|in:low,medium,high,critical',
                'conditions'      => 'required|array',
                'thresholds'      => 'nullable|array',
                'actions'         => 'required|array',
                'actions.*'       => 'in:block,flag,notify,challenge',
                'base_score'      => 'required|integer|min:0|max:100',
                'score_modifiers' => 'nullable|array',
                'is_blocking'     => 'boolean',
                'is_active'       => 'boolean',
                'tags'            => 'nullable|array',
                'tags.*'          => 'string|max:50',
            ]
        );

        // Ensure user can create fraud rules
        $this->authorize('create', FraudRule::class);

        $rule = FraudRule::create($request->all());

        // Clear rules cache
        Cache::forget('active_fraud_rules');

        return response()->json(
            [
                'message' => 'Fraud rule created successfully',
                'rule'    => $rule,
            ],
            201
        );
    }

    /**
     * @OA\Put(
     *     path="/api/v2/fraud/rules/{rule}",
     *     operationId="fraudRulesUpdate",
     *     tags={"Fraud Rules"},
     *     summary="Update fraud rule",
     *     description="Updates an existing fraud detection rule",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function update(Request $request, string $ruleId): JsonResponse
    {
        $request->validate(
            [
                'name'            => 'nullable|string|max:255|unique:fraud_rules,name,' . $ruleId,
                'description'     => 'nullable|string',
                'category'        => 'nullable|in:velocity,pattern,amount,geography,device,behavior',
                'severity'        => 'nullable|in:low,medium,high,critical',
                'conditions'      => 'nullable|array',
                'thresholds'      => 'nullable|array',
                'actions'         => 'nullable|array',
                'actions.*'       => 'in:block,flag,notify,challenge',
                'base_score'      => 'nullable|integer|min:0|max:100',
                'score_modifiers' => 'nullable|array',
                'is_blocking'     => 'nullable|boolean',
                'is_active'       => 'nullable|boolean',
                'tags'            => 'nullable|array',
                'tags.*'          => 'string|max:50',
            ]
        );

        $rule = FraudRule::findOrFail($ruleId);

        // Ensure user can update fraud rules
        $this->authorize('update', $rule);

        $rule->update($request->all());

        // Clear rules cache
        Cache::forget('active_fraud_rules');

        return response()->json(
            [
                'message' => 'Fraud rule updated successfully',
                'rule'    => $rule,
            ]
        );
    }

    /**
     * @OA\Delete(
     *     path="/api/v2/fraud/rules/{rule}",
     *     operationId="fraudRulesDestroy",
     *     tags={"Fraud Rules"},
     *     summary="Delete fraud rule",
     *     description="Deletes a fraud detection rule",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function destroy(string $ruleId): JsonResponse
    {
        $rule = FraudRule::findOrFail($ruleId);

        // Ensure user can delete fraud rules
        $this->authorize('delete', $rule);

        $rule->delete();

        // Clear rules cache
        Cache::forget('active_fraud_rules');

        return response()->json(
            [
                'message' => 'Fraud rule deleted successfully',
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/rules/{rule}/toggle",
     *     operationId="fraudRulesToggleStatus",
     *     tags={"Fraud Rules"},
     *     summary="Toggle rule status",
     *     description="Toggles the active status of a fraud rule",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function toggleStatus(string $ruleId): JsonResponse
    {
        $rule = FraudRule::findOrFail($ruleId);

        // Ensure user can update fraud rules
        $this->authorize('update', $rule);

        $rule->update(['is_active' => ! $rule->is_active]);

        // Clear rules cache
        Cache::forget('active_fraud_rules');

        return response()->json(
            [
                'message' => 'Rule status toggled successfully',
                'rule'    => $rule,
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/rules/{rule}/test",
     *     operationId="fraudRulesTest",
     *     tags={"Fraud Rules"},
     *     summary="Test fraud rule",
     *     description="Tests a fraud rule against provided context",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Parameter(name="rule", in="path", required=true, @OA\Schema(type="string")),
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function test(Request $request, string $ruleId): JsonResponse
    {
        $request->validate(
            [
                'context' => 'required|array',
            ]
        );

        $rule = FraudRule::findOrFail($ruleId);

        // Ensure user can test fraud rules
        $this->authorize('test', $rule);

        $triggered = $rule->evaluate($request->context);
        $score = $triggered ? $rule->calculateScore($request->context) : 0;

        return response()->json(
            [
                'triggered' => $triggered,
                'score'     => $score,
                'rule'      => [
                    'id'       => $rule->id,
                    'name'     => $rule->name,
                    'code'     => $rule->code,
                    'category' => $rule->category,
                    'severity' => $rule->severity,
                ],
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/rules/statistics",
     *     operationId="fraudRulesStatistics",
     *     tags={"Fraud Rules"},
     *     summary="Get rule statistics",
     *     description="Returns fraud rule statistics",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function statistics(Request $request): JsonResponse
    {
        $request->validate(
            [
                'date_from' => 'nullable|date',
                'date_to'   => 'nullable|date|after_or_equal:date_from',
            ]
        );

        $query = FraudRule::query();

        if ($request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $statistics = [
            'total_rules'    => FraudRule::count(),
            'active_rules'   => FraudRule::where('is_active', true)->count(),
            'blocking_rules' => FraudRule::where('is_blocking', true)->count(),
            'by_category'    => FraudRule::groupBy('category')
                ->selectRaw('category, COUNT(*) as count')
                ->pluck('count', 'category'),
            'by_severity' => FraudRule::groupBy('severity')
                ->selectRaw('severity, COUNT(*) as count')
                ->pluck('count', 'severity'),
            'recently_triggered' => FraudRule::where('last_triggered_at', '>=', now()->subDays(7))
                ->count(),
            'never_triggered' => FraudRule::whereNull('last_triggered_at')->count(),
            'most_triggered'  => FraudRule::orderBy('trigger_count', 'desc')
                ->take(10)
                ->get(['id', 'name', 'code', 'trigger_count', 'last_triggered_at']),
        ];

        return response()->json(['statistics' => $statistics]);
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/rules/create-defaults",
     *     operationId="fraudRulesCreateDefaults",
     *     tags={"Fraud Rules"},
     *     summary="Create default rules",
     *     description="Creates default fraud detection rules",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function createDefaults(): JsonResponse
    {
        // Ensure user can create fraud rules
        $this->authorize('create', FraudRule::class);

        $ruleEngine = app(\App\Domain\Fraud\Services\RuleEngineService::class);
        $ruleEngine->createDefaultRules();

        // Clear rules cache
        Cache::forget('active_fraud_rules');

        return response()->json(
            [
                'message'       => 'Default fraud rules created successfully',
                'rules_created' => FraudRule::count(),
            ]
        );
    }

    /**
     * @OA\Get(
     *     path="/api/v2/fraud/rules/export/all",
     *     operationId="fraudRulesExport",
     *     tags={"Fraud Rules"},
     *     summary="Export rules",
     *     description="Exports all fraud rules as JSON",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=200, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function export(): JsonResponse
    {
        // Ensure user can export fraud rules
        $this->authorize('export', FraudRule::class);

        $rules = FraudRule::all()->map(
            function ($rule) {
                return [
                    'name'            => $rule->name,
                    'code'            => $rule->code,
                    'description'     => $rule->description,
                    'category'        => $rule->category,
                    'severity'        => $rule->severity,
                    'conditions'      => $rule->conditions,
                    'thresholds'      => $rule->thresholds,
                    'actions'         => $rule->actions,
                    'base_score'      => $rule->base_score,
                    'score_modifiers' => $rule->score_modifiers,
                    'is_blocking'     => $rule->is_blocking,
                    'is_active'       => $rule->is_active,
                    'tags'            => $rule->tags,
                ];
            }
        );

        return response()->json(
            [
                'rules'       => $rules,
                'exported_at' => now()->toIso8601String(),
                'total_rules' => $rules->count(),
            ]
        );
    }

    /**
     * @OA\Post(
     *     path="/api/v2/fraud/rules/import",
     *     operationId="fraudRulesImport",
     *     tags={"Fraud Rules"},
     *     summary="Import rules",
     *     description="Imports fraud rules from JSON",
     *     security={{"sanctum":{}}},
     *
     *     @OA\Response(response=201, description="Successful operation"),
     *     @OA\Response(response=500, description="Server error")
     * )
     */
    public function import(Request $request): JsonResponse
    {
        $request->validate(
            [
                'rules'              => 'required|array',
                'rules.*.name'       => 'required|string|max:255',
                'rules.*.code'       => 'required|string|max:50',
                'rules.*.category'   => 'required|in:velocity,pattern,amount,geography,device,behavior',
                'rules.*.severity'   => 'required|in:low,medium,high,critical',
                'rules.*.conditions' => 'required|array',
                'rules.*.actions'    => 'required|array',
                'rules.*.base_score' => 'required|integer|min:0|max:100',
            ]
        );

        // Ensure user can import fraud rules
        $this->authorize('import', FraudRule::class);

        $imported = 0;
        $skipped = 0;

        foreach ($request->rules as $ruleData) {
            // Skip if rule with same code exists
            if (FraudRule::where('code', $ruleData['code'])->exists()) {
                $skipped++;

                continue;
            }

            FraudRule::create($ruleData);
            $imported++;
        }

        // Clear rules cache
        Cache::forget('active_fraud_rules');

        return response()->json(
            [
                'message'  => 'Rules imported successfully',
                'imported' => $imported,
                'skipped'  => $skipped,
            ]
        );
    }
}
