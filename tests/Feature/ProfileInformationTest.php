<?php

use App\Models\User;
use App\Notifications\ConfirmEmailChange;
use Illuminate\Support\Facades\Notification;
use Laravel\Jetstream\Http\Livewire\UpdateProfileInformationForm;
use Livewire\Livewire;

test('current profile information is available', function () {
    $this->actingAs($user = User::factory()->create());

    $component = Livewire::test(UpdateProfileInformationForm::class);

    expect($component->state['name'])->toEqual($user->name);
    expect($component->state['email'])->toEqual($user->email);
});

test('profile information can be updated', function () {
    Notification::fake();
    $this->actingAs($user = User::factory()->create());
    $originalEmail = $user->email;

    Livewire::test(UpdateProfileInformationForm::class)
        ->set('state', ['name' => 'Test Name', 'email' => 'test@example.com'])
        ->call('updateProfileInformation');

    // A new email address takes effect only after the link sent to it is
    // opened (GHSA-rrfq C65).
    expect($user->fresh())
        ->name->toEqual('Test Name')
        ->email->toEqual($originalEmail);

    Notification::assertSentOnDemand(ConfirmEmailChange::class);
});
