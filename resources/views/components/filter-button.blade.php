@props(['active' => false, 'type' => 'default'])

@php
    $classes = [
        'default' => 'bg-gray-100 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-300 dark:hover:bg-gray-600',
        'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        'green' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
        'red' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
    ];
@endphp

<button {{ $attributes->merge([
    'class' => 'px-3 py-1 rounded-lg text-sm ' . ($active ? $classes[$type] : $classes['default'])
]) }}>
    {{ $slot }}
</button>
