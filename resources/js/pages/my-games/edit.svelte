<script lang="ts">
    import GameStats from '@/components/GameStats.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { notify } from '@/components/Toast.svelte';
    import { Button, Card } from '@/components/ui';
    import { authenticatedFetch } from '@/utils/http';
    import { formatLocalDateTime } from '@/utils/date-formatting';
    import { untrack } from 'svelte';

    interface GameLink {
        id?: string;
        name: string;
        url: string;
        platform?: string | null;
        sort_order?: number;
        last_edited_at?: string;
        release_at?: string | null;
    }

    interface GamePayload {
        id: number;
        name: string;
        slug: string;
        additional_links?: GameLink[];
        thumb_url?: string | null;
        screenshots?: Array<{
            url: string;
            width?: number;
            height?: number;
            optimized?: Record<string, { path: string; width: number; height: number }>;
        }>;
        custom_screenshots?: Array<{
            url: string;
            width?: number;
            height?: number;
            optimized?: Record<string, { path: string; width: number; height: number }>;
        }>;
        optimized_thumbnails?: Record<string, { path: string; width: number; height: number }>;
    }

    interface DailyStats {
        date: string;
        page_views_unique: number;
        page_views_total: number;
        external_project_unique: number;
        external_project_total: number;
        custom_links_unique: number;
        custom_links_total: number;
    }

    interface ClickStats {
        page_views_total: number;
        page_views_unique: number;
        last_page_view?: string;
        external_project_total: number;
        external_project_unique: number;
        last_external_project?: string;
        custom_links?: Array<{ link_id: string; link_name: string; total_clicks: number; unique_clicks: number; last_click?: string }>;
    }

    interface Props {
        game: GamePayload;
        platforms: string[];
        clickStats?: ClickStats;
        dailyStats?: DailyStats[];
        metaTags?: { title?: string };
    }

    let { game, platforms, clickStats, dailyStats, metaTags }: Props = $props();

    let links = $state<GameLink[]>(
        untrack(() =>
            (Array.isArray(game.additional_links) ? [...game.additional_links] : []).map((link) => {
                if (!link.release_at) return link;
                const utcDate = new Date(link.release_at);
                const year = utcDate.getFullYear();
                const month = String(utcDate.getMonth() + 1).padStart(2, '0');
                const day = String(utcDate.getDate()).padStart(2, '0');
                const hours = String(utcDate.getHours()).padStart(2, '0');
                const minutes = String(utcDate.getMinutes()).padStart(2, '0');
                return { ...link, release_at: `${year}-${month}-${day}T${hours}:${minutes}` };
            }),
        ),
    );

    let saving = $state(false);
    let formErrors = $state<Record<string, string>>({});

    let sortedLinks = $derived(links.map((l, i) => ({ ...l, sort_order: i })));

    function addLink() {
        links = [...links, { id: undefined, name: '', url: '', platform: null, release_at: null }];
    }

    function updateLink(idx: number, next: GameLink) {
        links = links.map((l, i) => (i === idx ? next : l));
    }

    function removeLink(idx: number) {
        links = links.filter((_, i) => i !== idx);
    }

    async function save() {
        formErrors = {};
        saving = true;
        try {
            const timezoneOffset = -new Date().getTimezoneOffset() / 60;
            const res = await authenticatedFetch(route('browser-api.my-games.update', { game: game.slug }), {
                method: 'PUT',
                body: JSON.stringify({ links: sortedLinks, timezone_offset: timezoneOffset }),
            });
            const data = await res.json().catch(() => ({}));
            if (!res.ok || data?.success === false) {
                if (data?.errors) {
                    Object.entries(data.errors as Record<string, unknown>).forEach(([key, val]) => {
                        formErrors[key] = Array.isArray(val) ? String(val[0]) : String(val);
                    });
                }
                notify(data?.message || 'Failed to save changes', 'error');
                return;
            }
            notify('Changes saved successfully', 'success');
        } catch (e: unknown) {
            const errorMessage = e instanceof Error ? e.message : 'Request failed';
            formErrors['links'] = errorMessage;
            notify(errorMessage, 'error');
        } finally {
            saving = false;
        }
    }
