import { test, expect } from '@playwright/test';
import { createGameViewFixture, type GameViewFixture } from '../support/laravel';

let fixture: GameViewFixture;
const baseURL = process.env.E2E_BASE_URL || 'http://web:8088';
test.use({ timezoneId: 'Europe/Vienna' });
test.beforeAll(() => {
    fixture = createGameViewFixture();
});
test.beforeEach(async ({ page }) => {
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL }]);
    // The fixture deliberately references images which do not exist on disk.
    await page.route('**/storage/e2e/**', (route) =>
        route.fulfill({
            contentType: 'image/svg+xml',
            body: '<svg xmlns="http://www.w3.org/2000/svg" width="315" height="250"><rect width="100%" height="100%" fill="#c8d5e8"/></svg>',
        }),
    );
});

for (const viewport of [
    { width: 1440, height: 1000 },
    { width: 375, height: 812 },
]) {
    test(`list membership and entry feedback at ${viewport.width}px`, async ({ page }, testInfo) => {
        await page.setViewportSize(viewport);
        const errors: string[] = [];
        page.on('pageerror', (error) => errors.push(error.message));
        await page.goto(
            `/games?noDefaults=1&search=${encodeURIComponent(fixture.originalName)}&selectedPlatforms[]=windows&selectedPlatforms[]=linux`,
        );
        await expect(page.getByText('Matching ignored games are excluded from results.', { exact: false })).toBeVisible();
        await page.getByRole('button', { name: /^(Add to|Manage in) Lists$/ }).click();
        const listName = `Review ${viewport.width} ${Date.now()}`;
        await page.getByPlaceholder('New list name').fill(listName);
        await page.getByRole('button', { name: 'Create & Add' }).click();
        await expect(page.getByRole('button', { name: `${listName} Remove` })).toBeVisible();
        await page.getByRole('button', { name: 'Close dialog', exact: true }).click();
        await page.reload();
        await expect(page.getByRole('button', { name: 'Manage in Lists' })).toBeVisible();
        await page.getByRole('button', { name: /My Lists/ }).click();
        await page.getByRole('link', { name: listName, exact: true }).click();
        await expect(page.getByRole('heading', { name: listName, exact: true })).toBeVisible();
        await page.getByRole('button', { name: 'Edit', exact: true }).filter({ visible: true }).click();
        await page.getByLabel('Private Notes', { exact: true }).fill('Owner private regression note');
        await page.route('**/list-entries/*', (route) => route.fulfill({ status: 422, json: { message: 'Entry could not be saved' } }), { times: 1 });
        await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
        const alert = page.getByRole('alert', { name: 'error notification: Entry could not be saved' });
        await expect(alert).toBeVisible();
        const bounds = await alert.boundingBox();
        expect(bounds!.x).toBeGreaterThanOrEqual(0);
        expect(bounds!.x + bounds!.width).toBeLessThanOrEqual(viewport.width);
        await page.screenshot({ path: testInfo.outputPath(`list-feedback-${viewport.width}.png`), fullPage: true });
        await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
        await expect(page.getByLabel('Private Notes', { exact: true })).toHaveCount(0);
        await expect(page.getByText('"Owner private regression note"', { exact: false }).filter({ visible: true })).toBeVisible();
        expect(errors).toEqual([]);
    });
}

test('repeated link saves retain IDs and the selected date timezone', async ({ page }, testInfo) => {
    await page.goto(`/my/games/${fixture.slug}/edit`);
    await expect(page.getByRole('heading', { name: 'Download Links', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Add Link', exact: true }).click();
    await page.getByPlaceholder('Link name').last().fill('Release regression');
    await page.getByPlaceholder('https://...').last().fill('https://example.com/release');
    await page.getByLabel('Release Date & Time', { exact: false }).last().fill('2026-12-01T12:00');
    const firstResponse = page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().includes('/my-games/'));
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    const saved = await (await firstResponse).json();
    expect(saved.success).toBe(true);
    const link = saved.links.find((link: any) => link.name === 'Release regression');
    expect(link.id).toBeTruthy();
    expect(new Date(link.release_at).toISOString()).toBe('2026-12-01T11:00:00.000Z');
    await expect(page.getByLabel('Release Date & Time', { exact: false }).last()).toHaveValue('2026-12-01T12:00');
    const secondRequest = page.waitForRequest((request) => request.method() === 'PUT' && request.url().includes('/my-games/'));
    const secondResponse = page.waitForResponse((response) => response.request().method() === 'PUT' && response.url().includes('/my-games/'));
    await page.getByRole('button', { name: 'Save Changes', exact: true }).click();
    expect((await secondRequest).postDataJSON().links.find((entry: any) => entry.name === link.name).id).toBe(link.id);
    expect((await (await secondResponse).json()).links.find((entry: any) => entry.name === link.name).id).toBe(link.id);
    await page.reload();
    await expect(page.getByLabel('Release Date & Time', { exact: false }).last()).toHaveValue('2026-12-01T12:00');
    await page.screenshot({ path: testInfo.outputPath('saved-links-desktop.png'), fullPage: true });
});

test('dialogue language and pagination remain coherent on desktop and mobile', async ({ page }, testInfo) => {
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
                contexts: [],
            },
        }),
    );
    await page.goto(`/dialogue/browser/${fixture.slug}/${fixture.versionId}?q=hello&page=3`);
    await expect(page.getByRole('heading', { name: `Dialogue Browser - ${fixture.originalName}`, exact: true })).toBeVisible();
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('eng');
    await page.getByLabel('Language', { exact: true }).selectOption('deu');
    await expect(page).toHaveURL(/selectedLangs=deu/);
    expect(new URL(page.url()).searchParams.has('page')).toBe(false);
    expect(await page.evaluate(() => window.history.state.page.url)).toBe(new URL(page.url()).pathname + new URL(page.url()).search);
    await expect(page.getByText('Loading...', { exact: true })).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('dialogue-desktop.png'), fullPage: true });
    await page.setViewportSize({ width: 375, height: 812 });
    await page.reload();
    await expect(page.getByLabel('Language', { exact: true })).toHaveValue('deu');
    await expect(page.getByText('Loading...', { exact: true })).toHaveCount(0);
    await page.screenshot({ path: testInfo.outputPath('dialogue-mobile.png'), fullPage: true });
});
