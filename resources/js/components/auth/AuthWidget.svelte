<script lang="ts">
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { router } from '@inertiajs/svelte';
    import type { User } from '@/types';
    import { Button, Checkbox, Dialog } from '@/components/ui';

    interface Props {
        user?: User;
    }

    let { user }: Props = $props();

    let isOpen = $state(false);
    let rememberLogin = $state(true);

    function socialLoginHref(provider: string): string {
        return route('auth.redirect', {
            provider,
            remember: rememberLogin ? 1 : 0,
        });
    }

    function openDialog() {
        isOpen = true;
    }

    function closeDialog() {
        isOpen = false;
    }

    function handleLogout() {
        router.post(
            route('logout'),
            {},
            {
                onSuccess: () => {
                    closeDialog();
                    window.location.reload();
                },
            },
        );
    }
</script>

<Button onclick={openDialog} variant="soft" tone="neutral">
    {#if user}
        <div class="flex items-center gap-2">
            {#if user.avatar}
                <img src={user.avatar} alt={user.name} class="h-6 w-6 rounded-full" />
            {:else}
                <div class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500 text-xs font-bold text-white">
                    {user.name.charAt(0)}
                </div>
            {/if}
            <span class="hidden sm:inline">{user.name}</span>
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
            </svg>
        </div>
    {:else}
        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path
                stroke-linecap="round"
                stroke-linejoin="round"
                stroke-width="2"
                d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"
            />
        </svg>
        <span>Login</span>
    {/if}
</Button>

<Dialog open={isOpen} onClose={closeDialog} title={user ? 'Account' : 'Sign in with'} size="sm">
    {#if user}
        <div class="space-y-4 py-4">
            <div class="flex items-center gap-3">
                {#if user.avatar}
                    <img src={user.avatar} alt={user.name} class="h-10 w-10 rounded-full" />
                {:else}
                    <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-500 text-lg font-bold text-white">
                        {user.name.charAt(0)}
                    </div>
                {/if}
                <div>
                    <div class="font-medium text-gray-900 dark:text-gray-100">{user.name}</div>
                    {#if user.email}
                        <div class="text-sm text-gray-500 dark:text-gray-400">{user.email}</div>
                    {/if}
                </div>
            </div>

            <hr class="border-gray-200 dark:border-gray-700" />

            <Button href={route('dashboard')} variant="soft" tone="neutral" class="mb-3 w-full">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"
                    />
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                </svg>
                <span>User Dashboard</span>
            </Button>

            <Button href={route('lists.index')} class="mb-3 w-full">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"
                    />
                </svg>
                <span>My VN Lists</span>
            </Button>

            <Button onclick={handleLogout} tone="danger" class="w-full">
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        stroke-width="2"
                        d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"
                    />
                </svg>
                <span>Sign Out</span>
            </Button>
        </div>
    {:else}
        <div class="space-y-3 py-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 dark:border-gray-700 dark:bg-gray-900/40">
                <Checkbox bind:checked={rememberLogin} label="Keep me signed in on this device" class="dark:bg-gray-800" />
            </div>

            <Button href={socialLoginHref('discord')} inertia={false} variant="outline" tone="neutral" class="w-full justify-start">
                <i class="icon-discord mr-3 h-5 w-5 text-indigo-500"></i>
                <span>Discord</span>
            </Button>
            <Button href={socialLoginHref('google')} inertia={false} variant="outline" tone="neutral" class="w-full justify-start">
                <svg class="mr-3 h-5 w-5" viewBox="0 0 24 24">
                    <path
                        fill="#4285F4"
                        d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"
                    />
                    <path
                        fill="#34A853"
                        d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"
                    />
                    <path
                        fill="#FBBC05"
                        d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z"
                    />
                    <path
                        fill="#EA4335"
                        d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z"
                    />
                </svg>
                <span>Google</span>
            </Button>
            <Button href={socialLoginHref('itchio')} inertia={false} variant="outline" tone="neutral" class="w-full justify-start">
                <Itchio class="text-itchio mr-3 h-5 w-5" />
                <span>itch.io</span>
            </Button>
            <Button href={socialLoginHref('steam')} inertia={false} variant="outline" tone="neutral" class="w-full justify-start">
                <Steam class="mr-3 h-5 w-5" />
                <span>Steam</span>
            </Button>
            <Button href={socialLoginHref('telegram')} inertia={false} variant="outline" tone="neutral" class="w-full justify-start">
                <i class="icon-telegram mr-3 h-5 w-5 text-blue-500"></i>
                <span>Telegram</span>
            </Button>
        </div>
    {/if}

    <div class="mt-6 flex justify-end">
        <Button onclick={closeDialog} type="button" variant="outline" tone="neutral">Close</Button>
    </div>
</Dialog>
