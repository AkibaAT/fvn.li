import { describe, expect, test, vi } from 'vitest';
import { isDialogBackdropClick } from './dialog';

describe('dialog utilities', () => {
    test('detects clicks outside the dialog bounding box on the dialog backdrop', () => {
        const dialog = document.createElement('dialog');
        vi.spyOn(dialog, 'getBoundingClientRect').mockReturnValue({
            left: 10,
            top: 10,
            right: 110,
            bottom: 110,
            width: 100,
            height: 100,
            x: 10,
            y: 10,
            toJSON: () => ({}),
        });

        const backdropEvent = new MouseEvent('click', { clientX: 5, clientY: 50 });
        const insideEvent = new MouseEvent('click', { clientX: 50, clientY: 50 });
        Object.defineProperty(backdropEvent, 'target', { value: dialog });
        Object.defineProperty(insideEvent, 'target', { value: dialog });

        expect(isDialogBackdropClick(dialog, backdropEvent)).toBe(true);
        expect(isDialogBackdropClick(dialog, insideEvent)).toBe(false);
        expect(isDialogBackdropClick(null, new MouseEvent('click'))).toBe(false);
    });
});
