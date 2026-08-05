<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 1024 1024" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        transform="matrix(1, 0, 0, -1, 0, 960)"
        d="M0.35 448 0 760.074l384 52.144V448zM448 821.518 959.872 896V448H448zM959.998 384 959.872-64 448 8.016V384zM384 16.164 0.312 68.758 0.292 384H384z"
    />
</svg>
