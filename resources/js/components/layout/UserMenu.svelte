<script lang="ts">
    import { Link, router, usePage } from '@inertiajs/svelte';
    import { Button } from '@/components/ui';

    interface User {
        id: number;
        name: string;
        email: string;
        avatar?: string;
        is_admin?: boolean;
    }

    let showUserMenu = $state(false);
    let userMenuRef: HTMLDivElement | undefined = $state();

    const inertiaPage = usePage();
    const user = $derived((inertiaPage.props?.auth?.user ?? null) as User | null);

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
        <Button
            type="button"
            variant="soft"
            tone="neutral"
            onclick={() => (showUserMenu = !showUserMenu)}
            class="flex items-center space-x-2 rounded-lg bg-gray-100 px-3 py-2 transition-colors duration-200 hover:bg-gray-200 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none dark:bg-gray-800 dark:hover:bg-gray-700"
            aria-expanded={showUserMenu}
            aria-haspopup="menu"
            aria-controls="user-menu"
        >
            {#if user.avatar}
                <img src={user.avatar} alt={user.name} class="h-6 w-6 rounded-full" referrerpolicy="no-referrer" />
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
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </Button>

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
                            <img src={user.avatar} alt={user.name} class="h-10 w-10 rounded-full" referrerpolicy="no-referrer" />
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
                            class="flex w-full items-center space-x-2 rounded-lg bg-indigo-600 px-3 py-2 text-white transition-all duration-200 hover:bg-indigo-700 hover:shadow-md focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 focus:outline-none"
                            onclick={closeMenu}
                            role="menuitem"
                        >
                            <svg class="h-5 w-5 drop-shadow-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
                                />
                            </svg>
                            <span class="font-medium"> Dashboard </span>
                        </Link>

                        <Link
                            href={route('lists.index')}
                            class="flex w-full items-center space-x-2 rounded-lg bg-blue-600 px-3 py-2 text-white transition-colors hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
                            onclick={closeMenu}
                            role="menuitem"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                                />
                            </svg>
                            <span> My VN Lists </span>
                        </Link>

                        <Link
                            href={route('dashboard.discord.index')}
                            class="flex w-full items-center space-x-2 rounded-lg bg-[#5865F2] px-3 py-2 text-white transition-colors hover:bg-[#4752C4] focus:ring-2 focus:ring-[#5865F2] focus:ring-offset-2 focus:outline-none"
                            onclick={closeMenu}
                            role="menuitem"
                        >
                            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                                <path
                                    d="M20.317 4.37a19.791 19.791 0 00-4.885-1.515.074.074 0 00-.079.037c-.21.375-.444.864-.608 1.25a18.27 18.27 0 00-5.487 0 12.64 12.64 0 00-.617-1.25.077.077 0 00-.079-.037A19.736 19.736 0 003.677 4.37a.07.07 0 00-.032.027C.533 9.046-.32 13.58.099 18.057a.082.082 0 00.031.057 19.9 19.9 0 005.993 3.03.078.078 0 00.084-.028c.462-.63.874-1.295 1.226-1.994a.076.076 0 00-.041-.106 13.107 13.107 0 01-1.872-.892.077.077 0 01-.008-.128 10.2 10.2 0 00.372-.292.074.074 0 01.077-.01c3.928 1.793 8.18 1.793 12.062 0a.074.074 0 01.078.01c.12.098.246.198.373.292a.077.077 0 01-.006.127 12.299 12.299 0 01-1.873.892.077.077 0 00-.041.107c.36.698.772 1.362 1.225 1.993a.076.076 0 00.084.028 19.839 19.839 0 006.002-3.03.077.077 0 00.032-.054c.5-5.177-.838-9.674-3.549-13.66a.061.061 0 00-.031-.03zM8.02 15.33c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.956-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.956 2.418-2.157 2.418zm7.975 0c-1.183 0-2.157-1.085-2.157-2.419 0-1.333.955-2.419 2.157-2.419 1.21 0 2.176 1.096 2.157 2.42 0 1.333-.946 2.418-2.157 2.418z"
                                />
                            </svg>
                            <span> Discord Bot </span>
                        </Link>

                        <Button
                            type="button"
                            variant="solid"
                            tone="danger"
                            class="flex w-full items-center space-x-2 rounded-lg bg-red-600 px-3 py-2 text-white transition-colors hover:bg-red-700 focus:ring-2 focus:ring-red-500 focus:ring-offset-2 focus:outline-none"
                            onclick={handleLogout}
                            role="menuitem"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                                />
                            </svg>
                            <span> Sign Out </span>
                        </Button>
                    </div>
                </div>
            </div>
        {/if}
    </div>
{/if}
