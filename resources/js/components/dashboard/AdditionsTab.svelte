<script lang="ts">
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';
    import { cancelAdditionRequest, fetchAdditionRequests, submitAdditionRequests, type AdditionRequest } from '@/api';
    import { toast } from '@/utils/toast';
    import { Alert, Button, Card } from '@/components/ui';

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
    let submittingRequest = $state(false);

    const submitRequest = async () => {
        const trimmed = requestText.trim();
        if (!trimmed || submittingRequest) return;
        submittingRequest = true;
        try {
            const data = await submitAdditionRequests(trimmed);
            if (!data.success) throw new Error(data.message || 'No requests were submitted.');
            toast.success(data.message || `Successfully submitted ${data.result.success_count} request(s)!`);
            requestText = '';
            requestsRefresh++;
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'An error occurred while submitting requests.');
        } finally {
            submittingRequest = false;
        }
    };

    const cancelRequest = async (id: number) => {
        try {
            await cancelAdditionRequest(id);
            requestsRefresh++;
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to cancel addition request.');
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
                if (active) requestsError = error instanceof Error ? error.message : 'Failed to load addition requests.';
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

    const getStatusBadgeClasses = (color: string) => {
        switch (color) {
            case 'warning':
            case 'yellow':
                return 'border-amber-600/50 text-amber-800 dark:border-amber-500/40 dark:text-amber-300';
            case 'info':
            case 'blue':
                return 'border-border-strong text-fg-muted';
            case 'success':
            case 'green':
                return 'border-green-600/50 text-green-800 dark:border-green-500/40 dark:text-green-300';
            case 'danger':
            case 'red':
                return 'border-red-600/50 text-red-700 dark:border-red-500/40 dark:text-red-300';
            default:
                return 'border-border-strong text-fg-muted';
        }
    };
</script>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
    <div class="space-y-6 lg:col-span-2">
        <Card variant="flat" padding="lg">
            <h2 class="mb-4 text-lg font-semibold text-fg">Request VN Addition</h2>
            <p class="mb-3 text-sm text-fg-muted">
                Submit URLs for visual novels you'd like to see added to the site. We support itch.io, Steam, and other platforms. You can submit
                multiple URLs at once, one per line.
            </p>
            <div class="space-y-3">
                <div>
                    <label for="game-urls" class="block text-sm font-medium text-fg-muted">Game URLs</label>
                    <textarea
                        id="game-urls"
                        bind:value={requestText}
                        rows={5}
                        placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/game-name&#10;..."
                        class="mt-1 w-full rounded-md border border-border bg-surface-alt px-3 py-2 text-sm text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                    ></textarea>
                </div>
                <div class="flex gap-2">
                    <Button
                        type="button"
                        variant="solid"
                        tone="primary"
                        onclick={submitRequest}
                        disabled={submittingRequest || !requestText.trim()}
                        loading={submittingRequest}
                    >
                        {submittingRequest ? 'Submitting...' : 'Submit Requests'}
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
                <h2 class="text-lg font-semibold text-fg">My Requests</h2>
                {#if !requestsLoading && !requestsError}
                    <span class="text-sm text-fg-faint">{filteredRequests.length} request(s)</span>
                {/if}
            </div>
            <div class="mb-6 flex flex-col gap-4 sm:flex-row">
                <div class="flex-1">
                    <input
                        placeholder="Search by URL or status..."
                        class="w-full rounded-md border border-border bg-surface-alt px-3 py-2 text-fg placeholder:text-fg-faint focus:border-border-strong focus:outline-none"
                        type="text"
                        bind:value={requestSearch}
                    />
                </div>
                <div>
                    <select
                        aria-label="Filter addition requests by status"
                        bind:value={requestStatus}
                        class="rounded-md border border-border bg-surface-alt px-3 py-2 text-fg focus:border-border-strong focus:outline-none"
                    >
                        <option value="all">All Requests</option>
                        <option value="pending">Pending</option>
                        <option value="processing">Processing</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                    </select>
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
                                <span
                                    class="mt-1 inline-flex rounded-[3px] border px-1.5 py-0.5 text-[11px] font-semibold tracking-[0.02em] {getStatusBadgeClasses(
                                        req.status_color,
                                    )}">{req.status_label}</span
                                >
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
                <div class="py-8 text-center">
                    <div class="text-sm font-medium text-fg-muted">No requests found</div>
                    <div class="text-xs text-fg-faint">
                        {requestSearch || requestStatus !== 'all'
                            ? 'Try another search or status filter.'
                            : "You haven't submitted any addition requests yet."}
                    </div>
                </div>
            {/if}
        </Card>
    </div>
</div>
