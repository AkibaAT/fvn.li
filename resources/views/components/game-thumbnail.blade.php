@props(['game', 'variant' => 'default', 'class' => 'object-cover w-full h-full'])

<img
    src="{{ $game->getThumbnailUrl($variant) }}"
    width="{{ $game->optimized_thumbnails[$variant]['width'] ?? '' }}"
    height="{{ $game->optimized_thumbnails[$variant]['height'] ?? '' }}"
    alt="{{ $game->name }}"
    class="{{ $class }}"
    @if($lazy) loading="lazy" @endif
/>
