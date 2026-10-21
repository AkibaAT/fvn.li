<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import PageHeader from '@/components/layout/PageHeader.svelte';
    import SeoHead from '@/components/seo/SeoHead.svelte';
    import type { MetaTags } from '@/types/meta-tags';
    import { Button, Card } from '@/components/ui';

    let { metaTags }: { metaTags?: MetaTags } = $props();

    const curlExample = `curl -H 'Accept: application/json' \\
  -H 'Authorization: Bearer YOUR_TOKEN' \\
  'https://fvn.li/api/v1/games?q=detective&per_page=20'`;
    const lookupExample = `GET /api/v1/games/resolve?itch_id=708589
GET /api/v1/games/resolve?steam_app_id=1805130
GET /api/v1/games/resolve?url=https%3A%2F%2Fdawn-chorus.itch.io%2Fdawn-chorus

POST /api/v1/games/resolve
{"items":[{"itch_id":708589},{"url":"https://store.steampowered.com/app/1805130/Komorebi/"},{"url":"https://example.itch.io/not-in-the-catalog"}]}`;
    const batchResponseExample = `{
  "data": [
    { "input": { "itch_id": 708589 }, "status": "matched", "game": { "id": 194, "name": "Dawn Chorus", "platform": "itch_io", … } },
    { "input": { "url": "https://store.steampowered.com/app/1805130/Komorebi/" }, "status": "matched", "game": { "id": 406486, "name": "Komorebi", "platform": "steam", … } },
    { "input": { "url": "https://example.itch.io/not-in-the-catalog" }, "status": "unmatched", "game": null }
  ]
}`;
</script>

<SeoHead {metaTags} />

<PageHeader
    title="Developer API"
    description="Use the fvn.li API to discover catalog games and manage your private tags, lists, tracking, and search preferences."
>
    {#snippet actions()}
        <Button href={route('developers.swagger')}>Open API explorer</Button>
    {/snippet}
</PageHeader>

<div class="space-y-6">
    <Card variant="flat" padding="lg" class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="text-title font-semibold text-fg">Get started</h2>
            <a href={route('developers.openapi')} class="text-sm content-link">OpenAPI 3.1 specification</a>
        </div>
        <p class="text-sm text-fg-muted">
            Create a named token in your <Link href={route('dashboard')} class="underline">dashboard</Link>, choose the permissions your client needs,
            and copy the secret when it appears. Send it as an opaque HTTP bearer token. The production base URL is
            <code class="rounded bg-surface-alt px-1 py-0.5 text-fg">https://fvn.li/api/v1</code>.
        </p>
        <pre class="overflow-x-auto rounded-md border border-border bg-surface-alt p-4 text-xs leading-relaxed text-fg"><code>{curlExample}</code
            ></pre>
        <p class="text-sm text-fg-muted">
            Tokens expire after 90 days. You can extend an active token in the dashboard to 90 days from that action, up to 365 days from creation. At
            the cap, create a replacement and revoke the old one. Expired or revoked tokens cannot be revived. Bearer tokens cannot manage tokens.
        </p>
    </Card>

    <Card variant="flat" padding="lg" class="space-y-4">
        <h2 class="text-title font-semibold text-fg">Find a catalog game</h2>
        <p class="text-sm text-fg-muted">
            Resolve an exact itch.io ID, Steam app ID, or stored platform URL to a fvn.li game, whose <code>id</code> the other endpoints take. URL
            matching ignores scheme, query, fragment, and trailing slash while preserving path case and port. Single lookup returns
            <code>404</code> for no match and <code>409</code> for multiple matches.
        </p>
        <pre class="overflow-x-auto rounded-md border border-border bg-surface-alt p-4 text-xs leading-relaxed text-fg"><code>{lookupExample}</code
            ></pre>
        <p class="text-sm text-fg-muted">
            Batch lookup accepts 1–50 inputs and returns an ordered data array. Each result reports <code>matched</code>,
            <code>unmatched</code>, or <code>ambiguous</code>; only a unique match includes a game. One invalid input rejects the whole batch with
            <code>422</code>.
        </p>
        <pre class="overflow-x-auto rounded-md border border-border bg-surface-alt p-4 text-xs leading-relaxed text-fg"><code
                >{batchResponseExample}</code
            ></pre>
    </Card>

    <Card variant="flat" padding="lg" class="space-y-3">
        <h2 class="text-title font-semibold text-fg">Rate limits</h2>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-fg-muted">
                <thead class="text-fg"
                    ><tr><th class="border-b border-border p-2">Limit</th><th class="border-b border-border p-2">Requests per minute</th></tr></thead
                >
                <tbody>
                    <tr><td class="border-b border-border p-2">All requests</td><td class="border-b border-border p-2">120</td></tr>
                    <tr><td class="border-b border-border p-2">Writes</td><td class="border-b border-border p-2">30</td></tr>
                    <tr><td class="p-2">Batch lookups</td><td class="p-2">10</td></tr>
                </tbody>
            </table>
        </div>
    </Card>
</div>
