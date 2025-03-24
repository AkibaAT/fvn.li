<x-layouts.app :metaTags="$metaTags">
    <div class="bg-white dark:bg-gray-800 shadow-sm rounded-lg p-6">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900 dark:text-white">Edit List: {{ $vnList->name }}</h1>
        </div>

        <form action="{{ route('vn-lists.update', $vnList) }}" method="POST">
            @csrf
            @method('PUT')

            <div class="mb-4">
                <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">List Name</label>
                <input type="text" name="name" id="name" value="{{ old('name', $vnList->name) }}" required
                    class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">
                @error('name')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <div class="mb-4">
                <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Description (Optional)</label>
                <textarea name="description" id="description" rows="3" class="mt-1 focus:ring-blue-500 focus:border-blue-500 block w-full shadow-sm sm:text-sm border border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white rounded-md py-2 px-3">{{ old('description', $vnList->description) }}</textarea>
                @error('description')
                    <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror
            </div>

            <input type="hidden" name="type" value="{{ $vnList->type }}">

            <div class="mb-6">
                <div class="flex items-center">
                    <input type="checkbox" id="is_public" name="is_public" value="1" {{ old('is_public', $vnList->is_public) ? 'checked' : '' }} class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                    <label for="is_public" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                        Make this list public
                    </label>
                </div>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Public lists can be viewed by anyone, even users who are not logged in.</p>
            </div>

            <div class="flex justify-end space-x-3">
                <a href="{{ route('vn-lists.show', $vnList) }}" class="inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-800 dark:text-white uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 active:bg-gray-400 dark:active:bg-gray-500 focus:outline-none focus:border-gray-400 dark:focus:border-gray-500 focus:ring focus:ring-gray-200 dark:focus:ring-gray-700 transition">
                    Cancel
                </a>

                <button type="submit" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:border-blue-700 focus:ring focus:ring-blue-200 transition">
                    Update List
                </button>
            </div>
        </form>
    </div>
</x-layouts.app>
