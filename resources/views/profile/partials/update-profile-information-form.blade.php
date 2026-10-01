<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}" class="space-y-5">
    @csrf
    @method('patch')

    {{-- Name --}}
    <div>
        <x-input-label for="name" :value="__('Full Name')" />
        <x-text-input id="name" name="name" type="text"
            :value="old('name', $user->name)" required autofocus autocomplete="name"
            placeholder="Your name" />
        <x-input-error class="mt-1.5" :messages="$errors->get('name')" />
    </div>

    {{-- Email --}}
    <div>
        <x-input-label for="email" :value="__('Email Address')" />
        <x-text-input id="email" name="email" type="email"
            :value="old('email', $user->email)" required autocomplete="username"
            placeholder="you@example.com" />
        <x-input-error class="mt-1.5" :messages="$errors->get('email')" />

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="mt-2 flex items-center gap-2">
                <p class="text-xs text-amber-600">
                    Your email is unverified.
                    <button form="send-verification"
                            class="underline text-blue-600 hover:text-blue-800 font-medium">
                        Resend verification email
                    </button>
                </p>
            </div>

            @if (session('status') === 'verification-link-sent')
                <p class="mt-2 text-xs text-emerald-600 font-medium">
                    A new verification link has been sent to your email.
                </p>
            @endif
        @endif
    </div>

    {{-- Submit --}}
    <div class="flex items-center gap-3 pt-1">
        <x-primary-button type="submit">Save Changes</x-primary-button>

        @if (session('status') === 'profile-updated')
            <p x-data="{ show: true }" x-show="show" x-transition
               x-init="setTimeout(() => show = false, 2000)"
               class="text-xs text-emerald-600 font-medium">
                Saved successfully.
            </p>
        @endif
    </div>
</form>
