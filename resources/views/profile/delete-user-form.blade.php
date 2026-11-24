<x-action-section>
    <x-slot name="title">
        {{ __('Delete Account') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Permanently delete your account.') }}
    </x-slot>

    <x-slot name="content">
        <div class="max-w-xl text-sm text-gray-600 dark:text-gray-400">
            {{ __('When you delete your account, we erase your personal data and close the account. We keep only the records that the law requires. Download any data you want to keep before you delete the account.') }}
        </div>

        <div class="mt-5">
            <x-danger-button wire:click="confirmUserDeletion" wire:loading.attr="disabled">
                {{ __('Delete Account') }}
            </x-danger-button>
        </div>

        <!-- Delete User Confirmation Modal -->
        <x-dialog-modal wire:model.live="confirmingUserDeletion">
            <x-slot name="title">
                {{ __('Delete Account') }}
            </x-slot>

            <x-slot name="content">
                @if ($confirmsWithWord)
                    {{ __('Are you sure you want to delete your account? This cannot be undone. Type DELETE to confirm.') }}

                    @unless ($signedInRecently)
                        <div class="mt-4 text-sm text-gray-600 dark:text-gray-400">
                            {{ __('For your safety, you must have signed in within the last 15 minutes. Sign in again, and you come back to this page.') }}
                        </div>

                        <div class="mt-2">
                            <x-secondary-button wire:click="signInAgain" wire:loading.attr="disabled">
                                {{ __('Sign in again') }}
                            </x-secondary-button>
                        </div>
                    @endunless

                    <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.confirmation.focus(), 250)">
                        <x-input type="text" class="mt-1 block w-3/4"
                                    autocomplete="off"
                                    placeholder="DELETE"
                                    x-ref="confirmation"
                                    wire:model="confirmation"
                                    wire:keydown.enter="deleteUser" />

                        <x-input-error for="confirmation" class="mt-2" />
                    </div>
                @else
                    {{ __('Are you sure you want to delete your account? This cannot be undone. Enter your password to confirm.') }}

                    <div class="mt-4" x-data="{}" x-on:confirming-delete-user.window="setTimeout(() => $refs.password.focus(), 250)">
                        <x-input type="password" class="mt-1 block w-3/4"
                                    autocomplete="current-password"
                                    placeholder="{{ __('Password') }}"
                                    x-ref="password"
                                    wire:model="password"
                                    wire:keydown.enter="deleteUser" />

                        <x-input-error for="password" class="mt-2" />
                    </div>
                @endif
            </x-slot>

            <x-slot name="footer">
                <x-secondary-button wire:click="$toggle('confirmingUserDeletion')" wire:loading.attr="disabled">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-danger-button class="ms-3" wire:click="deleteUser" wire:loading.attr="disabled">
                    {{ __('Delete Account') }}
                </x-danger-button>
            </x-slot>
        </x-dialog-modal>
    </x-slot>
</x-action-section>
