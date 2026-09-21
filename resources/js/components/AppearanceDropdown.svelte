<script lang="ts">
    import CheckIcon from '@/components/icons/Check.svelte';
    import LaptopIcon from '@/components/icons/Laptop.svelte';
    import MoonIcon from '@/components/icons/Moon.svelte';
    import SunIcon from '@/components/icons/Sun.svelte';
    import { type Appearance, useAppearance } from '@/hooks/use-appearance.svelte';
    import { Popover } from '@/components/ui';

    const appearanceState = useAppearance();
    const updateAppearance = appearanceState.updateAppearance;

    let showMenu = $state(false);

    const onSelectAppearance = (mode: Appearance) => {
        updateAppearance(mode);
        showMenu = false;
    };

    const options = [
        { mode: 'light' as const, icon: SunIcon, label: 'Light', description: 'Always use light mode' },
        { mode: 'dark' as const, icon: MoonIcon, label: 'Dark', description: 'Always use dark mode' },
        { mode: 'system' as const, icon: LaptopIcon, label: 'System', description: 'Follow system preference' },
    ];
</script>

<Popover bind:open={showMenu}>
    <button
        type="button"
        onclick={() => (showMenu = !showMenu)}
        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-surface text-fg-muted transition-colors hover:text-fg"
        title="Change appearance"
        aria-label="Change appearance"
        aria-haspopup="menu"
        aria-expanded={showMenu}
        aria-controls="appearance-menu"
    >
        <SunIcon class="h-4 w-4 dark:hidden" />
        <MoonIcon class="hidden h-4 w-4 dark:block" />
    </button>

    {#if showMenu}
        <div
            id="appearance-menu"
            role="menu"
            aria-label="Appearance"
            class="popover-elevated absolute top-full right-0 z-50 mt-2 w-64 space-y-0.5 rounded-lg border border-border-strong p-1.5"
        >
            {#each options as opt (opt.mode)}
                {@const OptionIcon = opt.icon}
                {@const isSelected = appearanceState.appearance === opt.mode}
                <button
                    type="button"
                    role="menuitemradio"
                    aria-checked={isSelected}
                    onclick={() => onSelectAppearance(opt.mode)}
                    class="popover-row-hover flex w-full items-start gap-3 rounded-md px-3 py-2 text-left transition-colors focus:outline-none {isSelected
                        ? 'text-fg'
                        : 'text-fg-muted'}"
                >
                    <OptionIcon class="mt-0.5 h-4 w-4 shrink-0" />
                    <span class="min-w-0 flex-1">
                        <span class="block text-ui font-medium">{opt.label}</span>
                        <span class="block text-xs text-fg-faint">{opt.description}</span>
                    </span>
                    {#if isSelected}
                        <CheckIcon class="mt-0.5 h-4 w-4 shrink-0 text-fg" />
                    {/if}
                </button>
            {/each}
        </div>
    {/if}
</Popover>
