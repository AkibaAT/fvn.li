<script lang="ts">
    import LaptopIcon from '@/components/icons/Laptop.svelte';
    import MoonIcon from '@/components/icons/Moon.svelte';
    import SunIcon from '@/components/icons/Sun.svelte';
    import { type Appearance, useAppearance } from '@/hooks/use-appearance.svelte';

    const appearanceState = useAppearance();
    const updateAppearance = appearanceState.updateAppearance;

    let showMenu = $state(false);
    let containerEl: HTMLDivElement;

    const onSelectAppearance = (mode: Appearance) => {
        updateAppearance(mode);
        showMenu = false;
    };

    $effect(() => {
        if (!showMenu) return;

        const handleClickOutside = (event: MouseEvent) => {
            const target = event.target as Element;
            if (containerEl && !containerEl.contains(target)) {
                showMenu = false;
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    });

    const options = [
        { mode: 'light' as const, icon: SunIcon, label: 'Light', description: 'Always use light mode' },
        { mode: 'dark' as const, icon: MoonIcon, label: 'Dark', description: 'Always use dark mode' },
        { mode: 'system' as const, icon: LaptopIcon, label: 'System', description: 'Follow system preference' },
    ];
</script>

<div class="theme-menu-container relative" bind:this={containerEl}>
    <button
        type="button"
        onclick={() => (showMenu = !showMenu)}
        class="inline-flex h-8 w-8 items-center justify-center rounded-md border border-border bg-surface text-fg-muted transition-colors hover:text-fg"
        title="Change appearance"
        aria-label="Change appearance"
        aria-haspopup="menu"
        aria-expanded={showMenu}
    >
        <SunIcon class="h-4 w-4 dark:hidden" />
        <MoonIcon class="hidden h-4 w-4 dark:block" />
    </button>

    {#if showMenu}
        <div class="absolute top-full right-0 z-50 mt-2 w-64 rounded-md border border-border bg-surface p-1.5">
            <div class="space-y-0.5">
                {#each options as opt (opt.mode)}
                    {@const OptionIcon = opt.icon}
                    <button
                        type="button"
                        onclick={() => onSelectAppearance(opt.mode)}
                        class="flex w-full items-start gap-3 rounded-md px-3 py-2 text-left transition-colors hover:bg-surface-alt {appearanceState.appearance ===
                        opt.mode
                            ? 'text-fg'
                            : 'text-fg-muted'}"
                    >
                        <OptionIcon class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>
                            <span class="block text-[13px] font-medium">{opt.label}</span>
                            <span class="block text-[12px] text-fg-faint">{opt.description}</span>
                        </span>
                    </button>
                {/each}
            </div>
        </div>
    {/if}
</div>
