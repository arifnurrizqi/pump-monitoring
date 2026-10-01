<form method="post" action="{{ route('password.update') }}" class="space-y-5">
    @csrf
    @method('put')

    {{-- Current Password --}}
    <div>
        <x-input-label for="update_password_current_password" :value="__('Current Password')" />
        <x-text-input id="update_password_current_password" name="current_password" type="password"
            autocomplete="current-password" placeholder="••••••••" />
        <x-input-error :messages="$errors->updatePassword->get('current_password')" class="mt-1.5" />
    </div>

    {{-- New Password --}}
    <div>
        <x-input-label for="update_password_password" :value="__('New Password')" />
        <x-text-input id="update_password_password" name="password" type="password"
            autocomplete="new-password" placeholder="••••••••" />
        <x-input-error :messages="$errors->updatePassword->get('password')" class="mt-1.5" />
    </div>

    {{-- Confirm Password --}}
    <div>
        <x-input-label for="update_password_password_confirmation" :value="__('Confirm New Password')" />
        <x-text-input id="update_password_password_confirmation" name="password_confirmation" type="password"
            autocomplete="new-password" placeholder="••••••••" />
        <x-input-error :messages="$errors->updatePassword->get('password_confirmation')" class="mt-1.5" />
    </div>

    {{-- Submit --}}
    <div class="flex items-center gap-3 pt-1">
        <x-primary-button type="submit">Update Password</x-primary-button>

        @if (session('status') === 'password-updated')
            <p x-data="{ show: true }" x-show="show" x-transition
               x-init="setTimeout(() => show = false, 2000)"
               class="text-xs text-emerald-600 font-medium">
                Password updated.
            </p>
        @endif
    </div>
</form>
