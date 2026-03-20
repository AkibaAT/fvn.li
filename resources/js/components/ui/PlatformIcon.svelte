<script lang="ts">
    import SteamIcon from '@/components/icons/Steam.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';

    interface Props {
        platform: string;
        class?: string;
        showTooltip?: boolean;
    }

    let { platform, class: className = '', showTooltip = true }: Props = $props();

    interface PlatformInfo {
        name: string;
        component: typeof SteamIcon | typeof ItchioIcon;
    }

    function getPlatformInfo(): PlatformInfo {
        switch (platform) {
            case 'steam':
                return {
                    name: 'Steam',
                    component: SteamIcon,
                };
            case 'itch_io':
            default:
                return {
                    name: 'itch.io',
                    component: ItchioIcon,
                };
        }
    }

    let platformInfo = $derived(getPlatformInfo());
</script>

<span
    class={`inline-flex items-center ${className}`}
    title={showTooltip ? `From ${platformInfo.name}` : undefined}
>
    {#if platform === 'steam'}
        <SteamIcon class={`h-4 w-4 ${className}`} />
    {:else}
        <ItchioIcon class={`h-4 w-4 ${className}`} />
    {/if}
</span>
