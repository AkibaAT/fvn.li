<script lang="ts">
    import ArrowRightOnRectangleIcon from '@/components/icons/ArrowRightOnRectangle.svelte';
    import ChevronDownIcon from '@/components/icons/ChevronDown.svelte';
    import { Link, router, usePage } from '@inertiajs/svelte';
    import { Button } from '@/components/ui';
    import { localPushSubscription } from '@/utils/push';

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

    async function handleLogout() {
        closeMenu();
        const subscription = await localPushSubscription().catch(() => null);
        router.post(route('logout'), subscription ? { push_endpoint: subscription.endpoint } : {});
    }
</script>

{#if !user}
    <Link href={route('login')} class="text-sm font-medium text-fg-muted transition-colors hover:text-fg">Log in</Link>
{:else}
    <div class="relative" bind:this={userMenuRef}>
        <button
            type="button"
            onclick={() => (showUserMenu = !showUserMenu)}
            class="inline-flex h-8 items-center gap-2 rounded-md border border-border bg-surface px-1.5 text-fg-muted transition-colors hover:text-fg"
            aria-expanded={showUserMenu}
            aria-haspopup="menu"
            aria-controls="user-menu"
        >
            {#if user.avatar}
                <img src={user.avatar} alt={user.name} class="h-5 w-5 rounded-full" referrerpolicy="no-referrer" />
            {:else}
                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-surface-alt">
                    <span class="text-[10px] font-bold text-fg">
                        {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                    </span>
                </span>
            {/if}
            <span class="hidden max-w-24 truncate text-[13px] font-medium sm:inline">
                {user.name}
            </span>
            <ChevronDownIcon class="h-3.5 w-3.5 transition-transform duration-200 {showUserMenu ? 'rotate-180' : ''}" />
        </button>

        {#if showUserMenu}
            <div id="user-menu" class="absolute right-0 z-50 mt-2 w-64 rounded-md border border-border bg-surface" role="menu" aria-label="User menu">
                <div class="flex items-center gap-3 px-4 py-3">
                    {#if user.avatar}
                        <img src={user.avatar} alt={user.name} class="h-10 w-10 rounded-full" referrerpolicy="no-referrer" />
                    {:else}
                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-surface-alt">
                            <span class="text-base font-bold text-fg">
                                {user.name?.charAt(0)?.toUpperCase() ?? 'U'}
                            </span>
                        </span>
                    {/if}
                    <div class="min-w-0">
                        <div class="truncate text-[13px] font-medium text-fg">
                            {user.name}
                        </div>
                        {#if user.email}
                            <div class="truncate text-[12px] text-fg-faint">
                                {user.email}
                            </div>
                        {/if}
                    </div>
                </div>

                <div class="border-t border-border p-1.5" role="none">
                    <Link
                        href={route('dashboard')}
                        class="flex w-full items-center rounded-md px-3 py-2 text-[13px] font-medium text-fg-muted transition-colors hover:bg-surface-alt hover:text-fg"
                        onclick={closeMenu}
                        role="menuitem"
                    >
                        Dashboard
                    </Link>

                    <Link
                        href={route('lists.index')}
                        class="flex w-full items-center rounded-md px-3 py-2 text-[13px] font-medium text-fg-muted transition-colors hover:bg-surface-alt hover:text-fg"
                        onclick={closeMenu}
                        role="menuitem"
                    >
                        My VN Lists
                    </Link>

                    {#if canManageDiscordServers}
                        <Link
                            href={route('dashboard.discord.index')}
                            class="flex w-full items-center rounded-md px-3 py-2 text-[13px] font-medium text-fg-muted transition-colors hover:bg-surface-alt hover:text-fg"
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
