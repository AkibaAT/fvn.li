<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 24 24" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        d="M14.768 3.96v1e-3l-2e-3-5e-3a9.08 9.08 0 0 0-0.218-0.779c-0.13-0.394 0.21-0.8 0.602-0.67 0.29 0.096 0.575 0.205 0.855 0.328l0.01 5e-3A10.002 10.002 0 0 1 12 22a10.002 10.002 0 0 1-9.162-5.985l-4e-3-0.01a9.722 9.722 0 0 1-0.329-0.855c-0.13-0.392 0.277-0.732 0.67-0.602 0.257 0.084 0.517 0.157 0.78 0.218l4e-3 2e-3A9 9 0 0 0 14.999 6a9.09 9.09 0 0 0-0.231-2.04zM16.5 6c0 5.799-4.701 10.5-10.5 10.5-0.426 0-0.847-0.026-1.26-0.075A8.5 8.5 0 1 0 16.425 4.74C16.475 5.153 16.5 5.573 16.5 6z"
    />
</svg>
