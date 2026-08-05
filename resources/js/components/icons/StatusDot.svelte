<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg fill="currentColor" viewBox="0 0 8 8" class={className} aria-hidden={ariaHidden} {...restProps}><circle cx="4" cy="4" r="4" /></svg>
