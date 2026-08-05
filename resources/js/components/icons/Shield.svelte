<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 24 24" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path d="M13 15.5a1 1 0 1 1-2 0 1 1 0 0 1 2 0zM12.75 7.25a0.75 0.75 0 0 0-1.5 0v4.5a0.75 0.75 0 0 0 1.5 0z" />
    <path
        d="M11.46 0.637a1.748 1.748 0 0 1 1.08 0l8.25 2.675A1.75 1.75 0 0 1 22 4.976V10c0 6.19-3.77 10.705-9.401 12.83a1.704 1.704 0 0 1-1.198 0C5.771 20.704 2 16.19 2 10V4.976c0-0.76 0.49-1.43 1.21-1.664zM12.077 2.063a0.253 0.253 0 0 0-0.154 0L3.673 4.74A0.25 0.25 0 0 0 3.5 4.977V10c0 5.461 3.28 9.483 8.43 11.426a0.199 0.199 0 0 0 0.14 0C17.22 19.483 20.5 15.46 20.5 10V4.976a0.25 0.25 0 0 0-0.173-0.237z"
    />
</svg>
