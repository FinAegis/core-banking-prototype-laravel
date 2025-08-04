<?php

namespace Tests\Feature\Http\Controllers\Api\BIAN;

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\AccountBalance;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\ControllerTestCase;

class PaymentInitiationControllerTest extends ControllerTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected Account $payerAccount;

    protected Account $payeeAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        $this->payerAccount = Account::factory()->create([
            'user_uuid' => $this->user->uuid,
            'balance'   => 100000, // 1000.00
        ]);

        // Create AccountBalance for payer
        AccountBalance::factory()->create([
            'account_uuid' => $this->payerAccount->uuid,
            'asset_code'   => 'USD',
            'balance'      => 100000,
        ]);

        $this->payeeAccount = Account::factory()->create([
            'user_uuid' => $this->otherUser->uuid,
            'balance'   => 50000, // 500.00
        ]);

        // Create AccountBalance for payee
        AccountBalance::factory()->create([
            'account_uuid' => $this->payeeAccount->uuid,
            'asset_code'   => 'USD',
            'balance'      => 50000,
        ]);
    }

    #[Test]
    public function test_initiate_payment_with_sufficient_funds(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference'  => $this->payerAccount->uuid,
            'payeeReference'  => $this->payeeAccount->uuid,
            'paymentAmount'   => 25000, // 250.00
            'paymentCurrency' => 'USD',
            'paymentPurpose'  => 'Invoice payment #123',
            'paymentType'     => 'internal',
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'paymentInitiationTransaction' => [
                    'crReferenceId',
                    'paymentStatus',
                    'paymentDetails' => [
                        'payerReference',
                        'payerName',
                        'payeeReference',
                        'payeeName',
                        'paymentAmount',
                        'paymentCurrency',
                        'paymentPurpose',
                        'paymentType',
                    ],
                    'paymentSchedule' => [
                        'initiationDate',
                        'valueDate',
                    ],
                    'balanceAfterPayment' => [
                        'payerBalance',
                        'payeeBalance',
                    ],
                ],
            ])
            ->assertJson([
                'paymentInitiationTransaction' => [
                    'paymentStatus'  => 'completed',
                    'paymentDetails' => [
                        'payerReference'  => $this->payerAccount->uuid,
                        'payerName'       => $this->payerAccount->name,
                        'payeeReference'  => $this->payeeAccount->uuid,
                        'payeeName'       => $this->payeeAccount->name,
                        'paymentAmount'   => 25000,
                        'paymentCurrency' => 'USD',
                        'paymentPurpose'  => 'Invoice payment #123',
                        'paymentType'     => 'internal',
                    ],
                ],
            ]);
    }

    #[Test]
    public function test_initiate_instant_payment(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payeeAccount->uuid,
            'paymentAmount'  => 10000, // 100.00
            'paymentType'    => 'instant',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('paymentInitiationTransaction.paymentStatus', 'completed')
            ->assertJsonPath('paymentInitiationTransaction.paymentDetails.paymentType', 'instant');
    }

    #[Test]
    public function test_initiate_scheduled_payment(): void
    {
        Sanctum::actingAs($this->user);

        $futureDate = now()->addDays(7)->format('Y-m-d');

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payeeAccount->uuid,
            'paymentAmount'  => 50000, // 500.00
            'paymentType'    => 'scheduled',
            'valueDate'      => $futureDate,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('paymentInitiationTransaction.paymentStatus', 'scheduled')
            ->assertJsonPath('paymentInitiationTransaction.paymentSchedule.valueDate', $futureDate);
    }

    #[Test]
    public function test_initiate_payment_with_insufficient_funds(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payeeAccount->uuid,
            'paymentAmount'  => 200000, // 2000.00 (more than balance)
            'paymentType'    => 'internal',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'paymentInitiationTransaction' => [
                    'paymentStatus'         => 'rejected',
                    'statusReason'          => 'Insufficient funds',
                    'payerAvailableBalance' => 100000,
                    'requestedAmount'       => 200000,
                ],
            ]);
    }

    #[Test]
    public function test_initiate_requires_authentication(): void
    {
        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payeeAccount->uuid,
            'paymentAmount'  => 10000,
            'paymentType'    => 'internal',
        ]);

        $response->assertStatus(401);
    }

    #[Test]
    public function test_initiate_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference'  => 'invalid-uuid',
            'payeeReference'  => 'another-invalid-uuid',
            'paymentAmount'   => -100,
            'paymentCurrency' => 'INVALID',
            'paymentType'     => 'invalid-type',
            'valueDate'       => '2020-01-01', // Past date
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'payerReference',
                'payeeReference',
                'paymentAmount',
                'paymentCurrency',
                'paymentType',
                'valueDate',
            ]);
    }

    #[Test]
    public function test_initiate_prevents_self_transfer(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payerAccount->uuid, // Same account
            'paymentAmount'  => 10000,
            'paymentType'    => 'internal',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['payeeReference']);
    }

    #[Test]
    public function test_update_payment_status(): void
    {
        Sanctum::actingAs($this->user);

        $crReferenceId = fake()->uuid();

        $response = $this->putJson("/api/bian/payment-initiation/{$crReferenceId}/update", [
            'paymentStatus' => 'cancelled',
            'statusReason'  => 'Customer requested cancellation',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'paymentInitiationTransaction' => [
                    'crReferenceId' => $crReferenceId,
                    'updateAction'  => 'cancelled',
                    'updateReason'  => 'Customer requested cancellation',
                    'updateStatus'  => 'successful',
                ],
            ])
            ->assertJsonStructure([
                'paymentInitiationTransaction' => [
                    'updateDateTime',
                ],
            ]);
    }

    #[Test]
    public function test_update_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $crReferenceId = fake()->uuid();

        $response = $this->putJson("/api/bian/payment-initiation/{$crReferenceId}/update", [
            'paymentStatus' => 'invalid-status',
            'statusReason'  => '',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['paymentStatus', 'statusReason']);
    }

    #[Test]
    public function test_retrieve_payment_not_found(): void
    {
        Sanctum::actingAs($this->user);

        $nonExistentId = fake()->uuid();

        $response = $this->getJson("/api/bian/payment-initiation/{$nonExistentId}/retrieve");

        $response->assertStatus(404);
    }

    #[Test]
    public function test_execute_payment(): void
    {
        Sanctum::actingAs($this->user);

        $crReferenceId = fake()->uuid();

        $response = $this->postJson("/api/bian/payment-initiation/{$crReferenceId}/execute", [
            'executionMode' => 'immediate',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'paymentExecutionRecord' => [
                    'crReferenceId'   => $crReferenceId,
                    'executionMode'   => 'immediate',
                    'executionStatus' => 'completed',
                ],
            ])
            ->assertJsonStructure([
                'paymentExecutionRecord' => [
                    'executionDateTime',
                ],
            ]);
    }

    #[Test]
    public function test_execute_payment_with_retry(): void
    {
        Sanctum::actingAs($this->user);

        $crReferenceId = fake()->uuid();

        $response = $this->postJson("/api/bian/payment-initiation/{$crReferenceId}/execute", [
            'executionMode' => 'retry',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('paymentExecutionRecord.executionMode', 'retry')
            ->assertJsonPath('paymentExecutionRecord.executionStatus', 'completed');
    }

    #[Test]
    public function test_execute_validates_input(): void
    {
        Sanctum::actingAs($this->user);

        $crReferenceId = fake()->uuid();

        $response = $this->postJson("/api/bian/payment-initiation/{$crReferenceId}/execute", [
            'executionMode' => 'invalid-mode',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['executionMode']);
    }

    #[Test]
    public function test_request_payment_status(): void
    {
        Sanctum::actingAs($this->user);

        $crReferenceId = fake()->uuid();

        $response = $this->postJson("/api/bian/payment-initiation/{$crReferenceId}/payment-status/request");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'paymentStatusRecord' => [
                    'crReferenceId',
                    'bqReferenceId',
                    'paymentStatus',
                    'statusCheckDateTime',
                    'eventCount',
                ],
            ])
            ->assertJson([
                'paymentStatusRecord' => [
                    'crReferenceId' => $crReferenceId,
                    'paymentStatus' => 'not_found',
                    'eventCount'    => 0,
                ],
            ]);
    }

    #[Test]
    public function test_retrieve_payment_history_empty(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/bian/payment-initiation/{$this->payerAccount->uuid}/payment-history/retrieve");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'paymentHistoryRecord' => [
                    'accountReference',
                    'bqReferenceId',
                    'historyPeriod' => [
                        'fromDate',
                        'toDate',
                    ],
                    'payments',
                    'paymentCount',
                    'retrievalDateTime',
                ],
            ])
            ->assertJson([
                'paymentHistoryRecord' => [
                    'accountReference' => $this->payerAccount->uuid,
                    'payments'         => [],
                    'paymentCount'     => 0,
                ],
            ]);
    }

    #[Test]
    public function test_retrieve_payment_history_with_filters(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/bian/payment-initiation/{$this->payerAccount->uuid}/payment-history/retrieve?" . http_build_query([
            'fromDate'         => '2024-01-01',
            'toDate'           => '2024-12-31',
            'paymentDirection' => 'sent',
        ]));

        $response->assertStatus(200)
            ->assertJsonPath('paymentHistoryRecord.historyPeriod.fromDate', '2024-01-01')
            ->assertJsonPath('paymentHistoryRecord.historyPeriod.toDate', '2024-12-31');
    }

    #[Test]
    public function test_retrieve_payment_history_validates_dates(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->getJson("/api/bian/payment-initiation/{$this->payerAccount->uuid}/payment-history/retrieve?" . http_build_query([
            'fromDate'         => '2024-12-31',
            'toDate'           => '2024-01-01', // Before fromDate
            'paymentDirection' => 'invalid',
        ]));

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['toDate', 'paymentDirection']);
    }

    #[Test]
    public function test_retrieve_payment_history_for_non_existent_account(): void
    {
        Sanctum::actingAs($this->user);

        $nonExistentId = fake()->uuid();

        $response = $this->getJson("/api/bian/payment-initiation/{$nonExistentId}/payment-history/retrieve");

        $response->assertStatus(404);
    }

    #[Test]
    public function test_all_endpoints_require_authentication(): void
    {
        $crReferenceId = fake()->uuid();

        $endpoints = [
            ['POST', '/api/bian/payment-initiation/initiate'],
            ['GET', "/api/bian/payment-initiation/{$crReferenceId}/retrieve"],
            ['PUT', "/api/bian/payment-initiation/{$crReferenceId}/update"],
            ['POST', "/api/bian/payment-initiation/{$crReferenceId}/execute"],
            ['POST', "/api/bian/payment-initiation/{$crReferenceId}/payment-status/request"],
            ['GET', "/api/bian/payment-initiation/{$this->payerAccount->uuid}/payment-history/retrieve"],
        ];

        foreach ($endpoints as [$method, $url]) {
            $response = $this->json($method, $url);
            $response->assertStatus(401, "Failed for {$method} {$url}");
        }
    }

    #[Test]
    public function test_initiate_payment_with_minimum_required_fields(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payeeAccount->uuid,
            'paymentAmount'  => 5000, // 50.00
            'paymentType'    => 'internal',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('paymentInitiationTransaction.paymentStatus', 'completed')
            ->assertJsonPath('paymentInitiationTransaction.paymentDetails.paymentCurrency', 'USD') // Default
            ->assertJsonPath('paymentInitiationTransaction.paymentDetails.paymentPurpose', null);
    }

    #[Test]
    public function test_initiate_external_payment(): void
    {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/bian/payment-initiation/initiate', [
            'payerReference' => $this->payerAccount->uuid,
            'payeeReference' => $this->payeeAccount->uuid,
            'paymentAmount'  => 15000, // 150.00
            'paymentType'    => 'external',
            'paymentPurpose' => 'External bank transfer',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('paymentInitiationTransaction.paymentDetails.paymentType', 'external');
    }
}
