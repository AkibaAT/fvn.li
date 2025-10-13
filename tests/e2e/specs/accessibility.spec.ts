import { test, expect } from '@playwright/test';
import { scanPage, assertNoViolations, generateHtmlReport } from '../utils/accessibility-scanner';
import { writeFileSync, mkdirSync } from 'fs';
import { join } from 'path';

/**
 * Accessibility test suite
 * 
 * These tests scan pages for WCAG 2.1 Level AA compliance using axe-core.
 * Reports are generated in tests/e2e/reports/accessibility/
 */

test.describe('Accessibility Scans', () => {
  const reportsDir = join(process.cwd(), 'tests/e2e/reports/accessibility');
  
  test.beforeAll(() => {
    // Ensure reports directory exists
    mkdirSync(reportsDir, { recursive: true });
  });

  /**
   * Helper to save HTML report
   */
  function saveReport(pageName: string, results: any) {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const filename = `${pageName}-${timestamp}.html`;
    const filepath = join(reportsDir, filename);
    const html = generateHtmlReport(results);
    writeFileSync(filepath, html);
    console.log(`📊 Report saved: ${filepath}`);
  }

  test('Homepage accessibility scan', async ({ page }) => {
    await page.goto('/');
    
    // Wait for page to be fully loaded
    await page.waitForLoadState('networkidle');
    
    // Run scan
    const results = await scanPage(page, {
      failOnViolations: false, // Don't fail, just report
    });
    
    // Save report
    saveReport('homepage', results);
    
    // Log summary
    console.log('\n📊 Homepage Accessibility Summary:');
    console.log(`  Critical: ${results.violationsBySeverity.critical}`);
    console.log(`  Serious:  ${results.violationsBySeverity.serious}`);
    console.log(`  Moderate: ${results.violationsBySeverity.moderate}`);
    console.log(`  Minor:    ${results.violationsBySeverity.minor}`);
    console.log(`  Passes:   ${results.passes}\n`);
    
    // Assert no critical or serious violations
    await assertNoViolations(page, {
      failOnSeverity: 'serious',
    });
  });

  test('Games listing page accessibility scan', async ({ page }) => {
    await page.goto('/games');
    await page.waitForLoadState('networkidle');
    
    const results = await scanPage(page, {
      failOnViolations: false,
    });
    
    saveReport('games-listing', results);
    
    console.log('\n📊 Games Listing Accessibility Summary:');
    console.log(`  Critical: ${results.violationsBySeverity.critical}`);
    console.log(`  Serious:  ${results.violationsBySeverity.serious}`);
    console.log(`  Moderate: ${results.violationsBySeverity.moderate}`);
    console.log(`  Minor:    ${results.violationsBySeverity.minor}`);
    console.log(`  Passes:   ${results.passes}\n`);
    
    await assertNoViolations(page, {
      failOnSeverity: 'serious',
    });
  });

  test('Login page accessibility scan', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    const results = await scanPage(page, {
      failOnViolations: false,
    });
    
    saveReport('login', results);
    
    console.log('\n📊 Login Page Accessibility Summary:');
    console.log(`  Critical: ${results.violationsBySeverity.critical}`);
    console.log(`  Serious:  ${results.violationsBySeverity.serious}`);
    console.log(`  Moderate: ${results.violationsBySeverity.moderate}`);
    console.log(`  Minor:    ${results.violationsBySeverity.minor}`);
    console.log(`  Passes:   ${results.passes}\n`);
    
    await assertNoViolations(page, {
      failOnSeverity: 'serious',
    });
  });

  test('Register page accessibility scan', async ({ page }) => {
    await page.goto('/register');
    await page.waitForLoadState('networkidle');
    
    const results = await scanPage(page, {
      failOnViolations: false,
    });
    
    saveReport('register', results);
    
    console.log('\n📊 Register Page Accessibility Summary:');
    console.log(`  Critical: ${results.violationsBySeverity.critical}`);
    console.log(`  Serious:  ${results.violationsBySeverity.serious}`);
    console.log(`  Moderate: ${results.violationsBySeverity.moderate}`);
    console.log(`  Minor:    ${results.violationsBySeverity.minor}`);
    console.log(`  Passes:   ${results.passes}\n`);
    
    await assertNoViolations(page, {
      failOnSeverity: 'serious',
    });
  });

  test('Community lists page accessibility scan', async ({ page }) => {
    await page.goto('/community/lists');
    await page.waitForLoadState('networkidle');
    
    const results = await scanPage(page, {
      failOnViolations: false,
    });
    
    saveReport('community-lists', results);
    
    console.log('\n📊 Community Lists Accessibility Summary:');
    console.log(`  Critical: ${results.violationsBySeverity.critical}`);
    console.log(`  Serious:  ${results.violationsBySeverity.serious}`);
    console.log(`  Moderate: ${results.violationsBySeverity.moderate}`);
    console.log(`  Minor:    ${results.violationsBySeverity.minor}`);
    console.log(`  Passes:   ${results.passes}\n`);
    
    await assertNoViolations(page, {
      failOnSeverity: 'serious',
    });
  });
});

