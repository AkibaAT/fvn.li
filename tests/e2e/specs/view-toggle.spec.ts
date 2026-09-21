import { test, expect } from '@playwright/test';
import { createGameViewFixture } from '../support/laravel';

test.describe('home catalogue layout toggle', () => {
    test.beforeAll(() => {
        // The toggle only renders when the catalogue has teasers to show.
        createGameViewFixture();
    });

    test('switches between cards and the row list and remembers the choice', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const cardsToggle = page.getByRole('button', { name: 'Cards' });
        const listToggle = page.getByRole('button', { name: 'List' });
        const rows = page.locator('[data-game-update-row]');

        await expect(cardsToggle).toHaveAttribute('aria-pressed', 'true');
        await expect(rows).toHaveCount(0);
        await expect(page.locator('a[href*="/games/"]').first()).toBeVisible();

        await expect(async () => {
            await listToggle.click();
            await expect(listToggle).toHaveAttribute('aria-pressed', 'true');
            await expect(rows.first()).toBeVisible();
        }).toPass({ timeout: 10_000 });

        await expect(page.locator('a[href*="/games/"]').first()).toBeVisible();

        await expect(async () => {
            await cardsToggle.click();
            await expect(cardsToggle).toHaveAttribute('aria-pressed', 'true');
            await expect(rows).toHaveCount(0);
        }).toPass({ timeout: 10_000 });

        await expect(async () => {
            await listToggle.click();
            await expect(rows.first()).toBeVisible();
        }).toPass({ timeout: 10_000 });

        await page.reload();
        await expect(page.getByRole('button', { name: 'List' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('[data-game-update-row]').first()).toBeVisible();
    });
});

test.describe('games listing layout toggle', () => {
    test.beforeAll(() => {
        createGameViewFixture();
    });

    test('switches results between cards and rows and keeps the layout through filters', async ({ page }) => {
        await page.goto('/games');
        await page.waitForLoadState('networkidle');

        const cardsToggle = page.getByRole('button', { name: 'Cards' });
        const listToggle = page.getByRole('button', { name: 'List' });
        const rows = page.locator('[data-game-list-row]');

        await expect(cardsToggle).toHaveAttribute('aria-pressed', 'true');
        await expect(rows).toHaveCount(0);
        await expect(page.locator('a[href*="/games/"]').first()).toBeVisible();

        await expect(async () => {
            await listToggle.click();
            await expect(listToggle).toHaveAttribute('aria-pressed', 'true');
            await expect(rows.first()).toBeVisible();
        }).toPass({ timeout: 10_000 });

        // Filtering is a partial reload: the layout must not snap back to cards.
        const sort = page.getByLabel('Sort by', { exact: true });
        const current = await sort.inputValue();
        const options = (
            await sort.locator('option').evaluateAll((elements) => elements.map((element) => (element as HTMLOptionElement).value))
        ).filter((value) => value && value !== current);
        await sort.selectOption(options[0]);
        await expect(page).toHaveURL(/sort=/);
        await expect(page.getByRole('button', { name: 'List' })).toHaveAttribute('aria-pressed', 'true');
        await expect(rows.first()).toBeVisible();

        await page.reload();
        await expect(page.getByRole('button', { name: 'List' })).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('[data-game-list-row]').first()).toBeVisible();

        await expect(async () => {
            await cardsToggle.click();
            await expect(cardsToggle).toHaveAttribute('aria-pressed', 'true');
            await expect(rows).toHaveCount(0);
        }).toPass({ timeout: 10_000 });
    });
});
