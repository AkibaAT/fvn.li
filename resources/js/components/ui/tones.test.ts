import { describe, expect, test } from 'vitest';
import { formatListType, listTypeDotClass, listTypeIcon, listTypeLabel, listTypeTone } from './tones';
import ClipboardIcon from '@/components/icons/Clipboard.svelte';

describe('list type tones', () => {
    test('falls back to the custom list presentation for unknown types', () => {
        expect(listTypeTone('unknown')).toBe('neutral');
        expect(listTypeLabel('unknown')).toBe('Custom');
        expect(listTypeIcon(undefined)).toBe(ClipboardIcon);
        expect(listTypeDotClass(undefined)).toBe('bg-border-strong');
    });

    test('uses the accent for reading lists', () => {
        expect(listTypeDotClass('reading')).toBe('bg-accent');
    });

    test('formats custom list type names', () => {
        expect(formatListType('plan_to_read')).toBe('Plan To Read');
    });
});
