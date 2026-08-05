<script lang="ts">
    let {
        path,
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { path: string; class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg class={className} fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden={ariaHidden} {...restProps}>
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d={path} />
</svg>
