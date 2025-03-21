<header class="bg-white dark:bg-gray-800 shadow">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="flex-shrink-0 flex items-center">
                    <a href="{{ route('games.index') }}" class="text-xl font-bold text-gray-900 dark:text-gray-100">
                        {{ config('app.name') }}
                    </a>
                </div>
            </div>
            <div class="flex items-center">
                <livewire:social-auth />
            </div>
        </div>
    </div>
</header> 