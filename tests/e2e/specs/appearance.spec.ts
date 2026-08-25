import { expect, test } from '@playwright/test';

test('appearance cookie wins over stale local storage before first paint', async ({ baseURL, page }) => {
    await page.addInitScript(() => {
        localStorage.setItem('appearance', 'light');
    });
    await page.context().addCookies([
        {
            name: 'appearance',
            value: 'dark',
            url: baseURL ?? 'http://localhost:5273',
            sameSite: 'Lax',
        },
    ]);

    const response = await page.goto('/');
    expect(await response?.text()).toMatch(/<html[^>]+class="dark"/);
    await expect(page.locator('html')).toHaveClass(/\bdark\b/);
    const appearanceIcons = page.getByRole('button', { name: 'Change appearance' }).locator('svg');
    await expect(appearanceIcons.nth(0)).toBeHidden();
    await expect(appearanceIcons.nth(1)).toBeVisible();
    await expect.poll(() => page.evaluate(() => localStorage.getItem('appearance'))).toBe('light');
});
