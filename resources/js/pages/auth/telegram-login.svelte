<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { untrack } from 'svelte';
    import { Link, router } from '@inertiajs/svelte';
    import { Card } from '@/components/ui';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    interface Props {
        metaTags?: {
            title?: string;
        };
    }

    let { metaTags }: Props = $props();

    let widgetContainer = $state<HTMLDivElement | undefined>();

    const title = untrack(() => metaTags?.title || 'Login with Telegram');

    type TelegramUser = Record<string, string | number>;
    type TelegramWindow = Window & { onTelegramAuth?: (user: TelegramUser) => void };

    $effect(() => {
        if (!widgetContainer) return;

        const telegramWindow = window as TelegramWindow;
        telegramWindow.onTelegramAuth = (user) => router.post(route('auth.telegram.callback'), user);

        const scriptEl = document.createElement('script');
        scriptEl.async = true;
        scriptEl.src = 'https://telegram.org/js/telegram-widget.js?22';
        scriptEl.setAttribute('data-telegram-login', 'fvnli_bot');
        scriptEl.setAttribute('data-size', 'large');
        scriptEl.setAttribute('data-userpic', 'true');
        scriptEl.setAttribute('data-onauth', 'onTelegramAuth(user)');
        scriptEl.setAttribute('data-request-access', 'write');

        const container = widgetContainer;
        container.innerHTML = '';
        container.appendChild(scriptEl);

        return () => {
            delete telegramWindow.onTelegramAuth;
            if (container) {
                container.innerHTML = '';
            }
        };
    });
</script>

<SeoHead {title} />

<div class="flex min-h-[70vh] items-center justify-center">
    <Card variant="flat" padding="lg" class="w-full max-w-md text-center">
        <PageHeader {title} align="center" class="mb-6" />

        <div class="flex justify-center" bind:this={widgetContainer}></div>

        <div class="mt-6">
            <Link href={route('home')} class="text-fg-muted transition-colors hover:text-fg">Cancel and go back</Link>
        </div>
    </Card>
</div>
