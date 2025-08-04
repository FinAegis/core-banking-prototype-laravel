<?php

namespace Tests\Feature\Http\Controllers\Api\BIAN;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class CurrentAccountControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
    }

    #[Test]
    public function test_initiate_creates_new_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/current-account/initiate', [
            'customerReference' => $this->user->uuid,
            'accountName'       => 'My Checking Account',
            'accountType'       => 'current',
            'initialDeposit'    => 10000, // 100.00 in cents
            'currency'          => 'USD',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId',
                    'customerReference',
                    'accountName',
                    'accountType',
                    'accountStatus',
                    'accountBalance' => [
                        'amount',
                        'currency',
                    ],
                    'dateType' => [
                        'date',
                        'dateTypeName',
                    ],
                ],
            ])
            ->assertJson([
                'currentAccountFulfillmentArrangement' => [
                    'customerReference' => $this->user->uuid,
                    'accountName'       => 'My Checking Account',
                    'accountType'       => 'current',
                    'accountStatus'     => 'active',
                    'accountBalance'    => [
                        'amount'   => 10000,
                        'currency' => 'USD',
                    ],
                    'dateType' => [
                        'dateTypeName' => 'AccountOpeningDate',
                    ],
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'user_uuid' => $this->user->uuid,
            'name'      => 'My Checking Account',
            'balance'   => 10000,
        ]);
    }

    #[Test]
    public function test_initiate_without_initial_deposit(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/current-account/initiate', [
            'customerReference' => $this->user->uuid,
            'accountName'       => 'Zero Balance Account',
            'accountType'       => 'checking',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('currentAccountFulfillmentArrangement.accountBalance.amount', 0)
            ->assertJsonPath('currentAccountFulfillmentArrangement.accountType', 'checking');

        $this->assertDatabaseHas('accounts', [
            'user_uuid' => $this->user->uuid,
            'name'      => 'Zero Balance Account',
            'balance'   => 0,
        ]);
    }

    #[Test]
    public function test_initiate_requires_authentication(): void
    {
        $response = $this->postJson('/api/bian/current-account/initiate', [
            'customerReference' => $this->user->uuid,
            'accountName'       => 'Test Account',
            'accountType'       => 'current',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_initiate_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/current-account/initiate', [
            'customerReference' => 'invalid-uuid',
            'accountName'       => '',
            'accountType'       => 'invalid-type',
            'initialDeposit'    => -100,
            'currency'          => 'INVALID',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['customerReference', 'accountName', 'accountType', 'initialDeposit', 'currency']);
    }

    #[Test]
    public function test_retrieve_returns_account_details(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Test Account',
            'balance'   => 50000,
        ]);

        // Create AccountBalance for USD
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);

        $response = $this->getJson("/api/bian/current-account/{$account->uuid}/retrieve");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId',
                    'customerReference',
                    'accountName',
                    'accountType',
                    'accountStatus',
                    'accountBalance' => [
                        'amount',
                        'currency',
                    ],
                    'dateType' => [
                        'date',
                        'dateTypeName',
                    ],
                ],
            ])
            ->assertJson([
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId'     => $account->uuid,
                    'customerReference' => $this->user->uuid,
                    'accountName'       => 'Test Account',
                    'accountType'       => 'current',
                    'accountStatus'     => 'active',
                    'accountBalance'    => [
                        'amount'   => 50000,
                        'currency' => 'USD',
                    ],
                    'dateType' => [
                        'dateTypeName' => 'AccountOpeningDate',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_retrieve_returns_404_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson('/api/bian/current-account/non-existent-uuid/retrieve');

        $response->assertStatus(404);
    }

    #[Test]
    public function test_retrieve_requires_authentication(): void
    {
        $account = Account::factory()->create();

        $response = $this->getJson("/api/bian/current-account/{$account->uuid}/retrieve");

        $response->assertStatus(401);
    }

    #[Test]
    public function test_update_modifies_account_name(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'name'      => 'Old Name',
        ]);

        $response = $this->putJson("/api/bian/current-account/{$account->uuid}/update", [
            'accountName'   => 'New Account Name',
            'accountStatus' => 'active',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'currentAccountFulfillmentArrangement' => [
                    'crReferenceId'     => $account->uuid,
                    'customerReference' => $this->user->uuid,
                    'accountName'       => 'New Account Name',
                    'accountType'       => 'current',
                    'accountStatus'     => 'active',
                    'updateResult'      => 'successful',
                ],
            ]);

        $this->assertDatabaseHas('accounts', [
            'uuid' => $account->uuid,
            'name' => 'New Account Name',
        ]);
    }

    #[Test]
    public function test_update_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->putJson("/api/bian/current-account/{$account->uuid}/update", [
            'accountName'   => str_repeat('a', 256), // Too long
            'accountStatus' => 'invalid-status',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['accountName', 'accountStatus']);
    }

    #[Test]
    public function test_control_freezes_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        $response = $this->putJson("/api/bian/current-account/{$account->uuid}/control", [
            'controlAction' => 'freeze',
            'controlReason' => 'Suspicious activity detected',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'currentAccountFulfillmentControlRecord' => [
                    'crReferenceId' => $account->uuid,
                    'controlAction' => 'freeze',
                    'controlReason' => 'Suspicious activity detected',
                    'controlStatus' => 'frozen',
                ],
            ])
            ->assertJsonStructure([
                'currentAccountFulfillmentControlRecord' => [
                    'controlDateTime',
                ],
            ]);
    }

    #[Test]
    public function test_control_unfreezes_account(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        $response = $this->putJson("/api/bian/current-account/{$account->uuid}/control", [
            'controlAction' => 'unfreeze',
            'controlReason' => 'Issue resolved',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('currentAccountFulfillmentControlRecord.controlStatus', 'active');
    }

    #[Test]
    public function test_control_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->putJson("/api/bian/current-account/{$account->uuid}/control", [
            'controlAction' => 'invalid-action',
            'controlReason' => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['controlAction', 'controlReason']);
    }

    #[Test]
    public function test_execute_payment_with_sufficient_funds(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 100000, // 1000.00
        ]);

        // Create AccountBalance for USD
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000,
        ]);

        $response = $this->postJson("/api/bian/current-account/{$account->uuid}/payment/execute", [
            'paymentAmount'      => 25000, // 250.00
            'paymentType'        => 'withdrawal',
            'paymentDescription' => 'ATM withdrawal',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'paymentExecutionRecord' => [
                    'crReferenceId',
                    'bqReferenceId',
                    'executionStatus',
                    'paymentAmount',
                    'paymentType',
                    'paymentDescription',
                    'accountBalance',
                    'executionDateTime',
                ],
            ])
            ->assertJson([
                'paymentExecutionRecord' => [
                    'crReferenceId'      => $account->uuid,
                    'executionStatus'    => 'completed',
                    'paymentAmount'      => 25000,
                    'paymentType'        => 'withdrawal',
                    'paymentDescription' => 'ATM withdrawal',
                ],
            ]);
    }

    #[Test]
    public function test_execute_payment_with_insufficient_funds(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 10000, // 100.00
        ]);

        // Create AccountBalance for USD
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 10000,
        ]);

        $response = $this->postJson("/api/bian/current-account/{$account->uuid}/payment/execute", [
            'paymentAmount' => 25000, // 250.00
            'paymentType'   => 'payment',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'paymentExecutionRecord' => [
                    'crReferenceId'   => $account->uuid,
                    'executionStatus' => 'rejected',
                    'executionReason' => 'Insufficient funds',
                    'accountBalance'  => 10000,
                    'requestedAmount' => 25000,
                ],
            ]);
    }

    #[Test]
    public function test_execute_payment_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->postJson("/api/bian/current-account/{$account->uuid}/payment/execute", [
            'paymentAmount'      => 0,
            'paymentType'        => 'invalid-type',
            'paymentDescription' => str_repeat('a', 501),
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['paymentAmount', 'paymentType', 'paymentDescription']);
    }

    #[Test]
    public function test_execute_deposit(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 50000, // 500.00
        ]);

        $response = $this->postJson("/api/bian/current-account/{$account->uuid}/deposit/execute", [
            'depositAmount'      => 25000, // 250.00
            'depositType'        => 'cash',
            'depositDescription' => 'Cash deposit at branch',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'depositExecutionRecord' => [
                    'crReferenceId',
                    'bqReferenceId',
                    'executionStatus',
                    'depositAmount',
                    'depositType',
                    'depositDescription',
                    'accountBalance',
                    'executionDateTime',
                ],
            ])
            ->assertJson([
                'depositExecutionRecord' => [
                    'crReferenceId'      => $account->uuid,
                    'executionStatus'    => 'completed',
                    'depositAmount'      => 25000,
                    'depositType'        => 'cash',
                    'depositDescription' => 'Cash deposit at branch',
                ],
            ]);
    }

    #[Test]
    public function test_execute_deposit_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->postJson("/api/bian/current-account/{$account->uuid}/deposit/execute", [
            'depositAmount' => -100,
            'depositType'   => 'invalid-type',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['depositAmount', 'depositType']);
    }

    #[Test]
    public function test_retrieve_account_balance(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 123456, // 1234.56
        ]);

        // Create AccountBalance for USD
        AccountBalance::factory()->create([
            'account_uuid' => $account->uuid,
            'asset_code'   => 'USD',
            'balance'      => 123456,
        ]);

        $response = $this->getJson("/api/bian/current-account/{$account->uuid}/account-balance/retrieve");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'accountBalanceRecord' => [
                    'crReferenceId',
                    'bqReferenceId',
                    'balanceAmount',
                    'balanceCurrency',
                    'balanceType',
                    'balanceDateTime',
                ],
            ])
            ->assertJson([
                'accountBalanceRecord' => [
                    'crReferenceId'   => $account->uuid,
                    'balanceAmount'   => 123456,
                    'balanceCurrency' => 'USD',
                    'balanceType'     => 'available',
                ],
            ]);
    }

    #[Test]
    public function test_retrieve_transaction_report(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        $response = $this->getJson("/api/bian/current-account/{$account->uuid}/transaction-report/retrieve");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'transactionReportRecord' => [
                    'crReferenceId',
                    'bqReferenceId',
                    'reportPeriod' => [
                        'fromDate',
                        'toDate',
                    ],
                    'transactions',
                    'transactionCount',
                    'reportDateTime',
                ],
            ])
            ->assertJson([
                'transactionReportRecord' => [
                    'crReferenceId'    => $account->uuid,
                    'transactionCount' => 0,
                ],
            ]);
    }

    #[Test]
    public function test_retrieve_transaction_report_with_filters(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
        ]);

        $response = $this->getJson("/api/bian/current-account/{$account->uuid}/transaction-report/retrieve?" . http_build_query([
            'fromDate'        => '2024-01-01',
            'toDate'          => '2024-01-31',
            'transactionType' => 'credit',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('transactionReportRecord.reportPeriod.fromDate', '2024-01-01')
            ->assertJsonPath('transactionReportRecord.reportPeriod.toDate', '2024-01-31');
    }

    #[Test]
    public function test_retrieve_transaction_report_validates_dates(): void
    {
        Sanctum::actingAs($this->user);

        $account = Account::factory()->create();

        $response = $this->getJson("/api/bian/current-account/{$account->uuid}/transaction-report/retrieve?" . http_build_query([
            'fromDate'        => '2024-01-31',
            'toDate'          => '2024-01-01', // Before fromDate
            'transactionType' => 'invalid',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['toDate', 'transactionType']);
    }

    #[Test]
    public function test_all_endpoints_require_authentication(): void
    {
        $account = Account::factory()->create();

        $endpoints = [
            ['POST', '/api/bian/current-account/initiate'],
            ['GET', "/api/bian/current-account/{$account->uuid}/retrieve"],
            ['PUT', "/api/bian/current-account/{$account->uuid}/update"],
            ['PUT', "/api/bian/current-account/{$account->uuid}/control"],
            ['POST', "/api/bian/current-account/{$account->uuid}/payment/execute"],
            ['POST', "/api/bian/current-account/{$account->uuid}/deposit/execute"],
            ['GET', "/api/bian/current-account/{$account->uuid}/account-balance/retrieve"],
            ['GET', "/api/bian/current-account/{$account->uuid}/transaction-report/retrieve"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401, "Failed for {$method} {$url}");
        }
    }
}
