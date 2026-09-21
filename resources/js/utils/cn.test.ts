import { describe, expect, it } from 'vitest';
import { cn } from './cn';

describe('cn', () => {
    it('keeps a custom font size next to a text color', () => {
        expect(cn('text-ui', 'text-fg')).toBe('text-ui text-fg');
    });

    it('lets a later custom font size override an earlier one', () => {
        expect(cn('text-sm text-fg', 'text-md')).toBe('text-fg text-md');
    });
});
