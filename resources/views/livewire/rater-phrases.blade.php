<div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
    <h2 class="text-xl font-semibold text-gray-900 dark:text-gray-100 mb-4">Common Phrases in Reviews</h2>

    @if (empty($phrases))
        <p class="text-gray-500 dark:text-gray-400">No recurring phrases found in reviews.</p>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            @foreach ($phrases as $phrase => $data)
                @php
                    if ($data['avg_rating'] >= 4) {
                        $colorClasses = 'bg-green-50 dark:bg-green-900 text-green-900 dark:text-green-100';
                    } elseif ($data['avg_rating'] >= 3) {
                        $colorClasses = 'bg-gray-50 dark:bg-gray-700 text-gray-900 dark:text-gray-100';
                    } else {
                        $colorClasses = 'bg-red-50 dark:bg-red-900 text-red-900 dark:text-red-100';
                    }
                @endphp

                <div class="flex items-center justify-between p-2 rounded {{ $colorClasses }}">
                    <span class="flex-grow">{{ $phrase }}</span>
                    <div class="text-sm opacity-75 ml-2 flex items-center gap-2">
                        <span>{{ $data['count'] }}×</span>
                        <span>({{ number_format($data['avg_rating'], 1) }}★)</span>
                        <button
                            onclick="document.getElementById('phrase-context-{{ md5($phrase) }}').showModal()"
                            class="ml-1 text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                            title="Show contexts"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <dialog
                    id="phrase-context-{{ md5($phrase) }}"
                    class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-3xl dark:text-gray-100 backdrop:backdrop-blur-md"
                >
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-lg font-semibold">
                            "{{ $phrase }}"
                            <span class="text-sm font-normal">
                                ({{ $data['count'] }}× / {{ number_format($data['avg_rating'], 1) }}★)
                            </span>
                        </h3>
                        <button
                            onclick="this.closest('dialog').close()"
                            class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200"
                        >
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    <div class="overflow-y-auto space-y-4">
                        @foreach ($data['contexts'] as $gameName => $gameData)
                            <div>
                                <h4 class="font-medium text-gray-900 dark:text-gray-100 mb-2">
                                    {{ $gameName }}
                                    <span class="font-normal text-gray-500 dark:text-gray-400">
                                        ({{ $gameData['rating'] }}★)
                                    </span>
                                </h4>
                                <div class="space-y-2">
                                    @foreach ($gameData['sentences'] as $context)
                                        <div class="text-sm text-gray-600 dark:text-gray-400 p-2 rounded bg-gray-50 dark:bg-gray-700">
                                            {!! preg_replace('/(' . preg_quote($phrase, '/') . ')/i', '<span class="font-medium text-blue-600 dark:text-blue-400">$1</span>', htmlspecialchars($context)) !!}
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                </dialog>
            @endforeach
        </div>

        <div class="mt-4 text-sm text-gray-500 dark:text-gray-400 flex gap-4">
            <div>
                <span class="inline-block w-3 h-3 bg-green-100 dark:bg-green-900 rounded mr-1"></span>
                Positive context (4-5★)
            </div>
            <div>
                <span class="inline-block w-3 h-3 bg-gray-100 dark:bg-gray-700 rounded mr-1"></span>
                Neutral context (3★)
            </div>
            <div>
                <span class="inline-block w-3 h-3 bg-red-100 dark:bg-red-900 rounded mr-1"></span>
                Negative context (1-2★)
            </div>
        </div>
    @endif

    <script>
        document.querySelectorAll('dialog').forEach(dialog => {
            dialog.addEventListener('click', (e) => {
                if (e.target === e.currentTarget) {
                    e.currentTarget.close();
                }
            });
        });
    </script>
</div>
