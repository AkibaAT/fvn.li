<script lang="ts" module>
    export interface FlagProps {
        /** Flag text, always rendered uppercase (e.g. "In dev", "18+"). */
        label: string;
        /** Selected/active filter state: border and text switch to --text. */
        active?: boolean;
        onclick?: (event: MouseEvent) => void;
        /** Accessible name; defaults to `${label} filter`. */
        ariaLabel?: string;
        title?: string;
        class?: string;
    }
</script>

<script lang="ts">
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';

    let { label, active = false, onclick, ariaLabel, title, class: className = '' }: FlagProps = $props();
</script>

<button
    type="button"
    {onclick}
    title={title ?? ariaLabel ?? label}
    aria-label={ariaLabel}
    aria-pressed={active}
    data-flag="true"
    class={twMerge(
        clsx(
            'inline-flex shrink-0 items-center rounded-[3px] border bg-transparent px-[5px] py-px text-[10px] leading-[14px] font-semibold tracking-[0.02em] uppercase transition-colors',
            'border-border-strong text-fg-muted hover:text-fg',
            active && 'border-fg text-fg',
            className,
        ),
    )}
>
    {label}
</button>
