<script lang="ts">
    import type { GameCardGame } from '@/hooks/useGameCard.svelte';

    let { game }: { game: GameCardGame } = $props();

    const formatDate = (dateStr: string | null | undefined) => {
        if (!dateStr) return '\u2014';
        return new Date(dateStr).toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };
</script>

<dl class="min-h-24 space-y-1 overflow-hidden border-t border-gray-100 pt-2 text-sm dark:border-gray-700/50">
    <div class="grid grid-cols-[120px_1fr] gap-2">
        <dt class="text-gray-500 dark:text-gray-400">
            Words ({game.primary_language_label || 'EN'})
        </dt>
        <dd class="text-gray-700 dark:text-gray-200">
            {game.primary_word_count ? game.primary_word_count.toLocaleString() : '\u2014'}
            {#if game.primary_language_label && game.primary_language_label !== 'EN' && game.english_word_count}
                <span class="ml-1.5 text-xs text-gray-400 dark:text-gray-500">
                    EN: {game.english_word_count.toLocaleString()}
                </span>
            {/if}
        </dd>
    </div>
    <div class="grid grid-cols-[120px_1fr] gap-2">
        <dt class="text-gray-500 dark:text-gray-400">Released</dt>
        <dd class="text-gray-700 dark:text-gray-200">
            {formatDate(game.initially_published_at)}
        </dd>
    </div>
    <div class="grid grid-cols-[120px_1fr] gap-2">
        <dt class="text-gray-500 dark:text-gray-400">Updated</dt>
        <dd class="text-gray-700 dark:text-gray-200">
            {formatDate(game.latest_version_published_at)}
        </dd>
    </div>
    <div class="grid grid-cols-[120px_1fr] gap-2">
        <dt class="text-gray-500 dark:text-gray-400">Rating</dt>
        <dd class="text-gray-700 dark:text-gray-200">
            {typeof game.rating_score === 'number' ? game.rating_score.toFixed(1) : '\u2014'}
            {#if typeof game.rating_count === 'number'}
                <span class="ml-1 text-xs text-gray-600 dark:text-gray-300">
                    ({game.rating_count.toLocaleString()} reviews)
                </span>
            {/if}
        </dd>
    </div>
</dl>
