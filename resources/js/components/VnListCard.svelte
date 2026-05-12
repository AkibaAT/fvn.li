<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import { Badge, Button, Card } from '@/components/ui';
    import { formatListType, listTypeBorderClass, listTypeTone } from '@/components/ui/tones';
    import { gameCoverAltText } from '@/utils/imageAltText';

    export interface Game {
        id: number;
        name: string;
        effective_name: string;
        slug: string;
        thumb_url?: string;
        optimized_thumbnails?: { default?: { path: string } } | null;
        is_nsfw: boolean;
        is_paid: boolean;
        has_demo: boolean;
        is_on_sale: boolean;
        min_price?: number;
        platform?: 'itch_io' | 'steam' | 'other';
    }

    export interface VnListEntry {
        id: number;
        game: Game;
        sort_order: number;
    }

    export interface User {
        id: number;
        name: string;
        avatar?: string;
    }

    export interface VnList {
        id: number;
        name: string;
        description?: string;
        type: string;
        is_default: boolean;
        is_public: boolean;
        created_at: string;
        updated_at?: string;
        entries: VnListEntry[];
        entries_count?: number;
        user: User;
    }

    let {
        list,
        showUser = false,
        showActions = false,
        isOwner = false,
        onToggleVisibility,
        onDelete,
        class: className = '',
    }: {
        list: VnList;
        showUser?: boolean;
        showActions?: boolean;
        isOwner?: boolean;
        onToggleVisibility?: (list: VnList) => void | Promise<void>;
        onDelete?: (list: VnList) => void;
        class?: string;
    } = $props();

    const borderClass = $derived(listTypeBorderClass(list.type));
    const typeTone = $derived(listTypeTone(list.type));

    const formatDate = (dateStr?: string) => {
        if (!dateStr) return '';
        const d = new Date(dateStr);
        return d.toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    };

    const getThumb = (game: Game) => {
        if (game.optimized_thumbnails?.default?.path) {
            return `/storage/${game.optimized_thumbnails.default.path}`;
        }
        return game.thumb_url || '';
    };

    let index = $state(0);
    let isToggling = $state(false);
    const total = $derived(list.entries.length);
    const currentGame = $derived(total > 0 ? list.entries[index % total].game : undefined);

    const goPrev = (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (total === 0) return;
        index = (index - 1 + total) % total;
    };

    const goNext = (e: MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();
        if (total === 0) return;
        index = (index + 1) % total;
    };

    const handleToggleVisibility = async () => {
        if (!onToggleVisibility) return;
        isToggling = true;
        try {
            await onToggleVisibility(list);
        } finally {
            isToggling = false;
        }
    };

    const handleDelete = () => {
        if (!onDelete) return;
        if (!confirm('Are you sure you want to delete this list?')) return;
        onDelete(list);
    };

    const handleKeydown = (e: KeyboardEvent) => {
        if (e.key === 'ArrowLeft') {
            e.preventDefault();
            if (total > 0) index = (index - 1 + total) % total;
        }
        if (e.key === 'ArrowRight') {
            e.preventDefault();
            if (total > 0) index = (index + 1) % total;
        }
        if (e.key === 'Home') {
            e.preventDefault();
            index = 0;
        }
        if (e.key === 'End') {
            e.preventDefault();
            index = total - 1;
        }
    };

    const entriesCount = $derived(list.entries_count ?? list.entries.length);
</script>

<Card
    role="article"
    aria-labelledby="list-title-{list.id}"
    variant="glass"
    padding="none"
    hover
    class="flex h-full flex-col overflow-hidden border-l-4 {borderClass} {className}"
