<?php

declare(strict_types=1);

use App\Domain\PaymentRails\Services\PaymentRailRouter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/payment-rails')->name('api.payment-rails.')->middleware(['auth:sanctum'])->group(function (): void {
    Route::post('/send', function (Request $request): JsonResponse {
        $validated = $request->validate([
            'amount'                     => ['required', 'string'],
            'currency'                   => ['required', 'string', 'size:3'],
            'country'                    => ['required', 'string', 'size:2'],
            'urgency'                    => ['sometimes', 'string', 'in:normal,instant'],
            'beneficiary'                => ['required', 'array'],
            'beneficiary.name'           => ['required', 'string'],
            'beneficiary.account_number' => ['required_without:beneficiary.iban', 'string'],
            'beneficiary.routing_number' => ['required_without:beneficiary.iban', 'string'],
            'beneficiary.iban'           => ['required_without:beneficiary.account_number', 'string'],
            'beneficiary.bic'            => ['nullable', 'string'],
        ]);

        $router = app(PaymentRailRouter::class);
        /** @var App\Models\User $user */
        $user = $request->user();
        $result = $router->route(
            $user->id,
            $validated['amount'],
            $validated['currency'],
            $validated['country'],
            $validated['urgency'] ?? 'normal',
            $validated['beneficiary'],
        );

        return response()->json(['success' => true, 'data' => $result], 201);
    })->name('send');

    Route::get('/supported/{country}', function (string $country): JsonResponse {
        return response()->json([
            'success' => true,
            'data'    => app(PaymentRailRouter::class)->getSupportedRails($country),
        ]);
    })->name('supported');

    Route::get('/transactions/{id}', function (Request $request, string $id): JsonResponse {
        /** @var App\Models\User $user */
        $user = $request->user();
        $result = app(PaymentRailRouter::class)->getTransactionStatus($id, (int) $user->id);
        if ($result === null) {
            return response()->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Transaction not found']], 404);
        }

        return response()->json(['success' => true, 'data' => $result]);
    })->name('transaction.status');
});
