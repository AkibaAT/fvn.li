@props(['align' => 'left'])

<th {{ $attributes->merge(['class' => 'px-4 py-3 text-' . $align . ' text-sm font-semibold text-gray-900 dark:text-gray-100']) }}>
    {{ $slot }}
</th>
