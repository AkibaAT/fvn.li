import type { Page } from '@playwright/test';

export class HomePage {
  constructor(private page: Page) {}

  async goto() {
    await this.page.goto('/');
  }

  async headlineText() {
    return this.page.getByRole('heading', { name: 'Welcome to FVN.li' });
  }

  async clickBrowseGames() {
    await this.page.getByRole('link', { name: /browse games/i }).click();
  }
}

