<script lang="ts">
    import { Card, Checkbox, Select } from '@/components/ui';

    type Props = {
        showOnlyReviews: boolean;
        showOnlyVisibleGames: boolean;
        platform?: string;
        stars?: number | '';
        sortField: 'published_at' | 'rating';
        sortDirection: 'asc' | 'desc';
        showPlatform?: boolean;
        showStars?: boolean;
        onFilterChange: () => void;
        embedded?: boolean;
    };

    let {
        showOnlyReviews = $bindable(),
        showOnlyVisibleGames = $bindable(),
        platform = $bindable(''),
        stars = $bindable(''),
        sortField = $bindable(),
        sortDirection = $bindable(),
        showPlatform = false,
        showStars = false,
        onFilterChange,
        embedded = false,
    }: Props = $props();

    function updateSort(event: Event) {
        const [field, direction] = (event.target as HTMLSelectElement).value.split(':');
        sortField = field as 'published_at' | 'rating';
        sortDirection = direction as 'asc' | 'desc';
        onFilterChange();
    }

    function updateStars(event: Event) {
        const value = (event.target as HTMLSelectElement).value;
        stars = value === '' ? '' : Number(value);
        onFilterChange();
    }
</script>

{#snippet controls()}
    <div class="flex flex-wrap items-center gap-4">
        <Checkbox label="Reviews only" bind:checked={showOnlyReviews} onchange={onFilterChange} />
        <Checkbox label="Listed games only" bind:checked={showOnlyVisibleGames} onchange={onFilterChange} />
        {#if showPlatform}
            <div class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span>Platform:</span>
                <Select bind:value={platform} onchange={onFilterChange} class="py-1">
                    <option value="">Any</option>
                    <option value="itch_io">itch.io</option>
                    <option value="steam">Steam</option>
                </Select>
            </div>
        {/if}
        {#if showStars}
            <div class="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
                <span>Stars:</span>
                <Select value={stars} onchange={updateStars} class="py-1">
                    <option value="">Any</option>
                    {#each [5, 4, 3, 2, 1] as rating (rating)}
                        <option value={rating}>{rating} Stars</option>
                    {/each}
                </Select>
            </div>
        {/if}
        <div class="ml-auto inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <span>Sort by:</span>
            <Select value={`${sortField}:${sortDirection}`} onchange={updateSort} class="py-1">
                <option value="published_at:desc">Newest</option>
                <option value="published_at:asc">Oldest</option>
                <option value="rating:desc">Rating: High to Low</option>
                <option value="rating:asc">Rating: Low to High</option>
            </Select>
        </div>
    </div>
{/snippet}

{#if embedded}
    {@render controls()}
{:else}
    <Card padding="sm" class="shadow">{@render controls()}</Card>
{/if}
