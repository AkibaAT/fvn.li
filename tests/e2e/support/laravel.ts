import { execFileSync } from 'node:child_process';

export interface GameViewFixture {
  userId: number;
  authCookie: {
    name: string;
    value: string;
  };
  slug: string;
  originalName: string;
  customName: string;
  originalDescription: string;
  customDescription: string;
}

export function createGameViewFixture(): GameViewFixture {
  const output = execFileSync('php', ['tests/e2e/support/make-game-view-fixture.php'], {
    cwd: process.cwd(),
    env: {
      ...process.env,
      APP_ENV: 'testing',
      DB_DATABASE: 'db_test',
      MEILISEARCH_HOST: 'http://localhost:9999',
      SCOUT_DRIVER: 'collection',
      SESSION_DRIVER: 'database',
    },
    encoding: 'utf8',
  });

  return JSON.parse(output) as GameViewFixture;
}
