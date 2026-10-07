<script lang="ts">
    import clsx from 'clsx';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import { cn } from '@/utils/cn';

    interface Props {
        platforms: GameCardPlatform[];
        selectedPlatforms?: string[];
        onPlatformClick?: (platform: GameCardPlatform) => void;
        class?: string;
    }

    let { platforms, selectedPlatforms = [], onPlatformClick, class: className = '' }: Props = $props();

    const { getPlatformIcon } = usePlatformIcons();
</script>

<div class={cn('flex shrink-0 items-center', className)} data-platform-glyphs>
    {#each platforms as platform (platform)}
        {@const iconMeta = getPlatformIcon(platform)}
        {@const PlatformIcon = iconMeta.icon}
        {@const isActive = selectedPlatforms.includes(platform)}
        <button
            type="button"
            onclick={() => onPlatformClick?.(platform)}
            title={iconMeta.title}
            aria-label={iconMeta.title}
            aria-pressed={isActive}
            class="group/glyph inline-flex size-6 shrink-0 items-center justify-center"
        >
            <span
                class={clsx(
                    'inline-flex size-4.5 items-center justify-center rounded-sm transition-colors',
                    isActive ? 'bg-fg text-surface dark:bg-white/15 dark:text-fg' : 'text-fg-muted group-hover/glyph:text-fg',
                )}
            >
                <PlatformIcon class="size-3.5" />
            </span>
        </button>
    {/each}
</div>
