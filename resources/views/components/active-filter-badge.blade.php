@props(['type', 'label'])

@php
    $classes = [
        'sort' => 'bg-yellow-100 text-yellow-700 dark:bg-yellow-900 dark:text-yellow-300',
        'platform' => 'bg-blue-100 text-blue-700 dark:bg-blue-900 dark:text-blue-300',
        'status' => 'bg-green-100 text-green-700 dark:bg-green-900 dark:text-green-300',
        'engine' => 'bg-purple-100 text-purple-700 dark:bg-purple-900 dark:text-purple-300',
        'nsfw' => 'bg-red-100 text-red-700 dark:bg-red-900 dark:text-red-300'
    ];
@endphp

<button {{ $attributes->merge([
    'class' => 'inline-flex items-center px-3 py-1 rounded-full text-sm ' . ($classes[$type] ?? $classes['sort'])
]) }}>
    {{ $label }}
    <span class="ml-2">&times;</span>
</button>
