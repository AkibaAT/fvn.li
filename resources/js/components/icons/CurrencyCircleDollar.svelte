<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 32 32" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        d="M16 3a13 13 0 1 0 13 13 13.0138 13.0138 0 0 0-13-13zM16 27a11 11 0 1 1 11-11 11.0125 11.0125 0 0 1-11 11zM21 18.5a3.5 3.5 0 0 1-3.5 3.5H17v1a1 1 0 0 1-2 0v-1h-2a1 1 0 0 1 0-2h4.5a1.5 1.5 0 0 0 0-3h-3a3.5 3.5 0 0 1 0-7H15V9a1 1 0 0 1 2 0v1h2a1 1 0 0 1 0 2h-4.5a1.5 1.5 0 0 0 0 3h3a3.5 3.5 0 0 1 3.5 3.5z"
    />
</svg>