>
    <div class="flex-grow p-6">
        <div class="mb-4 flex items-start justify-between gap-4">
            <div class="min-w-0 flex-1">
                {#if showUser && list.user}
                    <div class="mb-2 flex items-center gap-2">
                        <Link href={route('lists.user-public', list.user.id)} class="shrink-0">
                            <div class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full bg-gray-200 dark:bg-gray-700">
                                {#if list.user.avatar}
                                    <img src={list.user.avatar} alt={list.user.name} class="h-full w-full object-cover" />
                                {:else}
                                    <span class="text-xs text-gray-600 dark:text-gray-300">
                                        {list.user.name.charAt(0).toUpperCase()}
                                    </span>
                                {/if}
                            </div>
                        </Link>
                        <Link
                            href={route('lists.user-public', list.user.id)}
                            class="truncate text-sm text-gray-600 hover:underline dark:text-gray-300"
                            title={list.user.name}
                        >
                            {list.user.name}
                        </Link>
                    </div>
                {/if}
                <div class="mb-2 flex items-center gap-2">
                    {#if !list.is_default}
                        <Badge tone={typeTone} size="sm">{formatListType(list.type)}</Badge>
                    {/if}
                    {#if list.is_public}
                        <Badge tone="primary" size="sm">Public</Badge>
                    {:else}
                        <Badge tone="orange" size="sm">Private</Badge>
                    {/if}
                </div>
                <h2 id="list-title-{list.id}" class="mb-2 truncate text-xl font-semibold text-gray-900 dark:text-white">
                    <Link
                        href={route('lists.show', list.id)}
                        class="transition-colors hover:text-blue-600 dark:hover:text-blue-400"
                        title={list.name}
                    >
                        {list.name}
                    </Link>
                </h2>
                <div class="mb-3 text-xs text-gray-500 dark:text-gray-400">
                    {entriesCount}
                    {entriesCount === 1 ? 'game' : 'games'} &middot; Updated {formatDate(list.updated_at || list.created_at)}
                </div>
                <p class="line-clamp-2 text-sm text-gray-600 dark:text-gray-300">
                    {list.description || 'No description available.'}
                </p>
            </div>
        </div>

        {#if currentGame}
            <div class="group relative mt-4" role="region" aria-roledescription="carousel" aria-label="Games in {list.name}">
                <span class="sr-only" aria-live="polite">
                    {currentGame?.effective_name} - slide {index + 1} of {total}
                </span>
                <Link href={route('games.show', currentGame.slug)} class="block" onkeydown={handleKeydown}>
                    <div
                        class="relative aspect-[315/250] w-full overflow-hidden rounded-lg bg-gray-100 ring-1 ring-gray-200/60 dark:bg-gray-700 dark:ring-gray-700/60"
                    >
                        {#if getThumb(currentGame)}
                            <img
                                src={getThumb(currentGame)}
                                alt={gameCoverAltText(currentGame.effective_name)}
                                title={currentGame.effective_name}
                                class="h-full w-full {currentGame.platform === 'steam'
                                    ? 'object-contain'
                                    : 'object-cover'} transition-opacity group-hover:opacity-90"
                            />
                        {:else}
                            <div class="flex h-full w-full items-center justify-center text-sm text-gray-400">No image</div>
                        {/if}
                        <div class="absolute inset-0 bg-black/60 opacity-0 transition-opacity group-hover:opacity-100"></div>
                        <div class="absolute top-2 right-2 rounded bg-black/60 px-2 py-0.5 text-xs text-white">
                            {index + 1}/{total}
                        </div>
                        {#if total > 1}
                            <Button
                                onclick={goPrev}
                                aria-label="Previous"
                                variant="ghost"
                                tone="neutral"
                                size="icon-sm"
                                class="absolute top-1/2 left-2 -translate-y-1/2 rounded-full bg-black/50 p-1 text-white opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-black/60 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-white/80"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path
                                        fill-rule="evenodd"
                                        d="M15.78 4.22a.75.75 0 010 1.06L9.06 12l6.72 6.72a.75.75 0 11-1.06 1.06l-7.25-7.25a.75.75 0 010-1.06l7.25-7.25a.75.75 0 011.06 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </Button>
                            <Button
                                onclick={goNext}
                                aria-label="Next"
                                variant="ghost"
                                tone="neutral"
                                size="icon-sm"
                                class="absolute top-1/2 right-2 -translate-y-1/2 rounded-full bg-black/50 p-1 text-white opacity-0 group-focus-within:opacity-100 group-hover:opacity-100 hover:bg-black/60 focus-visible:opacity-100 focus-visible:ring-2 focus-visible:ring-white/80"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="h-5 w-5">
                                    <path
                                        fill-rule="evenodd"
                                        d="M8.22 19.78a.75.75 0 010-1.06L14.94 12 8.22 5.28a.75.75 0 111.06-1.06l7.25 7.25a.75.75 0 010 1.06l-7.25 7.25a.75.75 0 01-1.06 0z"
                                        clip-rule="evenodd"
                                    />
                                </svg>
                            </Button>
                        {/if}
                    </div>
                </Link>
                <div class="mt-2">
                    <Link
                        href={route('games.show', currentGame.slug)}
                        class="block truncate text-sm break-words text-gray-900 hover:underline dark:text-gray-100"
                        title={currentGame.effective_name}
                    >
                        {currentGame.effective_name}
                    </Link>
                </div>
            </div>
        {:else if list.entries.length > 0}
            <div class="border-t border-gray-200/50 px-6 py-4 dark:border-gray-700/50">
                <h3 class="mb-3 text-sm font-medium text-gray-900 dark:text-white">Featured Games</h3>
                <div class="flex flex-wrap gap-2">
                    {#each list.entries.slice(0, 3) as entry (entry.id)}
                        <Link
                            href={route('games.show', entry.game.slug)}
                            class="rounded-md bg-gray-100/80 px-2 py-1 text-xs text-gray-800 transition-colors hover:bg-gray-200 dark:bg-gray-700/80 dark:text-gray-200 dark:hover:bg-gray-600"
                        >
                            {entry.game.effective_name.length > 25 ? entry.game.effective_name.substring(0, 25) + '...' : entry.game.effective_name}
                        </Link>
                    {/each}
                    {#if entriesCount > 3}
                        <span class="rounded-md bg-gray-100/80 px-2 py-1 text-xs text-gray-800 dark:bg-gray-700/80 dark:text-gray-200">
                            +{entriesCount - 3} more
                        </span>
                    {/if}
                </div>
            </div>
        {/if}
    </div>

    <div class="mt-auto border-t border-gray-200/50 px-6 py-4 dark:border-gray-700/50">
        <div class="flex flex-wrap items-center gap-4">
            <Link
                href={route('lists.show', list.id)}
                class="text-sm font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
            >
                View List
            </Link>
            {#if showUser && list.user}
                <Link
                    href={route('lists.user-public', list.user.id)}
                    class="text-sm font-medium text-blue-600 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                >
                    More by {list.user.name}
                </Link>
            {/if}
            {#if showActions && isOwner}
                {#if onToggleVisibility}
                    <Button
                        onclick={handleToggleVisibility}
                        disabled={isToggling}
                        variant="link"
                        tone={list.is_public ? 'primary' : 'neutral'}
                        size="sm"
                    >
                        {isToggling ? 'Updating...' : list.is_public ? 'Make Private' : 'Make Public'}
                    </Button>
                {/if}
                <Link
                    href={route('lists.edit', list.id)}
                    class="text-sm font-medium text-amber-700 transition-colors hover:text-amber-800 dark:text-amber-300 dark:hover:text-amber-200"
                >
                    Edit
                </Link>
                {#if !list.is_default && onDelete}
                    <Button onclick={handleDelete} variant="link" tone="danger" size="sm">Delete</Button>
                {/if}
            {/if}
        </div>
    </div>
</Card>
