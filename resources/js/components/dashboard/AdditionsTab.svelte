<script lang="ts">
  import { untrack } from 'svelte';
  import { SvelteURLSearchParams } from 'svelte/reactivity';
  import { Link } from '@inertiajs/svelte';
  import { authenticatedFetch } from '@/utils/csrf';
  import { toast } from '@/utils/toast';
  import { Button, Card } from '@/components/ui';

  interface AdditionRequest {
    id: number;
    game_url: string;
    platform?: string;
    status: string;
    status_label: string;
    status_color: string;
    created_at: string;
    reviewed_at?: string;
    rejection_reason?: string;
    game?: { id: number; name: string; slug: string };
    reviewer?: { name: string };
  }

  interface AdditionsTabProps {
    recentRequests: AdditionRequest[];
  }

  let { recentRequests: recentRequestsInitial }: AdditionsTabProps = $props();

  let requestText = $state('');
  type SubmissionResult = {
    success_count: number;
    duplicate_count: number;
    invalid_count: number;
    already_exists_count?: number;
    errors: string[];
  };
  let requests = $state<AdditionRequest[]>(untrack(() => recentRequestsInitial || []));
  let _requestsLoading = $state(false);
  let _requestResults: SubmissionResult | null = $state(null);
  let _showRequestSuccess = $state(false);
  let requestSearch = $state('');
  let requestStatus = $state<'all' | 'pending' | 'processing' | 'approved' | 'rejected'>('all');
  let submittingRequest = $state(false);

  async function jsonGet<T>(url: string): Promise<T> {
    const res = await fetch(url, { credentials: 'same-origin' });
    if (!res.ok) throw new Error(`GET ${url} failed (${res.status})`);
    return res.json();
  }

  async function jsonPost<T>(url: string, payload: unknown): Promise<T> {
    const res = await authenticatedFetch(url, { method: 'POST', body: JSON.stringify(payload) });
    const data = await res.json();
    if (!res.ok || data?.success === false) {
      if (data?.message) toast.error(String(data.message));
      throw new Error(data?.message || 'Request failed');
    }
    return data;
  }

  const loadRequests = async (opts?: { status?: string; search?: string }) => {
    _requestsLoading = true;
    try {
      const params = new SvelteURLSearchParams();
      params.set('status', (opts?.status ?? requestStatus) as string);
      if ((opts?.search ?? requestSearch).trim() !== '') params.set('search', (opts?.search ?? requestSearch).trim());
      const res = await jsonGet<{ success: boolean; requests: AdditionRequest[] }>(
        `${route('browser-api.dashboard.addition-requests.index')}?${params.toString()}`,
      );
      if (res.success) requests = res.requests;
    } catch {
      /* ignore */
    } finally {
      _requestsLoading = false;
    }
  };

  const submitRequest = async () => {
    const trimmed = requestText.trim();
    if (!trimmed) return;
    submittingRequest = true;
    try {
      const res = await authenticatedFetch(route('browser-api.dashboard.addition-requests.submit'), {
        method: 'POST',
        body: JSON.stringify({ urls: trimmed }),
      });
      const data = await res.json();
      if (res.ok && data?.success) {
        const result: SubmissionResult = data.result;
        _requestResults = result;
        _showRequestSuccess = result?.success_count > 0;
        if (result?.success_count > 0) {
          toast.success(data.message || `Successfully submitted ${result.success_count} request(s)!`);
          requestText = '';
        }
        await loadRequests({ status: requestStatus, search: requestSearch });
      } else {
        _requestResults = data?.result ?? { success_count: 0, duplicate_count: 0, invalid_count: 0, errors: [] };
        _showRequestSuccess = false;
      }
    } catch {
      toast.error('An error occurred while submitting requests.');
    } finally {
      submittingRequest = false;
    }
  };

  const cancelRequest = async (id: number) => {
    try {
      await jsonPost(route('browser-api.dashboard.addition-requests.cancel', { request: id }), {});
      await loadRequests({ status: requestStatus, search: requestSearch });
    } catch {
      /* noop */
    }
  };

  $effect(() => {
    void requestStatus;
    loadRequests({ status: requestStatus });
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
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400';
      case 'info':
      case 'blue':
        return 'bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400';
      case 'success':
      case 'green':
        return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400';
      case 'danger':
      case 'red':
        return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300';
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400';
    }
  };
</script>

