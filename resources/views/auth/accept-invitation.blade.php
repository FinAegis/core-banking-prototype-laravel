<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h2 class="text-xl font-bold text-center mb-2">You're invited!</h2>
        <p class="text-sm text-gray-500 text-center mb-6">
            Create your account for <strong>{{ $invitation->email }}</strong>
        </p>

        <x-validation-errors class="mb-4" />

        <form method="POST" action="{{ url('/invitation/accept') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">

            <div class="mb-4">
                <x-label for="name" value="{{ __('Name') }}" />
                <x-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            </div>

            <div class="mb-4">
                <x-label for="email" value="{{ __('Email') }}" />
                <x-input id="email" class="block mt-1 w-full bg-gray-100" type="email" value="{{ $invitation->email }}" disabled />
            </div>

            <div class="mb-4">
                <x-label for="password" value="{{ __('Password') }}" />
                <x-input id="password" class="block mt-1 w-full" type="password" name="password" required autocomplete="new-password" />
            </div>

            <div class="mb-4">
                <x-label for="password_confirmation" value="{{ __('Confirm Password') }}" />
                <x-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required autocomplete="new-password" />
            </div>

            <div class="flex items-center justify-end mt-4">
                <x-button class="w-full justify-center">
                    {{ __('Create Account') }}
                </x-button>
            </div>
        </form>
    </x-authentication-card>
</x-guest-layout>
