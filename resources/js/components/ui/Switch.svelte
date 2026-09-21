<script lang="ts">
    interface Props {
        checked: boolean;
        onchange?: (event: Event) => void;
        label?: string;
        ariaLabel?: string;
        id?: string;
        disabled?: boolean;
        class?: string;
        size?: 'sm' | 'md';
        tone?: 'primary' | 'danger';
    }

    let { checked, onchange, label, ariaLabel, id, disabled = false, class: className = '', size = 'md', tone = 'primary' }: Props = $props();

    const sizeClasses = {
        sm: 'h-5 w-9 after:start-[2px] after:top-[2px] after:h-4 after:w-4',
        md: 'h-6 w-11 after:start-[2px] after:top-0.5 after:h-5 after:w-5',
    };
    const toneClasses = {
        primary: 'peer-checked:bg-fg',
        danger: 'peer-checked:bg-red-600',
    };
</script>

<label class="relative inline-flex cursor-pointer items-center {disabled ? 'opacity-60' : ''} {className}" for={id}>
    <input
        {id}
        type="checkbox"
        {checked}
        {disabled}
        onchange={(event) => {
            onchange?.(event);
            event.currentTarget.checked = checked;
        }}
        aria-label={ariaLabel}
        class="peer sr-only"
    />
    <span
        class="peer rounded-full border border-border bg-surface-alt after:absolute after:rounded-full after:bg-surface after:transition-transform after:content-[''] peer-checked:after:translate-x-full peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent rtl:peer-checked:after:-translate-x-full {sizeClasses[
            size
        ]} {toneClasses[tone]}"
    ></span>
    {#if label}
        <span class="ms-3 text-[13px] text-fg-muted">{label}</span>
    {/if}
</label>
