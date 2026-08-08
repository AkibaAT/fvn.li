<script lang="ts">
    import ArrowRightOnRectangleIcon from '@/components/icons/ArrowRightOnRectangle.svelte';
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
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
    const canManageDiscordServers = $derived(user?.is_admin ?? false);

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
            <ChevronDownIcon class="h-4 w-4 text-gray-500 transition-transform duration-200 {showUserMenu ? 'rotate-180' : ''}" />
        </Button>

        {#if showUserMenu}
            <div
                id="user-menu"
                class="absolute right-0 z-50 mt-2 w-64 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800"
                role="menu"
                aria-label="User menu"
            >
                <div class="flex items-center gap-3 px-4 py-3">
                    {#if user.avatar}
                        <img src={user.avatar} alt={user.name} class="h-10 w-10 rounded-full" referrerpolicy="no-referrer" />
                    {:else}
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-green-500">
                            <span class="text-lg font-bold text-white">
                                {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                            </span>
                        </div>
                    {/if}
                    <div class="min-w-0">
                        <div class="truncate text-sm font-medium text-gray-900 dark:text-gray-100">
                            {user.name}
                        </div>
                        {#if user.email}
                            <div class="truncate text-sm text-gray-500 dark:text-gray-400">
                                {user.email}
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="border-t border-gray-200 p-1.5 dark:border-gray-700" role="none">
                    <Link
                        href={route('dashboard')}
                        class="flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-950 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white"
                        onclick={closeMenu}
                        role="menuitem"
                    >
                        Dashboard
                    </Link>

                    <Link
                        href={route('lists.index')}
                        class="flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-950 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white"
                        onclick={closeMenu}
                        role="menuitem"
                    >
                        My VN Lists
                    </Link>

                    {#if canManageDiscordServers}
                        <Link
                            href={route('dashboard.discord.index')}
                            class="flex w-full items-center rounded-md px-3 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-100 hover:text-gray-950 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:text-gray-200 dark:hover:bg-gray-700 dark:hover:text-white"
                            onclick={closeMenu}
                            role="menuitem"
                        >
                            Discord Bot
                        </Link>
                    {/if}

                    <Button type="button" variant="ghost" tone="danger" class="mt-1 w-full justify-start" onclick={handleLogout} role="menuitem">
                        <ArrowRightOnRectangleIcon class="h-4 w-4" />
                        <span>Sign Out</span>
                    </Button>
                </div>
            </div>
        {/if}
    </div>
{/if}
