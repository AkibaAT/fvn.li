<div>
    <!-- Login/User Profile Button -->
    <button
        @click="document.getElementById('login-dialog').showModal()"
        class="flex items-center gap-2 px-4 py-2 rounded-lg bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-200 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors"
    >
        @auth
            <div class="flex items-center gap-2">
                @if(auth()->user()->avatar)
                    <img src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->name }}" class="w-6 h-6 rounded-full">
                @else
                    <div class="w-6 h-6 rounded-full bg-blue-500 text-white flex items-center justify-center text-xs font-bold">
                        {{ substr(auth()->user()->name, 0, 1) }}
                    </div>
                @endif
                <span class="hidden sm:inline">{{ auth()->user()->name }}</span>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                </svg>
            </div>
        @else
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
            <span>Login</span>
        @endauth
    </button>

    <!-- Login Dialog - Using native HTML dialog element -->
    <dialog
        id="login-dialog"
        class="m-auto rounded-lg bg-white dark:bg-gray-800 p-6 shadow-xl w-full max-w-sm dark:text-gray-100 backdrop:backdrop-blur-md"
        wire:ignore
    >
        <div class="flex justify-between items-baseline mb-4">
            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-100">
                @auth
                    Account
                @else
                    Sign in with
                @endauth
            </h2>
            <button
                @click="$el.closest('dialog').close()"
                class="text-gray-400 hover:text-gray-500 dark:hover:text-gray-300"
            >
                <span class="sr-only">Close</span>
                <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        @auth
            <div class="py-4 space-y-4">
                <div class="flex items-center gap-3">
                    @if(auth()->user()->avatar)
                        <img src="{{ auth()->user()->avatar }}" alt="{{ auth()->user()->name }}" class="w-10 h-10 rounded-full">
                    @else
                        <div class="w-10 h-10 rounded-full bg-blue-500 text-white flex items-center justify-center text-lg font-bold">
                            {{ substr(auth()->user()->name, 0, 1) }}
                        </div>
                    @endif
                    <div>
                        <div class="font-medium text-gray-900 dark:text-gray-100">{{ auth()->user()->name }}</div>
                        @if(auth()->user()->email)
                            <div class="text-sm text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</div>
                        @endif
                    </div>
                </div>

                <hr class="border-gray-200 dark:border-gray-700">

                <button
                    wire:click="logout"
                    class="w-full flex items-center justify-center gap-2 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition-colors"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <span>Sign Out</span>
                </button>
            </div>
        @else
            <div class="py-4 space-y-3">
                <a
                    href="{{ route('auth.redirect', ['provider' => 'discord']) }}"
                    class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    <i class="icon-discord h-5 w-5 mr-3 text-indigo-500"></i>
                    <span>Discord</span>
                </a>

                <a
                    href="{{ route('auth.redirect', ['provider' => 'google']) }}"
                    class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg class="h-5 w-5 mr-3" viewBox="0 0 24 24">
                        <path fill="currentColor" d="M21.35 11.1h-9.17v2.73h6.5c-.33 3.8-3.5 5.44-6.5 5.44C8.36 19.27 5 16.25 5 12c0-4.1 3.2-7.27 7.2-7.27c3.09 0 4.9 1.97 4.9 1.97L19 4.72S16.56 2 12.1 2C6.42 2 2.03 6.8 2.03 12c0 5.05 4.13 10 10.22 10c5.35 0 9.25-3.67 9.25-9.09c0-1.15-.15-1.81-.15-1.81Z" />
                    </svg>
                    <span>Google</span>
                </a>

                <a
                    href="{{ route('auth.redirect', ['provider' => 'steam']) }}"
                    class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    <svg class="h-5 w-5 mr-3" viewBox="0 0 65 65">
                        <path d="M31.959 64c17.673 0 32-14.327 32-32s-14.327-32-32-32C15.001 0 1.124 13.193.028 29.874c2.074 3.477 2.879 5.628 1.275 11.328C5.259 54.386 17.488 64 31.959 64z" fill="#144b7e"/>
                        <path d="M30.31 23.985l.003.158-7.83 11.375c-1.268-.058-2.54.165-3.748.662a8.14 8.14 0 0 0-1.498.8L.042 29.893s-.398 6.546 1.26 11.424l12.156 5.016c.6 2.728 2.48 5.12 5.242 6.27a8.88 8.88 0 0 0 11.603-4.782 8.89 8.89 0 0 0 .684-3.656L42.18 36.16l.275.005c6.705 0 12.155-5.466 12.155-12.18s-5.44-12.16-12.155-12.174c-6.702 0-12.155 5.46-12.155 12.174zm-1.88 23.05c-1.454 3.5-5.466 5.147-8.953 3.694a6.84 6.84 0 0 1-3.524-3.362l3.957 1.64a5.04 5.04 0 0 0 6.591-2.719 5.05 5.05 0 0 0-2.715-6.601l-4.1-1.695c1.578-.6 3.372-.62 5.05.077 1.7.703 3 2.027 3.696 3.72s.692 3.56-.01 5.246M42.466 32.1a8.12 8.12 0 0 1-8.098-8.113a8.12 8.12 0 0 1 8.098-8.111a8.12 8.12 0 0 1 8.1 8.111a8.12 8.12 0 0 1-8.1 8.113m-6.068-8.126a6.09 6.09 0 0 1 6.08-6.095c3.355 0 6.084 2.73 6.084 6.095a6.09 6.09 0 0 1-6.084 6.093a6.09 6.09 0 0 1-6.081-6.093z" fill="#fff"/>
                    </svg>
                    <span>Steam</span>
                </a>

                <a
                    href="{{ route('auth.telegram') }}"
                    class="w-full flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg text-gray-700 dark:text-gray-200 hover:bg-gray-50 dark:hover:bg-gray-700 transition-colors"
                >
                    <i class="icon-telegram h-5 w-5 mr-3 text-blue-500"></i>
                    <span>Telegram</span>
                </a>
            </div>
        @endauth

        <div class="mt-6 flex justify-end">
            <button
                @click="$el.closest('dialog').close()"
                type="button"
                class="rounded-md bg-white dark:bg-gray-800 px-3 py-2 text-sm font-semibold text-gray-900 dark:text-gray-100 shadow-xs ring-1 ring-inset ring-gray-300 dark:ring-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700"
            >
                Close
            </button>
        </div>
    </dialog>

    <script>
        document.addEventListener('livewire:initialized', () => {
            Livewire.on('closeLoginDialog', () => {
                document.getElementById('login-dialog').close();
            });
        });

        // Close dialog when clicking outside
        document.getElementById('login-dialog').addEventListener('click', (e) => {
            if (e.target === e.currentTarget) {
                e.currentTarget.close();
            }
        });
    </script>
</div>
