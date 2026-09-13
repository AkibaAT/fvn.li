import { readFileSync, existsSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import { expect, test, vi } from 'vitest';

test('push notifications keep distinct game updates and use an existing badge', async () => {
    const handlers: Record<string, (event: any) => void> = {};
    const showNotification = vi.fn(async () => {});
    runInNewContext(readFileSync('public/service-worker.js', 'utf8'), {
        self: {
            addEventListener: (type: string, handler: (event: any) => void) => {
                handlers[type] = handler;
            },
            registration: { showNotification },
        },
    });
    for (const gameId of [1, 2]) {
        let work: Promise<void> | undefined;
        handlers.push({
            data: { json: () => ({ data: { game_id: gameId, game_version_id: 3 } }) },
            waitUntil: (promise: Promise<void>) => {
                work = promise;
            },
        });
        await work;
    }
    const options = showNotification.mock.calls.map((call: any) => call[1]);
    expect(options[0].tag).not.toBe(options[1].tag);
    expect(existsSync(`public${options[0].badge}`)).toBe(true);
});
