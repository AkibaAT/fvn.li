<script lang="ts">
    import type { GameCardPlatform, PlatformIconMeta } from '@/hooks/usePlatformIcons';
    import { Button } from '@/components/ui';

    let {
        platform,
        isActive = false,
        iconMeta,
        onclick,
    }: {
        platform: GameCardPlatform;
        isActive?: boolean;
        iconMeta: PlatformIconMeta;
        onclick: (platform: GameCardPlatform) => void;
    } = $props();
</script>

<Button
    onclick={() => onclick(platform)}
    variant={isActive ? 'solid' : 'outline'}
    tone="primary"
    size="xs"
    class="inline-flex cursor-pointer items-center rounded border px-2 py-1 text-xs transition-colors {isActive
        ? 'border-blue-700 dark:border-blue-500'
        : 'border-gray-200 text-gray-700 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200'}"
    title={iconMeta.title}
    aria-label={iconMeta.title}
    aria-pressed={isActive}
>
    <i class="{iconMeta.icon} {iconMeta.color}" aria-hidden="true"></i>
</Button>