test.describe('Accessibility - Keyboard Navigation', () => {
  test('Homepage keyboard navigation', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Test Tab navigation
    await page.keyboard.press('Tab');
    
    // Check if skip link is focused
    const skipLink = page.locator('a:has-text("Skip to main content")');
    await expect(skipLink).toBeFocused();
    
    // Press Enter to skip to main content
    await page.keyboard.press('Enter');
    
    // Main content should be in view
    const mainContent = page.locator('#main-content');
    await expect(mainContent).toBeVisible();
  });

  test('Games page keyboard navigation', async ({ page }) => {
    await page.goto('/games');
    await page.waitForLoadState('networkidle');
    
    // Tab through interactive elements
    await page.keyboard.press('Tab');
    
    // Verify focus is visible (check for focus-visible or focus styles)
    const focusedElement = page.locator(':focus');
    await expect(focusedElement).toBeVisible();
  });
});

test.describe('Accessibility - Screen Reader Support', () => {
  test('Homepage has proper ARIA landmarks', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Check for main landmark
    const main = page.locator('main[role="main"]');
    await expect(main).toBeVisible();
    
    // Check for navigation landmark
    const nav = page.locator('nav');
    await expect(nav.first()).toBeVisible();
    
    // Check for contentinfo (footer) - footer element has implicit contentinfo role
    const footer = page.locator('footer').first();
    await expect(footer).toBeVisible();
  });

  test('Images have alt text', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Get all images
    const images = page.locator('img');
    const count = await images.count();
    
    // Check each image has alt attribute
    for (let i = 0; i < count; i++) {
      const img = images.nth(i);
      const alt = await img.getAttribute('alt');
      
      // Alt can be empty string for decorative images, but must exist
      expect(alt).not.toBeNull();
    }
  });

  test('Form inputs have labels', async ({ page }) => {
    await page.goto('/login');
    await page.waitForLoadState('networkidle');
    
    // Get all input fields
    const inputs = page.locator('input[type="text"], input[type="email"], input[type="password"]');
    const count = await inputs.count();
    
    // Check each input has associated label or aria-label
    for (let i = 0; i < count; i++) {
      const input = inputs.nth(i);
      const id = await input.getAttribute('id');
      const ariaLabel = await input.getAttribute('aria-label');
      const ariaLabelledBy = await input.getAttribute('aria-labelledby');
      
      if (id) {
        // Check for associated label
        const label = page.locator(`label[for="${id}"]`);
        const hasLabel = await label.count() > 0;
        
        expect(
          hasLabel || ariaLabel !== null || ariaLabelledBy !== null,
          `Input at index ${i} should have a label, aria-label, or aria-labelledby`
        ).toBeTruthy();
      } else {
        // Must have aria-label or aria-labelledby
        expect(
          ariaLabel !== null || ariaLabelledBy !== null,
          `Input at index ${i} without id should have aria-label or aria-labelledby`
        ).toBeTruthy();
      }
    }
  });

  test('Buttons have accessible names', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Get all buttons
    const buttons = page.locator('button, a[role="button"]');
    const count = await buttons.count();
    
    // Check each button has accessible name
    for (let i = 0; i < count; i++) {
      const button = buttons.nth(i);
      const text = await button.textContent();
      const ariaLabel = await button.getAttribute('aria-label');
      const ariaLabelledBy = await button.getAttribute('aria-labelledby');
      
      expect(
        (text && text.trim().length > 0) || ariaLabel !== null || ariaLabelledBy !== null,
        `Button at index ${i} should have text content, aria-label, or aria-labelledby`
      ).toBeTruthy();
    }
  });
});

test.describe('Accessibility - Color Contrast', () => {
  test('Homepage color contrast check', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');
    
    // Run scan with color-contrast rule only
    const results = await scanPage(page, {
      runOnly: {
        type: 'rule',
        values: ['color-contrast'],
      },
      failOnViolations: false,
    });
    
    console.log(`\n🎨 Color Contrast Issues: ${results.violations.length}\n`);

    if (results.violations.length > 0) {
      const reportsDir = join(process.cwd(), 'tests/e2e/reports/accessibility');
      const reportPath = join(reportsDir, `homepage-color-contrast-${new Date().toISOString().replace(/:/g, '-')}.html`);
      mkdirSync(reportsDir, { recursive: true });
      const html = generateHtmlReport(results);
      writeFileSync(reportPath, html);
      console.log(`📊 Report saved: ${reportPath}`);
    }

    // Assert no color contrast violations
    expect(results.violations).toHaveLength(0);
  });
});

