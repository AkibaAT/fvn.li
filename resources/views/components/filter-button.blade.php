@props(['active' => false, 'type' => 'default'])

@php
    $classes = [
       'default' => 'bg-gray-50 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-transparent',
       'blue' => 'bg-blue-50 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
       'green' => 'bg-green-50 text-green-700 dark:bg-green-900 dark:text-green-300',
       'purple' => 'bg-purple-50 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
       'red' => 'bg-red-50 text-red-700 dark:bg-red-900 dark:text-red-300'
    ];
@endphp

<button {{ $attributes->merge([
    'class' => 'px-3 py-1 rounded-lg text-sm ' . ($active ? $classes[$type] : $classes['default'])
]) }}>
    {{ $slot }}
</button>
