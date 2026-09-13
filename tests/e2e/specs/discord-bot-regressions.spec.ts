import {test, expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
let fixture: any;
test.beforeEach(async ({page, baseURL}) => {
    fixture = JSON.parse(execFileSync('php', ['tests/e2e/support/make-preferences-review-fixture.php'], {encoding: 'utf8'}));
    await page.context().addCookies([{...fixture.authCookie, url: baseURL}]);
    for (const endpoint of ['channels', 'roles']) {
        await page.route(`**/browser-api/discord/servers/*/${endpoint}`, (route) => route.fulfill({json: {[endpoint]: []}}));
    }
    await page.route('**/browser-api/discord/servers/*/preview-embed', (route) => route.fulfill({json: {embed: {title: 'Audit preview'}}}));
    await page.goto(`/discord/${fixture.discordServerId}?tab=embeds`);
});
test('saves visible JSON edits without switching back to visual mode', async ({page}, info) => {
    await page.unroute('**/browser-api/discord/servers/*/preview-embed');
    await page.getByRole('button', {name: 'JSON', exact: true}).first().click();
    const autosave = page.waitForResponse((response) => response.url().endsWith('/config') && response.request().method() === 'PUT');
    await page.getByRole('textbox', {name: 'Embed JSON'}).fill('{"title":"Saved JSON edit"}');
    expect((await autosave).ok()).toBe(true);
    await expect(page.getByText('Saved JSON edit', {exact: true})).toBeVisible();
    await page.screenshot({path: info.outputPath('saved-json-editor.png'), fullPage: true});
    const save = page.waitForResponse((response) => response.url().endsWith('/config') && response.request().method() === 'PUT');
    await page.getByRole('button', {name: 'Save All', exact: true}).click();
    const response = await save;
    expect(response.ok()).toBe(true);
    expect(response.request().postDataJSON().new_game_embed.title).toBe('Saved JSON edit');
    await page.reload();
    await expect(page.getByLabel('Title', {exact: true}).first()).toHaveValue('Saved JSON edit');
});
test('copies variables and confirms the result', async ({page}) => {
    await page.evaluate(() => {
        (window as any).copied = [];
        Object.defineProperty(navigator, 'clipboard', {configurable: true, value: {writeText: async (text: string) => {(window as any).copied.push(text);}}});
    });
    const title = page.getByLabel('Title', {exact: true}).first();
    await title.focus();
    await page.getByRole('button', {name: 'Copy Variable', exact: true}).first().click();
    await page.locator('button[title="Click to copy: {game.name}"]').click();
    await expect(page.locator('button[title="Click to copy: {game.name}"]')).toHaveCount(0);
    await expect(title).toHaveValue('Fixture embed');
    expect(await page.evaluate(() => (window as any).copied)).toEqual(['{game.name}']);
});

test('invalid JSON in either editor prevents Save All from silently persisting the older template', async ({page}) => {
    await page.getByRole('button', {name: 'JSON', exact: true}).first().click();
    await page.getByRole('textbox', {name: 'Embed JSON'}).fill('{"title":');
    await expect(page.getByRole('textbox', {name: 'Embed JSON'})).toHaveAttribute('aria-invalid', 'true');
    await page.getByRole('button', {name: 'JSON', exact: true}).click();
    const autosave = page.waitForResponse((response) => response.url().endsWith('/config') && response.request().method() === 'PUT');
    await page.getByRole('textbox', {name: 'Embed JSON'}).nth(1).fill('{"title":"Valid second editor"}');
    expect((await autosave).ok()).toBe(true);
    let saves = 0;
    page.on('request', (request) => {if (request.url().endsWith('/config') && request.method() === 'PUT') saves++;});
    await page.getByRole('button', {name: 'Save All', exact: true}).click();
    await expect(page.getByText('Fix the embed JSON before saving.', {exact: true})).toBeVisible();
    expect(saves).toBe(0);
});

test('server validation errors retain the JSON draft and block switching to an older visual template', async ({page}) => {
    await page.getByRole('button', {name: 'JSON', exact: true}).first().click();
    const rejected = page.waitForResponse((response) => response.url().endsWith('/config') && response.status() === 422);
    await page.getByRole('textbox', {name: 'Embed JSON'}).fill('{"title":["Invalid title"]}');
    await rejected;
    await expect(page.getByRole('textbox', {name: 'Embed JSON'})).toHaveAttribute('aria-invalid', 'true');
    await page.getByRole('button', {name: 'Visual', exact: true}).click();
    await expect(page.getByRole('textbox', {name: 'Embed JSON'})).toHaveValue('{"title":["Invalid title"]}');
    await expect(page.getByRole('textbox', {name: 'Embed JSON'})).toHaveAttribute('aria-invalid', 'true');
});
