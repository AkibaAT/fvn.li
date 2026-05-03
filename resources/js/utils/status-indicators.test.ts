import { describe, expect, test } from 'vitest';
import {
    gameStatusConfig,
    getListTypeColor,
    getListTypeConfig,
    getStatusBadgeConfig,
    getStatusClasses,
    listStatusConfig,
} from './status-indicators';

describe('status indicator utilities', () => {
    test('builds accessible class strings from status configuration', () => {
        const classes = getStatusClasses({
            color: 'green',
            icon: 'ok',
            label: 'Complete',
            pattern: 'dashed',
            shape: 'pill',
        });

        expect(classes).toContain('bg-green-100');
        expect(classes).toContain('border-dashed');
        expect(classes).toContain('rounded-full');
    });

    test('returns badge configuration for known statuses and null for unknown statuses', () => {
        expect(getStatusBadgeConfig('reading', listStatusConfig, { size: 'lg' })).toMatchObject({
            label: 'Reading',
            ariaLabel: 'Reading',
        });
        expect(getStatusBadgeConfig('paid', gameStatusConfig)).toMatchObject({
            label: 'Paid Game',
        });
        expect(getStatusBadgeConfig('missing', listStatusConfig)).toBeNull();
    });

    test('falls back to custom list configuration for unknown list types', () => {
        expect(getListTypeColor('completed')).toBe('green');
        expect(getListTypeColor('unknown')).toBe('gray');
        expect(getListTypeConfig('unknown')).toBe(listStatusConfig.custom);
    });
});
