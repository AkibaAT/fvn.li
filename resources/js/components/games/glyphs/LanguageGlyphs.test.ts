import { fireEvent, render, screen } from '@testing-library/svelte';
import { describe, expect, test, vi } from 'vitest';

import LanguageGlyphs from './LanguageGlyphs.svelte';

const languages = [
    { iso_code: 'eng', ref_name: 'English', flag_code: 'gb' },
    { iso_code: 'deu', ref_name: 'German', flag_code: 'de' },
    { iso_code: 'spa', ref_name: 'Spanish', flag_code: 'es' },
    { iso_code: 'fra', ref_name: 'French', flag_code: 'fr' },
    { iso_code: 'jpn', ref_name: 'Japanese', flag_code: 'jp' },
    { iso_code: 'por', ref_name: 'Portuguese', flag_code: 'pt' },
];

describe('LanguageGlyphs', () => {
    test('card variant shows five flags and folds the rest into a single +N', () => {
        render(LanguageGlyphs, { props: { languages } });

        expect(screen.getByRole('button', { name: 'Japanese' })).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Portuguese' })).toBeNull();
        expect(screen.getAllByText(/^\+\d/).map((count) => count.textContent)).toEqual(['+1']);
    });

    test('full variant shows every language without a count', () => {
        render(LanguageGlyphs, { props: { languages, variant: 'full' } });

        expect(screen.getAllByRole('button')).toHaveLength(6);
        expect(screen.queryByText(/^\+\d/)).toBeNull();
    });

    test('languages sharing a flag render as one glyph', async () => {
        const onLanguageClick = vi.fn();

        render(LanguageGlyphs, {
            props: {
                languages: [
                    { iso_code: 'zho-Hans', ref_name: 'Chinese (Simplified)', flag_code: 'cn' },
                    { iso_code: 'eng', ref_name: 'English', flag_code: 'gb' },
                    { iso_code: 'zho-Hant', ref_name: 'Chinese (Traditional)', flag_code: 'cn' },
                ],
                selectedLanguages: ['zho-Hant'],
                onLanguageClick,
            },
        });

        const chinese = screen.getByRole('button', { name: 'Chinese (Simplified), Chinese (Traditional)' });
        expect(screen.getAllByRole('button')).toHaveLength(2);
        expect(chinese.getAttribute('aria-pressed')).toBe('true');

        await fireEvent.click(chinese);
        expect(onLanguageClick).toHaveBeenCalledWith('zho-Hans');
    });
});
