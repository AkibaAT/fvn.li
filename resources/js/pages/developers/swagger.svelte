<script>
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import { Alert, Button } from '@/components/ui';
    import 'swagger-ui-dist/swagger-ui.css';

    let { metaTags } = $props();
    let loadError = $state(false);

    onMount(() => {
        let active = true;
        import('swagger-ui-dist/swagger-ui-bundle.js')
            .then(({ default: SwaggerUIBundle }) => {
                if (!active) return;
                SwaggerUIBundle({
                    url: route('developers.openapi'),
                    dom_id: '#swagger-ui',
                    deepLinking: true,
                    docExpansion: 'list',
                    filter: true,
                    persistAuthorization: false,
                    validatorUrl: null,
                });
            })
            .catch(() => {
                if (active) loadError = true;
            });

        return () => {
            active = false;
        };
    });
</script>

<SeoHead {metaTags} />

<PageHeader title="API explorer" description="Browse endpoints and try requests with a token from your dashboard.">
    {#snippet actions()}
        <Link href={route('developers')} class="text-sm font-medium text-accent-fg underline">API guide</Link>
        <Button href={route('dashboard')} variant="outline">Manage tokens</Button>
    {/snippet}
</PageHeader>

<p class="mb-5 text-sm text-fg-muted">
    Paste a valid user API token into <strong>Authorize</strong> below. Tokens are shown once when created in your dashboard; expired or revoked tokens
    must be replaced.
</p>

{#if loadError}
    <Alert tone="danger" layout="inline" class="mb-5">
        The API explorer could not load. Refresh the page or use the <a href={route('developers.openapi')} class="underline">OpenAPI specification</a
        >.
    </Alert>
{/if}

<section class="api-explorer overflow-hidden rounded-lg border border-border bg-surface text-fg" aria-label="Interactive API reference">
    <div id="swagger-ui"></div>
</section>

<style>
    :global(.api-explorer .swagger-ui .topbar),
    :global(.api-explorer .swagger-ui .info) {
        display: none;
    }

    :global(.api-explorer .swagger-ui .wrapper) {
        max-width: none;
    }

    :global(.api-explorer .swagger-ui .scheme-container) {
        box-shadow: none;
    }
</style>
