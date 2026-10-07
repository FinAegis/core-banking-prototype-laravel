<?php

declare(strict_types=1);

use App\Domain\Compliance\Models\ComplianceAlert;
use App\Domain\Compliance\Models\ComplianceCase;
use App\Domain\Compliance\Models\KycVerification;
use App\Models\User;

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

describe('GraphQL Compliance API', function () {
    it('paginates kyc verifications', function () {
        $user = User::factory()->create();
        KycVerification::create([
            'user_id'  => $user->id,
            'type'     => 'identity',
            'status'   => 'pending',
            'provider' => 'manual',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        kycVerifications(first: 10, page: 1) {
                            data {
                                id
                                type
                                status
                                provider
                            }
                            paginatorInfo {
                                total
                                currentPage
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.kycVerifications');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(1);
    });

    it('paginates compliance alerts', function () {
        $user = User::factory()->create();
        $user->assignRole('compliance_officer'); // compliance records are staff-only
        ComplianceAlert::factory()->create([
            'type'     => 'suspicious_activity',
            'severity' => 'high',
            'status'   => 'open',
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        complianceAlerts(first: 10, page: 1) {
                            data {
                                id
                                type
                                severity
                                status
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.complianceAlerts');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(1);
    });

    it('paginates compliance cases', function () {
        $user = User::factory()->create();
        $user->assignRole('compliance_officer'); // compliance records are staff-only
        ComplianceCase::create([
            'case_number' => 'CASE-TEST-001',
            'title'       => 'Test Case',
            'description' => 'Test compliance case for GraphQL',
            'type'        => 'investigation',
            'priority'    => 'medium',
            'status'      => 'open',
            'created_by'  => $user->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/graphql', [
                'query' => '
                    {
                        complianceCases(first: 10, page: 1) {
                            data {
                                id
                                title
                                type
                                priority
                                status
                            }
                            paginatorInfo {
                                total
                            }
                        }
                    }
                ',
            ]);

        $response->assertOk();
        $data = $response->json('data.complianceCases');
        expect($data['data'])->toBeArray();
        expect($data['paginatorInfo']['total'])->toBeGreaterThanOrEqual(1);
    });

    it('rejects unauthenticated compliance queries', function () {
        $response = $this->postJson('/graphql', [
            'query' => '{ complianceAlerts(first: 10, page: 1) { data { id } paginatorInfo { total } } }',
        ]);

        $response->assertOk();
        expect($response->json())->toHaveKey('errors');
    });
});
