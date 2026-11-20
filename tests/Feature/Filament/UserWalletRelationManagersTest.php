<?php

declare(strict_types=1);

use App\Filament\Admin\Resources\UserResource;
use App\Filament\Admin\Resources\UserResource\RelationManagers\BlockchainAddressesRelationManager;
use App\Filament\Admin\Resources\UserResource\RelationManagers\BlockchainTransactionsRelationManager;
use App\Filament\Admin\Resources\UserResource\RelationManagers\WalletSendRecordsRelationManager;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\Relation;

it('registers the three wallet relation managers on the User resource', function (): void {
    expect(UserResource::getRelations())->toBe([
        BlockchainAddressesRelationManager::class,
        BlockchainTransactionsRelationManager::class,
        WalletSendRecordsRelationManager::class,
    ]);
});

it('points each wallet relation manager at a real User relation', function (): void {
    $user = User::factory()->create();

    $managers = [
        BlockchainAddressesRelationManager::class,
        BlockchainTransactionsRelationManager::class,
        WalletSendRecordsRelationManager::class,
    ];

    foreach ($managers as $manager) {
        $property = (new ReflectionClass($manager))->getProperty('relationship');
        $relationship = (string) $property->getValue();

        // The configured relationship resolves to a genuine Eloquent relation.
        expect(method_exists(User::class, $relationship))->toBeTrue()
            ->and($user->{$relationship}())->toBeInstanceOf(Relation::class);
    }
});
