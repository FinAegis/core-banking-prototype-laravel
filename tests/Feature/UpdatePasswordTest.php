<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Laravel\Jetstream\Http\Livewire\UpdatePasswordForm;
use Livewire\Livewire;

test('password can be updated', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(UpdatePasswordForm::class)
        ->set('state', [
            'current_password'      => 'password',
            'password'              => 'ComplexP@ssw0rd2024!',
            'password_confirmation' => 'ComplexP@ssw0rd2024!',
        ])
        ->call('updatePassword');

    expect(Hash::check('ComplexP@ssw0rd2024!', $user->fresh()->password))->toBeTrue();
});

test('current password must be correct', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(UpdatePasswordForm::class)
        ->set('state', [
            'current_password'      => 'wrong-password',
            'password'              => 'ComplexP@ssw0rd2024!',
            'password_confirmation' => 'ComplexP@ssw0rd2024!',
        ])
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('new passwords must match', function () {
    $this->actingAs($user = User::factory()->create());

    Livewire::test(UpdatePasswordForm::class)
        ->set('state', [
            'current_password'      => 'password',
            'password'              => 'ComplexP@ssw0rd2024!',
            'password_confirmation' => 'WrongComplexP@ssw0rd2024!',
        ])
        ->call('updatePassword')
        ->assertHasErrors(['password']);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
