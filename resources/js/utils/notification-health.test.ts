import { describe, expect, test } from 'vitest';

import { computeChannelStatus } from './notification-health';

describe('computeChannelStatus', () => {
    test('reports disabled before considering channel-specific health', () => {
        expect(computeChannelStatus('browser', { enabled: false })).toBe('disabled');
        expect(computeChannelStatus('discord', { enabled: false })).toBe('disabled');
    });

    test('keeps a broken Discord link actionable while the channel is switched off', () => {
        expect(computeChannelStatus('discord', { enabled: false, linked: true, dmStatus: 'undeliverable' })).toBe('action-needed');
        expect(computeChannelStatus('discord', { enabled: false, linked: true, dmStatus: 'unverified' })).toBe('disabled');
        expect(computeChannelStatus('discord', { enabled: false, linked: false, dmStatus: 'undeliverable' })).toBe('disabled');
    });

    test('requires server config, permission, a local subscription, and a stored subscription for browser health', () => {
        const healthy = { enabled: true, configured: true, subscriptionCount: 1 };
        expect(computeChannelStatus('browser', healthy, { permission: 'granted', subscribed: true })).toBe('working');
        expect(computeChannelStatus('browser', healthy, { permission: 'denied', subscribed: true })).toBe('action-needed');
        expect(computeChannelStatus('browser', healthy, { permission: 'granted', subscribed: false })).toBe('action-needed');
        expect(computeChannelStatus('browser', { ...healthy, subscriptionCount: 0 }, { permission: 'granted', subscribed: true })).toBe(
            'action-needed',
        );
    });

    test('requires a linked, empirically deliverable Discord account', () => {
        expect(computeChannelStatus('discord', { enabled: true, linked: true, dmStatus: 'deliverable' })).toBe('working');
        expect(computeChannelStatus('discord', { enabled: true, linked: true, dmStatus: 'unverified' })).toBe('action-needed');
        expect(computeChannelStatus('discord', { enabled: true, linked: false, dmStatus: 'deliverable' })).toBe('action-needed');
    });
});
