<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 21 21" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <g stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" fill="none" transform="translate(2 2)" fill-rule="evenodd">
        <circle cx="8.5" cy="8.5" r="8" />
        <g transform="rotate(90 8.5 8.5)">
            <path d="M5.5 11.5l6-6" />
            <path d="M5.5 5.5l6 6" />
        </g>
    </g>
</svg>
