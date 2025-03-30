<x-layouts.app :metaTags="$metaTags">
    <div class="container mx-auto mt-3">
        <div class="flex justify-between items-center mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900 dark:text-white">{{ $user->name }}'s Lists</h1>
                <p class="text-gray-600 dark:text-gray-400 mt-1">Public visual novel lists shared by this user</p>
            </div>
            <a href="{{ route('vn-lists.public') }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                Back to all public lists
            </a>
        </div>

        @if ($lists->count() > 0)
            <div class="grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
                @foreach ($lists as $list)
                    <x-lists::list-card :list="$list" :isOwner="false" />
                @endforeach
            </div>

            <div class="mt-6">
                {{ $lists->links() }}
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-8 text-center">
                <h2 class="text-xl font-semibold text-gray-900 dark:text-white mb-2">No Public Lists Available</h2>
                <p class="text-gray-600 dark:text-gray-400">This user hasn't shared any public lists yet.</p>
            </div>
        @endif
    </div>
</x-layouts.app>
