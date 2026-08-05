<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 32 32" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        transform="translate(0 49e-4)"
        d="M26 24H6a2.0023 2.0023 0 0 1-2-2V8a2.002 2.002 0 0 1 2-2h20a2.0023 2.0023 0 0 1 2 2v14a2.0027 2.0027 0 0 1-2 2zM6 8v14h20V8z"
    />
    <rect width="28" height="2" x="2" y="26.0049" />
</svg>
