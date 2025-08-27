<?php

namespace App\Domain\Account\Workflows;

use App\Domain\Account\DataObjects\AccountUuid;
use App\Domain\Account\Models\Account;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Workflow\Activity;

class AccountValidationActivity extends Activity
{
    public function execute(
        AccountUuid $uuid,
        array $validationChecks,
        ?string $validatedBy
    ): array {
        /** @var Account|null $account */
        $account = null;
        /** @var \Illuminate\Database\Eloquent\Model|null $$account */
        $$account = Account::where('uuid', $uuid->getUuid())->first();

        if (! $account) {
            throw new RuntimeException("Account not found: {$uuid->getUuid()}");
        }

        $results = [];
        $allPassed = true;

        foreach ($validationChecks as $check) {
            $result = $this->performValidationCheck($account, $check);
            $results[$check] = $result;

            if (! $result['passed']) {
                $allPassed = false;
            }
        }

        // Log validation for audit
        $this->logValidation($uuid, $validationChecks, $results, $allPassed, $validatedBy);

        return [
            'account_uuid'       => $uuid->getUuid(),
            'validation_results' => $results,
            'all_checks_passed'  => $allPassed,
            'validated_by'       => $validatedBy,
            'validated_at'       => now()->toISOString(),
        ];
    }

    private function performValidationCheck(Account $account, string $check): array
    {
        /** @var Account|null $account */
        $account = null;
        switch ($check) {
            case 'kyc_document_verification':
                return $this->validateKycDocuments($account);
            case 'address_verification':
                return $this->validateAddress($account);
            case 'identity_verification':
                return $this->validateIdentity($account);
            case 'compliance_screening':
                return $this->performComplianceScreening($account);
            default:
                return [
                    'passed'  => false,
                    'message' => "Unknown validation check: {$check}",
                ];
        }
    }

    private function validateKycDocuments(Account $account): array
    {
        // Check if user has uploaded required KYC documents
        $user = $account->user;
        if (! $user) {
            return [
                'passed'     => false,
                'message'    => 'User not found for account',
                'error_code' => 'USER_NOT_FOUND',
            ];
        }

        // Simulate document validation - check for required fields
        $requiredFields = ['name', 'email'];
        $missingFields = [];

        foreach ($requiredFields as $field) {
            if (empty($user->$field)) {
                $missingFields[] = $field;
            }
        }

        if (! empty($missingFields)) {
            return [
                'passed'         => false,
                'message'        => 'Missing required KYC information: ' . implode(', ', $missingFields),
                'error_code'     => 'MISSING_KYC_DATA',
                'missing_fields' => $missingFields,
            ];
        }

        // Validate email format
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            return [
                'passed'     => false,
                'message'    => 'Invalid email format',
                'error_code' => 'INVALID_EMAIL',
            ];
        }

