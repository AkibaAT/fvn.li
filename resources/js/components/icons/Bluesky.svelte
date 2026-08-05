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
        d="M223.6 835.6C340.4 748.2 466 570.6 512 475.2 558 570.4 683.6 748 800.4 835.6c84.2 63.2 220.6 112 220.6-43.6 0-31-17.8-261-28.2-298.4C956.4 364 824 330.8 706.2 351 912 316 964.4 200 851.2 84 636.4-136.4 542.6 139.2 518.6 209.8v0c-3.4 9.8-5.2 15.6-6.6 15.6s-3.2-6-6.6-15.6v0c-24-70.6-118-346.2-332.6-125.8-113 116-60.8 232 145 267C200 330.8 67.6 364 31.4 493.8 20.8 531.2 3 761.2 3 792.2 3 947.8 139.4 899 223.6 835.8z"
    />
</svg>
