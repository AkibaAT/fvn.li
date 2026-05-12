<script lang="ts">
    import { untrack } from 'svelte';
    import Itchio from '@/components/icons/Itchio.svelte';
    import Steam from '@/components/icons/Steam.svelte';
    import { Button } from '@/components/ui';
    import type { SocialAccount, User } from '@/types';
    import { toast } from '@/utils/toast';

    interface Props {
        user: User;
        connectedProviders: string[];
        socialAccounts: Record<string, SocialAccount>;
    }

    let { connectedProviders, socialAccounts }: Props = $props();

    let providers = $state<string[]>(untrack(() => connectedProviders || []));
    let accounts = $state<Record<string, SocialAccount>>(untrack(() => socialAccounts || {}));

    const PROVIDERS: Record<string, { name: string; iconType: string }> = {
        discord: { name: 'Discord', iconType: 'discord' },
        google: { name: 'Google', iconType: 'google' },
        itchio: { name: 'itch.io', iconType: 'itchio' },
        steam: { name: 'Steam', iconType: 'steam' },
        telegram: { name: 'Telegram', iconType: 'telegram' },
    };

    async function handleDisconnect(provider: string) {
        if (!confirm(`Are you sure you want to disconnect your ${PROVIDERS[provider]?.name} account?`)) {
            return;
        }

        try {
            const response = await fetch(route('user.disconnect', { provider }), {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                credentials: 'same-origin',
            });

            if (response.ok) {
                const data = await response.json().catch(() => ({}));
                toast.success(data?.message || `${PROVIDERS[provider]?.name} account disconnected successfully.`);
                providers = providers.filter((p) => p !== provider);
                const next = { ...accounts };
                delete next[provider];
                accounts = next;
            } else {
                toast.error('Failed to disconnect account.');
            }
        } catch (error) {
            console.error('Error disconnecting account:', error);
            toast.error('An error occurred while disconnecting the account.');
        }
    }

    function handleConnect(provider: string) {
        if (
            !confirm(
                `If an account already exists with this ${PROVIDERS[provider]?.name} login, it will be merged into your current account. This action cannot be undone. Continue?`,
            )
        ) {
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = route('user.merge', { provider });

        const csrfToken = document.createElement('input');
        csrfToken.type = 'hidden';
        csrfToken.name = '_token';
        csrfToken.value = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        form.appendChild(csrfToken);

        document.body.appendChild(form);
        form.submit();
    }
</script>

<div>
    <div class="grid grid-cols-1 gap-4">
        {#each Object.entries(PROVIDERS) as [provider, config] (provider)}
            {@const isConnected = providers.includes(provider)}
            {@const accountData = accounts[provider]}
            <div class="rounded-lg border p-4 transition-colors hover:bg-gray-50/50 dark:border-gray-700 dark:hover:bg-gray-700/30">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        {#if config.iconType === 'discord'}
                            <i class="icon-discord h-6 w-6 text-xl text-indigo-500"></i>
                        {:else if config.iconType === 'google'}
                            <svg class="h-6 w-6" viewBox="0 0 24 24">
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
                        {:else if config.iconType === 'itchio'}
                            <Itchio class="text-itchio h-6 w-6" />
                        {:else if config.iconType === 'steam'}
                            <Steam class="h-6 w-6" />
                        {:else if config.iconType === 'telegram'}
                            <i class="icon-telegram h-6 w-6 text-xl text-blue-500"></i>
                        {/if}
                        <span class="font-medium text-gray-900 dark:text-white">
                            {config.name}
                        </span>
                    </div>

                    {#if isConnected}
                        <div class="flex items-center gap-2">
                            {#if accountData}
                                <div class="flex items-center gap-2">
                                    {#if accountData.avatar}
                                        <img src={accountData.avatar} alt="{config.name} avatar" class="h-6 w-6 rounded-full" />
                                    {/if}
                                    {#if accountData.display_name}
                                        <span class="text-sm text-gray-600 dark:text-gray-400">
                                            {accountData.display_name}
                                        </span>
                                    {/if}
                                </div>
                            {/if}
                            <span class="text-sm font-medium text-green-500"> Connected </span>
                            <Button
                                type="button"
                                variant="ghost"
                                tone="danger"
                                size="icon-sm"
                                onclick={() => handleDisconnect(provider)}
                                class="ml-2 text-red-500 transition-colors hover:text-red-600"
                                title="Unlink {config.name} account"
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                        stroke-width="2"
                                        d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"
                                    />
                                </svg>
                            </Button>
                        </div>
                    {:else}
                        <Button
                            type="button"
                            variant="link"
                            tone="primary"
                            onclick={() => handleConnect(provider)}
                            class="text-sm font-medium text-blue-500 transition-colors hover:text-blue-600"
                        >
                            Connect
                        </Button>
                    {/if}
                </div>
            </div>
        {/each}
    </div>
</div>
