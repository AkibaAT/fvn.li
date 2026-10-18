import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { beforeEach, expect, test, vi } from 'vitest';

const api = vi.hoisted(() => ({ fetchUserLists: vi.fn(), fetchGameListMemberships: vi.fn(), toggleUserProgressUpdates: vi.fn() }));
vi.mock('@/api/lists', () => api);
const tagApi = vi.hoisted(() => ({ listPrivateTags: vi.fn(), setGamePrivateTag: vi.fn(), createPrivateTag: vi.fn() }));
vi.mock('@/api/private-tags', () => tagApi);
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

test('shows the assigned private tags and updates them when the dialog changes them', async () => {
    tagApi.listPrivateTags.mockResolvedValue([
        { id: 1, name: 'Favorites', games_count: 2 },
        { id: 2, name: 'Re-read', games_count: 1 },
    ]);
    tagApi.setGamePrivateTag.mockResolvedValue(undefined);
    render(GameCardUserSection, { gameId: 42, gameName: 'Fixture', privateTags: [{ id: 1, name: 'Favorites' }] });
    const chips = screen.getByRole('list', { name: 'Private tags' });
    expect(chips).toHaveTextContent('Favorites');

    await fireEvent.click(screen.getByRole('button', { name: 'Edit private tags' }));
    await fireEvent.click(await screen.findByRole('checkbox', { name: 'Re-read' }));
    await waitFor(() => expect(tagApi.setGamePrivateTag).toHaveBeenCalledWith(42, 2, true));
    await waitFor(() => expect(screen.getAllByRole('list', { name: 'Private tags' })[0]).toHaveTextContent('Re-read'));
});

test('offers the private tag dialog without chips when nothing is assigned', () => {
    render(GameCardUserSection, { gameId: 42, gameName: 'Fixture' });
    expect(screen.getByRole('button', { name: 'Private tags' })).toBeVisible();
    expect(screen.queryByRole('list', { name: 'Private tags' })).not.toBeInTheDocument();
});
