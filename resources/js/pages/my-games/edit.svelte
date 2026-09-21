<script lang="ts">
    import { refreshPage } from '@/utils/refreshPage';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import CheckIcon from '@/components/icons/Check.svelte';
    import PlusIcon from '@/components/icons/Plus.svelte';
    import XMarkIcon from '@/components/icons/XMark.svelte';
    import GameStats from '@/components/GameStats.svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { notify } from '@/components/Toast.svelte';
    import { Button, Card, Select, TextInput } from '@/components/ui';
    import { updateMyGameLinks } from '@/api/my-games';
    import { httpValidationErrors } from '@/utils/http';
    import { formatLocalDateTime, toLocalDateTimeInput } from '@/utils/date-formatting';

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

    function editableLinks(links: GameLink[]): GameLink[] {
        return links.map((link) => ({ ...link, release_at: toLocalDateTimeInput(link.release_at) }));
    }

    let links = $derived(editableLinks(game.additional_links ?? []));

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
        if (saving) return;
        formErrors = {};
        saving = true;
        try {
            await updateMyGameLinks(game.slug, sortedLinks);
            if (!(await refreshPage(['game']))) return;
            notify('Changes saved successfully', 'success');
        } catch (e: unknown) {
            const validationErrors = httpValidationErrors(e);
            if (validationErrors) {
                Object.entries(validationErrors).forEach(([key, val]) => {
                    formErrors[key] = Array.isArray(val) ? String(val[0]) : String(val);
                });
                notify(e instanceof Error ? e.message : 'Failed to save changes', 'error');
            } else {
                const errorMessage = e instanceof Error ? e.message : 'Request failed';
                formErrors['links'] = errorMessage;
                notify(errorMessage, 'error');
            }
        } finally {
            saving = false;
        }
    }
</script>

<SeoHead {metaTags} title={`Edit ${game.name}`} />

<div class="space-y-8">
    <PageHeader
        title={`Edit ${game.name}`}
        description="Manage download links and view analytics for your game"
        backHref={route('my-games.index')}
        backLabel="Back to My Games"
    >
        {#snippet actions()}
            <Button onclick={save} disabled={saving} loading={saving}>
                {#if !saving}
                    <CheckIcon class="h-4 w-4" />
                {/if}
                {saving ? 'Saving...' : 'Save Changes'}
            </Button>
        {/snippet}
    </PageHeader>

    <Card variant="flat">
        <div class="mb-6 flex items-start justify-between">
            <div>
                <h2 class="text-xl font-semibold text-fg">Download Links</h2>
                <p class="mt-1 text-sm text-fg-muted">Add download links for your game. {sortedLinks.length} of 15 links used.</p>
            </div>
            <Button onclick={addLink} disabled={saving || sortedLinks.length >= 15} tone="success">
                <PlusIcon class="h-4 w-4" />
                <span>{sortedLinks.length >= 15 ? 'Limit Reached' : 'Add Link'}</span>
            </Button>
        </div>

        <div class="space-y-3">
            {#if sortedLinks.length === 0}
                <div class="text-sm text-fg-muted">No links added yet.</div>
            {/if}
            {#each sortedLinks as link, index (link.id ?? `new-${index}`)}
                <div
                    class="grid grid-cols-12 items-start gap-3 rounded-lg border border-border bg-surface p-4 transition-colors hover:border-border-strong"
                >
                    <div class="col-span-3">
                        <TextInput
                            value={link.name}
                            oninput={(e) => updateLink(index, { ...link, name: e.currentTarget.value })}
                            placeholder="Link name"
                            disabled={saving}
                        />
                    </div>
                    <div class="col-span-6">
                        <TextInput
                            value={link.url}
                            oninput={(e) => updateLink(index, { ...link, url: e.currentTarget.value })}
                            placeholder="https://..."
                            disabled={saving}
                        />
                    </div>
                    <div class="col-span-2">
                        <Select
                            value={link.platform ?? ''}
                            onchange={(e) => updateLink(index, { ...link, platform: e.currentTarget.value || null })}
                            disabled={saving}
                        >
                            <option value="">Platform</option>
                            {#each platforms as p (p)}
                                <option value={p}>{p}</option>
                            {/each}
                        </Select>
                    </div>
                    <div class="col-span-1 flex items-center justify-end gap-1">
                        <Button onclick={() => removeLink(index)} tone="danger" size="icon-sm" aria-label="Remove link" disabled={saving}>
                            <XMarkIcon class="h-4 w-4" />
                        </Button>
                    </div>
                    <div class="col-span-12 mt-2">
                        <label for="release-date-{index}" class="mb-1 block text-sm font-medium text-fg-muted">
                            Release Date & Time <span class="text-fg-faint">(Optional)</span>
                        </label>
                        <TextInput
                            id="release-date-{index}"
                            type="datetime-local"
                            value={link.release_at || ''}
                            onchange={(e) => updateLink(index, { ...link, release_at: e.currentTarget.value || null })}
                            class="w-auto"
                            disabled={saving}
                        />
                        <p class="mt-1 text-xs text-fg-faint">Leave empty to make the download available immediately.</p>
                    </div>
                    {#if link.last_edited_at}
                        <div class="col-span-12 mt-1 text-xs text-fg-faint">
                            Last edited: {formatLocalDateTime(link.last_edited_at)}
                        </div>
                    {/if}
                </div>
            {/each}
        </div>

        {#if formErrors['links']}
            <div class="mt-3 text-sm text-red-600 dark:text-red-400">{formErrors['links']}</div>
        {/if}

        <div class="mt-4 text-xs text-fg-faint">Security: localhost and private IP addresses are blocked. Up to 15 links allowed.</div>
    </Card>

    <GameStats {clickStats} {dailyStats} />
</div>
