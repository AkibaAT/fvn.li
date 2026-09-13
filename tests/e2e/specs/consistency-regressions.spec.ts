import { test, expect, type Page, type Route } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import type { GameViewFixture } from '../support/laravel';

let fixture: GameViewFixture & { raterId: number; discordServerId: number };
const pageErrors = new WeakMap<Page, string[]>();
test.beforeAll(() => {
    fixture = JSON.parse(execFileSync('php', ['tests/e2e/support/make-consistency-fixture.php'], { encoding: 'utf8' }));
});
test.beforeEach(async ({ page, baseURL }) => {
    const errors: string[] = [];
    pageErrors.set(page, errors);
    page.on('pageerror', (error) => errors.push(error.message));
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
    await page.route('**/storage/e2e/**', (route) =>
        route.fulfill({ contentType: 'image/svg+xml', body: '<svg xmlns="http://www.w3.org/2000/svg"/>' }),
    );
    await page.route('**/browser-api/discord/servers/*/channels', (route) => route.fulfill({ json: { channels: [] } }));
    await page.route('**/browser-api/discord/servers/*/roles', (route) => route.fulfill({ json: { roles: [] } }));
});
test.afterEach(({ page }) => expect(pageErrors.get(page)).toEqual([]));

test('Ctrl-click opens navigation and pagination in a new tab', async ({ page, context }) => {
    await page.goto('/dashboard');
    const popup = context.waitForEvent('page');
    await page
        .getByRole('navigation', { name: 'Main navigation', exact: true })
        .getByRole('link', { name: 'Ratings', exact: true })
        .click({ modifiers: ['Control'] });
    const ratingsTab = await popup;
    await expect(ratingsTab).toHaveURL(/\/ratings$/);
    await expect(page).toHaveURL(/\/dashboard$/);
    await ratingsTab.close();

    await page.goto('/ratings');
    const nextPopup = context.waitForEvent('page');
    await page.getByRole('link', { name: 'Go to page 2', exact: true }).click({ modifiers: ['Control'] });
    const nextTab = await nextPopup;
    await expect(nextTab.getByLabel('Select page number')).toHaveValue('2');
    await expect(page.getByLabel('Select page number')).toHaveValue('1');
    await nextTab.close();
});

test('notification digest displays the delivered game and version', async ({ page }, info) => {
    await page.goto('/user/notifications/digest/2026-05-03');
    await expect(page.getByRole('heading', { name: 'Your Notifications', exact: true })).toBeVisible();
    await expect(page.locator('main h3').getByRole('link', { name: fixture.originalName, exact: true })).toHaveAttribute(
        'href',
        new RegExp(`/games/${fixture.slug}$`),
    );
    await expect(page.getByText('Version 1.0.0 · Sent via browser notification', { exact: true })).toBeVisible();
    await page.screenshot({ path: info.outputPath('notification-digest.png'), fullPage: true });
});

for (const responseStatus of [200, 500]) {
    test(`ignores a previous games late history response (${responseStatus})`, async ({ page }) => {
        let pending: Route | undefined;
        let count = 0;
        await page.route('**/raters/*/games/*/history', async (route) => {
            if (++count === 1) {
                pending = route;
                return;
            }
            await route.fulfill({ json: { ratings: [{ id: 2, rating: 4, review: 'SECOND GAME HISTORY', is_visible: true }] } });
        });
        await page.goto(`/raters/${fixture.raterId}`);
        const openers = page.getByRole('button', { name: '(1 previous rating)', exact: true });
        await expect(openers).toHaveCount(2);
        await openers.nth(0).click();
        await expect.poll(() => Boolean(pending)).toBe(true);
        await page.getByRole('button', { name: 'Close dialog', exact: true }).click();
        await openers.nth(1).click();
        const dialog = page.getByRole('dialog');
        await expect(dialog).toContainText('SECOND GAME HISTORY');
        const title = await dialog.locator('h2').innerText();
        const response = page.waitForResponse((response) => response.url() === pending!.request().url());
        await pending!.fulfill({ status: responseStatus, json: { ratings: [{ id: 1, rating: 1, review: 'FIRST GAME HISTORY', is_visible: true }] } });
        await (await response).finished();
        await expect(dialog).toContainText('SECOND GAME HISTORY');
        await expect(dialog).not.toContainText('FIRST GAME HISTORY');
        await expect(dialog).not.toContainText('Unable to load rating history.');
        await expect(dialog.locator('h2')).toHaveText(title);
    });
}

test('Discord switches persist changed values after reload', async ({ page }) => {
    await page.goto(`/discord/${fixture.discordServerId}`);
    await expect(page.getByRole('heading', { name: 'General Settings', exact: true })).toBeVisible();
    for (const label of ['Include game description', 'Include thumbnail', 'Include ratings']) {
        const checkbox = page.getByRole('checkbox', { name: label, exact: true });
        const original = await checkbox.isChecked();
        const saved = page.waitForResponse((response) => response.url().endsWith('/config') && response.request().method() === 'PUT');
        await checkbox.locator('..').click();
        expect((await saved).ok()).toBe(true);
        await expect(page.getByText('Configuration saved', { exact: true })).toBeVisible();
        await page.reload();
        await expect(checkbox).toBeChecked({ checked: !original });
    }
});

for (const viewport of [
    { width: 1440, height: 1000 },
    { width: 375, height: 812 },
]) {
    test(`Discord data and tabs survive reload and Back at ${viewport.width}px`, async ({ page }, info) => {
        await page.setViewportSize(viewport);
        await page.goto(`/discord/${fixture.discordServerId}?source=regression`);
        await expect(page.getByRole('heading', { name: 'General Settings', exact: true })).toBeVisible();
        await page.getByRole('tab', { name: 'Ignored VNs', exact: true }).click();
        await expect(page).toHaveURL(/source=regression&tab=ignored$/);
        await expect(page.locator('main tbody tr')).toHaveCount(1);
        await page.reload();
        await expect(page.getByRole('tab', { name: 'Ignored VNs', exact: true })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('main tbody tr')).toHaveCount(1);
        await page.getByRole('tab', { name: 'History', exact: true }).click();
        await expect(page.getByRole('heading', { name: 'Notification History', exact: true })).toBeVisible();
        await expect(page.locator('main tbody tr')).toHaveCount(1);
        await expect(page.locator('main tbody tr')).toContainText('sent');
        await expect(page.locator('main tbody tr')).toContainText(fixture.originalName);
        await page.goBack();
        await expect(page.getByRole('tab', { name: 'Ignored VNs', exact: true })).toHaveAttribute('aria-selected', 'true');
        await page.goForward();
        await expect(page.getByRole('tab', { name: 'History', exact: true })).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('main tbody tr')).toHaveCount(1);
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        await page.screenshot({ path: info.outputPath('discord-history.png'), fullPage: true });
    });
}
