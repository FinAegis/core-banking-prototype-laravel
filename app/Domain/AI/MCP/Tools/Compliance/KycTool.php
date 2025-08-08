<?php

declare(strict_types=1);

namespace App\Domain\AI\MCP\Tools\Compliance;

use App\Domain\AI\Contracts\MCPToolInterface;
use App\Domain\AI\ValueObjects\ToolExecutionResult;
use App\Domain\Compliance\Services\KycService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class KycTool implements MCPToolInterface
{
    public function __construct(
        private readonly KycService $kycService
    ) {
    }

    public function getName(): string
    {
        return 'compliance.kyc';
    }

    public function getCategory(): string
    {
        return 'compliance';
    }

    public function getDescription(): string
    {
        return 'Check and manage KYC (Know Your Customer) status';
    }

    public function getInputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'user_uuid' => [
                    'type'        => 'string',
                    'description' => 'UUID of the user to check/manage KYC',
                    'pattern'     => '^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$',
                ],
                'action' => [
                    'type'        => 'string',
                    'description' => 'Action to perform',
                    'enum'        => ['check_status', 'get_requirements', 'verify', 'reject'],
                ],
                'verified_by' => [
                    'type'        => 'string',
                    'description' => 'Verifier identifier (required for verify/reject actions)',
                ],
                'reason' => [
                    'type'        => 'string',
                    'description' => 'Reason for rejection (required for reject action)',
                ],
                'level' => [
                    'type'        => 'string',
                    'description' => 'KYC level for verification',
                    'enum'        => ['basic', 'enhanced', 'full'],
                    'default'     => 'enhanced',
                ],
                'risk_rating' => [
                    'type'        => 'string',
                    'description' => 'Risk rating assessment',
                    'enum'        => ['low', 'medium', 'high', 'very_high'],
                    'default'     => 'low',
                ],
                'pep_status' => [
                    'type'        => 'boolean',
                    'description' => 'Politically Exposed Person status',
                    'default'     => false,
                ],
            ],
            'required' => ['user_uuid', 'action'],
        ];
    }

    public function getOutputSchema(): array
    {
        return [
            'type'       => 'object',
            'properties' => [
                'user_uuid'        => ['type' => 'string'],
                'kyc_status'       => ['type' => 'string'],
                'kyc_level'        => ['type' => 'string'],
                'risk_rating'      => ['type' => 'string'],
                'pep_status'       => ['type' => 'boolean'],
                'submitted_at'     => ['type' => 'string'],
                'approved_at'      => ['type' => 'string'],
                'rejected_at'      => ['type' => 'string'],
                'expires_at'       => ['type' => 'string'],
                'documents_count'  => ['type' => 'integer'],
                'requirements'     => ['type' => 'array'],
                'action_performed' => ['type' => 'string'],
                'message'          => ['type' => 'string'],
            ],
        ];
    }

    public function execute(array $parameters, ?string $conversationId = null): ToolExecutionResult
    {
        try {
            $userUuid = $parameters['user_uuid'];
            $action = $parameters['action'];

            Log::info('MCP Tool: KYC action', [
                'user_uuid'       => $userUuid,
                'action'          => $action,
                'conversation_id' => $conversationId,
            ]);

            // Get user
            $user = User::where('uuid', $userUuid)->first();

            if (! $user) {
                return ToolExecutionResult::failure("User not found: {$userUuid}");
            }

            // Check authorization
            if (! $this->canAccessKyc($user)) {
                return ToolExecutionResult::failure('Unauthorized access to KYC information');
            }

            switch ($action) {
                case 'check_status':
                    return $this->checkStatus($user);

                case 'get_requirements':
                    return $this->getRequirements($user);

                case 'verify':
                    return $this->verifyKyc($user, $parameters);

                case 'reject':
                    return $this->rejectKyc($user, $parameters);

                default:
                    return ToolExecutionResult::failure("Unknown action: {$action}");
            }
        } catch (\Exception $e) {
            Log::error('MCP Tool error: compliance.kyc', [
                'error'      => $e->getMessage(),
                'parameters' => $parameters,
            ]);

            return ToolExecutionResult::failure($e->getMessage());
        }
    }

    private function checkStatus(User $user): ToolExecutionResult
    {
        $documents = $user->kycDocuments()->count();

        $response = [
            'user_uuid'        => $user->uuid,
            'kyc_status'       => $user->kyc_status ?? 'not_started',
            'kyc_level'        => $user->kyc_level ?? 'none',
            'risk_rating'      => $user->risk_rating ?? 'unknown',
            'pep_status'       => (bool) ($user->pep_status ?? false),
            'submitted_at'     => $user->kyc_submitted_at ? (is_string($user->kyc_submitted_at) ? $user->kyc_submitted_at : $user->kyc_submitted_at->toIso8601String()) : null,
            'approved_at'      => $user->kyc_approved_at ? (is_string($user->kyc_approved_at) ? $user->kyc_approved_at : $user->kyc_approved_at->toIso8601String()) : null,
            'rejected_at'      => $user->kyc_rejected_at ? (is_string($user->kyc_rejected_at) ? $user->kyc_rejected_at : $user->kyc_rejected_at->toIso8601String()) : null,
            'expires_at'       => $user->kyc_expires_at ? (is_string($user->kyc_expires_at) ? $user->kyc_expires_at : $user->kyc_expires_at->toIso8601String()) : null,
            'documents_count'  => $documents,
            'action_performed' => 'check_status',
            'message'          => $this->getStatusMessage($user->kyc_status ?? 'not_started'),
        ];

        return ToolExecutionResult::success($response);
    }

    private function getRequirements(User $user): ToolExecutionResult
    {
        $requirements = [];

        // Basic KYC requirements
        $requirements[] = [
            'type'        => 'identity_document',
            'description' => 'Government-issued photo ID (passport, driver\'s license, or national ID)',
            'required'    => true,
        ];

        $requirements[] = [
            'type'        => 'proof_of_address',
            'description' => 'Utility bill or bank statement (not older than 3 months)',
            'required'    => true,
        ];

        // Enhanced KYC requirements (if applicable)
        if (in_array($user->kyc_level, ['enhanced', 'full'])) {
            $requirements[] = [
                'type'        => 'source_of_funds',
                'description' => 'Documentation proving source of funds',
                'required'    => true,
            ];

            $requirements[] = [
                'type'        => 'employment_verification',
                'description' => 'Employment letter or business registration',
                'required'    => false,
            ];
        }

        // Full KYC requirements
        if ($user->kyc_level === 'full') {
            $requirements[] = [
                'type'        => 'financial_statements',
                'description' => 'Bank statements for the last 6 months',
                'required'    => true,
            ];

            $requirements[] = [
                'type'        => 'tax_returns',
                'description' => 'Tax returns for the last 2 years',
                'required'    => false,
            ];
        }

        $response = [
            'user_uuid'        => $user->uuid,
            'kyc_status'       => $user->kyc_status ?? 'not_started',
            'kyc_level'        => $user->kyc_level ?? 'basic',
            'requirements'     => $requirements,
            'action_performed' => 'get_requirements',
            'message'          => sprintf('KYC requirements for %s level', $user->kyc_level ?? 'basic'),
        ];

        return ToolExecutionResult::success($response);
    }

    private function verifyKyc(User $user, array $parameters): ToolExecutionResult
    {
        // Check if verifier is provided
        if (! isset($parameters['verified_by'])) {
            return ToolExecutionResult::failure('Verifier identifier is required for verification');
        }

        // Check if user has pending KYC
        if ($user->kyc_status !== 'pending') {
            return ToolExecutionResult::failure('KYC is not in pending state');
        }

        // Verify KYC using the service
        $this->kycService->verifyKyc($user, $parameters['verified_by'], [
            'level'       => $parameters['level'] ?? 'enhanced',
            'risk_rating' => $parameters['risk_rating'] ?? 'low',
            'pep_status'  => $parameters['pep_status'] ?? false,
            'expires_at'  => now()->addYears(2),
        ]);

        // Refresh user data
        $user->refresh();

        $response = [
            'user_uuid'        => $user->uuid,
            'kyc_status'       => $user->kyc_status,
            'kyc_level'        => $user->kyc_level,
            'risk_rating'      => $user->risk_rating,
            'pep_status'       => (bool) $user->pep_status,
            'approved_at'      => $user->kyc_approved_at ? (is_string($user->kyc_approved_at) ? $user->kyc_approved_at : $user->kyc_approved_at->toIso8601String()) : null,
            'expires_at'       => $user->kyc_expires_at ? (is_string($user->kyc_expires_at) ? $user->kyc_expires_at : $user->kyc_expires_at->toIso8601String()) : null,
            'action_performed' => 'verify',
            'message'          => 'KYC successfully verified',
        ];

        return ToolExecutionResult::success($response);
    }

    private function rejectKyc(User $user, array $parameters): ToolExecutionResult
    {
        // Check if reason is provided
        if (! isset($parameters['reason'])) {
            return ToolExecutionResult::failure('Reason is required for rejection');
        }

        // Check if user has pending KYC
        if ($user->kyc_status !== 'pending') {
            return ToolExecutionResult::failure('KYC is not in pending state');
        }

        // Reject KYC
        $this->kycService->rejectKyc($user, $parameters['verified_by'] ?? 'system', $parameters['reason']);

        // Refresh user data
        $user->refresh();

        $response = [
            'user_uuid'        => $user->uuid,
            'kyc_status'       => $user->kyc_status,
            'rejected_at'      => $user->kyc_rejected_at ? (is_string($user->kyc_rejected_at) ? $user->kyc_rejected_at : $user->kyc_rejected_at->toIso8601String()) : null,
            'action_performed' => 'reject',
            'message'          => sprintf('KYC rejected: %s', $parameters['reason']),
        ];

        return ToolExecutionResult::success($response);
    }

    private function canAccessKyc($user): bool
    {
        // Check if current user can access KYC information
        $currentUser = Auth::user();

        if (! $currentUser) {
            return false;
        }

        // User can access their own KYC
        if ($currentUser->uuid === $user->uuid) {
            return true;
        }

        // Check for compliance role
        if (method_exists($currentUser, 'hasRole') && $currentUser->hasRole(['admin', 'compliance'])) {
            return true;
        }

        // Check for specific permission
        if (method_exists($currentUser, 'can') && $currentUser->can('view-kyc', $user)) {
            return true;
        }

        return false;
    }

    private function getStatusMessage(string $status): string
    {
        return match ($status) {
            'not_started' => 'KYC process has not been started',
            'pending'     => 'KYC documents submitted and pending review',
            'approved'    => 'KYC verified and approved',
            'rejected'    => 'KYC rejected, resubmission required',
            'expired'     => 'KYC expired, renewal required',
            default       => 'Unknown KYC status',
        };
    }

    public function getCapabilities(): array
    {
        return [
            'read',
            'write',
            'compliance',
            'verification',
            'risk-assessment',
        ];
    }

    public function isCacheable(): bool
    {
        return false; // KYC data should always be fresh
    }

    public function getCacheTtl(): int
    {
        return 0;
    }

    public function validateInput(array $parameters): bool
    {
        // UUID validation
        if (! isset($parameters['user_uuid'])) {
            return false;
        }

        $uuid = $parameters['user_uuid'];
        if (! preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $uuid)) {
            return false;
        }

        // Action validation
        if (! in_array($parameters['action'] ?? '', ['check_status', 'get_requirements', 'verify', 'reject'])) {
            return false;
        }

        return true;
    }

    public function authorize(?string $userId): bool
    {
        // KYC operations require authentication
        if (! $userId && ! Auth::check()) {
            return false;
        }

        return true;
    }
}
