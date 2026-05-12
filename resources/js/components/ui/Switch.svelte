<script lang="ts">
    interface Props {
        checked: boolean;
        onchange?: (event: Event) => void;
        label?: string;
        id?: string;
        disabled?: boolean;
        class?: string;
    }

    let { checked = $bindable(false), onchange, label, id, disabled = false, class: className = '' }: Props = $props();
</script>

<label class="relative inline-flex cursor-pointer items-center {disabled ? 'opacity-60' : ''} {className}" for={id}>
    <input {id} type="checkbox" bind:checked {disabled} {onchange} class="peer sr-only" />
    <span
        class="peer h-6 w-11 rounded-full bg-gray-200 peer-checked:bg-blue-600 peer-focus:ring-4 peer-focus:ring-blue-300 after:absolute after:start-[2px] after:top-0.5 after:h-5 after:w-5 after:rounded-full after:border after:border-gray-300 after:bg-white after:transition-all after:content-[''] peer-checked:after:translate-x-full peer-checked:after:border-white dark:border-gray-600 dark:bg-gray-700 dark:peer-focus:ring-blue-800"
    ></span>
    {#if label}
        <span class="ms-3 text-sm text-gray-700 dark:text-gray-300">{label}</span>
    {/if}
</label>
