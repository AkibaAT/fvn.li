<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5h2m-1 14v-4m0 0l-2-2m2 2l2-2M5 13a7 7 0 1114 0 7 7 0 01-14 0z" />
</svg>
