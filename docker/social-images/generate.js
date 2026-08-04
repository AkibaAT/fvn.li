#!/usr/bin/env node

import { chromium } from 'playwright-core';
import { mkdir, access } from 'fs/promises';
import { join } from 'path';

// Configuration
const BASE_URL = process.env.BASE_URL || 'https://fvn-li.ddev.site';
const OUTPUT_DIR = process.env.OUTPUT_DIR || '/output';
const WIDTH = 1200;
const HEIGHT = 630; // 1.91:1 aspect ratio for social media
const ZOOM_LEVEL = 0.6;

// Page configurations
const pages = [
  {
    name: 'home',
    url: '/',
    output: 'social-home.jpg',
    waitFor: 'load' // Wait for page load event
  },
  {
    name: 'games_list',
    url: '/games',
    output: 'social-games.jpg',
    waitFor: 'load'
  },
  {
    name: 'public_lists',
    url: '/lists/public',
    output: 'social-lists.jpg',
    waitFor: 'load'
  },
  {
    name: 'ratings',
    url: '/ratings',
    output: 'social-ratings.jpg',
    waitFor: 'load'
  },
  {
    name: 'default',
    url: '/',
    output: 'social-fallback.jpg',
    waitFor: 'load'
  }
];

async function ensureOutputDir() {
  try {
    await access(OUTPUT_DIR);
  } catch {
    await mkdir(OUTPUT_DIR, { recursive: true });
    console.log(`Created output directory: ${OUTPUT_DIR}`);
  }
}

async function generateScreenshot(browser, page, config) {
  const url = `${BASE_URL}${config.url}`;
  console.log(`Generating screenshot for ${config.name}: ${url}`);

  try {
    // Navigate to the page
    await page.goto(url, {
      waitUntil: config.waitFor,
      timeout: 30000
    });

    // Apply CSS zoom to show more content
    await page.evaluate((zoom) => {
      document.body.style.zoom = zoom;
    }, ZOOM_LEVEL);

    // Wait a bit for any animations to complete
    await page.waitForTimeout(2000);

    // Take screenshot
    const outputPath = join(OUTPUT_DIR, config.output);
    await page.screenshot({
      path: outputPath,
      type: 'jpeg',
      quality: 90,
      fullPage: false // Only capture viewport
    });

    console.log(`Generated: ${config.output}`);
    return true;
  } catch (error) {
    console.error(`Failed to generate ${config.name}:`, error.message);
    return false;
  }
}

async function main() {
  console.log('=== Social Image Generator ===');
  console.log(`Base URL: ${BASE_URL}`);
  console.log(`Output Directory: ${OUTPUT_DIR}`);
  console.log(`Image Size: ${WIDTH}x${HEIGHT}`);
  console.log(`Zoom Level: ${ZOOM_LEVEL} (zoomed out)`);
  console.log('');

  // Ensure output directory exists
  await ensureOutputDir();

  // Launch browser
  console.log('Launching browser...');
  const browser = await chromium.launch({
    headless: true,
    args: [
      '--disable-gpu'
    ]
  });

  try {
    // Create browser context
    const context = await browser.newContext({
      viewport: { width: WIDTH, height: HEIGHT },
      deviceScaleFactor: 1,
      colorScheme: 'dark', // Enable dark mode
      userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
    });

    const page = await context.newPage();

    // Generate screenshots
    let successCount = 0;
    let failCount = 0;

    for (const pageConfig of pages) {
      const success = await generateScreenshot(browser, page, pageConfig);
      if (success) {
        successCount++;
      } else {
        failCount++;
      }
    }

    console.log('');
    console.log('=== Summary ===');
    console.log(`Successful: ${successCount}`);
    console.log(`Failed: ${failCount}`);
    console.log(`Total: ${successCount + failCount}`);

    if (failCount > 0) {
      process.exit(1);
    }
  } finally {
    await browser.close();
  }
}

// Run the script
main().catch((error) => {
  console.error('Fatal error:', error);
  process.exit(1);
});
