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
            <a href={route('developers.openapi')} class="text-sm font-medium text-accent-fg underline">OpenAPI 3.1 specification</a>
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

    <div class="grid gap-6 lg:grid-cols-2">
        <Card variant="flat" padding="lg" class="space-y-3">
            <h2 class="text-title font-semibold text-fg">Permissions</h2>
            <p class="text-sm text-fg-muted">
                Grant <code>catalog:read</code> for game search and lookup. Tags, lists, tracking, and preferences each have separate
                <code>:read</code> and <code>:write</code> permissions. Write does not imply read. Every operation lists its required permission as
                <code>x-required-ability</code> in the specification.
            </p>
        </Card>

        <Card variant="flat" padding="lg" class="space-y-3">
            <h2 class="text-title font-semibold text-fg">Pagination and errors</h2>
            <p class="text-sm text-fg-muted">
                Collections return <code>{'{"data":[],"links":{},"meta":{}}'}</code>. Follow <code>links.next</code> until it is null. Catalog search
                accepts <code>per_page</code> from 1 to 50; other collections use 20 per page. Validation returns
                <code>422</code> with field-keyed errors; missing tokens return <code>401</code>, missing permission <code>403</code>, and objects
                owned by someone else <code>404</code>.
            </p>
        </Card>
    </div>

    <Card variant="flat" padding="lg" class="space-y-4">
        <h2 class="text-title font-semibold text-fg">Find a catalog game</h2>
        <p class="text-sm text-fg-muted">
            Use a fvn.li game ID, or resolve an exact itch.io ID, Steam app ID, or stored platform URL. URL matching ignores scheme, query, fragment,
            and trailing slash while preserving path case and port. The API never imports a game or guesses from a URL slug. Hidden games are
            excluded. Single lookup returns <code>404</code> for no match and <code>409</code> for multiple matches.
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

    <div class="grid gap-6 lg:grid-cols-3">
        <Card variant="flat" padding="lg" class="space-y-3">
            <h2 class="text-title font-semibold text-fg">Private tags</h2>
            <p class="text-sm text-fg-muted">
                Use <code>/private-tags</code> to create, rename, or delete your labels, then attach them to any visible game with
                <code>PUT /games/{'{game}'}/private-tags/{'{tag}'}</code>. They remain private to your account.
            </p>
        </Card>
        <Card variant="flat" padding="lg" class="space-y-3">
            <h2 class="text-title font-semibold text-fg">Lists</h2>
            <p class="text-sm text-fg-muted">
                Use <code>/lists</code> for owned lists and <code>/lists/{'{list}'}/games/{'{game}'}</code> to add or remove entries. Private entry notes
                never appear on public lists. Default lists cannot be deleted, and a game belongs to only one default list.
            </p>
        </Card>
        <Card variant="flat" padding="lg" class="space-y-3">
            <h2 class="text-title font-semibold text-fg">Tracking and preferences</h2>
            <p class="text-sm text-fg-muted">
                Use <code>/me/tracked-games</code> for status, version, dates, notes, and update watching. The search-preferences and ignored-games endpoints
                expose your existing search controls. Ratings and review publishing remain on the website.
            </p>
        </Card>
    </div>

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
        <p class="text-sm text-fg-muted">
            Limits apply to your account and are shared by all of your tokens. A <code>429</code> response includes <code>Retry-After</code> and
            rate-limit headers. Successful authenticated responses include
            <code>X-Token-Expires-At</code>. See the <a href={route('developers.openapi')} class="underline">specification</a> for every request and response
            schema.
        </p>
    </Card>
</div>
