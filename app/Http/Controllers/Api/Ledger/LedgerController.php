<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\Ledger;

use App\Domain\Ledger\Enums\AccountType;
use App\Domain\Ledger\Services\ChartOfAccountsService;
use App\Domain\Ledger\Services\LedgerService;
use App\Http\Controllers\Controller;
use App\Support\DecimalString;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

final class LedgerController extends Controller
{
    public function __construct(
        private readonly LedgerService $ledgerService,
        private readonly ChartOfAccountsService $chartOfAccountsService,
    ) {
    }

    /**
     * List all chart of accounts.
     */
    public function accounts(): JsonResponse
    {
        $accounts = $this->chartOfAccountsService->getAll();

        return response()->json([
            'data' => $accounts->map(fn ($account): array => [
                'code'        => $account->code,
                'name'        => $account->name,
                'type'        => $account->type->value,
                'parent_code' => $account->parent_code,
                'currency'    => $account->currency,
                'is_active'   => $account->is_active,
            ])->values(),
        ]);
    }

    /**
     * Create a new ledger account.
     */
    public function createAccount(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code'        => 'required|string|max:20|unique:ledger_accounts,code',
            'name'        => 'required|string|max:255',
            'type'        => 'required|string|in:asset,liability,equity,revenue,expense',
            'parent_code' => 'nullable|string|max:20|exists:ledger_accounts,code',
            'currency'    => 'nullable|string|size:3',
            'description' => 'nullable|string|max:500',
        ]);

        $account = $this->chartOfAccountsService->createAccount(
            code: $validated['code'],
            name: $validated['name'],
            type: AccountType::from($validated['type']),
            parentCode: $validated['parent_code'] ?? null,
            currency: $validated['currency'] ?? 'USD',
            description: $validated['description'] ?? null,
        );

        return response()->json([
            'data' => [
                'code'        => $account->code,
                'name'        => $account->name,
                'type'        => $account->type->value,
                'parent_code' => $account->parent_code,
                'currency'    => $account->currency,
                'is_active'   => $account->is_active,
            ],
        ], 201);
    }

    /**
     * Get balance for a specific account.
     */
    public function balance(string $code): JsonResponse
    {
        try {
            $balance = $this->ledgerService->getBalance($code);

            return response()->json(['data' => $balance]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 404);
        }
    }

    /**
     * Get transaction history for an account.
     */
    public function history(Request $request, string $code): JsonResponse
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to'   => 'nullable|date|after_or_equal:from',
        ]);

        $from = isset($validated['from']) ? Carbon::parse($validated['from']) : Carbon::now()->subDays(30);
        $to = isset($validated['to']) ? Carbon::parse($validated['to']) : Carbon::now();

        $history = $this->ledgerService->getAccountHistory($code, $from, $to);

        return response()->json(['data' => $history->values()]);
    }

    /**
     * Post a new journal entry.
     */
    public function postEntry(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'description'          => 'required|string|max:500',
            'source_domain'        => 'nullable|string|max:100',
            'lines'                => 'required|array|min:2',
            'lines.*.account_code' => 'required|string|max:20',
            'lines.*.debit'        => 'required|numeric|min:0',
            'lines.*.credit'       => 'required|numeric|min:0',
            'lines.*.narrative'    => 'nullable|string|max:255',
        ]);

        try {
            $lines = [];
            foreach ($validated['lines'] as $line) {
                // The numeric rule also lets through values bcmath rejects,
                // such as 1e3 or a JSON float PHP prints as 1.0E+15.
                $debit = DecimalString::fromNumeric($line['debit'], 4);
                $credit = DecimalString::fromNumeric($line['credit'], 4);

                if ($debit === null || $credit === null) {
                    throw new RuntimeException('Each debit and credit must be a finite decimal number.');
                }

                $entry = [
                    'account_code' => (string) $line['account_code'],
                    'debit'        => $debit,
                    'credit'       => $credit,
                ];

                if (isset($line['narrative'])) {
                    $entry['narrative'] = (string) $line['narrative'];
                }

                $lines[] = $entry;
            }

            $entry = $this->ledgerService->post(
                description: $validated['description'],
                lines: $lines,
                sourceDomain: $validated['source_domain'] ?? null,
            );

            return response()->json([
                'data' => [
                    'id'           => $entry->id,
                    'entry_number' => $entry->entry_number,
                    'description'  => $entry->description,
                    'status'       => $entry->status->value,
                    'posted_at'    => $entry->posted_at?->toIso8601String(),
                ],
            ], 201);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Reverse a posted journal entry.
     */
    public function reverseEntry(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        try {
            $reversal = $this->ledgerService->reverse($id, $validated['reason']);

            return response()->json([
                'data' => [
                    'id'           => $reversal->id,
                    'entry_number' => $reversal->entry_number,
                    'description'  => $reversal->description,
                    'status'       => $reversal->status->value,
                    'posted_at'    => $reversal->posted_at?->toIso8601String(),
                ],
            ]);
        } catch (RuntimeException $e) {
            return response()->json(['error' => $e->getMessage()], 422);
        }
    }

    /**
     * Get the trial balance report.
     */
    public function trialBalance(): JsonResponse
    {
        $trialBalance = $this->ledgerService->getTrialBalance();

        $data = [];
        foreach ($trialBalance as $accountCode => $row) {
            $data[] = [
                'account_code' => $accountCode,
                'debit'        => $row['debit'],
                'credit'       => $row['credit'],
                'balance'      => $row['balance'],
            ];
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Get reconciliation history for a domain.
     */
    public function reconciliation(string $domain): JsonResponse
    {
        Log::info('Reconciliation history requested', ['domain' => $domain]);

        return response()->json([
            'data'   => [],
            'domain' => $domain,
        ]);
    }
}
