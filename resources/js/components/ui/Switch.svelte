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

    let {
        checked,
        onchange,
        label,
        ariaLabel,
        id,
        disabled = false,
        class: className = '',
        size = 'md',
        tone = 'primary',
    }: Props = $props();

    const sizeClasses = {
        sm: "h-5 w-9 after:start-[2px] after:top-[2px] after:h-4 after:w-4",
        md: 'h-6 w-11 after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:border after:border-gray-300 peer-checked:after:border-white',
    };
    const toneClasses = {
        primary: 'peer-checked:bg-indigo-600',
        danger: 'peer-checked:bg-red-500',
    };
</script>

<label class="relative inline-flex cursor-pointer items-center {disabled ? 'opacity-60' : ''} {className}" for={id}>
    <input {id} type="checkbox" {checked} {disabled} {onchange} aria-label={ariaLabel} class="peer sr-only" />
    <span
        class="peer rounded-full bg-gray-200 peer-focus:ring-4 peer-focus:ring-indigo-300 after:absolute after:rounded-full after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full rtl:peer-checked:after:-translate-x-full dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-indigo-800 {sizeClasses[
            size
        ]} {toneClasses[tone]}"
    ></span>
    {#if label}
        <span class="ms-3 text-sm text-gray-700 dark:text-gray-300">{label}</span>
    {/if}
</label>
