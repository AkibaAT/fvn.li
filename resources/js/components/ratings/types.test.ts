import { describe, expect, test } from 'vitest';
import { emptyStats } from './types';

describe('emptyStats', () => {
    test('returns independent all-game and visible-game defaults', () => {
        const stats = emptyStats();

        stats.all_games.rating_distribution[5] = 3;

        expect(stats.visible_games.rating_distribution[5]).toBe(0);
        expect(emptyStats().all_games.rating_distribution[5]).toBe(0);
    });
});
