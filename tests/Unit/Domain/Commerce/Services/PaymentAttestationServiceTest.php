<?php

declare(strict_types=1);

use App\Domain\Commerce\Enums\AttestationType;
use App\Domain\Commerce\Events\PaymentAttested;
use App\Domain\Commerce\Services\PaymentAttestationService;
use Illuminate\Support\Facades\Event;

uses(Tests\TestCase::class);

beforeEach(function (): void {
    Event::fake();
    $this->service = new PaymentAttestationService('test-attestor');
});

describe('PaymentAttestationService', function (): void {
    describe('createAttestation', function (): void {
        it('creates a payment attestation', function (): void {
            $attestation = $this->service->createAttestation(
                type: AttestationType::PAYMENT,
                subjectId: 'tx-123',
                claims: [
                    'amount'       => '100.00',
                    'currency'     => 'USD',
                    'payer_id'     => 'user-1',
                    'recipient_id' => 'user-2',
                    'timestamp'    => time(),
                ],
            );

            expect($attestation->attestationId)->not->toBeEmpty();
            expect($attestation->type)->toBe(AttestationType::PAYMENT);
            expect($attestation->issuerId)->toBe('test-attestor');
            expect($attestation->getClaim('amount'))->toBe('100.00');
            expect($attestation->signature)->not->toBeEmpty();

            Event::assertDispatched(PaymentAttested::class);
        });

        it('throws on missing required claims', function (): void {
            expect(fn () => $this->service->createAttestation(
                type: AttestationType::PAYMENT,
                subjectId: 'tx-123',
                claims: ['amount' => '100.00'], // Missing other required claims
            ))->toThrow(InvalidArgumentException::class, 'Missing required claims');
        });
    });

    describe('createPaymentAttestation', function (): void {
        it('creates a payment attestation with helper method', function (): void {
            $attestation = $this->service->createPaymentAttestation(
                payerId: 'user-1',
                recipientId: 'user-2',
                amount: '500.00',
                currency: 'EUR',
            );

            expect($attestation->type)->toBe(AttestationType::PAYMENT);
            expect($attestation->getClaim('amount'))->toBe('500.00');
            expect($attestation->getClaim('currency'))->toBe('EUR');
            expect($attestation->getClaim('payer_id'))->toBe('user-1');
            expect($attestation->getClaim('recipient_id'))->toBe('user-2');
        });
    });

    describe('createDeliveryAttestation', function (): void {
        it('creates a delivery attestation', function (): void {
            $attestation = $this->service->createDeliveryAttestation(
                itemId: 'item-123',
                recipientId: 'user-1',
                location: '123 Main St, City, Country',
            );

            expect($attestation->type)->toBe(AttestationType::DELIVERY);
            expect($attestation->getClaim('item_id'))->toBe('item-123');
            expect($attestation->getClaim('location'))->toBe('123 Main St, City, Country');
        });
    });

    describe('createReceiptAttestation', function (): void {
        it('creates a receipt attestation', function (): void {
            $attestation = $this->service->createReceiptAttestation(
                transactionId: 'tx-456',
                merchantId: 'merchant-1',
                amount: '75.50',
                currency: 'USD',
            );

            expect($attestation->type)->toBe(AttestationType::RECEIPT);
            expect($attestation->getClaim('transaction_id'))->toBe('tx-456');
            expect($attestation->getClaim('merchant_id'))->toBe('merchant-1');
        });
    });

    describe('verifyAttestation', function (): void {
        it('verifies valid attestations', function (): void {
            $attestation = $this->service->createPaymentAttestation(
                payerId: 'user-1',
                recipientId: 'user-2',
                amount: '100.00',
                currency: 'USD',
            );

            expect($this->service->verifyAttestation($attestation))->toBeTrue();
        });

        it('rejects attestations from different issuer', function (): void {
            $otherService = new PaymentAttestationService('other-attestor');
            $attestation = $otherService->createPaymentAttestation(
                payerId: 'user-1',
                recipientId: 'user-2',
                amount: '100.00',
                currency: 'USD',
            );

            expect($this->service->verifyAttestation($attestation))->toBeFalse();
        });

        it('rejects tampered attestations', function (): void {
            $attestation = $this->service->createPaymentAttestation(
                payerId: 'user-1',
                recipientId: 'user-2',
                amount: '100.00',
                currency: 'USD',
            );

            // Create tampered version (manually alter claims)
            $reflection = new ReflectionClass($attestation);
            $tamperedAttestation = $reflection->newInstanceWithoutConstructor();

            // Copy properties but alter claims
            foreach ($reflection->getProperties() as $property) {
                $property->setAccessible(true);
                if ($property->getName() === 'claims') {
                    $property->setValue($tamperedAttestation, ['amount' => '999999.00']);
                } else {
                    $property->setValue($tamperedAttestation, $property->getValue($attestation));
                }
            }

            expect($this->service->verifyAttestation($tamperedAttestation))->toBeFalse();
        });
    });

    describe('getAttestationHash', function (): void {
        it('returns consistent hash for same attestation', function (): void {
            $attestation = $this->service->createPaymentAttestation(
                payerId: 'user-1',
                recipientId: 'user-2',
                amount: '100.00',
                currency: 'USD',
            );

            $hash1 = $this->service->getAttestationHash($attestation);
            $hash2 = $this->service->getAttestationHash($attestation);

            expect($hash1)->toBe($hash2);
            expect(strlen($hash1))->toBe(64);
        });
    });

    describe('generateMerkleRoot', function (): void {
        it('generates merkle root for multiple attestations', function (): void {
            $attestations = [
                $this->service->createPaymentAttestation('user-1', 'user-2', '100.00', 'USD'),
                $this->service->createPaymentAttestation('user-3', 'user-4', '200.00', 'EUR'),
                $this->service->createPaymentAttestation('user-5', 'user-6', '300.00', 'GBP'),
            ];

            $merkleRoot = $this->service->generateMerkleRoot($attestations);

            expect($merkleRoot)->not->toBeEmpty();
            expect(strlen($merkleRoot))->toBe(64);
        });

        it('returns empty hash for empty array', function (): void {
            $merkleRoot = $this->service->generateMerkleRoot([]);

            expect(strlen($merkleRoot))->toBe(64); // SHA256 of empty string
        });

        it('returns same hash for single attestation', function (): void {
            $attestation = $this->service->createPaymentAttestation('user-1', 'user-2', '100.00', 'USD');
            $merkleRoot = $this->service->generateMerkleRoot([$attestation]);

            expect($merkleRoot)->toBe($attestation->getAttestationHash());
        });
    });
});
