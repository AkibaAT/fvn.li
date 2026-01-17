import { useState } from 'react';
import { useAdditionRequests, useCancelAdditionRequest } from '@/hooks/api';
import { toast } from '@/utils/toast';

const STATUS_OPTIONS = {
  all: 'All Requests',
  pending: 'Pending',
  approved: 'Approved',
  rejected: 'Rejected',
};

export function UserAdditionRequests() {
  const [statusFilter, setStatusFilter] = useState('all');
  const [search, setSearch] = useState('');

  const { data: requests = [], isLoading: loading } = useAdditionRequests({
    status: statusFilter,
    search,
  });

  const cancelMutation = useCancelAdditionRequest();

  const cancelRequest = async (requestId: number) => {
    if (!confirm('Are you sure you want to cancel this request?')) {
      return;
    }

    cancelMutation.mutate(requestId, {
      onSuccess: (data) => {
        if (data.success) {
          toast.success(data.message);
        } else {
          toast.error(data.message || 'Failed to cancel request.');
        }
      },
      onError: (error) => {
        console.error('Error canceling request:', error);
        toast.error('An error occurred while canceling the request.');
      },
    });
  };

  const getStatusBadgeClass = (status: string) => {
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
  };

  const filteredRequests = requests.filter((request) => {
    const matchesSearch =
      search === '' ||
      request.game_url.toLowerCase().includes(search.toLowerCase()) ||
      request.status_label.toLowerCase().includes(search.toLowerCase());
    return matchesSearch;
  });

  return (
    <div className="rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
      <div className="p-6">
        <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
          My Addition Requests
        </h2>

        {/* Filters */}
        <div className="mb-4 space-y-3 sm:flex sm:gap-3 sm:space-y-0">
          <div className="flex-1">
            <input
              type="text"
              placeholder="Search requests..."
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            />
          </div>
          <div>
            <select
              value={statusFilter}
              onChange={(e) => setStatusFilter(e.target.value)}
              className="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 shadow-sm focus:border-blue-500 focus:ring-blue-500 sm:w-auto dark:border-gray-600 dark:bg-gray-700 dark:text-white"
            >
              {Object.entries(STATUS_OPTIONS).map(([value, label]) => (
                <option key={value} value={value}>
                  {label}
                </option>
              ))}
            </select>
          </div>
        </div>

        {/* Requests List */}
        {loading ? (
          <div className="flex items-center justify-center py-8">
            <div className="h-8 w-8 animate-spin rounded-full border-b-2 border-blue-600"></div>
          </div>
        ) : filteredRequests.length === 0 ? (
          <div className="py-8 text-center text-gray-500 dark:text-gray-400">
            {search || statusFilter !== 'all'
              ? 'No requests match your filters.'
              : 'No requests found.'}
          </div>
        ) : (
          <div className="space-y-3">
            {filteredRequests.map((request) => (
              <div
                key={request.id}
                className="rounded-lg border border-gray-200 p-4 dark:border-gray-700"
              >
                <div className="flex items-start justify-between">
                  <div className="min-w-0 flex-1">
                    <div className="mb-2 flex items-center gap-2">
                      <span
                        className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${getStatusBadgeClass(request.status)}`}
                      >
                        {request.status_label}
                      </span>
                      <span className="text-xs text-gray-500 dark:text-gray-400">
                        {new Date(request.submitted_at).toLocaleDateString()}
                      </span>
                    </div>

                    <a
                      href={request.game_url}
                      target="_blank"
                      rel="noopener noreferrer"
                      className="block truncate text-sm font-medium text-blue-600 hover:underline dark:text-blue-400"
                    >
                      {request.game_url}
                    </a>

                    {request.admin_notes && (
                      <p className="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        <span className="font-medium">Admin notes:</span>{' '}
                        {request.admin_notes}
                      </p>
                    )}

                    {request.processed_at && (
                      <p className="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Processed:{' '}
                        {new Date(request.processed_at).toLocaleDateString()}
                      </p>
                    )}
                  </div>

                  {request.status === 'pending' && (
                    <button
                      onClick={() => cancelRequest(request.id)}
                      disabled={cancelMutation.isPending}
                      className="ml-3 text-sm text-red-600 hover:text-red-800 disabled:opacity-50 dark:text-red-400 dark:hover:text-red-300"
                    >
                      {cancelMutation.isPending ? 'Canceling...' : 'Cancel'}
                    </button>
                  )}
                </div>
              </div>
            ))}
          </div>
        )}
      </div>
    </div>
  );
}
