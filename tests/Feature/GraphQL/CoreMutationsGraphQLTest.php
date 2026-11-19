<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Core Domain Mutations', function () {
    it('freezes an account via mutation', function () {
        $user = User::factory()->create();
        $account = Account::factory()->create(['frozen' => false, 'user_uuid' => $user->uuid]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($input: FreezeAccountInput!) {
                        freezeAccount(input: $input) {
                            id
                            frozen
                        }
                    }
                ',
                'variables' => [
                    'input' => [
                        'id'     => (string) $account->id,
                        'reason' => 'Suspicious activity',
                    ],
                ],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        $data = $response->json('data.freezeAccount');
        expect($data['frozen'])->toBeTrue();
    });

    it('unfreezes an account via mutation', function () {
        $user = User::factory()->create();
        // The owner may lift a freeze they placed themselves.
        $account = Account::factory()->create(['frozen' => true, 'frozen_by_owner' => true, 'user_uuid' => $user->uuid]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    mutation ($id: ID!) {
                        unfreezeAccount(id: $id) {
                            id
                            frozen
                        }
                    }
                ',
                'variables' => ['id' => (string) $account->id],
            ]);

        $response->assertOk();
        $json = $response->json();
        expect($json)->not->toHaveKey('errors');
        $data = $response->json('data.unfreezeAccount');
        expect($data['frozen'])->toBeFalse();
    });

    it('rejects unauthenticated mutation calls', function () {
        $response = $this->postJson('/graphql', [
            'query' => '
                mutation {
                    freezeAccount(input: { id: "1", reason: "test" }) {
                        id
                    }
                }
            ',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });
});
