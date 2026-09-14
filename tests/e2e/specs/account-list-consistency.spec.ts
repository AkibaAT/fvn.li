import { test, expect, type Page, type Route } from '@playwright/test';
import type { GameViewFixture } from '../support/laravel';
import { execFileSync } from 'node:child_process';
let fixture: GameViewFixture & { discordServerId: number; listIds: number[]; gameId: number; extraGameId: number; extraGameName: string };
const pageErrors = new WeakMap<Page, string[]>();

test.beforeEach(async ({ page, baseURL }, info) => {
    const errors: string[] = [];
    pageErrors.set(page, errors);
    page.on('pageerror', (error) => errors.push(error.message));
    fixture = JSON.parse(
        execFileSync(
            'php',
            [
                'tests/e2e/support/make-account-list-fixture.php',
                info.title.includes('last provider') ? 'last-provider' : info.title.includes('account deletion') ? 'account-deletion' : '',
            ],
            {
                encoding: 'utf8',
            },
        ),
    );
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
    await page.route('**/storage/e2e/**', (r) => r.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg"/>' }));
    await page.route('**/browser-api/discord/servers/*/channels', (r) => r.fulfill({ json: { channels: [] } }));
    await page.route('**/browser-api/discord/servers/*/roles', (r) => r.fulfill({ json: { roles: [] } }));
});

test.afterEach(({ page }) => expect(pageErrors.get(page)).toEqual([]));

test('account deletion requires confirmation and can retry after failure', async ({ page }, info) => {
    let requests = 0;
    let pending: Route | undefined;
    await page.route('**/user/account', (route) => {
        expect(route.request().method()).toBe('DELETE');
        if (++requests === 1) {
            pending = route;
            return;
        }
        return route.continue();
    });
    await page.goto('/dashboard');
    await expect(page).toHaveTitle(/Dashboard/);
    const downloadPromise = page.waitForEvent('download');
    await page.getByRole('button', { name: 'Export My Data', exact: true }).click();
    const download = await downloadPromise;
    expect(download.suggestedFilename()).toMatch(/^user-data-.+\.zip$/);
    await download.saveAs(info.outputPath('account-data.zip'));
    expect(await download.failure()).toBeNull();
    const button = page.getByRole('button', { name: 'Delete Account', exact: true });
    await expect(button).toBeVisible();
    await button.scrollIntoViewIfNeeded();
    await page.screenshot({ path: info.outputPath('delete-account-desktop.png') });
    await page.setViewportSize({ width: 390, height: 844 });
    await button.scrollIntoViewIfNeeded();
    await expect(button).toBeVisible();
    await page.screenshot({ path: info.outputPath('delete-account-mobile.png') });

    page.once('dialog', (dialog) => dialog.dismiss());
    await button.click();
    await expect(button).toBeEnabled();
    expect(requests).toBe(0);

    page.on('dialog', (dialog) => dialog.accept());
    await button.click();
    await expect(page.getByRole('button', { name: /Deleting Account/ })).toBeDisabled();
    await expect.poll(() => Boolean(pending)).toBe(true);
    await pending!.fulfill({ status: 500, json: { message: 'Simulated deletion failure' } });
    await expect(page.getByText('Simulated deletion failure', { exact: true })).toBeVisible();
    await expect(button).toBeEnabled();
    await expect(page).toHaveURL(/\/dashboard$/);

    const deleted = page.waitForResponse((response) => response.url().endsWith('/user/account') && response.ok());
    await button.click();
    expect((await deleted).status()).toBe(200);
    await expect(page).toHaveURL(new URL('/', info.project.use.baseURL).href);
    await page.goto('/dashboard');
    await expect(page).toHaveURL(/\/login$/);
    expect(requests).toBe(2);
});

test('notification frequency survives tab changes, later saves, and Back navigation', async ({ page }, info) => {
    await page.goto('/dashboard');
    await page.getByRole('button', { name: 'Daily digest One daily update summary', exact: true }).click();
    await expect(page.getByText('Notification frequency updated.', { exact: true })).toBeVisible();
    let saved = await (await page.request.get('/browser-api/dashboard/notification-preferences')).json();
    expect(saved.preferences.notification_digest).toBe('daily');
    await page.getByRole('tab', { name: 'Search Preferences', exact: true }).click();
    await page.getByRole('tab', { name: 'Account', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Daily digest One daily update summary', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await page.getByRole('checkbox', { name: 'Enable Discord notifications', exact: true }).locator('..').click();
    await expect(page.getByText('Discord DMs enabled. Authorize the app and send a test DM to verify delivery.', { exact: true })).toBeVisible();
    saved = await (await page.request.get('/browser-api/dashboard/notification-preferences')).json();
    expect(saved.preferences.notification_digest).toBe('daily');
    await page.getByRole('tab', { name: 'Search Preferences', exact: true }).click();
    await expect(page).toHaveURL(/tab=search$/);
    await page.goBack();
    await expect(page.getByRole('button', { name: 'Daily digest One daily update summary', exact: true })).toHaveAttribute('aria-pressed', 'true');
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: info.outputPath('saved-notification-frequency.png'), fullPage: true });
});

test('failed notification save leaves the switch off', async ({ page }, info) => {
    await page.route('**/browser-api/dashboard/notification-preferences', async (r) => {
        if (r.request().method() === 'POST') return r.fulfill({ status: 500, json: { message: 'Simulated save failure' } });
        await r.continue();
    });
    await page.goto('/dashboard');
    const toggle = page.getByRole('checkbox', { name: 'Enable Discord notifications', exact: true });
    await expect(toggle).not.toBeChecked();
    await toggle.locator('..').click();
    await expect(page.getByText('Simulated save failure', { exact: true })).toBeVisible();
    await expect(toggle).not.toBeChecked();
    const saved = await (await page.request.get('/browser-api/dashboard/notification-preferences')).json();
    expect(saved.preferences.discord_notifications_enabled).toBe(false);
    await page.screenshot({ path: info.outputPath('failed-switch-off.png'), fullPage: true });
});

test('saved list metadata and visibility survive Back navigation', async ({ page }, info) => {
    await page.goto(`/lists/${fixture.listIds[0]}`);
    await page.getByRole('button', { name: 'Edit List', exact: true }).click();
    await page.getByLabel('List Name', { exact: true }).fill('Saved new list name');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Saved new list name', exact: true })).toBeVisible();
    await expect(page.getByText('List updated successfully', { exact: true })).toBeVisible();
    await expect(page).toHaveTitle(/Saved new list name/);
    await page.getByRole('button', { name: 'Make Public', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Make Private', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Back to Lists', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Your Visual Novel Lists', exact: true })).toBeVisible();
    await page.goBack();
    await expect(page.getByRole('heading', { name: 'Saved new list name', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Make Private', exact: true })).toBeVisible();
    await expect(page).toHaveTitle(/Saved new list name/);
    await page.screenshot({ path: info.outputPath('saved-list-after-back.png'), fullPage: true });
    await page.reload();
    await expect(page.getByRole('heading', { name: 'Saved new list name', exact: true })).toBeVisible();
});

test('both list Cancel controls discard drafts', async ({ page }) => {
    await page.goto(`/lists/${fixture.listIds[0]}`);
    await page.getByRole('button', { name: 'Edit List', exact: true }).click();
    await page.getByLabel('List Name', { exact: true }).fill('Unsaved draft');
    await page.getByRole('button', { name: 'Cancel Edit', exact: true }).click();
    await page.getByRole('button', { name: 'Edit List', exact: true }).click();
    await expect(page.getByLabel('List Name', { exact: true })).toHaveValue('Deleted list');
    await page.getByLabel('List Name', { exact: true }).fill('Another unsaved draft');
    await page.getByRole('button', { name: 'Cancel', exact: true }).click();
    await page.getByRole('button', { name: 'Edit List', exact: true }).click();
    await expect(page.getByLabel('List Name', { exact: true })).toHaveValue('Deleted list');
});

test('Discord override search ignores late results for older queries', async ({ page }, info) => {
    let first: Route | undefined;
    await page.route('**/browser-api/games/search*', async (r) => {
        const q = new URL(r.request().url()).searchParams.get('q');
        if (q === 'alpha') {
            first = r;
            return;
        }
        await r.fulfill({ json: [{ id: 98766, name: 'BETA GAME', slug: 'beta' }] });
    });
    await page.goto(`/discord/${fixture.discordServerId}?tab=overrides`);
    await page.getByRole('button', { name: 'Add VN', exact: true }).click();
    const search = page.getByLabel('Search for a visual novel', { exact: true });
    await search.fill('alpha');
    await expect.poll(() => Boolean(first)).toBe(true);
    await search.fill('beta');
    await expect(page.getByText('BETA GAME', { exact: true })).toBeVisible();
    const response = page.waitForResponse((r) => r.url() === first!.request().url());
    await first!.fulfill({ json: [{ id: 98765, name: 'ALPHA GAME', slug: 'alpha' }] });
    await (await response).finished();
    await expect(page.getByText('BETA GAME', { exact: true })).toBeVisible();
    await expect(page.getByText('ALPHA GAME', { exact: true })).toHaveCount(0);
    await expect(search).toHaveValue('beta');
    await page.screenshot({ path: info.outputPath('latest-game-search.png'), fullPage: true });
});

for (const tab of ['overrides', 'ignored']) {
    test(`repeated ${tab} submissions create one row and can retry after failure`, async ({ page }) => {
        const pending: Route[] = [];
        await page.route('**/browser-api/games/search*', (r) =>
            r.fulfill({ json: [{ id: fixture.extraGameId, name: fixture.extraGameName, slug: 'extra' }] }),
        );
        await page.route('**/browser-api/discord/servers/*/overrides', (r) => {
            if (r.request().method() === 'POST') {
                pending.push(r);
                return;
            }
            return r.continue();
        });
        await page.goto(`/discord/${fixture.discordServerId}?tab=${tab}`);
        await page.getByRole('button', { name: 'Add VN', exact: true }).click();
        await page.getByLabel('Search for a visual novel', { exact: true }).fill('extra');
        const add = page.getByRole('button', { name: tab === 'ignored' ? 'Ignore' : 'Add Override', exact: true });
        await add.dblclick();
        await expect.poll(() => pending.length).toBe(1);
        await expect(add).toBeDisabled();
        await pending[0].fulfill({ status: 500, json: { message: 'Simulated add failure' } });
        await expect(page.getByText('Simulated add failure', { exact: true })).toBeVisible();
        await expect(add).toBeEnabled();
        await add.dblclick();
        await expect.poll(() => pending.length).toBe(2);
        await pending[1].continue();
        await expect(page.getByText(`Added override for ${fixture.extraGameName}`, { exact: true })).toBeVisible();
        await expect(page.locator('main tbody tr')).toHaveCount(2);
        await expect(page.getByText(fixture.extraGameName, { exact: true })).toHaveCount(1);
        expect(pending).toHaveLength(2);
        const saved = await (await page.request.get(`/browser-api/discord/servers/${fixture.discordServerId}/overrides`)).json();
        expect(saved.overrides).toHaveLength(2);
    });
}

test('list filter Ctrl-click opens a new tab with the same filter context', async ({ page, context }) => {
    await page.goto('/lists?per_page=8');
    const personalUrl = page.url();
    const personalTabPromise = context.waitForEvent('page');
    await page.getByRole('link', { name: /^Public Lists \(/ }).click({ modifiers: ['Control'] });
    const personalTab = await personalTabPromise;
    await expect(personalTab).toHaveURL((url) => url.searchParams.get('visibility') === 'public' && url.searchParams.get('per_page') === '8');
    await expect(page).toHaveURL(personalUrl);
    await personalTab.close();
    await page.goto(`/lists/public?search=Public&sort=newest&per_page=8&game=${fixture.extraGameId}`);
    const publicUrl = new URL(page.url());
    const publicTabPromise = context.waitForEvent('page');
    await page.getByRole('link', { name: /^Custom \(/ }).click({ modifiers: ['Control'] });
    const publicTab = await publicTabPromise;
    await expect(publicTab).toHaveURL(
        (url) =>
            url.searchParams.get('type') === 'custom' &&
            url.searchParams.get('search') === 'Public' &&
            url.searchParams.get('sort') === 'newest' &&
            url.searchParams.get('per_page') === '8' &&
            url.searchParams.get('game') === String(fixture.extraGameId),
    );
    await expect(page).toHaveURL(
        (url) =>
            url.pathname === publicUrl.pathname &&
            url.searchParams.size === publicUrl.searchParams.size &&
            [...publicUrl.searchParams].every(([key, value]) => url.searchParams.get(key) === value),
    );
    await publicTab.close();
});

test('last provider unlink explains why it cannot disconnect', async ({ page }, info) => {
    await page.goto('/dashboard');
    page.on('dialog', (d) => d.accept());
    await page.getByRole('button', { name: 'Unlink Discord account', exact: true }).click();
    await expect(page.getByText(/Cannot disconnect your last social account/)).toBeVisible();
    await expect(page.getByRole('button', { name: 'Unlink Discord account', exact: true })).toBeVisible();
    await page.screenshot({ path: info.outputPath('last-provider-error.png'), fullPage: true });
});

test('notification health refreshes after unlinking Discord', async ({ page }, info) => {
    await page.goto('/dashboard');
    await expect(page.getByText('Switch on Discord notifications above to use this channel.', { exact: true })).toBeVisible();
    page.on('dialog', (d) => d.accept());
    await page.getByRole('button', { name: 'Unlink Discord account', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Unlink Discord account', exact: true })).toHaveCount(0);
    await expect(page.getByRole('checkbox', { name: 'Enable Discord notifications', exact: true })).toHaveCount(0);
    await expect(page.getByText('Link your Discord account to use this channel.', { exact: true })).toBeVisible();
    await page.evaluate(() => window.scrollTo(0, 0));
    await page.screenshot({ path: info.outputPath('disconnected-health.png'), fullPage: true });
    await page.reload();
    await expect(page.getByText('Link your Discord account to use this channel.', { exact: true })).toBeVisible();
});

for (const action of ['clear', 'close']) {
    test(`override search ignores a pending response after ${action}`, async ({ page }) => {
        let pending: Route | undefined;
        await page.route('**/browser-api/games/search*', (r) => {
            pending = r;
        });
        await page.goto(`/discord/${fixture.discordServerId}?tab=overrides`);
        await page.getByRole('button', { name: 'Add VN', exact: true }).click();
        const input = page.getByLabel('Search for a visual novel', { exact: true });
        await input.fill('alpha');
        await expect.poll(() => Boolean(pending)).toBe(true);
        if (action === 'clear') await input.fill('');
        else await page.getByRole('button', { name: 'Close Search', exact: true }).click();
        const response = page.waitForResponse((r) => r.url() === pending!.request().url());
        await pending!.fulfill({ json: [{ id: 98765, name: 'STALE GAME', slug: 'alpha' }] });
        await (await response).finished();
        await expect(page.getByText('STALE GAME', { exact: true })).toHaveCount(0);
        if (action === 'clear') await expect(input).toHaveValue('');
        else await expect(input).toHaveCount(0);
    });
}

async function backToList(page: Page) {
    await page.getByRole('link', { name: 'Back to Lists', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Your Visual Novel Lists', exact: true })).toBeVisible();
    await page.goBack();
    await expect(page.getByRole('heading', { name: 'Deleted list', exact: true })).toBeVisible();
}

test('saved entry details, notifications, moves, and removals survive Back', async ({ page }) => {
    const added = await page.request.post(`/browser-api/lists/${fixture.listIds[0]}/add-game`, { data: { game_id: fixture.extraGameId } });
    expect(added.ok()).toBe(true);
    await page.goto(`/lists/${fixture.listIds[0]}`);
    await page.getByRole('button', { name: 'Edit', exact: true }).filter({ visible: true }).click();
    await page.getByLabel('Public Notes', { exact: true }).fill('Saved reading notes');
    await page.getByLabel('Private Notes', { exact: true }).fill('Saved private notes');
    await page.getByLabel('Started At', { exact: true }).fill('2026-05-03');
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    await expect(page.getByText('Entry updated', { exact: true })).toBeVisible();
    await backToList(page);
    await page.getByRole('button', { name: 'Edit', exact: true }).filter({ visible: true }).click();
    await expect(page.getByLabel('Public Notes', { exact: true })).toHaveValue('Saved reading notes');
    await expect(page.getByLabel('Private Notes', { exact: true })).toHaveValue('Saved private notes');
    await expect(page.getByLabel('Started At', { exact: true })).toHaveValue('2026-05-03');
    await page.getByRole('button', { name: 'Cancel', exact: true }).filter({ visible: true }).last().click();
    const individual = page.getByRole('checkbox').filter({ visible: true }).last();
    await expect(individual).not.toBeChecked();
    await individual.locator('..').click();
    await expect(individual).toBeChecked();
    await backToList(page);
    await expect(individual).toBeChecked();
    await page.getByRole('checkbox', { name: 'Turn off notifications for all free entries', exact: true }).locator('..').click();
    await expect(individual).not.toBeChecked();
    await backToList(page);
    await expect(individual).not.toBeChecked();
    await page.getByRole('button', { name: 'Move', exact: true }).filter({ visible: true }).click();
    await page.getByLabel('Target List', { exact: true }).selectOption(String(fixture.listIds[1]));
    await page.getByRole('button', { name: 'Move to List', exact: true }).click();
    await expect(page.getByText('Entry moved successfully', { exact: true })).toBeVisible();
    await backToList(page);
    await expect(page.getByText('No visual novels in this list yet.', { exact: true })).toBeVisible();
    await page.goto(`/lists/${fixture.listIds[1]}`);
    await expect(
        page.getByRole('link', { name: fixture.extraGameName, exact: true }).filter({ visible: true, hasText: fixture.extraGameName }),
    ).toBeVisible();
    page.on('dialog', (dialog) => dialog.accept());
    await page.getByRole('button', { name: 'Remove', exact: true }).filter({ visible: true }).click();
    await expect(page.getByText('No visual novels in this list yet.', { exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Back to Lists', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Your Visual Novel Lists', exact: true })).toBeVisible();
    await page.goBack();
    await expect(page.getByText('No visual novels in this list yet.', { exact: true })).toBeVisible();
});

test('saved entry order survives Back', async ({ page }) => {
    for (const gameId of [fixture.gameId, fixture.extraGameId]) {
        const added = await page.request.post(`/browser-api/lists/${fixture.listIds[0]}/add-game`, { data: { game_id: gameId } });
        expect(added.ok()).toBe(true);
    }
    await page.goto(`/lists/${fixture.listIds[0]}`);
    const gameLinks = page.locator('main a[href*="/games/"]:not(:has(img))').filter({ visible: true });
    await expect(gameLinks).toHaveCount(2);
    const originalOrder = await gameLinks.evaluateAll((links) => links.map((link) => link.getAttribute('href')));
    const handles = page.getByRole('button', { name: 'Drag to reorder', exact: true }).filter({ visible: true });
    await handles.first().dragTo(handles.nth(1));
    await expect(page.getByText('List order updated.', { exact: true })).toBeVisible();
    expect(await gameLinks.evaluateAll((links) => links.map((link) => link.getAttribute('href')))).toEqual([...originalOrder].reverse());
    await backToList(page);
    expect(await gameLinks.evaluateAll((links) => links.map((link) => link.getAttribute('href')))).toEqual([...originalOrder].reverse());
});
