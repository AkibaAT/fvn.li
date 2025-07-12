<div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg">
    <div class="p-6">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">My Addition Requests</h2>
            <span class="text-sm text-gray-500 dark:text-gray-400">
                {{ $requests->count() }} request(s)
            </span>
        </div>

        <!-- Filters -->
        <div class="mb-6 flex flex-col sm:flex-row gap-4">
            <div class="flex-1">
                <input
                    wire:model.live.debounce.300ms="search"
                    type="text"
                    placeholder="Search by URL or status..."
                    class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                >
            </div>
            <div>
                <select
                    wire:model.live="statusFilter"
                    class="px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 dark:bg-gray-700 dark:text-white"
                >
                    @foreach ($statusOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <!-- Success/Error Messages -->
        @if (session('success'))
            <div class="mb-4 p-4 bg-green-100 dark:bg-green-900/20 border border-green-200 dark:border-green-800 rounded-lg">
                <p class="text-green-800 dark:text-green-400">{{ session('success') }}</p>
            </div>
        @endif

        @error('auth')
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-red-800 dark:text-red-400">{{ $message }}</p>
            </div>
        @enderror

        @error('request')
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-red-800 dark:text-red-400">{{ $message }}</p>
            </div>
        @enderror

        @error('cancel')
            <div class="mb-4 p-4 bg-red-100 dark:bg-red-900/20 border border-red-200 dark:border-red-800 rounded-lg">
                <p class="text-red-800 dark:text-red-400">{{ $message }}</p>
            </div>
        @enderror

        @if ($requests->isEmpty())
            <div class="text-center py-8">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">No requests found</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    @if ($statusFilter === 'all' && empty($search))
                        You haven't submitted any addition requests yet.
                    @else
                        No requests match your current filters.
                    @endif
                </p>
            </div>
        @else
            <!-- Requests List -->
            <div class="space-y-4">
                @foreach ($requests as $request)
                    <div class="border border-gray-200 dark:border-gray-700 rounded-lg p-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center gap-2 mb-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                                        @if ($request->status_color === 'yellow') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                        @elseif ($request->status_color === 'green') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                        @elseif ($request->status_color === 'red') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                        @endif">
                                        {{ $request->status_label }}
                                    </span>
                                    <span class="text-xs text-gray-500 dark:text-gray-400">
                                        Requested {{ $request->pivot->created_at->diffForHumans() }}
                                    </span>
                                </div>

                                <div class="mb-2">
                                    <a href="{{ $request->itch_url }}" target="_blank" rel="noopener noreferrer"
                                       class="text-blue-600 dark:text-blue-400 hover:underline break-all">
                                        {{ $request->itch_url }}
                                        <svg class="inline w-3 h-3 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                </div>

                                @if ($request->isApproved() && $request->game)
                                    <div class="mb-2">
                                        <span class="text-sm text-green-600 dark:text-green-400">
                                            ✓ Added to site:
                                            <a href="{{ route('games.show', $request->game) }}" class="hover:underline">
                                                {{ $request->game->name }}
                                            </a>
                                        </span>
                                    </div>
                                @endif

                                @if ($request->isRejected() && $request->rejection_reason)
                                    <div class="mb-2">
                                        <div class="text-sm text-red-600 dark:text-red-400">
                                            <strong>Rejection reason:</strong> {{ $request->rejection_reason }}
                                        </div>
                                    </div>
                                @endif

                                @if ($request->reviewed_at)
                                    <div class="text-xs text-gray-500 dark:text-gray-400">
                                        Reviewed {{ $request->reviewed_at->diffForHumans() }}
                                        @if ($request->reviewer)
                                            by {{ $request->reviewer->name }}
                                        @endif
                                    </div>
                                @endif
                            </div>

                            <div class="ml-4 flex-shrink-0 flex flex-col items-end gap-2">
                                @if ($request->users->count() > 1)
                                    <div class="text-xs text-gray-500 dark:text-gray-400 text-right">
                                        {{ $request->users->count() }} user(s) requested
                                    </div>
                                @endif

                                @if ($request->isPending())
                                    <button
                                        wire:click="cancelRequest({{ $request->id }})"
                                        wire:confirm="Are you sure you want to cancel this request? This action cannot be undone."
                                        wire:loading.attr="disabled"
                                        class="inline-flex items-center px-2.5 py-1.5 border border-red-300 dark:border-red-600 text-xs font-medium rounded text-red-700 dark:text-red-400 bg-white dark:bg-gray-800 hover:bg-red-50 dark:hover:bg-red-900/20 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-50 disabled:cursor-not-allowed transition-colors"
                                    >
                                        <svg class="w-3 h-3 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                        <span wire:loading.remove wire:target="cancelRequest({{ $request->id }})">Cancel</span>
                                        <span wire:loading wire:target="cancelRequest({{ $request->id }})">Cancelling...</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</div>
