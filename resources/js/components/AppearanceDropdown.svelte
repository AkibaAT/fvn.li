<script lang="ts">
    import { Button } from '@/components/ui';
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
    <Button
        onclick={() => (showMenu = !showMenu)}
        variant="soft"
        tone="neutral"
        size="icon-md"
        class="rounded-lg"
        title="Change appearance"
        ariaLabel="Change appearance"
        type="button"
    >
        <span class="flex h-6 w-6 items-center justify-center" aria-hidden="true">
            <SunIcon class="h-5 w-5 dark:hidden" />
            <MoonIcon class="hidden h-5 w-5 dark:block" />
        </span>
    </Button>

    {#if showMenu}
        <div
            class="absolute top-full right-0 z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="p-2">
                <div class="space-y-1">
                    {#each options as opt (opt.mode)}
                        {@const OptionIcon = opt.icon}
                        <Button
                            type="button"
                            variant="ghost"
                            tone="neutral"
                            onclick={() => onSelectAppearance(opt.mode)}
                            class="w-full justify-start gap-3 px-3 py-2 text-left {appearanceState.appearance === opt.mode
                                ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                : 'text-gray-700 dark:text-gray-300'}"
                        >
                            <OptionIcon class="h-5 w-5" />
                            <div>
                                <div class="font-medium">{opt.label}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{opt.description}</div>
                            </div>
                        </Button>
                    {/each}
                </div>
            </div>
        </div>
    {/if}
</div>
