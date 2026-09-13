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
