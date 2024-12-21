@props(['variant' => 'gray'])

@php
    $variants = [
        'gray' => 'bg-white dark:bg-gray-800 text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 hover:bg-gray-50 dark:hover:bg-gray-700',
        'blue' => 'bg-blue-50 dark:bg-blue-900/50 text-blue-600 dark:text-blue-300 border border-blue-200 dark:border-blue-800/50 hover:bg-blue-100/50 dark:hover:bg-blue-800/50',
        'green' => 'bg-green-50 dark:bg-green-900/50 text-green-600 dark:text-green-300 border border-green-200 dark:border-green-800/50 hover:bg-green-100/50 dark:hover:bg-green-800/50',
        'purple' => 'bg-purple-50 dark:bg-purple-900/50 text-purple-600 dark:text-purple-300 border border-purple-200 dark:border-purple-800/50 hover:bg-purple-100/50 dark:hover:bg-purple-800/50',
        'red' => 'bg-red-50 dark:bg-red-900/50 text-red-600 dark:text-red-300 border border-red-200 dark:border-red-800/50 hover:bg-red-100/50 dark:hover:bg-red-800/50',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-2 py-1 rounded-full text-xs font-medium transition-colors ' . $variants[$variant]]) }}>
    {{ $slot }}
</span>
