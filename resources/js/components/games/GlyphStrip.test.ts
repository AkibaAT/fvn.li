import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import GlyphStrip from './GlyphStrip.svelte';

const languages = [
    { iso_code: 'eng', ref_name: 'English', flag_code: 'gb' },
    { iso_code: 'deu', ref_name: 'German', flag_code: 'de' },
    { iso_code: 'spa', ref_name: 'Spanish', flag_code: 'es' },
    { iso_code: 'fra', ref_name: 'French', flag_code: 'fr' },
    { iso_code: 'jpn', ref_name: 'Japanese', flag_code: 'jp' },
    { iso_code: 'por', ref_name: 'Portuguese', flag_code: 'pt' },
];

describe('GlyphStrip', () => {
    test('renders store, platform, and language glyphs and forwards clicks', async () => {
        const onStoreClick = vi.fn();
        const onPlatformClick = vi.fn();
        const onLanguageClick = vi.fn();

        render(GlyphStrip, {
            props: {
                storePlatform: 'steam',
                onStoreClick,
                platforms: ['windows', 'linux'],
                onPlatformClick,
                languages: languages.slice(0, 2),
                onLanguageClick,
            },
        });

        await fireEvent.click(screen.getByRole('button', { name: 'Steam store' }));
        await fireEvent.click(screen.getByRole('button', { name: 'Windows' }));
        await fireEvent.click(screen.getByRole('button', { name: 'German' }));

        expect(onStoreClick).toHaveBeenCalledWith('steam');
        expect(onPlatformClick).toHaveBeenCalledWith('windows');
        expect(onLanguageClick).toHaveBeenCalledWith('deu');
    });

    test('shows the selected state as an inverted glyph box', () => {
        render(GlyphStrip, {
            props: {
                storePlatform: 'itch_io',
                isStoreActive: true,
                platforms: ['mac'],
                selectedPlatforms: ['mac'],
                languages: [languages[0]],
                selectedLanguages: ['eng'],
            },
        });

        const store = screen.getByRole('button', { name: 'itch.io store' });
        expect(store.classList.contains('bg-fg')).toBe(true);
        expect(store.classList.contains('text-surface')).toBe(true);

        const platform = screen.getByRole('button', { name: 'Mac' });
        expect(platform.classList.contains('bg-fg')).toBe(true);

        const language = screen.getByRole('button', { name: 'English' });
        expect(language.classList.contains('bg-fg')).toBe(true);

        expect(store.getAttribute('aria-pressed')).toBe('true');
    });

    test('card variant renders every language', () => {
        render(GlyphStrip, { props: { languages, variant: 'card' } });

        for (const language of languages) {
            expect(screen.getByRole('button', { name: language.ref_name })).toBeInTheDocument();
        }
    });

    test('row variant caps the languages and exposes responsive +N counts', () => {
        render(GlyphStrip, { props: { languages, variant: 'row' } });

        // Only the first four glyphs are mounted; the rest collapse into "+N".
        expect(screen.getByRole('button', { name: 'French' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Japanese' })).toBeNull();
        expect(screen.queryByRole('button', { name: 'Portuguese' })).toBeNull();

        const text = screen.getByText(/\+2/).textContent ?? '';
        expect(text).toContain('+2');
        expect(screen.getByText('+3')).toBeInTheDocument();
        expect(screen.getByText('+4')).toBeInTheDocument();
    });
});
