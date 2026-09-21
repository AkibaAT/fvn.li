<script lang="ts">
    import GlobeIcon from '@/components/icons/Globe.svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { usePlatformIcons, type GameCardPlatform } from '@/hooks/usePlatformIcons';
    import { useStorePlatformIcons, type StorePlatform } from '@/hooks/useStorePlatformIcons';
    import clsx from 'clsx';
    import { twMerge } from 'tailwind-merge';

    interface Language {
        iso_code: string;
        ref_name: string;
        flag_code: string;
    }

    interface Props {
        storePlatform?: StorePlatform | null;
        isStoreActive?: boolean;
        onStoreClick?: (platform: StorePlatform) => void;
        platforms?: GameCardPlatform[];
        selectedPlatforms?: string[];
        onPlatformClick?: (platform: GameCardPlatform) => void;
        languages?: Language[];
        selectedLanguages?: string[];
        onLanguageClick?: (iso: string) => void;
        variant?: 'card' | 'row';
        class?: string;
    }

    let {
        storePlatform = null,
        isStoreActive = false,
        onStoreClick,
        platforms = [],
        selectedPlatforms = [],
        onPlatformClick,
        languages = [],
        selectedLanguages = [],
        onLanguageClick,
        variant = 'card',
        class: className = '',
    }: Props = $props();

    const { getPlatformIcon } = usePlatformIcons();
    const { getStorePlatformIcon } = useStorePlatformIcons();

    const hasStore = $derived(Boolean(storePlatform));
    const storeTitle = $derived(storePlatform ? getStorePlatformIcon(storePlatform).title : '');
    const showDividerAfterStore = $derived(hasStore && (platforms.length > 0 || languages.length > 0));
    const showDividerAfterPlatforms = $derived(platforms.length > 0 && languages.length > 0);
</script>

<div
    class={twMerge(clsx('flex flex-wrap items-center gap-1.5 md:flex-nowrap md:overflow-hidden', variant === 'card' && 'md:flex-wrap', className))}
    data-glyph-strip={variant}
>
    {#if storePlatform}
        <button
            type="button"
            onclick={() => onStoreClick?.(storePlatform)}
            title={storeTitle}
            aria-label="{storeTitle} store"
            aria-pressed={isStoreActive}
            class={clsx(
                'inline-flex h-[18px] w-[18px] shrink-0 items-center justify-center rounded-[3px] transition-colors',
                isStoreActive ? 'bg-fg text-surface' : storePlatform === 'itch_io' ? 'text-itchio hover:text-fg' : 'text-fg-muted hover:text-fg',
            )}
        >
            {#if storePlatform === 'itch_io'}
                <Itchio class="h-[13px] w-[13px]" monochrome />
            {:else if storePlatform === 'steam'}
                <Steam class="h-[13px] w-[13px]" monochrome />
            {:else}
                <GlobeIcon class="h-[13px] w-[13px]" />
            {/if}
        </button>
    {/if}

    {#if showDividerAfterStore}
        <span class="mx-1 h-3 w-px shrink-0 bg-border" aria-hidden="true"></span>
    {/if}

    {#each platforms as platform (platform)}
        {@const iconMeta = getPlatformIcon(platform)}
        {@const PlatformIcon = iconMeta.icon}
        {@const isPlatformActive = selectedPlatforms.includes(platform)}
        <button
            type="button"
            onclick={() => onPlatformClick?.(platform)}
            title={iconMeta.title}
            aria-label={iconMeta.title}
            aria-pressed={isPlatformActive}
            class={clsx(
                'inline-flex h-[18px] min-w-[18px] shrink-0 items-center justify-center rounded-[3px] px-0.5 transition-colors',
                isPlatformActive ? 'bg-fg text-surface' : 'text-fg-muted hover:text-fg',
            )}
        >
            <PlatformIcon class="h-[14px] w-[14px]" />
        </button>
    {/each}

    {#if showDividerAfterPlatforms}
        <span class="mx-1 h-3 w-px shrink-0 bg-border" aria-hidden="true"></span>
    {/if}

    {#each languages as language, index (language.iso_code)}
        {#if variant === 'card' || index < 4}
            {@const isLanguageActive = selectedLanguages.includes(language.iso_code)}
            <button
                type="button"
                onclick={() => onLanguageClick?.(language.iso_code)}
                title={language.ref_name}
                aria-label={language.ref_name}
                aria-pressed={isLanguageActive}
                class={clsx(
                    'h-[15px] w-[20px] shrink-0 items-center justify-center overflow-hidden rounded-[3px] transition-colors',
                    variant === 'row' && (index < 2 ? 'inline-flex' : index === 2 ? 'inline-flex md:hidden lg:inline-flex' : 'hidden lg:inline-flex'),
                    isLanguageActive ? 'bg-fg' : 'bg-surface-alt hover:bg-border',
                )}
            >
                <span class="fi fi-{language.flag_code} text-[14px] leading-none"></span>
            </button>
        {/if}
    {/each}

    {#if variant === 'row' && languages.length > 3}
        <span class="text-[11px] leading-none text-fg-faint md:hidden" aria-hidden="true">+{languages.length - 3}</span>
    {/if}
    {#if variant === 'row' && languages.length > 2}
        <span class="hidden text-[11px] leading-none text-fg-faint md:inline lg:hidden" aria-hidden="true">+{languages.length - 2}</span>
    {/if}
    {#if variant === 'row' && languages.length > 4}
        <span class="hidden text-[11px] leading-none text-fg-faint lg:inline" aria-hidden="true">+{languages.length - 4}</span>
    {/if}
</div>
