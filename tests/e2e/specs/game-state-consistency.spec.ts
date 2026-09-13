import { test, expect, type Page, type Locator, type Route, type TestInfo } from '@playwright/test';
import type { GameViewFixture } from '../support/laravel';
import { execFileSync } from 'node:child_process';
let f: GameViewFixture & { gameId: number; discordServerId: number; overrideId: number };
const errors = new WeakMap<Page, string[]>();
test.beforeEach(async ({ page, baseURL }) => {
    const e: string[] = [];
    errors.set(page, e);
    page.on('pageerror', (err) => e.push(err.message));
    f = JSON.parse(execFileSync('php', ['tests/e2e/support/make-game-state-fixture.php'], { encoding: 'utf8' }));
    await page.context().addCookies([{ ...f.authCookie, url: baseURL! }]);
    await page.route('**/storage/e2e/**', (r) => r.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg"/>' }));
    await page.route('**/browser-api/discord/servers/*/channels', (r) => r.fulfill({ json: { channels: [] } }));
    await page.route('**/browser-api/discord/servers/*/roles', (r) => r.fulfill({ json: { roles: [] } }));
});
test.afterEach(({ page }) => expect(errors.get(page)).toEqual([]));
async function awayBack(page: Page) {
    await page.getByRole('navigation', { name: 'Main navigation', exact: true }).getByRole('link', { name: 'Lists', exact: true }).click();
    await expect(page).toHaveURL(/\/lists(?:\/public)?(?:\?|$)/);
    await page.goBack();
}
async function shot(page: Page, info: TestInfo, name: string, locator: Page | Locator = page) {
    await locator.screenshot({ path: info.outputPath(name + '.png') });
}

test('game page review saves survive Back', async ({ page }, info) => {
    await page.goto(`/games/${f.slug}`);
    const section = page.locator('#reviews');
    await section.getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(section.getByText('2/5', { exact: true })).toBeVisible();
    await section.getByRole('button', { name: '5 stars', exact: true }).click();
    const response = page.waitForResponse((r) => r.url().includes('/user-reviews/') && r.request().method() === 'POST');
    await section.getByRole('button', { name: 'Update Review', exact: true }).click();
    expect((await (await response).json()).review.rating).toBe(5);
    await expect(section.getByRole('button', { name: 'Edit', exact: true })).toBeVisible();
    await awayBack(page);
    await section.getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(section.getByText('5/5', { exact: true })).toBeVisible();
    await shot(page, info, 'saved-game-review', section);
    await page.reload();
    await section.getByRole('button', { name: 'Edit', exact: true }).click();
    await expect(section.getByText('5/5', { exact: true })).toBeVisible();
});

for (const width of [1440, 375]) {
    test(`game card tags and languages expand and collapse at ${width}px`, async ({ page }, info) => {
        await page.setViewportSize({ width, height: 1000 });
        await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(f.originalName)}`);
        const more = page.getByRole('button', { name: 'Show 2 more tags', exact: true });
        await more.click();
        await expect(page.locator('[data-tag-id]')).toHaveCount(12);
        await page.getByRole('button', { name: 'Show 1 more languages', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Show less', exact: true })).toHaveCount(2);
        const tag = page.locator('[data-tag-id]').last();
        await tag.scrollIntoViewIfNeeded();
        await expect(tag).toBeInViewport();
        await tag.click({ trial: true });
        await shot(page, info, 'expanded-card');
        await page.getByRole('button', { name: 'Show less', exact: true }).last().click();
        await expect(more).toBeVisible();
        await expect(page.locator('[data-tag-id]')).toHaveCount(10);
        await page.getByRole('button', { name: 'Show less', exact: true }).click();
        await expect(page.getByRole('button', { name: 'Show 1 more languages', exact: true })).toBeVisible();
    });
}

test('ignoring a game refreshes results counts and history', async ({ page }, info) => {
    await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(f.originalName)}`);
    const add = page.getByRole('button', { name: 'Add to ignore list', exact: true });
    await expect(add).toBeVisible();
    await add.click();
    await expect(add).toHaveCount(0);
    await expect(page.getByText('2 games are on your ignore list.', { exact: false })).toBeVisible();
    await awayBack(page);
    await expect(add).toHaveCount(0);
    await shot(page, info, 'ignored-game-excluded');
    await page.reload();
    await expect(add).toHaveCount(0);
    await expect(page.getByText('2 games are on your ignore list.', { exact: false })).toBeVisible();
});

