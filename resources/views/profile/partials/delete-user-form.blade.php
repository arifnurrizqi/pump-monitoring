<div class="space-y-4">
    <p class="text-sm text-gray-600">
        Once your account is deleted, all data will be permanently removed.
        Please download any information you wish to keep before proceeding.
    </p>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round"
                  d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16
                     19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772
                     5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114
                     1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964
                     51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/>
        </svg>
        Delete My Account
    </x-danger-button>
</div>

<x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
    <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
        @csrf
        @method('delete')

        <div class="flex items-start gap-4 mb-5">
            <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-red-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949
                             3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-gray-900">Delete Account?</h2>
                <p class="text-sm text-gray-500 mt-1">
                    This action is permanent and cannot be undone. Enter your password to confirm.
                </p>
            </div>
        </div>

        <div class="mb-5">
            <x-input-label for="password" :value="__('Password')" class="sr-only" />
            <x-text-input id="password" name="password" type="password"
                class="w-full" placeholder="Enter your password to confirm" />
            <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-1.5" />
        </div>

        <div class="flex justify-end gap-3">
            <x-secondary-button x-on:click="$dispatch('close')">
                Cancel
            </x-secondary-button>
            <x-danger-button type="submit">
                Yes, Delete Account
            </x-danger-button>
        </div>
    </form>
</x-modal>
