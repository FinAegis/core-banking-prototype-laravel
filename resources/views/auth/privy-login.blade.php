@php
    $brand = config('brand.name', 'Zelta');
    $step  = $step ?? request('step', 'email');
    $email = $email ?? old('email', (string) request('email', ''));
@endphp

<x-guest-layout>
    <x-authentication-card>
        <x-slot name="logo">
            <x-authentication-card-logo />
        </x-slot>

        <h1 class="text-xl font-bold mb-1">Sign in to {{ $brand }}</h1>
        <p class="text-sm text-gray-600 dark:text-gray-300 mb-4">
            Email-only, passwordless. New here? The same form signs you up.
        </p>

        <x-validation-errors class="mb-4" />

        @session('status')
            <div class="mb-4 font-medium text-sm text-emerald-700 dark:text-emerald-300">
                {{ $value }}
            </div>
        @endsession

        @if ($step === 'verify')
            {{-- Step 2: enter the code --}}
            <form method="POST" action="{{ route('login.privy.verify') }}">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">

                <div>
                    <x-label for="email-readonly" value="Email" />
                    <x-input id="email-readonly" class="block mt-1 w-full bg-gray-50 dark:bg-gray-900" type="email"
                             :value="$email" disabled readonly />
                </div>

                <div class="mt-4">
                    <x-label for="code" value="6-digit code" />
                    <x-input id="code" class="block mt-1 w-full font-mono tracking-widest text-lg"
                             type="text" name="code" required autocomplete="one-time-code"
                             inputmode="numeric" pattern="[0-9]*" autofocus
                             aria-describedby="code-help" />
                    <p id="code-help" class="text-xs text-gray-500 mt-1">Check your inbox for the code we sent to {{ $email }}.</p>
                </div>

                <div class="flex items-center justify-between mt-5">
                    <a href="{{ route('login') }}" class="text-sm text-gray-600 dark:text-gray-400 underline hover:no-underline">
                        Use a different email
                    </a>
                    <x-button>Verify and continue</x-button>
                </div>
            </form>

            <form method="POST" action="{{ route('login.privy.send') }}" class="mt-4">
                @csrf
                <input type="hidden" name="email" value="{{ $email }}">
                <button type="submit" class="text-sm text-gray-600 dark:text-gray-400 underline hover:no-underline">
                    Resend code
                </button>
            </form>
        @else
            {{-- Step 1: enter the email --}}
            <form method="POST" action="{{ route('login.privy.send') }}">
                @csrf

                <div>
                    <x-label for="email" value="Email" />
                    <x-input id="email" class="block mt-1 w-full" type="email" name="email"
                             :value="old('email', $email)" required autofocus autocomplete="email" />
                </div>

                <div class="flex items-center justify-end mt-4">
                    <x-button>Send code</x-button>
                </div>
            </form>
        @endif

        <p class="text-xs text-gray-500 mt-6 leading-relaxed">
            By signing in you agree to {{ $brand }}'s
            <a href="{{ route('legal.terms') }}" class="underline hover:no-underline">Terms</a>
            and
            <a href="{{ route('legal.privacy') }}" class="underline hover:no-underline">Privacy Policy</a>.
            We email you a one-time code; we never ask for a password.
        </p>
    </x-authentication-card>
</x-guest-layout>
