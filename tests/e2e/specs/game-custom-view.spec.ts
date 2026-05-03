import { expect, test } from '@playwright/test';
import { createGameViewFixture } from '../support/laravel';

test('developer preview and guest rendering follow the selected game view mode', async ({ baseURL, browser, page }) => {
  const fixture = createGameViewFixture();

  await page.context().addCookies([
    {
      ...fixture.authCookie,
      url: baseURL ?? 'http://localhost:5273',
      httpOnly: true,
      sameSite: 'Lax',
    },
  ]);

  await page.goto(`/games/${fixture.slug}`);
  await expect(page.getByRole('heading', { name: fixture.customName })).toBeVisible();
  await expect(page.getByText(fixture.customDescription)).toBeVisible();

  await page.getByRole('button', { name: 'Preview visitor view' }).click();
  await expect(page.getByRole('heading', { name: fixture.originalName })).toBeVisible();
  await expect(page.getByText(fixture.originalDescription)).toBeVisible();

  await page.getByRole('button', { name: 'Exit preview' }).click();
  const [customModeResponse] = await Promise.all([
    page.waitForResponse(/\/browser-api\/games\/\d+\/view-mode$/),
    page.getByTitle('Show visitors custom content').click(),
  ]);
  expect(customModeResponse.status(), await customModeResponse.text()).toBe(200);
  await page.getByRole('button', { name: 'Preview visitor view' }).click();
  await expect(page.getByRole('heading', { name: fixture.customName })).toBeVisible();
  await expect(page.getByText(fixture.customDescription)).toBeVisible();

  const guestContext = await browser.newContext();
  const guestPage = await guestContext.newPage();
  await guestPage.goto(`/games/${fixture.slug}`);
  await expect(guestPage.getByRole('heading', { name: fixture.customName })).toBeVisible();
  await expect(guestPage.getByText(fixture.customDescription)).toBeVisible();
  await guestContext.close();

  await page.getByRole('button', { name: 'Exit preview' }).click();
  const [originalModeResponse] = await Promise.all([
    page.waitForResponse(/\/browser-api\/games\/\d+\/view-mode$/),
    page.getByTitle('Show visitors original itch.io content').click(),
  ]);
  expect(originalModeResponse.status(), await originalModeResponse.text()).toBe(200);

  const originalGuestContext = await browser.newContext();
  const originalGuestPage = await originalGuestContext.newPage();
  await originalGuestPage.goto(`/games/${fixture.slug}`);
  await expect(originalGuestPage.getByRole('heading', { name: fixture.originalName })).toBeVisible();
  await expect(originalGuestPage.getByText(fixture.originalDescription)).toBeVisible();
  await originalGuestContext.close();
});
