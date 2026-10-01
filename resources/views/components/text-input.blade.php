@props(['disabled' => false])

<input
    @disabled($disabled)
    {{ $attributes->merge(['class' => 'block w-full rounded-xl border border-gray-200 bg-gray-50 px-3.5 py-2.5 text-sm text-gray-900 shadow-sm focus:border-blue-400 focus:ring-blue-400 focus:bg-white transition-colors placeholder:text-gray-400 disabled:bg-gray-100 disabled:text-gray-500 disabled:cursor-not-allowed']) }}
>
