import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { beforeEach, expect, test, vi } from 'vitest';

const api = vi.hoisted(() => ({ fetchUserLists: vi.fn(), fetchGameListMemberships: vi.fn(), toggleUserProgressUpdates: vi.fn() }));
vi.mock('@/api/lists', () => api);
vi.mock('@inertiajs/svelte', () => ({
    page: { props: { auth: { user: { id: 1 } } } },
    router: {
        reload: ({ onSuccess, onFinish }: { onSuccess: () => void; onFinish: () => void }) => {
            onSuccess();
            onFinish();
        },
    },
}));
import GameCardUserSection from './GameCardUserSection.svelte';
import Toast from './Toast.svelte';

beforeEach(() => {
    vi.resetAllMocks();
    vi.stubGlobal('route', () => '/lists/7');
    HTMLDialogElement.prototype.showModal = function () {
        this.open = true;
    };
    HTMLDialogElement.prototype.close = function () {
        this.open = false;
    };
});

test('shows memberships before opening the dialog and accepts lists without owner objects', async () => {
    api.fetchUserLists.mockResolvedValue([{ id: 7, name: 'Favorites', type: 'custom' }]);
    api.fetchGameListMemberships.mockResolvedValue([7]);
    render(GameCardUserSection, {
        gameId: 42,
        gameName: 'Fixture',
        listMemberships: [{ list_id: 7, name: 'Favorites', type: 'custom', is_default: false }],
    });
    expect(screen.getByRole('button', { name: 'Manage in Lists' })).toBeVisible();
    await fireEvent.click(screen.getByRole('button', { name: 'My Lists 1' }));
    expect(screen.getByRole('link', { name: 'Favorites' })).toBeVisible();
    await fireEvent.click(screen.getByRole('button', { name: 'Manage in Lists' }));
    await waitFor(() => expect(api.fetchUserLists).toHaveBeenCalled());
    expect(await screen.findByRole('button', { name: 'Favorites Remove' })).toBeVisible();
    expect(screen.getByRole('button', { name: 'My Lists 1' })).toBeVisible();
});

test('notification failures are visible and keep the previous subscription state', async () => {
    api.toggleUserProgressUpdates.mockRejectedValue(new Error('Subscription could not be saved'));
    render(Toast);
    render(GameCardUserSection, { gameId: 42, gameName: 'Fixture' });
    await fireEvent.click(screen.getByRole('checkbox', { name: 'Notifications off' }));
    expect(await screen.findByRole('alert', { name: 'error notification: Subscription could not be saved' })).toBeVisible();
    expect(screen.getByRole('checkbox', { name: 'Notifications off' })).not.toBeChecked();
});
