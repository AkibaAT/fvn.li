import { get } from 'svelte/store';
import { beforeEach, describe, expect, test, vi } from 'vitest';

describe('toast store', () => {
    beforeEach(() => {
        vi.resetModules();
        vi.useFakeTimers();
    });

    test('adds typed toasts and dismisses them manually', async () => {
        const { toastStore } = await import('./toast');

        toastStore.success('Saved');
        toastStore.error('Failed');

        expect(get(toastStore)).toMatchObject([
            { message: 'Saved', type: 'success' },
            { message: 'Failed', type: 'error' },
        ]);

        toastStore.dismiss(get(toastStore)[0].id);
        expect(get(toastStore)).toHaveLength(1);
        expect(get(toastStore)[0].type).toBe('error');
    });

    test('auto-dismisses toasts after five seconds', async () => {
        const { toast } = await import('./toast');
        const { toastStore } = await import('./toast');

        toast.info('Working');
        expect(get(toastStore)).toHaveLength(1);

        vi.advanceTimersByTime(5000);
        expect(get(toastStore)).toEqual([]);
    });
});
