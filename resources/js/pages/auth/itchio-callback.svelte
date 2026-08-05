<script lang="ts">
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import XCircleSolidIcon from '@/components/icons/XCircleSolid.svelte';
    import ItchioIcon from '@/components/icons/Itchio.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Card } from '@/components/ui';
    import { Link } from '@inertiajs/svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';

    let error = $state<string | null>(null);
    const title = 'Completing itch.io Login';

    $effect(() => {
        if (typeof window === 'undefined') return;
        const hash = window.location.hash?.substring(1) ?? '';
        const params = new URLSearchParams(hash);
        const accessToken = params.get('access_token');

        if (accessToken) {
            window.location.href = `${route('auth.itchio.process')}?hash=${encodeURIComponent(hash)}`;
        } else {
            error = "We couldn't complete your itch.io login. Please try again.";
        }
    });
</script>

<SeoHead {title} />

<div class="flex min-h-[60vh] items-center justify-center">
    <Card padding="lg" class="w-full max-w-md text-center shadow-md">
        <PageHeader {title} align="center" class="mb-6" />

        {#if !error}
            <div class="flex flex-col items-center justify-center space-y-4">
                <ItchioIcon class="text-itchio h-12 w-12" />
                <div class="flex items-center justify-center space-x-3">
                    <LoadingSpinner class="h-5 w-5 text-blue-500" currentColor label="Completing itch.io authentication" />
                    <span class="text-gray-700 dark:text-gray-300"> Processing authentication... </span>
                </div>
            </div>
        {:else}
            <div>
                <div class="flex items-center justify-center space-x-3 text-red-600 dark:text-red-400">
                    <XCircleSolidIcon class="h-5 w-5" />
                    <span>{error}</span>
                </div>
                <div class="mt-6">
                    <Link href={route('home')} class="text-blue-600 hover:underline dark:text-blue-400">Go back and try again</Link>
                </div>
            </div>
        {/if}
    </Card>
</div>
