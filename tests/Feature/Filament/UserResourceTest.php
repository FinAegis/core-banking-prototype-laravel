<?php

use App\Filament\Admin\Resources\UserResource;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Tables\Actions\BulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ViewAction;
use function Pest\Livewire\livewire;

beforeEach(function () {
    $this->setUpFilamentWithAuth();
});

it('can render user resource page', function () {
    $this->get(UserResource::getUrl('index'))
        ->assertSuccessful();
});

it('can list users', function () {
    $users = User::factory()->count(3)->create();

    livewire(UserResource\Pages\ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

it('can render user creation page', function () {
    $this->get(UserResource::getUrl('create'))
        ->assertSuccessful();
});

it('can create user', function () {
    $userData = [
        'uuid' => fake()->uuid(),
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'password' => 'password123',
        'password_confirmation' => 'password123',
    ];

    livewire(UserResource\Pages\CreateUser::class)
        ->fillForm($userData)
        ->call('create')
        ->assertHasNoFormErrors();

    $this->assertDatabaseHas('users', [
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);
});

it('validates required fields when creating user', function () {
    livewire(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'name' => '',
            'email' => '',
            'password' => '',
        ])
        ->call('create')
        ->assertHasFormErrors(['name', 'email', 'password']);
});

it('validates unique email when creating user', function () {
    $existingUser = User::factory()->create();

    livewire(UserResource\Pages\CreateUser::class)
        ->fillForm([
            'uuid' => fake()->uuid(),
            'name' => 'John Doe',
            'email' => $existingUser->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])
        ->call('create')
        ->assertHasFormErrors(['email']);
});

it('can render user edit page', function () {
    $user = User::factory()->create();

    $this->get(UserResource::getUrl('edit', ['record' => $user]))
        ->assertSuccessful();
});

it('can retrieve user data for editing', function () {
    $user = User::factory()->create();

    livewire(UserResource\Pages\EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet([
            'name' => $user->name,
            'email' => $user->email,
        ]);
});

it('can update user', function () {
    $user = User::factory()->create();

    $newData = [
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
    ];

    livewire(UserResource\Pages\EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm($newData)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($user->refresh())
        ->name->toBe('Updated Name')
        ->email->toBe('updated@example.com');
});

it('can delete user', function () {
    $user = User::factory()->create();

    livewire(UserResource\Pages\EditUser::class, ['record' => $user->getRouteKey()])
        ->callAction(DeleteAction::class);

    $this->assertModelMissing($user);
});

it('can view user details', function () {
    $user = User::factory()->create();

    livewire(UserResource\Pages\ViewUser::class, ['record' => $user->getRouteKey()])
        ->assertSuccessful();
});

it('can search users by name', function () {
    $user1 = User::factory()->create(['name' => 'John Doe']);
    $user2 = User::factory()->create(['name' => 'Jane Smith']);
    $user3 = User::factory()->create(['name' => 'Bob Johnson']);

    livewire(UserResource\Pages\ListUsers::class)
        ->searchTable('John')
        ->assertCanSeeTableRecords([$user1, $user3])
        ->assertCanNotSeeTableRecords([$user2]);
});

it('can search users by email', function () {
    $user1 = User::factory()->create(['email' => 'john@example.com']);
    $user2 = User::factory()->create(['email' => 'jane@test.com']);

    livewire(UserResource\Pages\ListUsers::class)
        ->searchTable('example')
        ->assertCanSeeTableRecords([$user1])
        ->assertCanNotSeeTableRecords([$user2]);
});

it('can sort users by name', function () {
    $userA = User::factory()->create(['name' => 'Alice']);
    $userB = User::factory()->create(['name' => 'Bob']);
    $userC = User::factory()->create(['name' => 'Charlie']);

    livewire(UserResource\Pages\ListUsers::class)
        ->sortTable('name')
        ->assertCanSeeTableRecords([$userA, $userB, $userC], inOrder: true);
});

it('can filter users by creation date', function () {
    $oldUser = User::factory()->create(['created_at' => now()->subMonth()]);
    $newUser = User::factory()->create(['created_at' => now()]);

    livewire(UserResource\Pages\ListUsers::class)
        ->filterTable('created_at', [
            'created_from' => now()->subWeek()->toDateString(),
        ])
        ->assertCanSeeTableRecords([$newUser])
        ->assertCanNotSeeTableRecords([$oldUser]);
});

it('can perform bulk delete on users', function () {
    $users = User::factory()->count(3)->create();

    livewire(UserResource\Pages\ListUsers::class)
        ->callTableBulkAction('delete', $users);

    foreach ($users as $user) {
        $this->assertModelMissing($user);
    }
});

it('shows user accounts count in table', function () {
    $user = User::factory()->create();
    $user->accounts()->createMany([
        ['uuid' => fake()->uuid(), 'name' => 'Account 1', 'balance' => 10000],
        ['uuid' => fake()->uuid(), 'name' => 'Account 2', 'balance' => 20000],
    ]);

    livewire(UserResource\Pages\ListUsers::class)
        ->assertTableColumnStateSet('accounts_count', '2', record: $user);
});

it('displays formatted registration date', function () {
    $user = User::factory()->create(['created_at' => '2025-01-15 10:30:00']);

    livewire(UserResource\Pages\ListUsers::class)
        ->assertTableColumnFormattedStateSet('created_at', '15 Jan 2025', record: $user);
});

it('can edit user and see related data', function () {
    $user = User::factory()->create();
    $account = $user->accounts()->create([
        'uuid' => fake()->uuid(),
        'name' => 'Test Account',
        'balance' => 50000,
    ]);

    livewire(UserResource\Pages\EditUser::class, ['record' => $user->getRouteKey()])
        ->assertFormSet([
            'name' => $user->name,
            'email' => $user->email,
        ]);
});