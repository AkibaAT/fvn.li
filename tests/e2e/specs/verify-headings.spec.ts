import { test } from '@playwright/test';

test('Verify heading structure on games page', async ({ page }) => {
  await page.goto('/games');
  await page.waitForLoadState('networkidle');
  
  // Get all headings
  const h1s = await page.locator('h1').all();
  const h2s = await page.locator('h2').all();
  const h3s = await page.locator('h3').all();
  
  console.log(`\nHeading structure:`);
  console.log(`H1 elements: ${h1s.length}`);
  for (let i = 0; i < h1s.length; i++) {
    const text = await h1s[i].textContent();
    console.log(`  H1 ${i + 1}: "${text}"`);
  }
  
  console.log(`\nH2 elements: ${h2s.length}`);
  for (let i = 0; i < Math.min(h2s.length, 5); i++) {
    const text = await h2s[i].textContent();
    console.log(`  H2 ${i + 1}: "${text}"`);
  }
  if (h2s.length > 5) {
    console.log(`  ... and ${h2s.length - 5} more`);
  }
  
  console.log(`\nH3 elements: ${h3s.length}`);
  for (let i = 0; i < Math.min(h3s.length, 3); i++) {
    const text = await h3s[i].textContent();
    console.log(`  H3 ${i + 1}: "${text}"`);
  }
  if (h3s.length > 3) {
    console.log(`  ... and ${h3s.length - 3} more`);
  }
});

