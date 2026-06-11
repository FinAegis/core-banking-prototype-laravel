<?php

declare(strict_types=1);

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('stores client_id as a UUID-compatible column on oauth_auth_codes', function () {
    $type = Schema::getColumnType('oauth_auth_codes', 'client_id');
    // SQLite reports 'varchar', MySQL 'char' (Passport uses char(36)/uuid).
    // The intent is string-typed and UUID-capable — not an integer column.
    expect($type)->toBeIn(['varchar', 'char', 'uuid']);
});

it('stores client_id as a UUID-compatible column on oauth_access_tokens', function () {
    $type = Schema::getColumnType('oauth_access_tokens', 'client_id');
    // SQLite reports 'varchar', MySQL 'char' (Passport uses char(36)/uuid).
    // The intent is string-typed and UUID-capable — not an integer column.
    expect($type)->toBeIn(['varchar', 'char', 'uuid']);
});

it('stores client_id as a UUID-compatible column on oauth_personal_access_clients', function () {
    $type = Schema::getColumnType('oauth_personal_access_clients', 'client_id');
    // SQLite reports 'varchar', MySQL 'char' (Passport uses char(36)/uuid).
    // The intent is string-typed and UUID-capable — not an integer column.
    expect($type)->toBeIn(['varchar', 'char', 'uuid']);
});

it('round-trips a UUID client_id through oauth_auth_codes without truncation', function () {
    $uuid = '019ddaae-8521-7126-97be-8a2120715a7b';

    DB::table('oauth_auth_codes')->insert([
        'id'         => str_repeat('a', 80),
        'user_id'    => 1,
        'client_id'  => $uuid,
        'scopes'     => '[]',
        'revoked'    => 0,
        'expires_at' => now()->addMinutes(10),
    ]);

    $row = DB::table('oauth_auth_codes')->first();
    expect($row)->not->toBeNull();
    /** @var stdClass $row */
    expect($row->client_id)->toBe($uuid);

    DB::table('oauth_auth_codes')->where('id', str_repeat('a', 80))->delete();
});
