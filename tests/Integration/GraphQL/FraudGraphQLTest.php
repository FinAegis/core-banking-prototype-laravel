<?php

declare(strict_types=1);

use App\Domain\Fraud\Models\FraudCase;
use App\Models\User;

uses(Tests\TestCase::class);
uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Fraud API', function () {
    it('queries fraud case by id', function () {
        $user = User::factory()->create();
        $user->assignRole('risk_manager'); // fraud cases are staff-only
        $fraudCase = FraudCase::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    query ($id: ID!) {
                        fraudCase(id: $id) {
                            id
                            case_number
                            status
                            severity
                        }
                    }
                ',
                'variables' => ['id' => (string) $fraudCase->id],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        $data = $response->json('data.fraudCase');
        expect($data['case_number'])->toBe($fraudCase->case_number);
    });

    it('paginates fraud cases', function () {
        $user = User::factory()->create();
        $user->assignRole('risk_manager'); // fraud cases are staff-only
        FraudCase::factory()->count(3)->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '{ fraudCases(first: 10) { data { id status severity } paginatorInfo { total } } }',
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        expect($response->json('data.fraudCases.paginatorInfo.total'))->toBe(3);
    });

    it('rejects unauthenticated fraud queries', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ fraudCases(first: 10) { data { id } } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });
});
