<script lang="ts">
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { cancelAdditionRequest, fetchAdditionRequests, submitAdditionRequests, type AdditionRequest } from '@/api';
    import { getErrorMessage, useAsyncAction } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { Alert, Badge, Button, Card, EmptyState, Select, TextInput, Textarea } from '@/components/ui';
    import type { BadgeTone } from '@/components/ui/Badge.svelte';

    interface AdditionsTabProps {
        recentRequests: AdditionRequest[];
    }

    let { recentRequests: recentRequestsInitial }: AdditionsTabProps = $props();

    let requestText = $state('');
    let requests = $state<AdditionRequest[]>(untrack(() => recentRequestsInitial || []));
    let requestsLoading = $state(true);
    let requestsError = $state<string | null>(null);
    let requestsRefresh = $state(0);
    let requestSearch = $state('');
    let requestStatus = $state<'all' | 'pending' | 'processing' | 'approved' | 'rejected'>('all');
    const submitAction = useAsyncAction();

    const submitRequest = async () => {
        const trimmed = requestText.trim();
        if (!trimmed || submitAction.isLoading) return;
        const data = await submitAction.run(
            async () => {
                const result = await submitAdditionRequests(trimmed);
                if (!result.success) throw new Error(result.message || 'No requests were submitted.');
                return result;
            },
            { fallbackError: 'An error occurred while submitting requests.' },
        );
        if (!data) return;
        toast.success(data.message || `Successfully submitted ${data.result.success_count} request(s)!`);
        requestText = '';
        requestsRefresh++;
    };

    const cancelRequest = async (id: number) => {
        try {
            await cancelAdditionRequest(id);
            requestsRefresh++;
        } catch (error) {
            toast.error(getErrorMessage(error, 'Failed to cancel addition request.'));
        }
    };

    $effect(() => {
        const status = requestStatus;
        void requestsRefresh;
        let active = true;
        requestsLoading = true;
        requestsError = null;
        fetchAdditionRequests({ status })
            .then((result) => {
                if (active) requests = result;
            })
            .catch((error) => {
                if (active) requestsError = getErrorMessage(error, 'Failed to load addition requests.');
            })
            .finally(() => {
                if (active) requestsLoading = false;
            });
        return () => {
            active = false;
        };
    });

    const filteredRequests = $derived(
        requests.filter((request) => {
            const search = requestSearch.trim().toLowerCase();

            if (!search) {
                return true;
            }

            return (
                request.game_url.toLowerCase().includes(search) ||
                request.status.toLowerCase().includes(search) ||
                request.status_label.toLowerCase().includes(search) ||
                (request.game?.name?.toLowerCase() ?? '').includes(search)
            );
        }),
    );

    const statusBadgeTones: Record<string, BadgeTone> = {
        yellow: 'warning',
        green: 'success',
        red: 'danger',
    };
</script>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
    <div class="space-y-6 lg:col-span-2">
        <Card variant="flat" padding="lg">
            <h2 class="mb-4 text-title font-semibold text-fg">Request VN Addition</h2>
            <p class="mb-3 text-sm text-fg-muted">
                Submit URLs for visual novels you'd like to see added to the site. We support itch.io, Steam, and other platforms. You can submit
                multiple URLs at once, one per line.
            </p>
            <div class="space-y-3">
                <Textarea
                    id="game-urls"
                    label="Game URLs"
                    bind:value={requestText}
                    rows={5}
                    placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/game-name&#10;..."
                />
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        onclick={submitRequest}
                        disabled={submitAction.isLoading || !requestText.trim()}
                        loading={submitAction.isLoading}
                    >
                        {submitAction.isLoading ? 'Submitting...' : 'Submit Requests'}
                    </Button>
                    <Button type="button" variant="soft" tone="neutral" onclick={() => (requestText = '')}>Clear</Button>
                </div>
            </div>
            <Alert title="Guidelines" tone="info" role="status" class="mt-4">
                <ul class="list-inside list-disc space-y-1">
                    <li>Supported platforms: itch.io, Steam, and other game storefronts</li>
                    <li>Submit one URL per line for bulk requests</li>
                    <li>Maximum 50 URLs per submission</li>
                    <li>Games already on the site will be automatically filtered out</li>
                    <li>Duplicate requests are automatically handled</li>
                </ul>
            </Alert>
        </Card>
    </div>

    <div class="space-y-6 lg:col-span-3">
        <Card variant="flat" padding="lg">
            <div class="mb-6 flex items-center justify-between">
                <h2 class="text-title font-semibold text-fg">My Requests</h2>
                {#if !requestsLoading && !requestsError}
                    <span class="text-sm text-fg-faint">{filteredRequests.length} request(s)</span>
                {/if}
            </div>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row">
                <div class="flex-1">
                    <TextInput
                        type="text"
                        placeholder="Search by URL or status..."
                        aria-label="Search addition requests"
                        bind:value={requestSearch}
                    />
                </div>
                <div>
                    <Select aria-label="Filter addition requests by status" bind:value={requestStatus}>
                        <option value="all">All Requests</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </Select>
                </div>
            </div>
            {#if requestsLoading}
                <p role="status" class="py-8 text-center text-sm text-fg-muted">Loading requests…</p>
            {:else if requestsError}
                <Alert title="Could not load requests" tone="danger">
                    <p>{requestsError}</p>
                    {#snippet actions()}
                        <Button type="button" variant="outline" tone="danger" onclick={() => requestsRefresh++}>Retry</Button>
                    {/snippet}
                </Alert>
            {:else if filteredRequests.length > 0}
                <div class="space-y-2">
                    {#each filteredRequests as req (req.id)}
                        <div class="flex items-center justify-between rounded-lg border border-border bg-surface-alt p-3">
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-fg">
                                    {req.game?.name || req.game_url}
                                </div>
                                {#if req.game}
                                    <a
                                        href={req.game_url}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="mt-1 block truncate text-xs text-fg-muted hover:text-fg hover:underline"
                                    >
                                        {req.game_url}
                                    </a>
                                {/if}
                                <Badge tone={statusBadgeTones[req.status_color] ?? 'neutral'} class="mt-1">{req.status_label}</Badge>
                            </div>
                            <div class="ml-3 flex items-center gap-3">
                                {#if req.status === 'approved' && req.game}
                                    <Link href={route('games.show', req.game.slug)} class="text-xs text-fg-muted hover:text-fg hover:underline"
                                        >View entry</Link
                                    >
                                {/if}
                                {#if req.status === 'pending' || req.status === 'processing'}
                                    <Button type="button" variant="link" tone="danger" onclick={() => cancelRequest(req.id)}>Cancel</Button>
                                {/if}
                            </div>
                        </div>
                    {/each}
                </div>
            {:else}
                <EmptyState
                    title="No requests found"
                    description={requestSearch || requestStatus !== 'all'
                        ? 'Try another search or status filter.'
                        : "You haven't submitted any addition requests yet."}
                />
            {/if}
        </Card>
    </div>
</div>
