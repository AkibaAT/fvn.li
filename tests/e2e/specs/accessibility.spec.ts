import { test, expect, type Page } from '@playwright/test';
import { scanPage, generateHtmlReport } from '../utils/accessibility-scanner';
import { createGameViewFixture, type GameViewFixture } from '../support/laravel';
import { assertStructuredHeadings, assertVisibleImagesHaveAltText } from '../support/accessibilityAssertions';
import { writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';

/**
 * Accessibility test suite.
 *
 * These tests scan pages for WCAG 2.2 Level AA compliance using axe-core.
 * Reports are generated in tests/e2e/reports/accessibility/
 */

const reportsDir = join(process.cwd(), 'tests/e2e/reports/accessibility');
const baseURL = process.env.E2E_BASE_URL || 'http://web:8088';
const appearances = ['light', 'dark'] as const;
type Appearance = (typeof appearances)[number];
let authFixture: GameViewFixture;

function saveReport(pageName: string, results: any) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${pageName}-${timestamp}.html`;
    const filepath = join(reportsDir, filename);
    const html = generateHtmlReport(results);
    writeFileSync(filepath, html);
    console.log(`Report saved: ${filepath}`);
}

async function gotoWithAppearance(page: Page, path: string, appearance: Appearance) {
    await page.addInitScript((mode) => {
        localStorage.setItem('appearance', mode);
    }, appearance);

    await page.goto(path);
    await page.waitForLoadState('networkidle');

    await page.evaluate((mode) => {
        localStorage.setItem('appearance', mode);
        document.cookie = `appearance=${mode};path=/;max-age=31536000;SameSite=Lax`;
        document.documentElement.classList.toggle('dark', mode === 'dark');
    }, appearance);
    await expect
        .poll(() => page.evaluate((mode) => document.documentElement.classList.contains('dark') === (mode === 'dark'), appearance))
        .toBe(true);
    await expect(page.locator('#main-content')).toBeVisible();
}

async function authenticatePage(page: Page) {
    await page.context().addCookies([
        {
            name: authFixture.authCookie.name,
            value: authFixture.authCookie.value,
            url: baseURL,
        },
    ]);
}

async function gotoAuthenticatedWithAppearance(page: Page, path: string, appearance: Appearance) {
    await authenticatePage(page);
    await gotoWithAppearance(page, path, appearance);
}

async function scanAndAssert(page: Page, reportName: string) {
    await assertStructuredHeadings(page, reportName);
    await assertVisibleImagesHaveAltText(page, reportName);

    const results = await scanPage(page, {
        failOnViolations: false,
    });

    saveReport(reportName, results);

    console.log(`\n${reportName} Accessibility Summary:`);
    console.log(`  Critical: ${results.violationsBySeverity.critical}`);
    console.log(`  Serious:  ${results.violationsBySeverity.serious}`);
    console.log(`  Moderate: ${results.violationsBySeverity.moderate}`);
    console.log(`  Minor:    ${results.violationsBySeverity.minor}`);
    console.log(`  Passes:   ${results.passes}\n`);

    expect(results.violations).toHaveLength(0);
}

async function expectStrongFocusIndicator(locator: ReturnType<Page['locator']>) {
    await expect(locator).toBeFocused();
    await expect(locator).toBeVisible();
    await expect
        .poll(async () =>
            locator.evaluate((el) => {
                const styles = window.getComputedStyle(el);
                return {
                    outlineStyle: styles.outlineStyle,
                    outlineWidth: styles.outlineWidth,
                };
            }),
        )
        .toMatchObject({
            outlineStyle: 'solid',
            outlineWidth: '3px',
        });
}

async function openDashboardTab(page: Page, appearance: Appearance, hash: '' | '#my-games' | '#additions' | '#search', tabName: string) {
    await gotoAuthenticatedWithAppearance(page, `/dashboard${hash}`, appearance);
    await expect(page.getByRole('tab', { name: tabName })).toHaveAttribute('aria-selected', 'true');
}

test.beforeAll(() => {
    mkdirSync(reportsDir, { recursive: true });
    authFixture = createGameViewFixture();
});

test.describe('Accessibility Scans @accessibility', () => {
    const pagesToScan = [
        { name: 'homepage', path: '/' },
        { name: 'games-listing', path: '/games' },
        { name: 'login', path: '/login' },
        { name: 'public-lists', path: '/lists/public' },
    ] as const;

    for (const pageInfo of pagesToScan) {
        for (const appearance of appearances) {
            test(`${pageInfo.name} accessibility scan (${appearance})`, async ({ page }) => {
                await gotoWithAppearance(page, pageInfo.path, appearance);
                await scanAndAssert(page, `${pageInfo.name}-${appearance}`);
            });
        }
    }

    const authenticatedPagesToScan = [
        { name: 'lists-authenticated', path: '/lists' },
        { name: 'lists-create-authenticated', path: '/lists/create' },
        { name: 'my-games-authenticated', path: '/my/games' },
        { name: 'system-status-authenticated', path: '/system/status' },
    ] as const;

    for (const pageInfo of authenticatedPagesToScan) {
        for (const appearance of appearances) {
            test(`${pageInfo.name} accessibility scan (${appearance})`, async ({ page }) => {
                await gotoAuthenticatedWithAppearance(page, pageInfo.path, appearance);
                await scanAndAssert(page, `${pageInfo.name}-${appearance}`);
            });
        }
    }

    const dashboardTabs = [
        { name: 'dashboard-account', hash: '', tabName: 'Account' },
        { name: 'dashboard-my-games', hash: '#my-games', tabName: 'My Games' },
        { name: 'dashboard-additions', hash: '#additions', tabName: 'VN Additions' },
        { name: 'dashboard-search-preferences', hash: '#search', tabName: 'Search Preferences' },
    ] as const;

    for (const tab of dashboardTabs) {
        for (const appearance of appearances) {
            test(`${tab.name} authenticated control panel tab (${appearance})`, async ({ page }) => {
                await openDashboardTab(page, appearance, tab.hash, tab.tabName);
                await scanAndAssert(page, `${tab.name}-${appearance}`);
            });
        }
    }

    for (const appearance of appearances) {
        test(`bug report workflow accessibility (${appearance})`, async ({ page }) => {
            await gotoAuthenticatedWithAppearance(page, '/dashboard', appearance);
            await page.getByRole('button', { name: /Report a Bug/i }).click();
            await expect(page.getByRole('dialog', { name: 'Report a Bug' })).toBeVisible();
            await scanAndAssert(page, `bug-report-dialog-${appearance}`);

            await page.locator('#bug-description').fill(`Accessibility workflow bug report ${appearance} ${Date.now()}`);
            await page.getByRole('button', { name: 'Submit Report' }).click();
            await expect(page.getByRole('dialog', { name: 'Report a Bug' })).toBeHidden();

            await expect(page.getByRole('heading', { name: 'Your Bug Reports' })).toBeVisible();
            await page.getByText('Fixture dashboard bug report').click();
            await expect(page.getByRole('dialog', { name: /Bug Report #/ })).toBeVisible();
            await scanAndAssert(page, `bug-report-conversation-${appearance}`);

            await page.locator('#new-comment').fill(`Follow-up details from ${appearance} accessibility workflow.`);
            await page.getByRole('button', { name: 'Send' }).click();
            await expect(page.getByText(`Follow-up details from ${appearance} accessibility workflow.`)).toBeVisible();
        });

        test(`addition request workflow accessibility (${appearance})`, async ({ page }) => {
            await openDashboardTab(page, appearance, '#additions', 'VN Additions');

            const requestUrl = `https://a11y-${appearance}-${Date.now()}.itch.io/requested-vn`;
            await page.locator('#game-urls').fill(requestUrl);
            await scanAndAssert(page, `addition-request-form-filled-${appearance}`);

            await page.getByRole('button', { name: 'Submit Requests' }).click();
            await expect(page.getByText(requestUrl)).toBeVisible();
            await scanAndAssert(page, `addition-request-submitted-${appearance}`);
        });

        test(`game detail logged-in analytics accessibility (${appearance})`, async ({ page }) => {
            await gotoAuthenticatedWithAppearance(page, `/games/${authFixture.slug}`, appearance);
            await expect(page.locator('#analytics')).toBeVisible();
            await expect(page.getByRole('heading', { name: 'Analytics' })).toBeVisible();
            await expect(page.getByAltText(`${authFixture.originalName} cover image`)).toBeVisible();
            await expect(page.getByAltText(`${authFixture.originalName} screenshot 1`)).toBeVisible();
            await scanAndAssert(page, `game-detail-authenticated-analytics-${appearance}`);
        });
    }

    test('Homepage color contrast check in light and dark modes', async ({ page }) => {
        for (const appearance of appearances) {
            await gotoWithAppearance(page, '/', appearance);

            const results = await scanPage(page, {
                runOnly: {
                    type: 'rule',
                    values: ['color-contrast'],
                },
                failOnViolations: false,
            });

            console.log(`\nHomepage Color Contrast Issues (${appearance}): ${results.violations.length}\n`);

            if (results.violations.length > 0) {
                saveReport(`homepage-color-contrast-${appearance}`, results);
            }

            expect(results.violations).toHaveLength(0);
        }
    });
});

