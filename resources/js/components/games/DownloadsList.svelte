<script lang="ts">
    import ArrowLongRightIcon from '@/components/icons/ArrowLongRight.svelte';
    import ExternalLinkIcon from '@/components/icons/ExternalLink.svelte';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { Card } from '@/components/ui';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';

    export interface AdditionalLink {
        id: number | string;
        url: string;
        name: string;
        platform?: string | null;
        last_edited_at?: string | null;
    }

    interface Props {
        gameId: number;
        links: AdditionalLink[];
    }

    let { gameId, links }: Props = $props();
    const { getPlatformIcon } = usePlatformIcons();

    const platformIcon = (platform?: string | null) => {
        if (platform && ['windows', 'linux', 'mac', 'android', 'web'].includes(platform)) {
            return getPlatformIcon(platform as GameCardPlatform);
        }
        return { icon: ExternalLinkIcon, color: 'text-fg-muted', title: 'External link' };
    };
</script>

{#if links && links.length > 0}
    <Card id="downloads" variant="flat" class="mb-6 scroll-mt-28">
        <h2 class="mb-6 text-xl font-semibold text-fg">Downloads</h2>
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
            {#each links as link (link.id)}
                {@const iconMeta = platformIcon(link.platform)}
                {@const Icon = iconMeta.icon}
                <a
                    href={route('track.custom-link', { game_id: gameId, link_id: link.id, url: link.url })}
                    target="_blank"
                    rel="noopener noreferrer"
                    class="group flex items-center gap-4 rounded-lg border border-border p-4 transition-colors hover:border-border-strong hover:bg-surface-alt"
                >
                    <div class="flex-shrink-0">
                        <div class="rounded-md bg-surface-alt p-2 text-xl">
                            <Icon class="h-5 w-5 {iconMeta.color}" />
                        </div>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="mb-1 font-semibold text-fg">
                            {link.name}
                        </div>
                        <div class="flex items-center gap-2 text-sm text-fg-muted">
                            {#if link.platform}
                                <span class="font-medium capitalize">{link.platform}</span>
                            {/if}
                            {#if link.last_edited_at}
                                {#if link.platform}<span>&bull;</span>{/if}
                                <span>Updated {formatLocalDate(link.last_edited_at)}</span>
                            {/if}
                        </div>
                    </div>
                    <div class="flex-shrink-0">
                        <ArrowLongRightIcon class="h-5 w-5 text-fg-faint transition-colors group-hover:text-fg" />
                    </div>
                </a>
            {/each}
        </div>
    </Card>
{/if}