test('per-game Discord channel saves retain the latest edit', async ({ page }, info) => {
    const pending: Route[] = [];
    await page.route('**/browser-api/discord/servers/*/overrides/*', (r) => {
        pending.push(r);
    });
    await page.goto(`/discord/${f.discordServerId}?tab=overrides`);
    const input = page.getByPlaceholder('Enter channel ID');
    await expect(input).toHaveValue('111');
    await input.fill('222');
    await input.press('Tab');
    await expect.poll(() => pending.length).toBe(1);
    await input.fill('333');
    await input.press('Tab');
    expect(pending).toHaveLength(1);
    await expect(input).toHaveValue('333');
    await pending[0].continue();
    await expect.poll(() => pending.length).toBe(2);
    await expect(input).toHaveValue('333');
    await pending[1].continue();
    await expect
        .poll(async () => {
            const d = await (await page.request.get(`/browser-api/discord/servers/${f.discordServerId}`)).json();
            return d.server.game_overrides[0].channel_id;
        })
        .toBe('333');
    await page.reload();
    await expect(input).toHaveValue('333');
    await shot(page, info, 'saved-override-channel');
});

test('failed per-game Discord channel save restores the saved input', async ({ page }, info) => {
    await page.route('**/browser-api/discord/servers/*/overrides/*', (r) => r.fulfill({ status: 500, json: { message: 'Channel save failed' } }));
    await page.goto(`/discord/${f.discordServerId}?tab=overrides`);
    const input = page.getByPlaceholder('Enter channel ID');
    await expect(input).toHaveValue('111');
    await input.fill('222');
    await input.press('Tab');
    await expect(page.getByText('Channel save failed', { exact: true })).toBeVisible();
    await expect(input).toHaveValue('111');
    const d = await (await page.request.get(`/browser-api/discord/servers/${f.discordServerId}`)).json();
    expect(d.server.game_overrides[0].channel_id).toBe('111');
    await shot(page, info, 'restored-override-channel');
});

test('custom game names survive Back', async ({ page }, info) => {
    await page.goto(`/games/${f.slug}`);
    await page.getByTitle('Edit name', { exact: true }).click();
    const input = page.locator('input[maxlength="255"]');
    await input.fill('Saved audit title');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Saved audit title', exact: true })).toBeVisible();
    await awayBack(page);
    await expect(page.getByRole('heading', { name: 'Saved audit title', exact: true })).toBeVisible();
    await shot(page, info, 'saved-custom-name');
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Saved audit title', exact: true })).toBeVisible();
});

test('download links survive Back and subsequent saves', async ({ page }, info) => {
    await page.goto(`/my/games/${f.slug}/edit`);
    await page.getByRole('button', { name: 'Add Link', exact: true }).click();
    await page.getByPlaceholder('Link name').last().fill('Saved audit link');
    await page.getByPlaceholder('https://...').last().fill('https://example.test/download');
    const saved = page.waitForResponse((r) => r.url().includes('/my-games/') && r.request().method() === 'PUT');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    expect((await (await saved).json()).links).toHaveLength(2);
    await expect(page.getByRole('button', { name: 'Save Changes', exact: true })).toBeEnabled();
    await awayBack(page);
    await expect(page.getByPlaceholder('Link name')).toHaveCount(2);
    await shot(page, info, 'saved-links-after-back');
    const staleSave = page.waitForResponse((r) => r.url().includes('/my-games/') && r.request().method() === 'PUT');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    expect((await (await staleSave).json()).links).toHaveLength(2);
    await page.reload();
    await expect(page.getByPlaceholder('Link name')).toHaveCount(2);
});

