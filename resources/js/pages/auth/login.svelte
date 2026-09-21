<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import GoogleIcon from '@/components/icons/Google.svelte';
    import DiscordIcon from '@/components/icons/Discord.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import SteamIcon from '@/components/icons/Steam.svelte';
    import TelegramIcon from '@/components/icons/Telegram.svelte';
    import { Link } from '@inertiajs/svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import { Button, Card, Checkbox } from '@/components/ui';

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
        <Card variant="flat" padding="none">
            <div class="p-6 text-fg">
                <PageHeader title="Welcome to FVN.li" description="Log in to manage your visual novel collections" align="center" class="mb-6" />

                <div class="space-y-3">
                    <div class="rounded-md border border-border bg-surface-alt px-4 py-3">
                        <Checkbox id="remember-login" bind:checked={rememberLogin} label="Keep me signed in on this device" />
                    </div>

                    <Button href={socialLoginHref('discord')} inertia={false} variant="outline" tone="neutral" class="w-full">
                        <DiscordIcon class="h-5 w-5 text-[#5865F2]" />
                        <span>Continue with Discord</span>
                    </Button>

                    <Button href={socialLoginHref('google')} inertia={false} variant="outline" tone="neutral" class="w-full">
                        <GoogleIcon class="h-5 w-5" />
                        <span>Continue with Google</span>
                    </Button>

                    <Button href={socialLoginHref('itchio')} inertia={false} variant="outline" tone="neutral" class="w-full">
                        <ItchioIcon class="text-itchio h-5 w-5" />
                        <span>Continue with itch.io</span>
                    </Button>

                    <Button href={socialLoginHref('steam')} inertia={false} variant="outline" tone="neutral" class="w-full">
                        <SteamIcon class="h-5 w-5" />
                        <span>Continue with Steam</span>
                    </Button>

                    <Button href={socialLoginHref('telegram')} inertia={false} variant="outline" tone="neutral" class="w-full">
                        <TelegramIcon class="h-5 w-5 text-[#229ED9]" />
                        <span>Continue with Telegram</span>
                    </Button>
                </div>

                <div class="mt-6 text-center">
                    <Link href={route('home')} class="text-sm text-fg-muted transition-colors hover:text-fg">Back to home</Link>
                </div>
            </div>
        </Card>
    </div>
</div>
