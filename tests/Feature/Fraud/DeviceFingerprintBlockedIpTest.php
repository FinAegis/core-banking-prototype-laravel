<?php

declare(strict_types=1);

use App\Domain\Account\Models\Account;
use App\Domain\Account\Models\TransactionProjection;
use App\Domain\Fraud\Models\DeviceFingerprint;
use App\Domain\Fraud\Services\DeviceFingerprintService;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

it('counts devices on an IP address whose user has a blocked transaction', function (): void {
    // The query filtered on a "last_ip" column that device_fingerprints does
    // not have (the column is ip_address). MySQL rejects that; SQLite reads
    // "last_ip" as a string literal, so the count was always 0.
    config(['cache.default' => 'array']);
    Cache::flush();

    $user = User::factory()->create();
    $account = Account::factory()->create(['user_uuid' => $user->uuid]);
    TransactionProjection::factory()->create(['account_uuid' => $account->uuid, 'status' => 'blocked']);
    TransactionProjection::factory()->create(['account_uuid' => $account->uuid, 'status' => 'completed']);

    (new DeviceFingerprint())->forceFill([
        'id'               => (string) Str::uuid(),
        'fingerprint_hash' => hash('sha256', 'blocked-ip-test'),
        'user_id'          => $user->id,
        'user_agent'       => 'PestTest/1.0',
        'ip_address'       => '203.0.113.7',
        'first_seen_at'    => now(),
        'last_seen_at'     => now(),
    ])->save();

    $service = app(DeviceFingerprintService::class);
    $count = (new ReflectionMethod($service, 'countBlockedTransactionsForIp'))->invoke($service, '203.0.113.7');

    expect($count)->toBe(1);
});
