<?php

use App\Livewire\Profile\DeleteUserForm;
use App\Models\User;
use Livewire\Livewire;

test('user accounts can be deleted', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'password')
        ->call('deleteUser');

    // The account is erased through GdprService: the row stays as an
    // anonymous entry so the records the law requires stay linked to it.
    expect($user->fresh()?->name)->toStartWith('ANONYMIZED_');
});

test('correct password must be provided before account can be deleted', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(DeleteUserForm::class)
        ->set('password', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});