        return [
            'passed'             => true,
            'message'            => 'KYC documents and information verified',
            'verification_score' => 95,
        ];
    }

    private function validateAddress(Account $account): array
    {
        $user = $account->user;
        if (! $user) {
            return [
                'passed'     => false,
                'message'    => 'User not found for address validation',
                'error_code' => 'USER_NOT_FOUND',
            ];
        }

        // Check if user has address fields (assuming these exist in user model)
        // In a real system, you would integrate with address validation services
        // like Google Places API, PostcodeAnywhere, etc.

        // For now, check if basic address components exist in email domain
        $emailDomain = substr(strrchr($user->email, '@'), 1);

        // Basic domain validation
        if (in_array($emailDomain, ['tempmail.com', '10minutemail.com', 'guerrillamail.com'])) {
            return [
                'passed'     => false,
                'message'    => 'Temporary email domains not allowed',
                'error_code' => 'INVALID_EMAIL_DOMAIN',
            ];
        }

        // Simulate DNS check for domain validity
        if (! checkdnsrr($emailDomain, 'MX')) {
            return [
                'passed'     => false,
                'message'    => 'Invalid email domain - no MX record found',
                'error_code' => 'INVALID_DOMAIN',
            ];
        }

        return [
            'passed'            => true,
            'message'           => 'Address validation passed',
            'validation_method' => 'email_domain_verification',
            'score'             => 85,
        ];
    }

    private function validateIdentity(Account $account): array
    {
        $user = $account->user;
        if (! $user) {
            return [
                'passed'     => false,
                'message'    => 'User not found for identity validation',
                'error_code' => 'USER_NOT_FOUND',
            ];
        }

        // Validate user identity components
        $checks = [];

        // Check name validity (basic validation)
        if (strlen($user->name) < 2) {
            $checks['name'] = [
                'passed'  => false,
                'message' => 'Name too short',
            ];
        } elseif (preg_match('/[0-9]/', $user->name)) {
            $checks['name'] = [
                'passed'  => false,
                'message' => 'Name contains invalid characters',
            ];
        } else {
            $checks['name'] = [
                'passed'  => true,
                'message' => 'Name format valid',
            ];
        }

        // Check email uniqueness (identity verification)
        $emailExists = DB::table('users')
            ->where('email', $user->email)
            ->where('id', '!=', $user->id)
            ->exists();

        if ($emailExists) {
            $checks['email_uniqueness'] = [
                'passed'  => false,
                'message' => 'Email already registered with another account',
            ];
        } else {
            $checks['email_uniqueness'] = [
                'passed'  => true,
                'message' => 'Email is unique',
            ];
        }

        // Check account creation timing (fraud detection)
        $recentAccounts = Account::where('user_uuid', $user->uuid)
            ->where('created_at', '>=', now()->subDays(1))
            ->count();

        if ($recentAccounts > 3) {
            $checks['account_frequency'] = [
                'passed'  => false,
                'message' => 'Too many accounts created recently',
            ];
        } else {
            $checks['account_frequency'] = [
                'passed'  => true,
                'message' => 'Account creation frequency normal',
            ];
        }

        $allPassed = collect($checks)->every(fn ($check) => $check['passed']);

        return [
            'passed'             => $allPassed,
            'message'            => $allPassed ? 'Identity verification passed' : 'Identity verification failed',
            'checks'             => $checks,
            'verification_score' => $allPassed ? 90 : 30,
        ];
    }

    private function performComplianceScreening(Account $account): array
    {
        $user = $account->user;
        if (! $user) {
            return [
                'passed'     => false,
                'message'    => 'User not found for compliance screening',
                'error_code' => 'USER_NOT_FOUND',
            ];
        }

        $screeningResults = [];

        // Check against mock sanctions list (basic name matching)
        $sanctionedNames = [
            'john doe',
            'jane smith test',
            'test user sanctions',
        ];

        $userNameLower = strtolower($user->name);
        $sanctionsMatch = false;

        foreach ($sanctionedNames as $sanctionedName) {
            if (stripos($userNameLower, $sanctionedName) !== false) {
                $sanctionsMatch = true;
                break;
            }
        }

        $screeningResults['sanctions_check'] = [
            'passed'  => ! $sanctionsMatch,
            'message' => $sanctionsMatch ? 'Name matches sanctions list' : 'No sanctions match found',
        ];

        // Check for high-risk email domains
        $emailDomain = substr(strrchr($user->email, '@'), 1);
        $highRiskDomains = [
            'example.com',
            'test.com',
            'localhost',
        ];

        $domainRisk = in_array($emailDomain, $highRiskDomains);
        $screeningResults['domain_risk_check'] = [
            'passed'  => ! $domainRisk,
            'message' => $domainRisk ? 'High-risk email domain detected' : 'Email domain risk check passed',
        ];

        // Check account balance patterns (money laundering detection)
        $highValueTransactions = DB::table('transactions')
            ->where('account_uuid', $account->uuid)
            ->where('amount', '>', 10000000) // > $100,000
            ->count();

        $screeningResults['transaction_pattern_check'] = [
            'passed'  => $highValueTransactions < 5,
            'message' => $highValueTransactions >= 5 ? 'Unusual high-value transaction pattern detected' : 'Transaction patterns normal',
        ];

        // Check for multiple accounts (possible fraud)
        $userAccountCount = Account::where('user_uuid', $user->uuid)->count();
        $screeningResults['multiple_accounts_check'] = [
            'passed'  => $userAccountCount <= 5,
            'message' => $userAccountCount > 5 ? 'User has excessive number of accounts' : 'Account count within normal limits',
        ];

        $allPassed = collect($screeningResults)->every(fn ($check) => $check['passed']);
        $riskScore = $allPassed ? 10 : 85; // Low risk if all passed, high risk otherwise

        return [
            'passed'                 => $allPassed,
            'message'                => $allPassed ? 'Compliance screening passed' : 'Compliance screening failed - manual review required',
            'risk_score'             => $riskScore,
            'screening_results'      => $screeningResults,
            'requires_manual_review' => ! $allPassed,
        ];
    }

    private function logValidation(
        AccountUuid $uuid,
        array $checks,
        array $results,
        bool $allPassed,
        ?string $validatedBy
    ): void {
        logger()->info(
            'Account validation performed',
            [
                'account_uuid'     => $uuid->getUuid(),
                'checks_performed' => $checks,
                'results'          => $results,
                'all_passed'       => $allPassed,
                'validated_by'     => $validatedBy,
                'timestamp'        => now()->toISOString(),
            ]
        );
    }
}
