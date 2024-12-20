@props(['variant' => 'gray'])

@php
    $variants = [
        'gray' => 'bg-gray-100 dark:bg-gray-900 text-gray-700 dark:text-gray-300',
        'blue' => 'bg-blue-100 dark:bg-blue-900 text-blue-700 dark:text-blue-300',
        // Add more variants as needed
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center px-1.5 py-0.5 rounded-full text-xs font-medium ' . $variants[$variant]]) }}>
    {{ $slot }}
</span>
