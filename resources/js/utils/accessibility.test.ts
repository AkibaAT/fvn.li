import { describe, expect, test, vi } from 'vitest';
import {
    announceError,
    announceLoading,
    announceToScreenReader,
    createProgressBar,
    generateId,
    isVisible,
    setBusy,
    trapFocus,
} from './accessibility';

describe('accessibility utilities', () => {
    test('announces messages to screen readers and removes them later', () => {
        vi.useFakeTimers();

        announceToScreenReader('Saved', 'assertive');

        const announcement = document.body.querySelector('[aria-live="assertive"]');
        expect(announcement?.textContent).toBe('Saved');

        vi.advanceTimersByTime(1000);
        expect(document.body.querySelector('[aria-live="assertive"]')).toBeNull();
    });

    test('traps focus between first and last focusable controls', () => {
        const container = document.createElement('div');
        container.innerHTML = '<button>First</button><a href="/">Last</a>';
        document.body.appendChild(container);

        const cleanup = trapFocus(container);
        const [first, last] = Array.from(container.querySelectorAll<HTMLElement>('button, a'));

        expect(document.activeElement).toBe(first);

        first.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', shiftKey: true, bubbles: true }));
        expect(document.activeElement).toBe(last);

        last.dispatchEvent(new KeyboardEvent('keydown', { key: 'Tab', bubbles: true }));
        expect(document.activeElement).toBe(first);

        cleanup();
    });

    test('sets ARIA state and checks visibility', () => {
        const element = document.createElement('button');
        document.body.appendChild(element);

        setBusy(element, false);

        expect(element.getAttribute('aria-busy')).toBe('false');
        expect(isVisible(element)).toBe(false);
    });

    test('creates and updates progress bars', () => {
        const container = document.createElement('div');
        document.body.appendChild(container);

        const progress = createProgressBar(container, 10, 90);
        progress.update(120, 'Almost done');

        const progressBar = container.querySelector('[role="progressbar"]');
        expect(progressBar?.getAttribute('aria-valuenow')).toBe('90');
        expect(progressBar?.textContent).toContain('Almost done');

        progress.complete();
        expect(progressBar?.getAttribute('aria-valuenow')).toBe('90');
        progress.remove();
        expect(container.querySelector('[role="progressbar"]')).toBeNull();
    });

    test('exports active announcement and ID helpers', () => {
        expect(generateId('field')).toMatch(/^field-/);

        announceLoading();
        announceError();
        expect(document.body.querySelectorAll('[aria-live]')).toHaveLength(2);
    });
});
