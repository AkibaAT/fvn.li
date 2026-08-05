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
        d="M640 960c-35.4 0-64-28.6-64-64s28.6-64 64-64h165.4L402.8 429.2c-25-25-25-65.6 0-90.6s65.6-25 90.6 0L896 741.4V576c0-35.4 28.6-64 64-64s64 28.6 64 64v320c0 35.4-28.6 64-64 64zM160 896C71.6 896 0 824.4 0 736V96C0 7.6 71.6-64 160-64h640c88.4 0 160 71.6 160 160v224c0 35.4-28.6 64-64 64s-64-28.6-64-64V96c0-17.6-14.4-32-32-32H160c-17.6 0-32 14.4-32 32v640c0 17.6 14.4 32 32 32h224c35.4 0 64 28.6 64 64s-28.6 64-64 64z"
    />
</svg>
