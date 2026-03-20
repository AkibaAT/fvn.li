<script lang="ts">
    import { Link, page, router } from '@inertiajs/svelte';

    interface User {
        id: number;
        name: string;
        email: string;
        avatar?: string;
        is_admin?: boolean;
    }

    let showUserMenu = $state(false);
    let userMenuRef: HTMLDivElement | undefined = $state();

    const user = $derived((($page.props as any)?.auth?.user ?? null) as User | null);

    $effect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (showUserMenu && userMenuRef && !userMenuRef.contains(event.target as Node)) {
                showUserMenu = false;
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    });

    function closeMenu() {
        showUserMenu = false;
    }

    function handleLogout() {
        closeMenu();
        router.post(route('logout'));
    }
</script>

{#if !user}
    <div class="flex items-center space-x-2">
        <Link
            href={route('login')}
            class="rounded-lg bg-blue-600 px-4 py-2 font-medium text-white shadow-sm transition-all duration-200 hover:bg-blue-700 hover:shadow-md"
        >
            Login
        </Link>
    </div>
{:else}
    <div class="relative" bind:this={userMenuRef}>
        <button
            onclick={() => showUserMenu = !showUserMenu}
            class="flex items-center space-x-2 rounded-lg bg-gray-100 px-3 py-2 transition-colors duration-200 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
            aria-expanded={showUserMenu}
            aria-haspopup="menu"
            aria-controls="user-menu"
        >
            {#if user.avatar}
                <img
                    src={user.avatar}
                    alt={user.name}
                    class="h-6 w-6 rounded-full"
                    referrerpolicy="no-referrer"
                />
            {:else}
                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-green-500">
                    <span class="text-xs font-bold text-white">
                        {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                    </span>
                </div>
            {/if}
            <span class="hidden text-sm font-medium text-gray-700 sm:inline dark:text-gray-300">
                {user.name}
            </span>
            <svg
                class="h-4 w-4 text-gray-500 transition-transform duration-200 {showUserMenu ? 'rotate-180' : ''}"
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    stroke-width="2"
                    d="M19 9l-7 7-7-7"
                />
            </svg>
        </button>

        {#if showUserMenu}
            <div
                id="user-menu"
                class="absolute right-0 z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-xl dark:border-gray-700 dark:bg-gray-800"
                role="menu"
                aria-label="User menu"
            >
                <div class="p-4">
                    <!-- User Info -->
                    <div class="mb-4 flex items-center space-x-3">
                        {#if user.avatar}
                            <img
                                src={user.avatar}
                                alt={user.name}
                                class="h-10 w-10 rounded-full"
                                referrerpolicy="no-referrer"
                            />
                        {:else}
                            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500">
                                <span class="text-lg font-bold text-white">
                                    {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                                </span>
                            </div>
                        {/if}
                        <div>
                            <div class="font-medium text-gray-900 dark:text-gray-100">
                                {user.name}
                            </div>
                            {#if user.email}
                                <div class="text-sm text-gray-500 dark:text-gray-400">
                                    {user.email}
                                </div>
                            {/if}
                        </div>
                    </div>

                    <hr class="mb-4 border-gray-200 dark:border-gray-700" />

                    <!-- Menu Items -->
                    <div class="space-y-2" role="none">
                        <Link
                            href={route('dashboard')}
                            class="flex w-full items-center space-x-2 rounded-lg bg-indigo-600 px-3 py-2 text-white transition-all duration-200 hover:bg-indigo-700 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2"
                            onclick={closeMenu}
                            role="menuitem"
                        >
                            <svg
                                class="h-5 w-5 drop-shadow-sm"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                            <span class="font-medium">
                                Dashboard
                            </span>
                        </Link>

                        <Link
                            href={route('lists.index')}
                            class="flex w-full items-center space-x-2 rounded-lg bg-blue-600 px-3 py-2 text-white transition-colors hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                            onclick={closeMenu}
                            role="menuitem"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                            <span>
                                My VN Lists
                            </span>
                        </Link>

                        <button
                            class="flex w-full items-center space-x-2 rounded-lg bg-red-600 px-3 py-2 text-white transition-colors hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2"
                            onclick={handleLogout}
                            role="menuitem"
                        >
                            <svg
                                class="h-5 w-5"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                                aria-hidden="true"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                            <span>
                                Sign Out
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
{/if}