test.describe('Accessibility - Keyboard Navigation @accessibility', () => {
    test('Homepage keyboard navigation', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        await page.keyboard.press('Tab');

        const skipLink = page.locator('a:has-text("Skip to main content")');
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeVisible();

        await page.keyboard.press('Enter');

        const mainContent = page.locator('#main-content');
        await expect(mainContent).toBeVisible();
        await expect(mainContent).toBeFocused();

        await page.keyboard.press('Tab');
        await expectStrongFocusIndicator(page.locator('.hero-section a:has-text("Explore Library")'));

        await page.keyboard.press('Tab');
        await expect(page.locator('.hero-section a:has-text("Log In")')).toBeFocused();

        await page.keyboard.press('Tab');
        await expect(page.locator('section:has(h2:has-text("Recently Added")) a:has-text("View all")')).toBeFocused();
    });

    test('Games page keyboard navigation', async ({ page }) => {
        test.setTimeout(60_000);
        await gotoWithAppearance(page, '/games', 'light');

        const skipLink = page.locator('a:has-text("Skip to main content")');
        await expect(skipLink).toBeAttached();

        await page.keyboard.press('Tab');
        await expect(skipLink).toBeFocused();
        await expect(skipLink).toBeVisible();

        await page.keyboard.press('Enter');
        const mainContent = page.locator('#main-content');
        await expect(mainContent).toBeVisible();
        await expect(mainContent).toBeFocused();
    });
});

