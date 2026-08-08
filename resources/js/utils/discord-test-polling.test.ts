import { describe, expect, test, vi } from 'vitest';
import { waitForDiscordTest, type DiscordTestResult } from './discord-test-polling';

const noDelay = async (): Promise<void> => {};

describe('waitForDiscordTest', () => {
    test('waits beyond 30 seconds and ignores a stale successful test', async () => {
        const results: DiscordTestResult[] = [...Array.from({ length: 6 }, () => ({ id: 41, status: 'sent' })), { id: 42, status: 'sent' }];
        const fetchLastTest = vi.fn(async () => results.shift() ?? null);

        await expect(waitForDiscordTest(42, fetchLastTest, { sleep: noDelay })).resolves.toBeUndefined();
        expect(fetchLastTest).toHaveBeenCalledTimes(7);
    });

    test('surfaces a matching delivery failure immediately', async () => {
        const fetchLastTest = vi.fn(async () => ({ id: 42, status: 'failed', error: 'Discord rejected the DM.' }));

        await expect(waitForDiscordTest(42, fetchLastTest, { sleep: noDelay })).rejects.toThrow('Discord rejected the DM.');
        expect(fetchLastTest).toHaveBeenCalledOnce();
    });

    test('uses a timeout longer than the bot polling interval', async () => {
        const fetchLastTest = vi.fn(async () => null);

        await expect(waitForDiscordTest(42, fetchLastTest, { sleep: noDelay })).rejects.toThrow('within 75 seconds');
        expect(fetchLastTest).toHaveBeenCalledTimes(15);
    });
});
