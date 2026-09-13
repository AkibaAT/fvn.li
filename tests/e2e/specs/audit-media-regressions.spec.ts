import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
let f;
test.beforeEach(async ({ page, baseURL }, info) => {
    const args = ['tests/e2e/support/make-audit-fixture.php'];
    if (info.title.includes('without a thumbnail')) args.push('--without-thumbnail');
    f = JSON.parse(execFileSync('php', args, { encoding: 'utf8' }));
    await page.context().addCookies([{ ...f.authCookie, url: baseURL }]);
    await page.route('**/storage/e2e/**', (r) =>
        r.fulfill({
            contentType: 'image/svg+xml',
            body: '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720" viewBox="0 0 1280 720"><rect width="1280" height="720" fill="#236a84"/><text x="640" y="380" text-anchor="middle" fill="white" font-family="sans-serif" font-size="48">Audit screenshot</text></svg>',
        }),
    );
});
test('screenshot edits survive Back and delete the chosen image', async ({ page }) => {
    await page.goto(`/games/${f.slug}`);
    page.on('dialog', (d) => d.accept());
    await expect(page.locator('#screenshots-gallery img')).toHaveCount(3);
    await page.getByRole('button', { name: 'Delete screenshot', exact: true }).nth(0).click();
    await expect(page.locator('#screenshots-gallery img')).toHaveCount(2);
    await expect(page.locator('#screenshots-gallery img').nth(0)).toHaveAttribute('src', /audit-shot-b/);
    await page.getByRole('navigation', { name: 'Main navigation', exact: true }).getByRole('link', { name: 'Lists', exact: true }).click();
    await expect(page).toHaveURL(/\/lists(?:\/public)?$/);
    await page.goBack();
    await expect(page.locator('#screenshots-gallery img')).toHaveCount(2);
    await expect(page.locator('#screenshots-gallery img').nth(1)).toHaveAttribute('src', /audit-shot-c/);
    await page.getByRole('button', { name: 'Delete screenshot', exact: true }).nth(1).click();
    await expect(page.locator('#screenshots-gallery img')).toHaveCount(1);
    await expect(page.locator('#screenshots-gallery img').nth(0)).toHaveAttribute('src', /audit-shot-b/);
    await page.reload();
    await expect(page.locator('#screenshots-gallery img')).toHaveCount(1);
    await expect(page.locator('#screenshots-gallery img')).toHaveAttribute('src', /audit-shot-b/);
});
test('lightbox contains keyboard focus and restores the opener', async ({ page }, info) => {
    await page.goto(`/games/${f.slug}`);
    const link = page.locator('#screenshots-gallery a').first();
    await link.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('button', { name: 'Zoom in screenshot', exact: true })).toBeVisible();
    await expect(page.getByRole('dialog', { name: /Screenshots for/ })).toBeVisible();
    await page.screenshot({ path: info.outputPath('desktop-lightbox.png') });
    for (let i = 0; i < 12; i++) {
        await page.keyboard.press('Tab');
        expect(await page.evaluate(() => document.activeElement?.closest('dialog') !== null)).toBe(true);
    }
    await page.keyboard.press('Escape');
    await expect(link).toBeFocused();
});
test('games without a thumbnail retain the upload control', async ({ page }) => {
    await page.goto(`/games/${f.slug}`);
    await expect(page.getByText('No thumbnail', { exact: true })).toBeVisible();
    const input = page.getByLabel('Upload thumbnail', { exact: true });
    await input.focus();
    await expect(input).toBeFocused();
});
test('media upload controls are keyboard reachable', async ({ page }) => {
    await page.goto(`/games/${f.slug}`);
    const files = page.getByLabel(/^(Upload thumbnail|Add screenshots)$/);
    await expect(files).toHaveCount(2);
    for (const input of await files.all()) {
        await input.focus();
        await expect(input).toBeFocused();
        expect(await input.evaluate((el) => getComputedStyle(el).display)).not.toBe('none');
    }
});
test('first tag condition picker accepts selections independently', async ({ page }) => {
    await page.route('**/browser-api/discord/servers/*/channels', (r) => r.fulfill({ json: { channels: [] } }));
    await page.route('**/browser-api/discord/servers/*/roles', (r) => r.fulfill({ json: { roles: [] } }));
    await page.goto(`/discord/${f.discordServerId}?tab=routing`);
    await page.getByRole('button', { name: /^Audit tags rule Priority/ }).click();
    await page.getByRole('button', { name: 'audit alpha', exact: true }).click();
    await expect(page.getByRole('textbox', { name: 'Filter values', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Audit Beta', exact: true }).click();
    await expect(page.getByRole('textbox', { name: 'Filter values', exact: true })).toBeVisible();
    await expect(page.getByRole('button', { name: 'audit alpha, audit beta', exact: true })).toBeVisible();
});
test('route map failed version request offers retry and preserves the current graph', async ({ page }) => {
    const errors = [];
    page.on('pageerror', (e) => errors.push(e.message));
    await page.goto(`/games/${f.slug}/route-map?version_id=${f.olderVersionId}`);
    await page.route('**/route-graph*', (r) =>
        r.fulfill({ status: 500, contentType: 'application/json', body: JSON.stringify({ message: 'Audit graph failed' }) }),
    );
    await page.locator('main select').first().selectOption(String(f.versionId));
    await expect(page.getByRole('alert')).toContainText('Audit graph failed');
    expect(errors).toEqual([]);
    await expect(page.locator('main select').first()).toHaveValue(String(f.olderVersionId));
    await page.unroute('**/route-graph*');
    await page.getByRole('button', { name: 'Retry', exact: true }).click();
    await expect(page.getByRole('alert')).toHaveCount(0);
    await expect(page.locator('main select').first()).toHaveValue(String(f.versionId));
    await expect(page.locator('main select').first()).toBeEnabled();
});

test('service worker removes old personalized caches and never serves authenticated HTML offline', async ({ page, context, baseURL }) => {
    test.skip(!baseURL?.includes('localhost'), 'Service workers require the secure loopback browser origin.');
    await page.goto('/robots.txt');
    await page.evaluate(async () => {
        const old = await caches.open('fvn-cache-v2');
        await old.put('/', new Response('private account content'));
    });
    await page.goto(`/games/${f.slug}`);
    await page.evaluate(() => navigator.serviceWorker.ready);
    await expect.poll(() => page.evaluate(async () => (await caches.keys()).includes('fvn-cache-v2'))).toBe(false);
    await page.reload();
    await expect.poll(() => page.evaluate(async () => (await (await caches.open('fvn-cache-v3')).keys()).length)).toBeGreaterThan(0);
    const cachedPaths = await page.evaluate(async () =>
        (
            await Promise.all((await caches.keys()).map(async (name) => (await (await caches.open(name)).keys()).map((r) => new URL(r.url).pathname)))
        ).flat(),
    );
    expect(cachedPaths.every((path) => path.startsWith('/build/assets/'))).toBe(true);
    await context.clearCookies();
    await context.setOffline(true);
    const response = await page.evaluate(async () =>
        fetch(location.href, { cache: 'no-store' })
            .then((r) => r.text())
            .catch(() => null),
    );
    expect(response).toBeNull();
    await context.setOffline(false);
});

test('push synchronization follows account changes in the same tab', async ({ page, context, baseURL }) => {
    test.skip(!baseURL?.includes('localhost'), 'Push APIs require the secure loopback browser origin.');
    const second = JSON.parse(execFileSync('php', ['tests/e2e/support/make-audit-fixture.php'], { encoding: 'utf8' }));
    await page.addInitScript(() => {
        Object.defineProperty(Notification, 'permission', { configurable: true, get: () => 'granted' });
        Object.defineProperty(PushManager.prototype, 'getSubscription', {
            configurable: true,
            value: async () => ({ toJSON: () => ({ endpoint: 'https://push.example/audit', keys: { p256dh: 'audit-key', auth: 'audit-auth' } }) }),
        });
    });
    let syncs = 0;
    await page.route('**/browser-api/push-subscriptions', (route) => {
        syncs++;
        return route.fulfill({ json: { success: true } });
    });
    await page.goto('/lists/public');
    await expect.poll(() => syncs).toBe(1);
    await context.addCookies([{ ...second.authCookie, url: baseURL }]);
    await page.getByRole('navigation', { name: 'Main navigation', exact: true }).getByRole('link', { name: 'Games', exact: true }).click();
    await expect(page.getByRole('banner', { name: 'Main navigation', exact: true })).toContainText(second.userName);
    await expect.poll(() => syncs).toBe(2);
});

test('lightbox and upload controls fit a narrow viewport', async ({ page }, info) => {
    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto(`/games/${f.slug}`);
    await page.locator('#screenshots-gallery a').first().click();
    await expect(page.getByRole('dialog', { name: /Screenshots for/ })).toBeVisible();
    await page.screenshot({ path: info.outputPath('mobile-lightbox.png') });
    const overflow = await page.evaluate(() =>
        [...document.querySelectorAll('main *')]
            .filter((el) => el.getBoundingClientRect().right > innerWidth)
            .map((el) => ({ tag: el.tagName, class: el.className, width: el.getBoundingClientRect().width }))
            .slice(0, 15),
    );
    expect(await page.evaluate(() => document.documentElement.scrollWidth), JSON.stringify(overflow)).toBeLessThanOrEqual(390);
});
