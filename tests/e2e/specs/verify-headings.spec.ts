import { expect, test, type Page } from '@playwright/test';
import { assertStructuredHeadings } from '../support/accessibilityAssertions';
import { createGameViewFixture, type GameViewFixture } from '../support/laravel';

const baseURL = process.env.E2E_BASE_URL || 'http://web:8088';
let authFixture: GameViewFixture;

async function authenticatePage(page: Page) {
    await page.context().addCookies([
        {
            name: authFixture.authCookie.name,
            value: authFixture.authCookie.value,
            url: baseURL,
        },
    ]);
}

async function gotoReady(page: Page, path: string) {
    await page.goto(path);
    await page.waitForLoadState('networkidle');
    await expect(page.locator('#main-content')).toBeVisible();
}

async function gotoAuthenticatedReady(page: Page, path: string) {
    await authenticatePage(page);
    await gotoReady(page, path);
}

async function openDashboardTab(page: Page, hash: '' | '#my-games' | '#additions' | '#search', tabName: string) {
    await gotoAuthenticatedReady(page, `/dashboard${hash}`);
    await expect(page.getByRole('tab', { name: tabName })).toHaveAttribute('aria-selected', 'true');
}

test.beforeAll(() => {
    authFixture = createGameViewFixture();
});

test.describe('Heading Structure @accessibility', () => {
    const publicPages = [
        { name: 'homepage', path: '/' },
        { name: 'games-listing', path: '/games' },
        { name: 'login', path: '/login' },
        { name: 'public-lists', path: '/lists/public' },
    ] as const;

    for (const pageInfo of publicPages) {
        test(`${pageInfo.name} has structured headings`, async ({ page }) => {
            await gotoReady(page, pageInfo.path);
            await assertStructuredHeadings(page, pageInfo.name);
        });
    }

    const authenticatedPages = [
        { name: 'lists-authenticated', path: '/lists' },
        { name: 'lists-create-authenticated', path: '/lists/create' },
        { name: 'my-games-authenticated', path: '/my/games' },
        { name: 'system-status', path: '/system/status' },
    ] as const;

    for (const pageInfo of authenticatedPages) {
        test(`${pageInfo.name} has structured headings`, async ({ page }) => {
            await gotoAuthenticatedReady(page, pageInfo.path);
            await assertStructuredHeadings(page, pageInfo.name);
        });
    }

    const dashboardTabs = [
        { name: 'dashboard-account', hash: '', tabName: 'Account' },
        { name: 'dashboard-my-games', hash: '#my-games', tabName: 'My Games' },
        { name: 'dashboard-additions', hash: '#additions', tabName: 'VN Additions' },
        { name: 'dashboard-search-preferences', hash: '#search', tabName: 'Search Preferences' },
    ] as const;

    for (const tab of dashboardTabs) {
        test(`${tab.name} has structured headings`, async ({ page }) => {
            await openDashboardTab(page, tab.hash, tab.tabName);
            await assertStructuredHeadings(page, tab.name);
        });
    }

    test('bug report workflow states have structured headings', async ({ page }) => {
        await gotoAuthenticatedReady(page, '/dashboard');
        await page.getByRole('button', { name: /Report a Bug/i }).click();
        await expect(page.getByRole('dialog', { name: 'Report a Bug' })).toBeVisible();
        await assertStructuredHeadings(page, 'bug-report-dialog');

        await page.getByRole('button', { name: 'Cancel' }).click();
        await expect(page.getByRole('dialog', { name: 'Report a Bug' })).toBeHidden();
        await page.getByText('Fixture dashboard bug report').click();
        await expect(page.getByRole('dialog', { name: /Bug Report #/ })).toBeVisible();
        await assertStructuredHeadings(page, 'bug-report-conversation');
    });

    test('addition request workflow states have structured headings', async ({ page }) => {
        await openDashboardTab(page, '#additions', 'VN Additions');
        await page.locator('#game-urls').fill(`https://heading-${Date.now()}.itch.io/requested-vn`);
        await assertStructuredHeadings(page, 'addition-request-form-filled');

        await page.getByRole('button', { name: 'Submit Requests' }).click();
        await expect(page.getByText('Requests submitted successfully')).toBeVisible();
        await assertStructuredHeadings(page, 'addition-request-submitted');
    });

    test('logged-in game detail analytics page has structured headings', async ({ page }) => {
        await gotoAuthenticatedReady(page, `/games/${authFixture.slug}`);
        await expect(page.locator('#analytics')).toBeVisible();
        await expect(page.getByRole('heading', { name: 'Analytics' })).toBeVisible();
        await assertStructuredHeadings(page, 'game-detail-authenticated-analytics');
    });
});
