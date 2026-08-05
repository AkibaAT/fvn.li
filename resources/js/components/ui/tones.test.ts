import { describe, expect, test } from 'vitest';
import { formatListType, listTypeBorderClass, listTypeDotClass, listTypeIcon, listTypeLabel, listTypeTone } from './tones';
import ClipboardIcon from '@/components/icons/Clipboard.svelte';
import XCircleIcon from '@/components/icons/XCircle.svelte';

describe('list type tones', () => {
    test('returns consistent presentation metadata for known list types', () => {
        expect(listTypeTone('completed')).toBe('success');
        expect(listTypeLabel('plan_to_read')).toBe('Plan to Read');
        expect(listTypeIcon('dropped')).toBe(XCircleIcon);
        expect(listTypeBorderClass('reading')).toBe('border-blue-500');
        expect(listTypeDotClass('on_hold')).toBe('bg-orange-500');
    });

    test('falls back to the custom list presentation for unknown types', () => {
        expect(listTypeTone('unknown')).toBe('neutral');
        expect(listTypeLabel('unknown')).toBe('Custom');
        expect(listTypeIcon(undefined)).toBe(ClipboardIcon);
        expect(listTypeBorderClass('unknown')).toBe('border-gray-500');
        expect(listTypeDotClass(undefined)).toBe('bg-gray-500');
    });

    test('formats custom list type names', () => {
        expect(formatListType('plan_to_read')).toBe('Plan To Read');
    });
});
