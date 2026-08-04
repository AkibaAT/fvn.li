<script lang="ts">
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import SteamIcon from '@/components/icons/Steam.svelte';
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

<svelte:head>
    <title>{metaTags?.title || 'Log in'}</title>
</svelte:head>

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
                        <i class="icon-discord mr-3 h-5 w-5 text-indigo-500"></i>
                        <span>Continue with Discord</span>
                    </a>

                    <a
                        href={socialLoginHref('google')}
                        class="flex w-full items-center justify-center rounded-lg border border-gray-300 px-4 py-2 text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-200 dark:hover:bg-gray-700"
                    >
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
                        <i class="icon-telegram mr-2 h-5 w-5 text-blue-500"></i>
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
