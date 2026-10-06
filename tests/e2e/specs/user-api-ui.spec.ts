import { expect, test } from '@playwright/test';
import { createGameViewFixture } from '../support/laravel';

test('Swagger follows light, dark, and live system appearance changes', async ({ page, baseURL }) => {
    const fixture = createGameViewFixture();
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);
    await page.emulateMedia({ colorScheme: 'light' });
    await page.goto('/developers/swagger');

    const schemes = page.locator('.swagger-ui .scheme-container');
    await expect(schemes).toHaveCSS('background-color', 'rgb(255, 255, 255)');

    await page.getByRole('button', { name: 'Change appearance' }).click();
    await page.getByRole('menuitemradio', { name: /Dark/ }).click();
    await expect(schemes).toHaveCSS('background-color', 'rgb(28, 32, 34)');
    await page.getByRole('button', { name: 'Authorize', exact: true }).first().click();
    await expect(page.locator('.swagger-ui .modal-ux')).toHaveCSS('background-color', 'rgb(42, 46, 48)');
    await expect(page.getByRole('textbox', { name: 'auth-bearer-value' })).toHaveCSS('color', 'rgb(240, 241, 241)');
    await page.getByRole('button', { name: 'Close', exact: true }).click();

    await page.getByRole('button', { name: 'Change appearance' }).click();
    await page.getByRole('menuitemradio', { name: /Light/ }).click();
    await expect(schemes).toHaveCSS('background-color', 'rgb(255, 255, 255)');

    await page.getByRole('button', { name: 'Change appearance' }).click();
    await page.getByRole('menuitemradio', { name: /System/ }).click();
    await page.emulateMedia({ colorScheme: 'dark' });
    await expect(schemes).toHaveCSS('background-color', 'rgb(28, 32, 34)');
    await page.reload();
    await expect(schemes).toHaveCSS('background-color', 'rgb(28, 32, 34)');
    await page.emulateMedia({ colorScheme: 'light' });
    await expect(schemes).toHaveCSS('background-color', 'rgb(255, 255, 255)');
});

test('dashboard tokens and game private tags work in the browser', async ({ page, baseURL }) => {
    const fixture = createGameViewFixture();
    await page.context().addCookies([{ ...fixture.authCookie, url: baseURL! }]);

    await page.goto('/dashboard');
    await expect(page.getByRole('heading', { name: 'API access' })).toBeVisible();
    await page.getByRole('textbox', { name: 'Token name' }).fill('Browser test script');
    await page.getByRole('button', { name: 'Create token' }).click();
    const token = await page.getByText(/^[0-9]+\|/).innerText();
    const apiResponse = await page.request.get('/api/v1/games', { headers: { Authorization: `Bearer ${token}` } });
    expect(apiResponse.status()).toBe(200);

    await page.getByRole('link', { name: 'try Swagger UI' }).click();
    await expect(page.getByRole('heading', { name: 'API explorer', exact: true })).toBeVisible();
    await expect(page.getByRole('navigation', { name: 'Main navigation' })).toBeVisible();
    await expect(page.getByText('Search visible catalog games')).toBeVisible();
    await page.getByRole('button', { name: 'Authorize', exact: true }).first().click();
    await page.getByRole('textbox', { name: 'auth-bearer-value' }).fill(token);
    await page.getByRole('button', { name: 'Apply credentials' }).click();
    await page.getByRole('button', { name: 'Close', exact: true }).click();
    await page.getByText('Search visible catalog games').click();
    await page.getByRole('button', { name: 'Try it out' }).first().click();
    const swaggerResponse = page.waitForResponse((response) => response.url().endsWith('/api/v1/games') && response.request().method() === 'GET');
    await page.getByRole('button', { name: 'Execute' }).first().click();
    expect((await swaggerResponse).status()).toBe(200);

    await page.getByRole('link', { name: 'API guide' }).click();
    await expect(page.getByRole('heading', { name: 'Developer API', exact: true })).toBeVisible();
    await expect(page.getByRole('contentinfo').getByRole('link', { name: 'Developer API' })).toBeVisible();
    await page.getByRole('button', { name: /E2E Admin/ }).click();
    await expect(page.getByRole('menuitem', { name: 'API Explorer' })).toBeVisible();
    await page.getByRole('menuitem', { name: 'API Explorer' }).click();
    await expect(page.getByText('Search visible catalog games')).toBeVisible();

    await page.goto('/dashboard');
    await expect(page.getByText('Copy this token now')).toHaveCount(0);
    const tokenRow = page.getByRole('listitem').filter({ hasText: 'Browser test script' });
    await tokenRow.getByRole('button', { name: 'Extend' }).click();
    await expect(page.getByText('Token extended')).toBeVisible();

    await page.goto(`/games/${fixture.slug}`);
    await page.getByRole('button', { name: 'Private tags' }).first().click();
    await page.getByRole('textbox', { name: 'New private tag' }).fill('Browser shelf');
    await page.getByRole('button', { name: 'Add', exact: true }).click();
    await expect(page.getByRole('checkbox', { name: 'Browser shelf' })).toBeChecked();

    await page.goto('/dashboard');
    await page.getByRole('tab', { name: 'Private Tags', exact: true }).click();
    await expect(page.getByRole('button', { name: /^Browser shelf/ })).toBeVisible();
});
