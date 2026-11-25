@php
    /** @var \Illuminate\Support\Collection<int, \App\Domain\Account\Models\Account> $accounts */
    $currentKey = $apiKey ?? null;
    $pinned = old('allowed_account_uuids', $currentKey?->pinnedAccountUuids() ?? []);
    $pinned = is_array($pinned) ? $pinned : [];
@endphp

<!-- Money limits -->
<div class="border-t border-gray-200 pt-6">
    <h2 class="text-lg font-semibold text-gray-900">Money Limits</h2>
    <p class="mt-1 text-sm text-gray-600">
        These limits apply whenever this key moves money.
        They do not apply when you use the app yourself.
        Amounts count in whole units of the currency being moved.
        For example, a limit of 1,000 allows 1,000 euros or 1,000 US dollars.
    </p>

    <div class="mt-4 grid grid-cols-1 gap-6 sm:grid-cols-2">
        <div>
            <label for="transfer_limit" class="block text-sm font-medium text-gray-700 mb-2">
                Largest single amount
            </label>
            <input type="text"
                   inputmode="decimal"
                   name="transfer_limit"
                   id="transfer_limit"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                   placeholder="{{ \App\Models\ApiKey::displayAmount(\App\Models\ApiKey::defaultTransferLimit()) }}"
                   value="{{ old('transfer_limit', $currentKey?->transfer_limit) }}">
            @error('transfer_limit')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-sm text-gray-500">
                This is the most the key can move in one request.
                Leave this empty to use the standard limit of {{ \App\Models\ApiKey::displayAmount(\App\Models\ApiKey::defaultTransferLimit()) }}.
            </p>
        </div>

        <div>
            <label for="daily_limit" class="block text-sm font-medium text-gray-700 mb-2">
                Largest total per day
            </label>
            <input type="text"
                   inputmode="decimal"
                   name="daily_limit"
                   id="daily_limit"
                   class="w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                   placeholder="{{ \App\Models\ApiKey::displayAmount(\App\Models\ApiKey::defaultDailyLimit()) }}"
                   value="{{ old('daily_limit', $currentKey?->daily_limit) }}">
            @error('daily_limit')
                <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
            @enderror
            <p class="mt-1 text-sm text-gray-500">
                Leave this empty to use the standard limit of {{ \App\Models\ApiKey::displayAmount(\App\Models\ApiKey::defaultDailyLimit()) }}.
                The daily total starts again at midnight, Coordinated Universal Time.
            </p>
        </div>
    </div>

    <p class="mt-3 text-sm text-gray-500">
        Neither limit can be higher than {{ \App\Models\ApiKey::displayAmount(\App\Models\ApiKey::limitCeiling()) }}.
    </p>

    <fieldset class="mt-6">
        <legend class="block text-sm font-medium text-gray-700 mb-2">
            Accounts this key can move money from
        </legend>
        @if($accounts->isEmpty())
            <p class="text-sm text-gray-500">You do not have any accounts yet. This key will be able to use the accounts you open later.</p>
        @else
            <div class="space-y-2">
                @foreach($accounts as $account)
                    <label class="flex items-center">
                        <input type="checkbox"
                               name="allowed_account_uuids[]"
                               value="{{ $account->uuid }}"
                               class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                               {{ in_array($account->uuid, $pinned, true) ? 'checked' : '' }}>
                        <span class="ml-2 text-sm text-gray-900">{{ $account->name }}</span>
                    </label>
                @endforeach
            </div>
            <p class="mt-1 text-sm text-gray-500">
                Leave every box empty to let this key use all of your accounts.
            </p>
        @endif
        @error('allowed_account_uuids')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
        @error('allowed_account_uuids.*')
            <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
        @enderror
    </fieldset>
</div>
