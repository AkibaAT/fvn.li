<script lang="ts">
    import { cn } from '@/utils/cn';
    import LanguageGlyphs from '@/components/games/glyphs/LanguageGlyphs.svelte';
    import PlatformGlyphs from '@/components/games/glyphs/PlatformGlyphs.svelte';
    import StoreGlyph from '@/components/games/glyphs/StoreGlyph.svelte';
    import type { GameCardPlatform } from '@/hooks/usePlatformIcons';
    import type { StorePlatform } from '@/hooks/useStorePlatformIcons';

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
        languageLimit?: 'row' | 'full';
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
        languageLimit = 'row',
        class: className = '',
    }: Props = $props();
</script>

<div class={cn('-my-0.75 -ml-0.75 flex flex-nowrap items-center gap-x-1.5 overflow-hidden', className)} data-glyph-strip>
    {#if storePlatform || platforms.length > 0}
        <div class="flex shrink-0 items-center">
            {#if storePlatform}
                <StoreGlyph {storePlatform} active={isStoreActive} onclick={onStoreClick} />
            {/if}
            {#if storePlatform && platforms.length > 0}
                <span class="mx-1.75 h-3 w-px shrink-0 bg-border" aria-hidden="true"></span>
            {/if}
            {#if platforms.length > 0}
                <PlatformGlyphs {platforms} {selectedPlatforms} {onPlatformClick} />
            {/if}
        </div>
    {/if}

    {#if languages.length > 0}
        <LanguageGlyphs {languages} {selectedLanguages} {onLanguageClick} variant={languageLimit} />
    {/if}
</div>
