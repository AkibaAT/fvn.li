import '@testing-library/jest-dom/vitest';
import { fireEvent, render, screen, waitFor } from '@testing-library/svelte';
import { tick } from 'svelte';
import { beforeEach, expect, test, vi } from 'vitest';

const api = vi.hoisted(() => ({
    fetchDialogueOptions: vi.fn(),
    fetchDialogueVersionStats: vi.fn(),
    fetchDialogueSearch: vi.fn(),
    fetchDialogueDuplicates: vi.fn(),
    fetchWordFrequency: vi.fn(),
}));
vi.mock('@/api', () => api);
vi.mock('@inertiajs/svelte', async (importOriginal) => ({
    ...(await importOriginal<typeof import('@inertiajs/svelte')>()),
    page: { props: {} },
    router: {
        push: (options: { url: string }) => window.history.pushState({ page: { component: 'dialogue/browser', url: options.url } }, '', options.url),
        replace: (options: { url: string }) =>
            window.history.replaceState({ page: { component: 'dialogue/browser', url: options.url } }, '', options.url),
    },
}));
import Browser from './browser.svelte';

const options = {
    versions: [
        { id: 1, version: '1.0' },
        { id: 2, version: '2.0' },
    ],
    languages: [
        { id: 'eng', name: 'English' },
        { id: 'deu', name: 'German' },
    ],
    characters: [],
    contexts: ['Opening'],
};
function deferred() {
    let resolve!: (value: any) => void;
    const promise = new Promise<any>((done) => {
        resolve = done;
    });
    return { promise, resolve };
}
function results(text: string) {
    return {
        results: [{ id: 1, text_content: text, highlighted_text: text }],
        pagination: { current_page: 1, per_page: 25, total: 1, last_page: 1 },
    };
}

beforeEach(() => {
    vi.resetAllMocks();
    vi.stubGlobal('route', () => '/games/fixture');
    window.history.replaceState({ page: { component: 'dialogue/browser' } }, '', '/games/fixture/dialogue?versionId=1&q=hello&page=3');
    api.fetchDialogueOptions.mockResolvedValue(options);
    api.fetchDialogueVersionStats.mockResolvedValue({ totalLines: 0 });
    api.fetchDialogueSearch.mockResolvedValue(results('Current dialogue'));
    api.fetchDialogueDuplicates.mockResolvedValue([]);
    api.fetchWordFrequency.mockResolvedValue([]);
});

test('changing language resets pagination, survives reload, and ignores old responses', async () => {
    const oldSearch = deferred();
    const oldOptions = deferred();
    const oldWords = deferred();
    api.fetchDialogueSearch.mockReturnValueOnce(oldSearch.promise);
    api.fetchWordFrequency.mockReturnValueOnce(oldWords.promise);
    const view = render(Browser, { initial: { gameId: 1, gameName: 'Fixture', gameSlug: 'fixture', versionId: 1 } });
    await waitFor(() => expect(screen.getByRole('option', { name: 'German' })).toBeInTheDocument());
    expect(api.fetchDialogueSearch).toHaveBeenCalledWith(expect.objectContaining({ page: 3, language: 'eng' }));

    api.fetchDialogueOptions.mockReturnValueOnce(oldOptions.promise);
    await fireEvent.change(screen.getByLabelText('Language'), { target: { value: 'deu' } });
    await fireEvent.change(screen.getByLabelText('Language'), { target: { value: 'eng' } });
    await fireEvent.change(screen.getByLabelText('Language'), { target: { value: 'deu' } });
    await waitFor(() => expect(screen.getByText('Current dialogue')).toBeVisible());
    expect(api.fetchDialogueSearch).toHaveBeenLastCalledWith(expect.objectContaining({ page: 1, language: 'deu' }));
    expect(new URL(window.location.href).searchParams.get('selectedLangs')).toBe('deu');
    expect(new URL(window.location.href).searchParams.has('page')).toBe(false);
    expect(window.history.state.page.component).toBe('dialogue/browser');
    expect(window.history.state.page.url).toBe(window.location.pathname + window.location.search);

    oldSearch.resolve(results('Stale dialogue'));
    oldOptions.resolve({ ...options, contexts: ['Stale context'] });
    oldWords.resolve([{ text: 'Stale word', value: 1 }]);
    await tick();
    expect(screen.queryByText('Stale dialogue')).toBeNull();
    expect(screen.queryByText('Stale context')).toBeNull();
    expect(screen.queryByText('Stale word')).toBeNull();
    expect(screen.getByText('Current dialogue')).toBeVisible();

    view.unmount();
    render(Browser, { initial: { gameId: 1, gameName: 'Fixture', gameSlug: 'fixture', versionId: 1 } });
    await waitFor(() => expect(screen.getByLabelText('Language')).toHaveValue('deu'));
});

test('older version statistics and duplicate results cannot replace the selected version', async () => {
    const oldStats = deferred();
    const oldDuplicates = deferred();
    api.fetchDialogueVersionStats.mockReturnValueOnce(oldStats.promise);
    api.fetchDialogueDuplicates.mockReturnValueOnce(oldDuplicates.promise);
    render(Browser, { initial: { gameId: 1, gameName: 'Fixture', gameSlug: 'fixture', versionId: 1 } });
    await waitFor(() => expect(screen.getByRole('option', { name: '2.0' })).toBeInTheDocument());
    await fireEvent.click(screen.getByRole('button', { name: 'Show Duplicates' }));
    await fireEvent.change(screen.getByLabelText('Version'), { target: { value: '2' } });
    await waitFor(() => expect(screen.getByText(/No duplicate lines found/)).toBeVisible());
    oldStats.resolve({ totalLines: 987654 });
    oldDuplicates.resolve([{ text_id: 1, text_content: 'Stale duplicate', usage_count: 10 }]);
    await tick();
    expect(screen.queryByText('987,654')).toBeNull();
    expect(screen.queryByText('Stale duplicate')).toBeNull();
    expect(screen.getByLabelText('Version')).toHaveValue('2');
});
