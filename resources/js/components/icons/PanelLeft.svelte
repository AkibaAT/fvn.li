<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path d="M3 5a2 2 0 012-2h14a2 2 0 012 2v14a2 2 0 01-2 2H5a2 2 0 01-2-2V5z" />
    <path d="M15 3v18" />
</svg>