test.describe('Accessibility - Screen Reader Support @accessibility', () => {
    test('Homepage has proper ARIA landmarks', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const main = page.getByRole('main');
        await expect(main).toBeVisible();

        const nav = page.locator('nav');
        await expect(nav.first()).toBeVisible();

        const footer = page.locator('footer').first();
        await expect(footer).toBeVisible();
    });

    test('Images have alt text', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const images = page.locator('img');
        const count = await images.count();

        for (let i = 0; i < count; i++) {
            const img = images.nth(i);
            const alt = await img.getAttribute('alt');

            expect(alt).not.toBeNull();
        }
    });

    test('Form inputs have labels', async ({ page }) => {
        await page.goto('/login');
        await page.waitForLoadState('networkidle');

        const inputs = page.locator('input[type="text"], input[type="email"], input[type="password"]');
        const count = await inputs.count();

        for (let i = 0; i < count; i++) {
            const input = inputs.nth(i);
            const id = await input.getAttribute('id');
            const ariaLabel = await input.getAttribute('aria-label');
            const ariaLabelledBy = await input.getAttribute('aria-labelledby');

            if (id) {
                const label = page.locator(`label[for="${id}"]`);
                const hasLabel = (await label.count()) > 0;

                expect(
                    hasLabel || ariaLabel !== null || ariaLabelledBy !== null,
                    `Input at index ${i} should have a label, aria-label, or aria-labelledby`,
                ).toBeTruthy();
            } else {
                expect(
                    ariaLabel !== null || ariaLabelledBy !== null,
                    `Input at index ${i} without id should have aria-label or aria-labelledby`,
                ).toBeTruthy();
            }
        }
    });

    test('Buttons have accessible names', async ({ page }) => {
        await page.goto('/');
        await page.waitForLoadState('networkidle');

        const buttons = page.locator('button, a[role="button"]');
        const count = await buttons.count();

        for (let i = 0; i < count; i++) {
            const button = buttons.nth(i);
            const text = await button.textContent();
            const ariaLabel = await button.getAttribute('aria-label');
            const ariaLabelledBy = await button.getAttribute('aria-labelledby');

            expect(
                (text && text.trim().length > 0) || ariaLabel !== null || ariaLabelledBy !== null,
                `Button at index ${i} should have text content, aria-label, or aria-labelledby`,
            ).toBeTruthy();
        }
    });
});
