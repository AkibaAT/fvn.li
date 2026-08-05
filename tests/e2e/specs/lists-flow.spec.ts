import { test, expect, type Page } from '@playwright/test';
import { createGameViewFixture, type GameViewFixture } from '../support/laravel';

/**
 * Functional coverage for the lists CRUD flow, exercising the typed api layer
 * (storeVnList, toggleVnListVisibility, destroyVnList) through the real UI.
 */

const baseURL = process.env.E2E_BASE_URL || 'http://web:8088';
let authFixture: GameViewFixture;

test.beforeAll(() => {
    authFixture = createGameViewFixture();
});

async function authenticatePage(page: Page) {
    await page.context().addCookies([
        {
            name: authFixture.authCookie.name,
            value: authFixture.authCookie.value,
            url: baseURL,
        },
    ]);
}

test('user can create, toggle visibility on, and delete a list', async ({ page }) => {
    await authenticatePage(page);

    const listName = `E2E Flow List ${Date.now()}`;

    await page.goto('/lists/create');
    await page.locator('#name').fill(listName);
    await page.getByRole('button', { name: 'Create List' }).click();

    await expect(page).toHaveURL(/\/lists\/\d+/);
    await expect(page.getByRole('heading', { name: listName })).toBeVisible();

    // The list starts private; the toggle round-trips through the api layer.
    await page.getByRole('button', { name: 'Make Public' }).click();
    await expect(page.getByRole('button', { name: 'Make Private' })).toBeVisible();

    await page.goto('/lists');
    const card = page.locator('[aria-labelledby^="list-title-"]').filter({ hasText: listName });
    await expect(card).toBeVisible();

    page.on('dialog', (dialog) => dialog.accept());
    await card.getByRole('button', { name: 'Delete' }).click();
    await expect(card).toHaveCount(0);
});
