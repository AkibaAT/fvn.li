<script lang="ts">
    import { type Appearance, useAppearance } from '@/hooks/use-appearance.svelte';

    const appearanceState = useAppearance();
    const updateAppearance = appearanceState.updateAppearance;

    let showMenu = $state(false);
    let containerEl: HTMLDivElement;

    const toggleIcon = $derived.by(() => {
        const mode = appearanceState.appearance;
        if (mode === 'dark') return 'icon-moon';
        if (mode === 'light') return 'icon-sun';
        // system: check actual preference
        if (typeof window !== 'undefined' && window.matchMedia('(prefers-color-scheme: dark)').matches) return 'icon-moon';
        return 'icon-sun';
    });

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

    const options: { mode: Appearance; icon: string; label: string; description: string }[] = [
        { mode: 'light', icon: 'icon-sun', label: 'Light', description: 'Always use light mode' },
        { mode: 'dark', icon: 'icon-moon', label: 'Dark', description: 'Always use dark mode' },
        { mode: 'system', icon: 'icon-laptop', label: 'System', description: 'Follow system preference' },
    ];
</script>

<div class="theme-menu-container relative" bind:this={containerEl}>
    <button
        onclick={() => (showMenu = !showMenu)}
        class="flex items-center rounded-lg bg-gray-100 px-3 py-2 text-gray-700 transition-colors duration-200 hover:bg-gray-200 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
        title="Change appearance"
        aria-label="Change appearance"
        type="button"
    >
        <span class="flex h-6 w-6 items-center justify-center" aria-hidden="true">
            <i class={toggleIcon}></i>
        </span>
    </button>

    {#if showMenu}
        <div
            class="absolute top-full right-0 z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
        >
            <div class="p-2">
                <div class="space-y-1">
                    {#each options as opt (opt.mode)}
                        <button
                            onclick={() => onSelectAppearance(opt.mode)}
                            class="flex w-full items-center gap-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 {appearanceState.appearance ===
                            opt.mode
                                ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                : 'text-gray-700 dark:text-gray-300'}"
                            type="button"
                        >
                            <i class="{opt.icon} text-lg" aria-hidden="true"></i>
                            <div>
                                <div class="font-medium">{opt.label}</div>
                                <div class="text-xs text-gray-500 dark:text-gray-400">{opt.description}</div>
                            </div>
                        </button>
                    {/each}
                </div>
            </div>
        </div>
    {/if}
</div>
