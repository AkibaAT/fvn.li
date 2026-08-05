<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 1024 1024" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        transform="matrix(1, 0, 0, -1, 0, 960)"
        d="M512 960C229.2 960 0 730.8 0 448S229.2-64 512-64s512 229.2 512 512-229.2 512-512 512zM763.6 609l-84-395.8c-5.8-28.2-22.8-34.8-46.4-21.8l-128 94.6-61.4-59.8c-7.2-7-12.8-12.8-25.6-12.8-16.6 0-13.8 6.2-19.4 22l-43.6 143.2L228.6 418c-27.4 8.4-27.6 27.2 6.2 40.6L728 649c22.4 10.2 44.2-5.4 35.6-40z"
    />
</svg>
