<dialog
    wire:ignore.self
    id="file-stats"
    class="rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-2xl dark:text-gray-100 backdrop:backdrop-blur-md"
>
    <x-dialog-header title="File Statistics"/>

    @if($selectedVersion && $selectedVersion->fileCategories->isNotEmpty())
        <div class="space-y-6">
            {{-- Summary --}}
            <div>
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-4">
                    Version {{ $selectedVersion->version }}
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    @foreach ($selectedVersion->fileCategories as $category)
                        <div class="bg-gray-50 dark:bg-gray-700/50 p-4 rounded-lg">
                            <div class="text-sm font-medium text-gray-500 dark:text-gray-400">
                                {{ Str::title($category->category) }}
                            </div>
                            <div class="mt-1 text-xl font-semibold text-gray-900 dark:text-gray-100">
                                {{ number_format($category->total_count) }}
                            </div>
                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                {{ \App\Services\HelperService::formatBytes($category->total_size) }}
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Detailed Breakdown --}}
            <div class="space-y-6">
                @foreach ($selectedVersion->fileCategories as $category)
                    @if ($category->total_count > 0)
                        <div>
                            <h4 class="text-base font-medium text-gray-900 dark:text-gray-100 mb-2">
                                {{ Str::title($category->category) }} Files
                            </h4>
                            <div class="bg-gray-50 dark:bg-gray-700/50 rounded-lg overflow-hidden">
                                <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-600">
                                    <thead>
                                    <tr>
                                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Type
                                        </th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Count
                                        </th>
                                        <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                            Size
                                        </th>
                                    </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200 dark:divide-gray-600">
                                    @foreach ($category->fileTypes as $type)
                                        <tr>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                {{ $type->extension }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">
                                                {{ number_format($type->count) }}
                                            </td>
                                            <td class="px-4 py-2 text-sm text-gray-900 dark:text-gray-100 text-right">
                                                {{ \App\Services\HelperService::formatBytes($type->size) }}
                                            </td>
                                        </tr>
                                    @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endif
                @endforeach
            </div>
        </div>
    @else
        <div class="text-center py-6 text-gray-500 dark:text-gray-400">
            No file statistics available for this version.
        </div>
    @endif

    <x-dialog-footer/>
</dialog>
