import { test, expect, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import type { GameViewFixture } from '../support/laravel';

let fixture: GameViewFixture & { olderVersionId: number; raterId: number; gameSearch: string };
const pageErrors = new WeakMap<Page, string[]>();
test.beforeAll(() => {
    fixture = JSON.parse(execFileSync('php', ['tests/e2e/support/make-history-fixture.php'], { encoding: 'utf8' }));
});
test.beforeEach(async ({ page, baseURL }) => {
    const errors: string[] = [];
    pageErrors.set(page, errors);
    page.on('pageerror', (error) => errors.push(error.message));
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
    await page.route('**/storage/e2e/**', (route) =>
        route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"/>' }),
    );
});
test.afterEach(({ page }) => expect(pageErrors.get(page)).toEqual([]));

async function expectSavedUrl(page: Page) {
    await expect
        .poll(() => page.evaluate(() => history.state?.page?.url))
        .toBe(new URL(page.url()).pathname + new URL(page.url()).search + new URL(page.url()).hash);
}

async function navigateToRatings(page: Page) {
    const nav = page.getByRole('navigation', { name: 'Main navigation', exact: true, includeHidden: true });
    if (!(await nav.isVisible())) await page.getByRole('button', { name: 'Open navigation menu' }).click();
    await nav.getByRole('link', { name: 'Ratings', exact: true }).click();
    await expect(page).toHaveURL(/\/ratings$/);
    await expect(page.getByRole('heading', { name: 'Global Rating Statistics', exact: true })).toBeVisible();
    await expect(nav.getByRole('link', { name: 'Ratings', exact: true, includeHidden: true })).toHaveAttribute('aria-current', 'page');
}

for (const viewport of [
    { width: 1440, height: 1000 },
    { width: 375, height: 812 },
]) {
    test.describe(`${viewport.width}px`, () => {
        test.use({ viewport });
        test('dashboard tabs restore without requests and retain query parameters', async ({ page }, info) => {
            await page.goto('/dashboard?source=history-test');
            await expect(page.getByRole('tab', { name: 'Account', exact: true })).toHaveAttribute('aria-selected', 'true');
            const requests: string[] = [];
            page.on('request', (request) => {
                if (request.headers()['x-inertia']) requests.push(request.url());
            });
            await page.getByRole('tab', { name: 'Search Preferences', exact: true }).click();
            await expect(page).toHaveURL(/\?source=history-test&tab=search$/);
            await expectSavedUrl(page);
            await page.getByRole('tab', { name: 'VN Additions', exact: true }).click();
            await expect(page).toHaveURL(/tab=additions$/);
            await page.goBack();
            await expect(page.getByRole('tab', { name: 'Search Preferences', exact: true })).toHaveAttribute('aria-selected', 'true');
            await page.goForward();
            await expect(page.getByRole('tab', { name: 'VN Additions', exact: true })).toHaveAttribute('aria-selected', 'true');
            expect(requests).toEqual([]);
            await page.reload();
            await expect(page.getByRole('tab', { name: 'VN Additions', exact: true })).toHaveAttribute('aria-selected', 'true');
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
            await page.screenshot({ path: info.outputPath('dashboard-history.png') });
            await page.goto('/dashboard#search');
            await expect(page.getByRole('tab', { name: 'Search Preferences', exact: true })).toHaveAttribute('aria-selected', 'true');
        });

        test('global search follows navigation and Back cancels pending typing', async ({ page }, info) => {
            await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(fixture.gameSearch)}`);
            const search =
                viewport.width < 1024
                    ? page.getByPlaceholder('Search games, authors, tags...').filter({ visible: true })
                    : page.getByRole('textbox', { name: 'Search games, authors, and tags' });
            if (viewport.width < 1024) await page.getByRole('button', { name: 'Show search' }).click();
            await expect(search).toHaveValue(fixture.gameSearch);
            await navigateToRatings(page);
            if (viewport.width < 1024 && !(await search.isVisible())) await page.getByRole('button', { name: 'Show search' }).click();
            await expect(search).toHaveValue('');
            await page.goBack();
            await expect(search).toHaveValue(fixture.gameSearch);
            await navigateToRatings(page);
            if (viewport.width < 1024 && !(await search.isVisible())) await page.getByRole('button', { name: 'Show search' }).click();
            await search.fill('pending query');
            await page.goBack();
            await expect(search).toHaveValue(fixture.gameSearch);
            await page.waitForTimeout(650);
            await expect(page).toHaveURL(new RegExp(`search=${encodeURIComponent(fixture.gameSearch)}`));
            const historyLength = await page.evaluate(() => history.length);
            await search.fill(`${fixture.gameSearch} 1`);
            await expect.poll(() => new URL(page.url()).searchParams.get('search')).toBe(`${fixture.gameSearch} 1`);
            await expect(search).toBeFocused();
            expect(await page.evaluate(() => history.length)).toBe(historyLength);
            await page.screenshot({ path: info.outputPath('search-history.png') });
        });
    });
}

test('route-map version survives linked URLs, refresh, Back and Forward', async ({ page }, info) => {
    await page.goto(`/games/${fixture.slug}/route-map?version_id=${fixture.olderVersionId}`);
    const version = page.locator('main select').first();
    await expect(version).toHaveValue(String(fixture.olderVersionId));
    await expectSavedUrl(page);
    await page.reload();
    await expect(version).toHaveValue(String(fixture.olderVersionId));
    await version.selectOption(String(fixture.versionId));
    await expect(page).toHaveURL(new RegExp(`version_id=${fixture.versionId}$`));
    await expectSavedUrl(page);
    await page.goBack();
    await expect(version).toHaveValue(String(fixture.olderVersionId));
    await page.goForward();
    await expect(version).toHaveValue(String(fixture.versionId));
    await version.selectOption(String(fixture.olderVersionId));
    await expect(page).toHaveURL(new RegExp(`version_id=${fixture.olderVersionId}$`));
    await navigateToRatings(page);
    await page.goBack();
    await expect(version).toHaveValue(String(fixture.olderVersionId));
    await expectSavedUrl(page);
    await page.screenshot({ path: info.outputPath('route-map-history.png') });
});

for (const scope of ['ratings', 'rater']) {
    test(`${scope} filters and pagination make restorable history entries`, async ({ page }) => {
        await page.goto(scope === 'ratings' ? '/ratings' : `/raters/${fixture.raterId}`);
        await page.getByRole('link', { name: 'Go to page 2', exact: true }).click();
        await expect(page.getByLabel('Select page number')).toHaveValue('2');
        await page.getByLabel('Reviews only', { exact: true }).uncheck();
        await expect(page.getByLabel('Select page number')).toHaveValue('1');
        await page.goBack();
        await expect(page.getByLabel('Reviews only', { exact: true })).toBeChecked();
        await expect(page.getByLabel('Select page number')).toHaveValue('2');
        await page.goBack();
        await expect(page.getByLabel('Select page number')).toHaveValue('1');
        await page.goForward();
        await expect(page.getByLabel('Select page number')).toHaveValue('2');
        await expectSavedUrl(page);
    });
}

test('game pagination and sort changes restore their controls and results', async ({ page }) => {
    await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(fixture.gameSearch)}`);
    await page.getByRole('link', { name: 'Go to page 2', exact: true }).click();
    await expect(page.getByLabel('Select page number')).toHaveValue('2');
    const sort = page.getByLabel('Sort by', { exact: true });
    const oldSort = await sort.inputValue();
    await sort.selectOption('name');
    await expect(page).toHaveURL(/sort=name/);
    await page.goBack();
    await expect(sort).toHaveValue(oldSort);
    await expect(page.getByLabel('Select page number')).toHaveValue('2');
    await page.goBack();
    await expect(page.getByLabel('Select page number')).toHaveValue('1');
    await page.goForward();
    await expect(page.getByLabel('Select page number')).toHaveValue('2');
});

test('dialogue filters restore through Back, Forward and refresh', async ({ page }) => {
    await page.route('**/dialogue/options*', (route) =>
        route.fulfill({
            json: {
                success: true,
                versions: [{ id: fixture.versionId, version: '1.0.0' }],
                languages: [
                    { id: 'eng', name: 'English' },
                    { id: 'deu', name: 'German' },
                ],
                characters: [],
                contexts: ['Opening'],
            },
        }),
    );
    await page.route('**/dialogue/search*', (route) =>
        route.fulfill({
            json: {
                success: true,
                results: [],
                pagination: {
                    current_page: Number(new URL(route.request().url()).searchParams.get('page') || 1),
                    per_page: 25,
                    total: 50,
                    last_page: 2,
                },
            },
        }),
    );
    await page.goto(`/dialogue/browser/${fixture.slug}/${fixture.versionId}?q=hello`);
    await page.getByLabel('Language', { exact: true }).selectOption('deu');
    await expect(page).toHaveURL(/selectedLangs=deu/);
    await expectSavedUrl(page);
    await page.getByLabel('Context', { exact: true }).selectOption('Opening');
    await expect(page).toHaveURL(/context=Opening/);
    await page.reload();
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('deu');
    await expect(page.getByLabel('Context', { exact: true })).toHaveValue('Opening');
    await page.goBack();
    await expect(page.getByLabel('Context', { exact: true })).toHaveValue('');
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('deu');
    await page.goBack();
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('eng');
    await page.goForward();
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('deu');
    await navigateToRatings(page);
    await page.goBack();
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('deu');
    await expectSavedUrl(page);
    await page.getByRole('button', { name: 'Go to page 2', exact: true }).click();
    await expect(page.getByLabel('Select page number')).toHaveValue('2');
    await page.goBack();
    await expect(page.getByLabel('Select page number')).toHaveValue('1');
    await page.goForward();
    await expect(page.getByLabel('Select page number')).toHaveValue('2');
    await page.reload();
    await expect(page.getByLabel('Select page number')).toHaveValue('2');
    const historyLength = await page.evaluate(() => history.length);
    await page.getByLabel('Search', { exact: true }).fill('hello again');
    await expect.poll(() => new URL(page.url()).searchParams.get('page')).toBeNull();
    expect(await page.evaluate(() => history.length)).toBe(historyLength);
    await expectSavedUrl(page);
});