<div class="grid grid-cols-1 gap-6 lg:grid-cols-5">
    <div class="space-y-6 lg:col-span-2">
      <Card padding="lg">
        <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">Request VN Addition</h2>
        <p class="mb-3 text-sm text-gray-600 dark:text-gray-400">
          Submit URLs for visual novels you'd like to see added to the site. We support itch.io, Steam, and other
          platforms. You can submit multiple URLs at once, one per line.
        </p>
        <div class="space-y-3">
          <div>
            <label for="game-urls" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Game URLs</label>
            <textarea
              id="game-urls"
              bind:value={requestText}
              rows={5}
              placeholder="https://developer.itch.io/game-name&#10;https://store.steampowered.com/app/123456/game-name&#10;..."
              class="mt-1 w-full rounded-md border border-gray-300 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700 dark:text-white"
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
              class="rounded-md bg-blue-600 px-4 py-2 text-sm font-medium text-white hover:bg-blue-700 disabled:opacity-50"
            >
              {submittingRequest ? 'Submitting...' : 'Submit Requests'}
            </Button>
            <Button
              type="button"
              variant="soft"
              tone="neutral"
              onclick={() => (requestText = '')}
              class="rounded-md bg-gray-200 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-300 dark:bg-gray-600 dark:text-gray-300 dark:hover:bg-gray-500"
            >
              Clear
            </Button>
          </div>
        </div>
        <div class="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4 dark:border-blue-800 dark:bg-blue-900/20">
          <h3 class="text-sm font-semibold text-blue-800 dark:text-blue-300">Guidelines:</h3>
          <ul class="mt-2 list-inside list-disc space-y-1 text-sm text-blue-700 dark:text-blue-400">
            <li>Supported platforms: itch.io, Steam, and other game storefronts</li>
            <li>Submit one URL per line for bulk requests</li>
            <li>Maximum 50 URLs per submission</li>
            <li>Games already on the site will be automatically filtered out</li>
            <li>Duplicate requests are automatically handled</li>
          </ul>
        </div>
      </Card>
    </div>

    <div class="space-y-6 lg:col-span-3">
      <Card padding="lg">
        <div class="mb-6 flex items-center justify-between">
          <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My Requests</h2>
          <span class="text-sm text-gray-500 dark:text-gray-400">{filteredRequests.length} request(s)</span>
        </div>
        <div class="mb-6 flex flex-col gap-4 sm:flex-row">
          <div class="flex-1">
            <input
              placeholder="Search by URL or status..."
              class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
              type="text"
              bind:value={requestSearch}
            />
          </div>
          <div>
            <select
              aria-label="Filter addition requests by status"
              bind:value={requestStatus}
              class="rounded-lg border border-gray-300 px-3 py-2 focus:border-blue-500 focus:ring-2 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
              <option value="all">All Requests</option>
              <option value="pending">Pending</option>
              <option value="processing">Processing</option>
              <option value="approved">Approved</option>
              <option value="rejected">Rejected</option>
            </select>
          </div>
        </div>
        {#if filteredRequests.length > 0}
          <div class="space-y-2">
            {#each filteredRequests as req (req.id)}
              <div class="flex items-center justify-between rounded-lg bg-gray-50 p-3 dark:bg-gray-700/50">
                <div class="min-w-0 flex-1">
                  <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                    {req.game?.name || req.game_url}
                  </div>
                  {#if req.game}
                    <a
                      href={req.game_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      class="mt-1 block truncate text-xs text-gray-500 hover:text-gray-700 hover:underline dark:text-gray-400 dark:hover:text-gray-200"
                    >
                      {req.game_url}
                    </a>
                  {/if}
                  <span
                    class="inline-flex rounded-full px-2 py-0.5 text-xs font-medium {getStatusBadgeClasses(
                      req.status_color,
                    )}">{req.status_label}</span
                  >
                </div>
                <div class="ml-3 flex items-center gap-3">
                  {#if req.status === 'approved' && req.game}
                    <Link
                      href={route('games.show', req.game.slug)}
                      class="text-xs text-blue-600 hover:underline dark:text-blue-400">View entry</Link
                    >
                  {/if}
                  {#if req.status === 'pending' || req.status === 'processing'}
                    <Button type="button" variant="link" tone="danger" onclick={() => cancelRequest(req.id)}
                      >Cancel</Button
                    >
                  {/if}
                </div>
              </div>
            {/each}
          </div>
        {:else}
          <div class="py-8 text-center">
            <div class="mx-auto mb-3 h-12 w-12 text-gray-300 dark:text-gray-600">
              <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5"
                ><path
                  stroke-linecap="round"
                  stroke-linejoin="round"
                  d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"
                /></svg
              >
            </div>
            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">No requests found</div>
            <div class="text-xs text-gray-400 dark:text-gray-500">You haven't submitted any addition requests yet.</div>
          </div>
        {/if}
      </Card>
    </div>
  </div>
