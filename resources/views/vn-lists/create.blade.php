<x-layouts.app :metaTags="$metaTags">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Create New Custom List</h1>
            <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">Create a custom list to organize your visual novels. For tracking reading status, use the default lists (Reading, Completed, etc.) instead.</p>
        </div>

        <form action="{{ route('vn-lists.store') }}" method="POST">
            @csrf

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">List Name</label>
                <input type="text" name="name" id="name" value="{{ old('name') }}" required class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (Optional)</label>
                <textarea name="description" id="description" rows="3" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">{{ old('description') }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <div class="flex items-center">
                    <input type="checkbox" name="is_public" id="is_public" {{ old('is_public') ? 'checked' : '' }} class="rounded border-gray-300 dark:border-gray-600 text-blue-600 shadow-sm focus:border-blue-300 focus:ring focus:ring-blue-200 focus:ring-opacity-50 dark:bg-gray-800 dark:focus:ring-blue-600">
                    <label for="is_public" class="ml-2 block text-sm text-gray-700 dark:text-gray-300">Make this list public</label>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Public lists are visible to all users</p>
            </div>

            <div class="flex items-center justify-end">
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Create List
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
