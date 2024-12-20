@props(['align' => 'left'])

<td {{ $attributes->merge(['class' => 'px-4 py-3 text-sm text-gray-500 dark:text-gray-400 text-' . $align]) }}>
    {{ $slot }}
</td>
