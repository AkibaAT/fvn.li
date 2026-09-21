<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg fill="none" stroke="currentColor" viewBox="0 0 24 24" class={className} aria-hidden={ariaHidden} {...restProps}>
    <rect x="3.5" y="3.5" width="7" height="7" rx="1.5" stroke-width="2" />
    <rect x="13.5" y="3.5" width="7" height="7" rx="1.5" stroke-width="2" />
    <rect x="3.5" y="13.5" width="7" height="7" rx="1.5" stroke-width="2" />
    <rect x="13.5" y="13.5" width="7" height="7" rx="1.5" stroke-width="2" />
</svg>
