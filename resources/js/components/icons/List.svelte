<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path stroke-linecap="round" stroke-width="2.5" d="M4 6h.01M4 12h.01M4 18h.01" />
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.5 6H20M8.5 12H20M8.5 18H20" />
</svg>
