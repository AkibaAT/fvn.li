import { expect, test, vi } from 'vitest';

const router = vi.hoisted(() => ({ reload: vi.fn() }));
vi.mock('@inertiajs/svelte', () => ({ router }));
import { refreshPage } from './refreshPage';

test.each(['success', 'cancel', 'failure'])('refresh completion distinguishes %s from a successful save', async (outcome) => {
    router.reload.mockImplementationOnce(({ onSuccess, onCancel, onFinish }) => {
        if (outcome === 'success') onSuccess();
        if (outcome === 'cancel') onCancel();
        onFinish();
    });
    const refreshing = refreshPage(['game']);
    if (outcome === 'failure') await expect(refreshing).rejects.toThrow('The page could not be refreshed');
    else await expect(refreshing).resolves.toBe(outcome === 'success');
});
