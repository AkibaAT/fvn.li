@props(['active' => false, 'type' => 'default'])

@php
    $classes = [
       'default' => 'bg-gray-50 text-gray-700 dark:bg-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-600 border border-gray-200 dark:border-transparent',
       'blue' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300 hover:bg-blue-200 dark:hover:bg-blue-800',
       'green' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300 hover:bg-green-200 dark:hover:bg-green-800',
       'purple' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300 hover:bg-purple-200 dark:hover:bg-purple-800',
       'red' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300 hover:bg-red-200 dark:hover:bg-red-800',
       'yellow' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300 hover:bg-yellow-200 dark:hover:bg-yellow-800',
       'indigo' => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900 dark:text-indigo-300 hover:bg-indigo-200 dark:hover:bg-indigo-800'
    ];
@endphp

<button {{ $attributes->merge([
    'class' => 'px-3 py-1 rounded-lg text-sm cursor-pointer transition-colors duration-150 ' . ($active ? $classes[$type] : $classes['default'] . ' hover:bg-gray-100 dark:hover:bg-gray-600')
]) }}>
    {{ $slot }}
</button>
