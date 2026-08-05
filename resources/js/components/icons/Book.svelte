<script lang="ts">
    let {
        class: className = '',
        'aria-hidden': ariaHidden = true,
        ...restProps
    }: { class?: string; 'aria-hidden'?: boolean; [key: string]: any } = $props();
</script>

<svg viewBox="0 0 24 24" fill="currentColor" class={className} aria-hidden={ariaHidden} {...restProps}>
    <path
        d="M0 3.75A0.75 0.75 0 0 1 0.75 3h7.497c1.566 0 2.945 0.8 3.751 2.014A4.495 4.495 0 0 1 15.75 3h7.5A0.75 0.75 0 0 1 24 3.75v15.063a0.752 0.752 0 0 1-0.755 0.75l-7.682-0.052a3 3 0 0 0-2.142 0.878l-0.89 0.891a0.75 0.75 0 0 1-1.061 0l-0.902-0.901A2.996 2.996 0 0 0 8.447 19.5H0.75A0.75 0.75 0 0 1 0 18.75zM12.75 18.982a4.503 4.503 0 0 1 2.823-0.971l6.927 0.047V4.5h-6.75a3 3 0 0 0-3 3zM11.247 7.497a3 3 0 0 0-3-2.997H1.5V18h6.947c1.018 0 2.006 0.346 2.803 0.98z"
    />
</svg>
