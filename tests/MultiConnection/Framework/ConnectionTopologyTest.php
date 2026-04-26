<?php

declare(strict_types=1);

namespace Tests\MultiConnection\Framework;

use App\Domain\CardIssuance\Models\Cardholder;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('routes User writes to the default connection', function () {
    $user = new User();
    expect($user->getConnectionName())->toBeNull();
});

it('routes Cardholder writes to the tenant connection', function () {
    $cardholder = new Cardholder();
    expect($cardholder->getConnectionName())->toBe('tenant');
});

it('uses distinct PDO instances on default and tenant connections', function () {
    $defaultPdo = DB::connection()->getPdo();
    $tenantPdo = DB::connection('tenant')->getPdo();

    expect($defaultPdo)->not->toBe($tenantPdo);
});

it('does not see uncommitted writes from another connection', function () {
    $email = 'topology-' . uniqid() . '@example.invalid';

    DB::connection()->beginTransaction();

    try {
        DB::connection()->table('users')->insert([
            'uuid'              => (string) Str::uuid(),
            'name'              => 'Topology Test',
            'email'             => $email,
            'password'          => 'irrelevant',
            'email_verified_at' => now(),
            'created_at'        => now(),
            'updated_at'        => now(),
        ]);

        // Same connection (same session) sees it.
        $sameConn = DB::connection()->table('users')->where('email', $email)->exists();
        // Different connection (different session) does NOT see it.
        $tenantConn = DB::connection('tenant')->table('users')->where('email', $email)->exists();
    } finally {
        DB::connection()->rollBack();
    }

    expect($sameConn)->toBeTrue();
    expect($tenantConn)->toBeFalse();
});
