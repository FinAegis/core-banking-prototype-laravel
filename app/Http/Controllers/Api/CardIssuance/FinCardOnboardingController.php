<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\CardIssuance;

use App\Domain\CardIssuance\Http\Requests\CreateFinCardCardholderRequest;
use App\Domain\CardIssuance\Http\Requests\UploadKycDocumentRequest;
use App\Domain\CardIssuance\Models\Cardholder;
use App\Domain\CardIssuance\Services\FinCardCardholderService;
use App\Domain\CardIssuance\Support\FinCardCardholderMapper;
use App\Http\Controllers\Controller;
use App\Infrastructure\FinCard\FinCardApiException;
use App\Infrastructure\FinCard\FinCardClient;
use App\Models\User;
use App\Support\ErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;

/**
 * FinCard cardholder onboarding + KYC (Phase 2).
 *
 * Mobile-facing surface for the dedicated FinCard KYC flow: prefill + field
 * schema, ID document upload, cardholder creation, and status polling. Cards
 * can only be created once the cardholder reaches `verified` (pass_audit).
 *
 * @see docs/FINCARD_MOBILE_INTEGRATION.md §4.1
 */
class FinCardOnboardingController extends Controller
{
    /** Fields the mobile app must collect (beyond the three ID photos). */
    private const REQUIRED_FIELDS = [
        'first_name', 'last_name', 'gender', 'birthday', 'nationality',
        'occupation', 'annual_salary', 'expected_monthly_volume', 'account_purpose',
        'phone', 'phone_country_code', 'email', 'country', 'city', 'address',
        'zip_code', 'id_type', 'id_number',
    ];

    public function __construct(
        private readonly FinCardClient $client,
        private readonly FinCardCardholderService $cardholders,
    ) {
    }

    /**
     * Prefilled draft + the field schema the app renders the KYC form from.
     */
    public function onboarding(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ErrorResponse::make('ERR_CARDS_007');
        }

        if ($this->cardholders->existingFor($user) !== null) {
            return ErrorResponse::make('ERR_CARDS_009');
        }

        // Reference data is best-effort — a FinCard outage must not block the
        // form from rendering (occupations degrade to empty).
        $occupations = [];
        try {
            $response = $this->client->getOccupations($this->context($request));
            $occupations = is_array($response['data'] ?? null) ? $response['data'] : [];
        } catch (FinCardApiException $e) {
            Log::warning('FinCard occupations fetch failed', ['msg' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'prefill' => array_filter([
                    'email'   => $user->email,
                    'country' => $user->country_code ?? null,
                ], static fn ($v): bool => $v !== null && $v !== ''),
                'schema' => [
                    'required_fields'    => self::REQUIRED_FIELDS,
                    'id_types'           => FinCardCardholderMapper::ID_TYPES,
                    'occupations'        => $occupations,
                    'required_documents' => ['id_front', 'selfie'],
                    'optional_documents' => ['id_back'],
                ],
            ],
        ]);
    }

    /**
     * Available card products for the tenant (id, network, currency, product
     * name). Optional for the app: a single-product v1 tenant can skip this and
     * omit `card_type_id` on open — the backend applies the configured default.
     * `default_card_type_id` echoes that fallback so the UI can pre-select it.
     * Item shape passes through FinCard *(pending FinCard schema confirmation)*.
     */
    public function cardTypes(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ErrorResponse::make('ERR_CARDS_007');
        }

        try {
            $response = $this->client->getCardTypes($this->context($request));
        } catch (FinCardApiException $e) {
            Log::warning('FinCard card-types fetch failed', ['msg' => $e->getMessage()]);

            return ErrorResponse::make('ERR_CARDS_016');
        }

        $default = config('cardissuance.issuers.fincard.default_card_type_id');

        return response()->json([
            'success' => true,
            'data'    => [
                'card_types'           => is_array($response['data'] ?? null) ? $response['data'] : [],
                'default_card_type_id' => $default !== null ? (int) $default : null,
            ],
        ]);
    }

    /**
     * Upload one KYC photo → returns the fileId to reference on create.
     */
    public function uploadDocument(UploadKycDocumentRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            return ErrorResponse::make('ERR_CARDS_011');
        }

        $contents = file_get_contents($file->getRealPath());
        if ($contents === false) {
            return ErrorResponse::make('ERR_CARDS_011');
        }

        try {
            $fileId = $this->cardholders->uploadDocument(
                $contents,
                $file->getClientOriginalName(),
                $file->getMimeType() ?? 'application/octet-stream',
                $this->context($request),
            );
        } catch (FinCardApiException $e) {
            Log::warning('FinCard document upload failed', ['user_id' => $user->id, 'msg' => $e->getMessage()]);

            return ErrorResponse::make('ERR_CARDS_011');
        }

        if ($fileId === '') {
            return ErrorResponse::make('ERR_CARDS_011');
        }

        return response()->json([
            'success' => true,
            'data'    => ['file_id' => $fileId, 'type' => (string) $request->validated('type')],
        ], 201);
    }

    /**
     * Create the FinCard cardholder and start the KYC review.
     */
    public function createCardholder(CreateFinCardCardholderRequest $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if ($this->cardholders->existingFor($user) !== null) {
            return ErrorResponse::make('ERR_CARDS_009');
        }

        /** @var array<string, mixed> $validated */
        $validated = $request->validated();

        if (FinCardCardholderMapper::isRestrictedCountry((string) $validated['country'])) {
            return ErrorResponse::make('ERR_CARDS_010');
        }

        try {
            $cardholder = $this->cardholders->createCardholder($user, $validated, $this->context($request));
        } catch (FinCardApiException $e) {
            Log::warning('FinCard cardholder create rejected', ['user_id' => $user->id, 'msg' => $e->getMessage()]);

            return ErrorResponse::make('ERR_CARDS_012');
        }

        return response()->json([
            'success' => true,
            'data'    => $this->present($cardholder),
        ], 201);
    }

    /**
     * The user's cardholder KYC status (one cardholder per user).
     */
    public function status(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $cardholder = $this->cardholders->existingFor($user);

        if (! $cardholder instanceof Cardholder) {
            return response()->json([
                'success' => true,
                'data'    => ['cardholder_id' => null, 'kyc_status' => 'not_started', 'kyc_stage' => null],
            ]);
        }

        return response()->json(['success' => true, 'data' => $this->present($cardholder)]);
    }

    /**
     * @return array<string, mixed>
     */
    private function present(Cardholder $cardholder): array
    {
        return [
            'cardholder_id'    => $cardholder->id,
            'kyc_status'       => $cardholder->kyc_status,
            'kyc_stage'        => $cardholder->kyc_stage,
            'rejection_reason' => $cardholder->kyc_rejection_reason,
        ];
    }

    /**
     * FinCard context headers from the mobile request (real end-user IP/device).
     *
     * @return array<string, mixed>
     */
    private function context(Request $request): array
    {
        return [
            'forwarded_for' => $request->ip() ?? '127.0.0.1',
            'platform'      => (string) ($request->header('platform') ?? 'ios'),
            'device_id'     => (string) ($request->header('deviceId') ?? ''),
        ];
    }
}
