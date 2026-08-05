<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 24 24" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        d="M5 3.75C5 2.784 5.784 2 6.75 2h10.5C18.216 2 19 2.784 19 3.75v17.5a0.75 0.75 0 0 1-1.218 0.586L12 17.21 6.219 21.835A0.75 0.75 0 0 1 5 21.25zM6.75 3.5A0.25 0.25 0 0 0 6.5 3.75v15.94l5.031-4.026a0.749 0.749 0 0 1 0.938 0L17.5 19.69V3.75a0.25 0.25 0 0 0-0.25-0.25z"
    />
</svg>