test('failed game-card ignore explains the failure and permits retry', async ({ page }, info) => {
    await page.route('**/user/ignored-games/toggle', (r) => r.fulfill({ status: 500, json: { message: 'Ignore failed audit' } }), { times: 1 });
    await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(f.originalName)}`);
    const add = page.getByRole('button', { name: 'Add to ignore list', exact: true });
    const response = page.waitForResponse((r) => r.request().method() === 'POST' && r.url().includes('ignore'));
    await add.click();
    expect((await response).status()).toBe(500);
    await expect(add).toBeEnabled();
    await expect(page.getByText('Ignore failed audit', { exact: true })).toBeVisible();
    await shot(page, info, 'ignore-failure-feedback');
    await add.click();
    await expect(add).toHaveCount(0);
});

test('deleting a game-page review stays deleted after Back', async ({ page }) => {
    await page.goto(`/games/${f.slug}`);
    const section = page.locator('#reviews');
    await section.getByRole('button', { name: 'Delete', exact: true }).click();
    await section.getByRole('button', { name: 'Confirm', exact: true }).click();
    await expect(section.getByRole('button', { name: 'Write a review', exact: true })).toBeVisible();
    await awayBack(page);
    await expect(section.getByRole('button', { name: 'Write a review', exact: true })).toBeVisible();
    await expect(section.getByText('Your Review', { exact: true })).toHaveCount(0);
    await page.reload();
    await expect(section.getByRole('button', { name: 'Write a review', exact: true })).toBeVisible();
});

test('failed override saves preserve later edits and successful changes', async ({ page }) => {
    const pending: Route[] = [];
    await page.route('**/browser-api/discord/servers/*/overrides/*', (r) => {
        pending.push(r);
    });
    await page.goto(`/discord/${f.discordServerId}?tab=overrides`);
    const input = page.getByPlaceholder('Enter channel ID');
    const ignored = page.getByRole('checkbox', { name: `Ignore ${f.originalName}`, exact: true });
    await input.fill('222');
    await input.press('Tab');
    await expect.poll(() => pending.length).toBe(1);
    await ignored.locator('..').click();
    await input.fill('333');
    await input.press('Tab');
    await pending[0].fulfill({ status: 500, json: { message: 'First override save failed' } });
    await expect(page.getByText('First override save failed', { exact: true })).toBeVisible();
    await expect(input).toHaveValue('333');
    await expect(ignored).toBeChecked();
    await expect.poll(() => pending.length).toBe(2);
    await pending[1].continue();
    await expect.poll(() => pending.length).toBe(3);
    await pending[2].fulfill({ status: 500, json: { message: 'Last override save failed' } });
    await expect(page.getByText('Last override save failed', { exact: true })).toBeVisible();
    await expect(input).toHaveValue('111');
    await expect(ignored).toBeChecked();
    await page.reload();
    await expect(input).toHaveValue('111');
    await expect(ignored).toBeChecked();
});

test('unignore stays saved when ignored games remain visible', async ({ page }) => {
    await page.goto(`/games?noDefaults=1&showIgnored=1&search=${encodeURIComponent(f.originalName)}`);
    await page.getByRole('button', { name: 'Add to ignore list', exact: true }).click();
    await page.getByRole('button', { name: 'Remove from ignore list', exact: true }).click();
    const add = page.getByRole('button', { name: 'Add to ignore list', exact: true });
    await expect(add).toBeVisible();
    await expect(add).toBeEnabled();
    await awayBack(page);
    await expect(add).toBeVisible();
    await page.reload();
    await expect(add).toBeVisible();
});

test('saving a custom name preserves an unsaved review draft', async ({ page }) => {
    await page.goto(`/games/${f.slug}`);
    const section = page.locator('#reviews');
    await section.getByRole('button', { name: 'Edit', exact: true }).click();
    await section.getByRole('button', { name: '4 stars', exact: true }).click();
    await page.getByTitle('Edit name', { exact: true }).click();
    await page.locator('input[maxlength="255"]').fill('Saved name with draft review');
    await page.getByRole('button', { name: 'Save', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Saved name with draft review', exact: true })).toBeVisible();
    await expect(section.getByText('4/5', { exact: true })).toBeVisible();
    const saved = await (await page.request.get(`/browser-api/user-reviews/${f.gameId}`)).json();
    expect(saved.review.rating).toBe(2);
});
