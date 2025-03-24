<x-layouts.app :metaTags="$metaTags">
    <div class="container mx-auto mt-3">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Public Visual Novel Lists</h1>
        </div>

        @if ($lists->count() > 0)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lists as $list)
                    <x-vn-list-card :list="$list" :isOwner="false" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $lists->links() }}
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No Public Lists Available</h2>
                <p class="text-gray-600 dark:text-gray-400 mb-4">Be the first to share your visual novel list with the community!</p>
                <a href="{{ route('vn-lists.create') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-white hover:bg-blue-700">
                    Create a List
                </a>
            </div>
        @endif
    </div>
</x-layouts.app>
