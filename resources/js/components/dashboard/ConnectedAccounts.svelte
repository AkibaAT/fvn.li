<script lang="ts">
    import GoogleIcon from '@/components/icons/Google.svelte';
    import DiscordIcon from '@/components/icons/Discord.svelte';
    import LinkIcon from '@/components/icons/Link.svelte';
    import TelegramIcon from '@/components/icons/Telegram.svelte';
    import { disconnectSocialAccount } from '@/api';
    import { untrack } from 'svelte';
    import { getCsrfToken } from '@/utils/csrf';
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
            const message = await disconnectSocialAccount(provider);
            toast.success(message || `${PROVIDERS[provider]?.name} account disconnected successfully.`);
            providers = providers.filter((p) => p !== provider);
            const next = { ...accounts };
            delete next[provider];
            accounts = next;
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
        csrfToken.value = getCsrfToken();
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
                            <DiscordIcon class="h-6 w-6 text-indigo-500" />
                        {:else if config.iconType === 'google'}
                            <GoogleIcon class="h-6 w-6" />
                        {:else if config.iconType === 'itchio'}
                            <Itchio class="text-itchio h-6 w-6" />
                        {:else if config.iconType === 'steam'}
                            <Steam class="h-6 w-6" />
                        {:else if config.iconType === 'telegram'}
                            <TelegramIcon class="h-6 w-6 text-blue-500" />
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
                            <span class="text-sm font-medium text-green-700 dark:text-green-400"> Connected </span>
                            <Button
                                type="button"
                                variant="ghost"
                                tone="danger"
                                size="icon-sm"
                                onclick={() => handleDisconnect(provider)}
                                class="ml-2 text-red-500 transition-colors hover:text-red-600"
                                title="Unlink {config.name} account"
                            >
                                <LinkIcon class="h-5 w-5" />
                            </Button>
                        </div>
                    {:else}
                        <Button
                            type="button"
                            variant="link"
                            tone="primary"
                            onclick={() => handleConnect(provider)}
                            class="text-sm font-medium text-blue-700 transition-colors hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300"
                        >
                            Connect
                        </Button>
                    {/if}
                </div>
            </div>
        {/each}
    </div>
</div>
