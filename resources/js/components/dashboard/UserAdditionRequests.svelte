<script lang="ts">
  import { Button, Card } from '@/components/ui';
  import { onMount } from 'svelte';
  import { fetchAdditionRequests, cancelAdditionRequest, type AdditionRequest } from '@/hooks/api/useAdditionRequests';
  import { toast } from '@/utils/toast';

  const STATUS_OPTIONS: Record<string, string> = {
    all: 'All Requests',
    pending: 'Pending',
    approved: 'Approved',
    rejected: 'Rejected',
  };

  let statusFilter = $state('all');
  let search = $state('');
  let requests = $state<AdditionRequest[]>([]);
  let loading = $state(true);
  let isCanceling = $state(false);

  async function loadRequests() {
    loading = true;
    try {
      requests = await fetchAdditionRequests({ status: statusFilter, search });
    } catch (error) {
      console.error('Failed to fetch requests:', error);
    } finally {
      loading = false;
    }
  }

  onMount(() => {
    loadRequests();
  });

  $effect(() => {
    // Re-fetch when filters change
    void statusFilter;
    void search;
    loadRequests();
  });

  async function cancelRequest(requestId: number) {
    if (!confirm('Are you sure you want to cancel this request?')) {
      return;
    }

    isCanceling = true;

    try {
      const data = await cancelAdditionRequest(requestId);
      if (data.success) {
        toast.success(data.message);
        await loadRequests();
      } else {
        toast.error(data.message || 'Failed to cancel request.');
      }
    } catch (error) {
      console.error('Error canceling request:', error);
      toast.error('An error occurred while canceling the request.');
    } finally {
      isCanceling = false;
    }
  }

  function getStatusBadgeClass(status: string): string {
    switch (status) {
      case 'pending':
        return 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-300';
      case 'approved':
        return 'bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-300';
      case 'rejected':
        return 'bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-300';
      default:
        return 'bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-300';
    }
  }

  const filteredRequests = $derived(
    requests.filter((request) => {
      const matchesSearch =
        search === '' ||
        request.game_url.toLowerCase().includes(search.toLowerCase()) ||
        request.status_label.toLowerCase().includes(search.toLowerCase());
      return matchesSearch;
    }),
  );
</script>

<Card variant="glass" padding="none">
  <div class="p-6">
    <h2 class="mb-4 text-lg font-semibold text-gray-900 dark:text-white">My Addition Requests</h2>

    <!-- Filters -->
    <div class="mb-4 space-y-3 sm:flex sm:gap-3 sm:space-y-0">
      <div class="flex-1">
        <input
          type="text"
          placeholder="Search requests..."
          bind:value={search}
          class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        />
      </div>
      <div>
        <select
          aria-label="Filter addition requests by status"
          bind:value={statusFilter}
          class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:w-auto dark:border-gray-600 dark:bg-gray-700 dark:text-white"
        >
          {#each Object.entries(STATUS_OPTIONS) as [value, label] (value)}
            <option {value}>{label}</option>
          {/each}
        </select>
      </div>
    </div>

    <!-- Requests List -->
    {#if loading}
      <div class="flex items-center justify-center py-8">
        <div class="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
      </div>
    {:else if filteredRequests.length === 0}
      <div class="py-8 text-center text-gray-500 dark:text-gray-400">
        {search || statusFilter !== 'all' ? 'No requests match your filters.' : 'No requests found.'}
      </div>
    {:else}
      <div class="space-y-3">
        {#each filteredRequests as request (request.id)}
          <div class="rounded-lg border border-gray-200 p-4 dark:border-gray-700">
            <div class="flex items-start justify-between">
              <div class="min-w-0 flex-1">
                <div class="mb-2 flex items-center gap-2">
                  <span
                    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium {getStatusBadgeClass(
                      request.status,
                    )}"
                  >
                    {request.status_label}
                  </span>
                  <span class="text-xs text-gray-500 dark:text-gray-400">
                    {new Date(request.submitted_at).toLocaleDateString()}
                  </span>
                </div>

                <a
                  href={request.game_url}
                  target="_blank"
                  rel="noopener"
                  class="block truncate text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                >
                  {request.game_url}
                </a>

                {#if request.admin_notes}
                  <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                    <span class="font-medium">Admin notes:</span>
                    {request.admin_notes}
                  </p>
                {/if}

                {#if request.processed_at}
                  <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                    Processed:
                    {new Date(request.processed_at).toLocaleDateString()}
                  </p>
                {/if}
              </div>

              {#if request.status === 'pending'}
                <Button
                  type="button"
                  variant="link"
                  tone="danger"
                  onclick={() => cancelRequest(request.id)}
                  disabled={isCanceling}
                  loading={isCanceling}
                  class="ml-3 text-sm text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-300 dark:hover:text-red-200"
                >
                  {isCanceling ? 'Canceling...' : 'Cancel'}
                </Button>
              {/if}
            </div>
          </div>
        {/each}
      </div>
    {/if}
  </div>
</Card>
