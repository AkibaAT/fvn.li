<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2" class={className} aria-hidden={ariaHidden} {...restProps}>
    <polyline points="15 18 9 12 15 6"></polyline>
</svg>
