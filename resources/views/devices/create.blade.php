<x-app-layout>
    <div class="flex items-center gap-3 mb-6">
        <a href="{{ $backUrl }}"
           class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
        </a>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 leading-tight">Tambah gateway</h1>
            <p class="text-sm text-gray-500 mt-1">Daftarkan gateway untuk pompa dan sensor produksi</p>
        </div>
    </div>

    <div class="max-w-2xl">
        <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-6">
            <form action="{{ route('devices.store') }}" method="POST">
                @include('devices._form')
            </form>
        </div>
    </div>

</x-app-layout>
