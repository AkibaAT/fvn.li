@props(['icon', 'active' => false])

<button {{ $attributes->merge([
    'class' => 'px-4 py-2 bg-white dark:bg-gray-800 text-gray-700 dark:text-gray-300 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700 flex items-center gap-2'
]) }}>
    <x-dynamic-component :component="$icon" class="w-5 h-5" />
    {{ $slot }}
    @if($active)
        <span class="w-2 h-2 rounded-full bg-blue-500"></span>
    @endif
</button>