</script>

<svelte:head>
    <title>{metaTags?.title || `Edit ${game.name}`}</title>
</svelte:head>

<div class="space-y-8">
    <PageHeader
        title={`Edit ${game.name}`}
        description="Manage download links and view analytics for your game"
        backHref={route('my-games.index')}
        backLabel="Back to My Games"
        class="mb-0"
    >
        {#snippet actions()}
            <Button onclick={save} disabled={saving} loading={saving}>
                {#if !saving}
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                {/if}
                {saving ? 'Saving...' : 'Save Changes'}
            </Button>
        {/snippet}
    </PageHeader>

    <Card variant="glass">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white">Download Links</h2>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">Add download links for your game. {sortedLinks.length} of 15 links used.</p>
            </div>
            <Button onclick={addLink} disabled={saving || sortedLinks.length >= 15} tone="success">
                <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
                </svg>
                <span>{sortedLinks.length >= 15 ? 'Limit Reached' : 'Add Link'}</span>
            </Button>
        </div>

        <div class="space-y-3">
            {#if sortedLinks.length === 0}
                <div class="text-sm text-gray-600 dark:text-gray-400">No links added yet.</div>
            {/if}
            {#each sortedLinks as link, index (link.id ?? `new-${index}`)}
                <div
                    class="grid grid-cols-12 items-start gap-3 rounded-lg border border-gray-200 bg-white p-4 transition-all hover:bg-gray-50 dark:border-gray-600 dark:bg-gray-800 dark:hover:bg-gray-700"
                >
                    <div class="col-span-3">
                        <input
                            value={link.name}
                            oninput={(e) => updateLink(index, { ...link, name: e.currentTarget.value })}
                            placeholder="Link name"
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                            disabled={saving}
                        />
                    </div>
                    <div class="col-span-6">
                        <input
                            value={link.url}
                            oninput={(e) => updateLink(index, { ...link, url: e.currentTarget.value })}
                            placeholder="https://..."
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                            disabled={saving}
                        />
                    </div>
                    <div class="col-span-2">
                        <select
                            value={link.platform ?? ''}
                            onchange={(e) => updateLink(index, { ...link, platform: e.currentTarget.value || null })}
                            class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                            disabled={saving}
                        >
                            <option value="">Platform</option>
                            {#each platforms as p (p)}
                                <option value={p}>{p}</option>
                            {/each}
                        </select>
                    </div>
                    <div class="col-span-1 flex items-center justify-end gap-1">
                        <Button onclick={() => removeLink(index)} tone="danger" size="icon-sm" aria-label="Remove link" disabled={saving}>
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </Button>
                    </div>
                    <div class="col-span-12 mt-2">
                        <label for="release-date-{index}" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Release Date & Time <span class="text-gray-500">(Optional)</span>
                        </label>
                        <input
                            id="release-date-{index}"
                            type="datetime-local"
                            value={link.release_at || ''}
                            onchange={(e) => updateLink(index, { ...link, release_at: e.currentTarget.value || null })}
                            class="w-auto rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm dark:border-gray-500 dark:bg-gray-700 dark:text-white"
                            disabled={saving}
                        />
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Leave empty to make the download available immediately.</p>
                    </div>
                    {#if link.last_edited_at}
                        <div class="col-span-12 mt-1 text-xs text-gray-500 dark:text-gray-400">
                            Last edited: {formatLocalDateTime(link.last_edited_at)}
                        </div>
                    {/if}
                </div>
            {/each}
        </div>

        {#if formErrors['links']}
            <div class="mt-3 text-sm text-red-600 dark:text-red-400">{formErrors['links']}</div>
        {/if}

        <div class="mt-4 text-xs text-gray-500 dark:text-gray-400">
            Security: localhost and private IP addresses are blocked. Up to 15 links allowed.
        </div>
    </Card>

    <GameStats {clickStats} {dailyStats} />
</div>
