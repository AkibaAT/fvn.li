<x-layouts.app>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Log in') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-md mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="mb-6 text-center">
                        <h1 class="text-2xl font-bold mb-2">Welcome to fvn.li</h1>
                        <p class="text-gray-600 dark:text-gray-400">Log in to manage your visual novel collections</p>
                    </div>

                    <div class="mt-8">
                        <livewire:auth.social-login-buttons />
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-layouts.app> 