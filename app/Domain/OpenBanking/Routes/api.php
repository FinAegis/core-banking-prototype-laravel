<?php

declare(strict_types=1);

use App\Domain\OpenBanking\Services\AispService;
use App\Domain\OpenBanking\Services\ConsentService;
use App\Domain\OpenBanking\Services\PispService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Consent management (requires user auth only)
Route::prefix('v1/open-banking')->name('api.open-banking.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/consents', function (Request $request): JsonResponse {
        $validated = $request->validate([
            'tpp_id'      => ['required', 'string'],
            'permissions' => ['required', 'array'],
            'account_ids' => ['nullable', 'array'],
        ]);
        $consent = app(ConsentService::class)->createConsent(
            $validated['tpp_id'],
            (int) Auth::id(),
            $validated['permissions'],
            $validated['account_ids'] ?? null,
        );

        return response()->json(['success' => true, 'data' => $consent->toArray()], 201);
    })->name('consents.create');

    Route::get('/consents/{id}', function (string $id): JsonResponse {
        $consent = app(ConsentService::class)->getConsent($id, (int) Auth::id());
        if ($consent === null) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Consent not found']], 404);
        }

        return response()->json(['success' => true, 'data' => $consent->toArray()]);
    })->name('consents.show');

    Route::post('/consents/{id}/authorize', function (string $id): JsonResponse {
        $consent = app(ConsentService::class)->authorizeConsent($id, (int) Auth::id());

        return response()->json(['success' => true, 'data' => $consent->toArray()]);
    })->name('consents.authorize');

    Route::delete('/consents/{id}', function (string $id): JsonResponse {
        $consent = app(ConsentService::class)->revokeConsent($id, (int) Auth::id());

        return response()->json(['success' => true, 'data' => $consent->toArray()]);
    })->name('consents.revoke');

    Route::get('/consents', function (): JsonResponse {
        $consents = app(ConsentService::class)->getActiveConsentsForUser((int) Auth::id());

        return response()->json(['success' => true, 'data' => $consents->toArray()]);
    })->name('consents.index');
});

// AISP/PISP endpoints (requires TPP cert + consent enforcement)
Route::prefix('v1/open-banking')->name('api.open-banking.')->middleware(['auth:sanctum'])->group(function (): void {
    // Account information (AISP)
    Route::get('/accounts', function (Request $request): JsonResponse {
        $consentId = $request->header('X-Consent-ID', '');
        $tppId = $request->attributes->get('tpp_id', '');
        $accounts = app(AispService::class)->getAccounts($consentId, $tppId, (int) Auth::id());

        return response()->json(['success' => true, 'data' => $accounts]);
    })->name('accounts.index');

    Route::get('/accounts/{accountId}', function (Request $request, string $accountId): JsonResponse {
        $consentId = $request->header('X-Consent-ID', '');
        $tppId = $request->attributes->get('tpp_id', '');
        $account = app(AispService::class)->getAccountDetail($consentId, $tppId, (int) Auth::id(), $accountId);

        return response()->json(['success' => true, 'data' => $account]);
    })->name('accounts.show');

    Route::get('/accounts/{accountId}/balances', function (Request $request, string $accountId): JsonResponse {
        $consentId = $request->header('X-Consent-ID', '');
        $tppId = $request->attributes->get('tpp_id', '');
        $balances = app(AispService::class)->getBalances($consentId, $tppId, (int) Auth::id(), $accountId);

        return response()->json(['success' => true, 'data' => $balances]);
    })->name('accounts.balances');

    Route::get('/accounts/{accountId}/transactions', function (Request $request, string $accountId): JsonResponse {
        $consentId = $request->header('X-Consent-ID', '');
        $tppId = $request->attributes->get('tpp_id', '');
        $transactions = app(AispService::class)->getTransactions(
            $consentId,
            $tppId,
            (int) Auth::id(),
            $accountId,
            $request->query('from_date'),
            $request->query('to_date'),
        );

        return response()->json(['success' => true, 'data' => $transactions]);
    })->name('accounts.transactions');

    // Payment initiation (PISP)
    Route::post('/payments', function (Request $request): JsonResponse {
        $consentId = $request->header('X-Consent-ID', '');
        $tppId = $request->attributes->get('tpp_id', '');
        $validated = $request->validate([
            'creditor_iban' => ['required', 'string'],
            'amount'        => ['required', 'string'],
            'currency'      => ['required', 'string', 'size:3'],
            'reference'     => ['nullable', 'string'],
        ]);

        $result = app(PispService::class)->initiatePayment($consentId, $tppId, (int) Auth::id(), $validated);

        return response()->json(['success' => true, 'data' => $result], 201);
    })->name('payments.create');

    Route::get('/payments/{paymentId}', function (string $paymentId): JsonResponse {
        $result = app(PispService::class)->getPaymentStatus($paymentId);

        return response()->json(['success' => true, 'data' => $result]);
    })->name('payments.show');
});
