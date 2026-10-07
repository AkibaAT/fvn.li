<script lang="ts">
    import clsx from 'clsx';
    import { cn } from '@/utils/cn';

    interface Language {
        iso_code: string;
        ref_name: string;
        flag_code: string;
    }

    type Variant = 'card' | 'row' | 'full';

    interface Props {
        languages: Language[];
        selectedLanguages?: string[];
        onLanguageClick?: (iso: string) => void;
        variant?: Variant;
        class?: string;
    }

    let { languages, selectedLanguages = [], onLanguageClick, variant = 'card', class: className = '' }: Props = $props();

    const SLOTS: Record<Exclude<Variant, 'full'>, string[]> = {
        card: ['inline-flex', 'inline-flex', 'inline-flex', 'inline-flex', 'inline-flex'],
        row: ['inline-flex', 'inline-flex', 'inline-flex md:hidden lg:inline-flex', 'hidden lg:inline-flex'],
    };

    const OVERFLOW: Record<Exclude<Variant, 'full'>, Array<{ shown: number; class: string }>> = {
        card: [{ shown: 5, class: '' }],
        row: [
            { shown: 3, class: 'md:hidden' },
            { shown: 2, class: 'hidden md:inline lg:hidden' },
            { shown: 4, class: 'hidden lg:inline' },
        ],
    };

    const glyphs = $derived.by(() => {
        const merged: Array<{ key: string; flagCode: string; isoCodes: string[]; names: string[] }> = [];
        for (const language of languages) {
            const key = language.flag_code || language.iso_code;
            const glyph = merged.find((candidate) => candidate.key === key);
            if (glyph) {
                glyph.isoCodes.push(language.iso_code);
                glyph.names.push(language.ref_name);
            } else {
                merged.push({ key, flagCode: language.flag_code, isoCodes: [language.iso_code], names: [language.ref_name] });
            }
        }
        return merged;
    });

    const slots = $derived(variant === 'full' ? glyphs.map(() => 'inline-flex') : SLOTS[variant]);
    const overflowCounts = $derived(
        variant === 'full'
            ? []
            : OVERFLOW[variant].map((band) => ({ ...band, hidden: glyphs.length - band.shown })).filter((band) => band.hidden > 0),
    );
</script>

<div class={cn('flex shrink-0 items-center', className)} data-language-glyphs>
    {#each glyphs.slice(0, slots.length) as glyph, index (glyph.key)}
        {@const isActive = glyph.isoCodes.some((iso) => selectedLanguages.includes(iso))}
        {@const label = glyph.names.join(', ')}
        <button
            type="button"
            onclick={() => onLanguageClick?.(glyph.isoCodes[0])}
            title={label}
            aria-label={label}
            aria-pressed={isActive}
            class={clsx('group/glyph size-6 shrink-0 items-center justify-center', slots[index])}
        >
            <span
                class={clsx(
                    'block h-3.5 w-4.5 overflow-hidden rounded-sm transition-colors',
                    isActive ? 'bg-fg dark:bg-white/15' : 'bg-surface-alt group-hover/glyph:bg-border',
                )}
            >
                <span class="fi fi-{glyph.flagCode} block! size-full!"></span>
            </span>
        </button>
    {/each}

    {#each overflowCounts as band (band.shown)}
        <span class="shrink-0 text-2xs leading-none text-fg-faint {band.class}" aria-hidden="true">+{band.hidden}</span>
    {/each}
</div>
