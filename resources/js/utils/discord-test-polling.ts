export interface DiscordTestResult {
    id: number;
    status: string;
    error?: string | null;
}

interface DiscordTestPollingOptions {
    intervalMs?: number;
    timeoutMs?: number;
    sleep?: (milliseconds: number) => Promise<void>;
}

const defaultIntervalMs = 5000;
const defaultTimeoutMs = 75000;

export async function waitForDiscordTest(
    notificationId: number,
    fetchLastTest: () => Promise<DiscordTestResult | null>,
    options: DiscordTestPollingOptions = {},
): Promise<void> {
    const intervalMs = options.intervalMs ?? defaultIntervalMs;
    const timeoutMs = options.timeoutMs ?? defaultTimeoutMs;
    const sleep = options.sleep ?? ((milliseconds) => new Promise((resolve) => setTimeout(resolve, milliseconds)));
    const attempts = Math.ceil(timeoutMs / intervalMs);

    for (let attempt = 0; attempt < attempts; attempt++) {
        await sleep(intervalMs);
        const lastTest = await fetchLastTest();

        if (lastTest?.id !== notificationId) continue;
        if (lastTest.status === 'sent') return;
        if (lastTest.status === 'failed') throw new Error(lastTest.error || 'Discord could not deliver the test DM.');
    }

    throw new Error(`Discord did not finish the delivery test within ${Math.ceil(timeoutMs / 1000)} seconds. Check the bot status and try again.`);
}
