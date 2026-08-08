export type ChannelStatus = 'working' | 'action-needed' | 'disabled';

export interface ChannelHealth {
    enabled: boolean;
    configured?: boolean;
    linked?: boolean;
    subscriptionCount?: number;
    dmStatus?: 'unverified' | 'deliverable' | 'undeliverable';
    lastFailure?: { code: string } | null;
}

export function computeChannelStatus(
    channel: 'browser' | 'discord',
    health: ChannelHealth,
    client: { permission?: NotificationPermission; subscribed?: boolean } = {},
): ChannelStatus {
    // A recorded delivery failure stays actionable even when the channel is switched
    // off, so the reason and its fix steps remain reachable.
    const broken = channel === 'discord' && health.linked === true && health.dmStatus === 'undeliverable';

    if (!health.enabled) return broken ? 'action-needed' : 'disabled';

    if (channel === 'browser') {
        return health.configured && client.permission === 'granted' && client.subscribed && (health.subscriptionCount ?? 0) > 0
            ? 'working'
            : 'action-needed';
    }

    return health.linked && health.dmStatus === 'deliverable' ? 'working' : 'action-needed';
}
