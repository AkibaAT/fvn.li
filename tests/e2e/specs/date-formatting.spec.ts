import { test, expect } from '@playwright/test';
import { createGameViewFixture, type GameViewFixture } from '../support/laravel';

let fixture: GameViewFixture;
test.beforeAll(() => {
    fixture = createGameViewFixture();
});

for (const settings of [
    { timezoneId: 'UTC', locale: 'en-US', viewport: { width: 1440, height: 1000 } },
    { timezoneId: 'America/New_York', locale: 'en-US', viewport: { width: 1440, height: 1000 } },
    { timezoneId: 'Europe/Vienna', locale: 'de-AT', viewport: { width: 375, height: 812 } },
]) {
    test.describe(settings.timezoneId, () => {
        test.use(settings);
        test('preserves reading dates and localizes timestamps consistently', async ({ page, baseURL }, testInfo) => {
            const errors: string[] = [];
            page.on('pageerror', (error) => errors.push(error.message));
            page.on('console', (message) => {
                if (['error', 'warning'].includes(message.type()) && !message.text().includes('Failed to load resource')) errors.push(message.text());
            });
            await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
            await page.route('**/storage/e2e/**', (route) =>
                route.fulfill({
                    contentType: 'image/svg+xml',
                    body: '<svg xmlns="http://www.w3.org/2000/svg" width="315" height="250"><rect width="100%" height="100%" fill="#c8d5e8"/></svg>',
                }),
            );
            const dateOptions = { year: 'numeric', month: 'short', day: 'numeric' } as const;
            const expectedDate = new Intl.DateTimeFormat(settings.locale, { ...dateOptions, timeZone: settings.timezoneId }).format(
                new Date('2026-05-03T00:30:00Z'),
            );
            const calendarDate = new Intl.DateTimeFormat(settings.locale, { ...dateOptions, timeZone: 'UTC' }).format(
                new Date('2026-05-03T00:00:00Z'),
            );

            await page.goto(`/games/${fixture.slug}`);
            await expect(page.getByRole('heading', { name: fixture.customName, exact: true })).toBeVisible();
            await expect(page.getByText('Initial Release', { exact: true }).locator('..')).toContainText(expectedDate);
            await page.goto(`/games?noDefaults=1&search=${encodeURIComponent(fixture.originalName)}`);
            await page.getByRole('button', { name: /^(Add to|Manage in) Lists$/ }).click();
            const listName = `Calendar dates ${Date.now()}`;
            await page.getByPlaceholder('New list name').fill(listName);
            await page.getByRole('button', { name: 'Create & Add' }).click();
            await expect(page.getByRole('button', { name: `${listName} Remove` })).toBeVisible();
            await page.getByRole('button', { name: 'Close dialog', exact: true }).click();
            await page.getByRole('button', { name: /My Lists/ }).click();
            await page.getByRole('link', { name: listName, exact: true }).click();
            await expect(page.getByRole('heading', { name: listName, exact: true })).toBeVisible();
            await page.getByRole('button', { name: 'Edit', exact: true }).filter({ visible: true }).click();
            await page.getByLabel('Started At', { exact: true }).fill('2026-05-03');
            await page.getByLabel('Completed At', { exact: true }).fill('2026-05-04');
            await page.getByLabel('Last Read Version', { exact: true }).selectOption(String(fixture.versionId));
            await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
            await expect(page.getByLabel('Started At', { exact: true })).toHaveCount(0);
            await page.reload();
            await expect(page.getByText(calendarDate, { exact: true }).filter({ visible: true }).first()).toBeVisible();
            await page.evaluate(() => window.scrollTo(0, 0));
            await page.screenshot({ path: testInfo.outputPath('reading-dates.png'), fullPage: true });
            await page.getByRole('button', { name: 'Edit', exact: true }).filter({ visible: true }).click();
            await expect(page.getByLabel('Started At', { exact: true })).toHaveValue('2026-05-03');

            await page.route('**/dialogue/options*', (route) =>
                route.fulfill({
                    json: {
                        success: true,
                        versions: [{ id: fixture.versionId, version: '1.0.0', published_at: '2026-05-03 00:30:00' }],
                        languages: [{ id: 'eng', name: 'English' }],
                        characters: [],
                        contexts: [],
                    },
                }),
            );
            await page.route('**/dialogue/search*', (route) =>
                route.fulfill({
                    json: {
                        success: true,
                        data: [
                            {
                                id: 1,
                                highlighted_text: 'hello',
                                first_seen_version: {
                                    id: fixture.versionId,
                                    version: '1.0.0',
                                    published_at: '2026-05-03T00:30:00.000000Z',
                                },
                            },
                        ],
                        pagination: { current_page: 1, per_page: 25, total: 1, last_page: 1 },
                    },
                }),
            );
            await page.goto(`/dialogue/browser/${fixture.slug}/${fixture.versionId}?q=hello`);
            await expect(page.getByLabel('Version', { exact: true }).locator('option:checked')).toHaveText(`1.0.0 (${expectedDate})`);
            await expect(page.getByText(`First seen in version 1.0.0 (${expectedDate})`, { exact: true })).toBeVisible();
            await page.screenshot({ path: testInfo.outputPath('dialogue-version-date.png'), fullPage: true });

            await page.goto('/user/notifications/digest/2026-05-03');
            await expect(page).toHaveTitle(/Notification Digest/);
            await expect(page.getByRole('heading', { name: 'Notification Digest', exact: true })).toBeVisible();
            await expect(page.getByText(`Notifications for ${calendarDate} (UTC)`, { exact: true })).toBeVisible();
            const expectedTime = new Intl.DateTimeFormat(settings.locale, {
                ...dateOptions,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                timeZoneName: 'short',
                timeZone: settings.timezoneId,
            }).format(new Date('2026-05-03T00:30:00Z'));
            await expect(page.getByText(expectedTime, { exact: true })).toBeVisible();
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true);
            await page.screenshot({ path: testInfo.outputPath('digest-local-time.png'), fullPage: true });
            expect(errors).toEqual([]);
        });
    });
}

test.describe('chart dates', () => {
    test.use({ timezoneId: 'America/New_York', locale: 'en-US' });
    test('daily chart labels retain their UTC calendar buckets', async ({ page, baseURL }, testInfo) => {
        await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
        await page.addInitScript(() => {
            (window as any).chartLabels = [];
            const fillText = CanvasRenderingContext2D.prototype.fillText;
            CanvasRenderingContext2D.prototype.fillText = function (...args) {
                (window as any).chartLabels.push(args[0]);
                return fillText.apply(this, args);
            };
        });
        await page.goto(`/my/games/${fixture.slug}/edit`);
        await page.getByRole('navigation', { name: 'Tabs' }).scrollIntoViewIfNeeded();
        await expect(page.locator('canvas')).toBeVisible();
        const todayLabel = new Intl.DateTimeFormat('en-US', { month: 'short', day: 'numeric', timeZone: 'UTC' }).format(new Date());
        await expect.poll(() => page.evaluate(() => (window as any).chartLabels)).toContain(todayLabel);
        await page.screenshot({ path: testInfo.outputPath('daily-chart.png') });
    });
});
