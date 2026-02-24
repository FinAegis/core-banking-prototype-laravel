<?php

declare(strict_types=1);

use App\Domain\Compliance\Services\IdentityVerificationService;

uses(Tests\TestCase::class);

beforeEach(function () {
    $this->service = new IdentityVerificationService();
});

describe('IdentityVerificationService – Smile ID', function () {
    it('creates a session with default country code', function () {
        $session = $this->service->createVerificationSession('smileid', [
            'first_name' => 'Adebayo',
            'last_name'  => 'Ogunlesi',
        ]);

        expect($session['session_id'])->toStartWith('smileid_');
        expect($session['upload_url'])->toStartWith('https://upload.smileidentity.com/');
        expect($session['country_code'])->toBe('NG');
        expect($session['id_type'])->toBe('NATIONAL_ID');
        expect($session['job_type'])->toBe(5);
        expect($session)->toHaveKey('expires_at');
    });

    it('creates a session with explicit country code and id type', function () {
        $session = $this->service->createVerificationSession('smileid', [
            'first_name'   => 'Kwame',
            'last_name'    => 'Mensah',
            'country_code' => 'GH',
            'id_type'      => 'PASSPORT',
        ]);

        expect($session['country_code'])->toBe('GH');
        expect($session['id_type'])->toBe('PASSPORT');
    });

    it('returns result with expected shape', function () {
        $result = $this->service->getVerificationResult('smileid', 'smileid_abc123');

        expect($result['status'])->toBe('completed');
        expect($result['confidence'])->toBeFloat();
        expect($result['checks'])->toHaveKeys([
            'document_validity',
            'face_match',
            'liveness_check',
            'id_authority_check',
        ]);
        expect($result['extracted_data'])->toHaveKeys([
            'first_name',
            'last_name',
            'date_of_birth',
            'document_number',
            'document_type',
            'issuing_country',
            'expiry_date',
        ]);
        expect($result['smile_job_id'])->toStartWith('sjid_');
        expect($result)->toHaveKey('result_code');
    });

    it('throws InvalidArgumentException for unknown provider', function () {
        $this->service->createVerificationSession('unknown_provider', []);
    })->throws(InvalidArgumentException::class);

    it('throws InvalidArgumentException for unknown provider on result', function () {
        $this->service->getVerificationResult('unknown_provider', 'some_session');
    })->throws(InvalidArgumentException::class);
});
