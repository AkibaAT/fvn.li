<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import GoogleIcon from '@/components/icons/Google.svelte';
    import DiscordIcon from '@/components/icons/Discord.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import SteamIcon from '@/components/icons/Steam.svelte';
    import TelegramIcon from '@/components/icons/Telegram.svelte';
    import { Link } from '@inertiajs/svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Card } from '@/components/ui';

    interface Props {
        metaTags?: {
            title?: string;
        };
    }

    let { metaTags }: Props = $props();
    let rememberLogin = $state(true);

    function socialLoginHref(provider: string): string {
        return route('auth.redirect', {
            provider,
            remember: rememberLogin ? 1 : 0,
        });
    }
</script>

<SeoHead {metaTags} title="Log in" />

<div class="py-12">
    <div class="mx-auto max-w-md sm:px-6 lg:px-8">
        <Card variant="glass" padding="none" class="overflow-hidden sm:rounded-2xl">
            <div class="p-6 text-gray-900 dark:text-gray-100">
                <PageHeader title="Welcome to FVN.li" description="Log in to manage your visual novel collections" align="center" class="mb-6" />

                <div class="space-y-3">
                    <label
                        class="flex items-center gap-3 rounded-lg border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-gray-700 dark:bg-gray-900/40 dark:text-gray-200"
                    >
                        <input
                            bind:checked={rememberLogin}
                            type="checkbox"
                            class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-800"
                        />
                        <span>Keep me signed in on this device</span>
                    </label>

                    <a
                        href={socialLoginHref('discord')}
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <DiscordIcon class="mr-3 h-5 w-5 text-indigo-500" />
                        <span>Continue with Discord</span>
                    </a>

                    <a
                        href={socialLoginHref('google')}
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <GoogleIcon class="mr-3 h-5 w-5" />
                        <span>Continue with Google</span>
                    </a>

                    <a
                        href={socialLoginHref('itchio')}
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <ItchioIcon class="text-itchio mr-3 h-5 w-5" />
                        <span>Continue with itch.io</span>
                    </a>

                    <a
                        href={socialLoginHref('steam')}
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <SteamIcon class="mr-3 h-5 w-5" />
                        <span>Continue with Steam</span>
                    </a>

                    <a
                        href={socialLoginHref('telegram')}
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
                        <TelegramIcon class="mr-2 h-5 w-5 text-blue-500" />
                        <span>Continue with Telegram</span>
                    </a>
                </div>

                <div class="mt-6 text-center">
                    <Link
                        href={route('home')}
                        class="text-sm text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
                    >
                        Back to home
                    </Link>
                </div>
            </div>
        </Card>
    </div>
</div>
