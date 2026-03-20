<script lang="ts">
    import { untrack } from 'svelte';
    import { Link } from '@inertiajs/svelte';

    interface Props {
        metaTags?: {
            title?: string;
        };
    }

    let { metaTags }: Props = $props();

    let widgetContainer = $state<HTMLDivElement | undefined>();

    const title = untrack(() => metaTags?.title || 'Login with Telegram');

    $effect(() => {
        if (!widgetContainer) return;

        const scriptEl = document.createElement('script');
        scriptEl.async = true;
        scriptEl.src = 'https://telegram.org/js/telegram-widget.js?22';
        scriptEl.setAttribute('data-telegram-login', 'fvnli_bot');
        scriptEl.setAttribute('data-size', 'large');
        scriptEl.setAttribute('data-userpic', 'true');
        scriptEl.setAttribute('data-auth-url', route('auth.callback', { provider: 'telegram' }));
        scriptEl.setAttribute('data-request-access', 'write');

        const container = widgetContainer;
        container.innerHTML = '';
        container.appendChild(scriptEl);

        return () => {
            if (container) {
                container.innerHTML = '';
            }
        };
    });
</script>

<svelte:head>
    <title>{title}</title>
</svelte:head>

<div class="flex min-h-[70vh] items-center justify-center">
    <div class="w-full max-w-md rounded-lg bg-white p-8 text-center shadow-md dark:bg-gray-800">
        <h2 class="mb-6 text-xl font-bold text-gray-900 dark:text-gray-100">
            {title}
        </h2>

        <div class="flex justify-center" bind:this={widgetContainer}></div>

        <div class="mt-6">
            <Link href={route('home')} class="text-blue-600 hover:underline dark:text-blue-400">Cancel and go back</Link>
        </div>
    </div>
</div>
