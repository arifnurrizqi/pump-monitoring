<x-app-layout>
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900 leading-tight">Settings</h1>
        <p class="text-sm text-gray-500 mt-1">Manage your account information and security</p>
    </div>

    @if(auth()->user()?->is_admin)
        <a href="{{ route('settings.mqtt.edit') }}" class="mb-6 flex items-center justify-between rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4 transition-colors hover:bg-blue-100">
            <span>
                <span class="block text-sm font-bold text-blue-900">System MQTT Broker</span>
                <span class="mt-0.5 block text-xs text-blue-700">Configure internal or external broker connection for all device commands.</span>
            </span>
            <span class="text-xl text-blue-600">&rarr;</span>
        </a>
    @endif

    <div class="w-full max-w-none grid grid-cols-1 lg:grid-cols-3 gap-6 items-start">

        {{-- Profile Information --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Profile Information</h3>
                <p class="text-xs text-gray-400 mt-0.5">Update your name and email address.</p>
            </div>
            <div class="px-6 pb-6">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        {{-- Update Password --}}
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h3 class="text-sm font-semibold text-gray-900">Update Password</h3>
                <p class="text-xs text-gray-400 mt-0.5">Ensure your account uses a strong, unique password.</p>
            </div>
            <div class="px-6 pb-6">
                @include('profile.partials.update-password-form')
            </div>
        </div>

        {{-- Delete Account --}}
        <div class="bg-white rounded-2xl border border-red-100 shadow-sm">
            <div class="px-6 py-4 border-b border-red-100">
                <h3 class="text-sm font-semibold text-red-700">Danger Zone</h3>
                <p class="text-xs text-red-400 mt-0.5">Irreversible actions — proceed with caution.</p>
            </div>
            <div class="px-6 pt-4 pb-6">
                @include('profile.partials.delete-user-form')
            </div>
        </div>

    </div>

</x-app-layout>
