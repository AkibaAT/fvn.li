<script lang="ts">
    import { createApiToken, extendApiToken, listApiTokens, revokeApiToken, type ApiToken } from '@/api/api-tokens';
    import { useAsyncAction } from '@/utils/async-action.svelte';
    import { formatLocalDate } from '@/utils/date-formatting';
    import { Button, Card, Checkbox, TextInput } from '@/components/ui';
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    const DAY_MS = 86400000;

    let tokens = $state<ApiToken[]>([]);
    let scopes = $state<string[]>([]);
    let name = $state('');
    let abilities = $state<string[]>(['catalog:read', 'tags:read', 'lists:read']);
    let secret = $state('');

    const loadAction = useAsyncAction();
    const tokenAction = useAsyncAction();

    onMount(async () => {
        const loaded = await loadAction.run(listApiTokens, { fallbackError: 'Could not load API tokens' });
        if (!loaded) return;
        tokens = loaded.tokens;
        scopes = loaded.abilities;
    });

    function toggle(scope: string) {
        abilities = abilities.includes(scope) ? abilities.filter((item) => item !== scope) : [...abilities, scope];
    }

    async function create() {
        if (!name.trim() || abilities.length === 0 || tokenAction.isLoading) return;
        const created = await tokenAction.run(() => createApiToken(name.trim(), abilities), { fallbackError: 'Could not create API token' });
        if (!created) return;
        tokens = [created.token, ...tokens];
        secret = created.secret;
        name = '';
    }

    async function extend(token: ApiToken) {
        if (tokenAction.isLoading) return;
        const extended = await tokenAction.run(() => extendApiToken(token.id), {
            success: 'Token extended',
            fallbackError: 'Could not extend token',
        });
        if (!extended) return;
        tokens = tokens.map((item) => (item.id === token.id ? extended : item));
    }

    async function revoke(token: ApiToken) {
        if (tokenAction.isLoading || !confirm(`Revoke “${token.name}”? Scripts using it will stop working.`)) return;
        const revoked = await tokenAction.run(
            async () => {
                await revokeApiToken(token.id);
                return true;
            },
            { success: 'Token revoked', fallbackError: 'Could not revoke token' },
        );
        if (!revoked) return;
        tokens = tokens.filter((item) => item.id !== token.id);
    }

    const daysLeft = (token: ApiToken) => Math.ceil((new Date(token.expires_at).getTime() - Date.now()) / DAY_MS);
</script>

<Card variant="flat" padding="lg">
    <h2 class="mb-2 text-title font-semibold text-fg">API access</h2>
    <p class="mb-4 text-sm text-fg-muted">
        Create a token for your scripts. Tokens last 90 days, and an active token can be extended here up to one year from creation.
        <Link href={route('developers')} class="underline">Read the API guide</Link> or
        <Link href={route('developers.swagger')} class="underline">try Swagger UI</Link>.
    </p>

    {#if secret}
        <div class="mb-4 rounded border border-border bg-surface-alt p-3">
            <p class="mb-2 text-sm font-medium text-fg">Copy this token now. It will not be shown again.</p>
            <code class="block text-sm break-all text-fg">{secret}</code>
            <Button type="button" size="sm" class="mt-2" onclick={() => (secret = '')}>I copied it</Button>
        </div>
    {/if}

    <form
        class="space-y-3"
        onsubmit={(event) => {
            event.preventDefault();
            void create();
        }}
    >
        <TextInput id="api-token-name" label="Token name" bind:value={name} maxlength={100} required />
        <fieldset>
            <legend class="mb-2 text-sm font-medium text-fg">Permissions</legend>
            <div class="grid grid-cols-2 gap-2">
                {#each scopes as scope (scope)}
                    <Checkbox label={scope} checked={abilities.includes(scope)} onchange={() => toggle(scope)} />
                {/each}
            </div>
        </fieldset>
        <Button type="submit" disabled={tokenAction.isLoading || !name.trim() || abilities.length === 0}>Create token</Button>
    </form>

    <h3 class="mt-6 mb-2 font-semibold text-fg">Your tokens</h3>
    {#if loadAction.isLoading}<p class="text-sm text-fg-muted">Loading…</p>{/if}
    <ul class="space-y-3">
        {#each tokens as token (token.id)}
            <li class="rounded border border-border p-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <strong class="text-sm text-fg">{token.name}</strong>
                    <div class="flex gap-2">
                        <Button
                            type="button"
                            size="xs"
                            variant="outline"
                            disabled={tokenAction.isLoading || !token.is_extendable}
                            ariaLabel={`Extend ${token.name}`}
                            onclick={() => extend(token)}>Extend</Button
                        >
                        <Button
                            type="button"
                            size="xs"
                            variant="outline"
                            tone="danger"
                            disabled={tokenAction.isLoading}
                            ariaLabel={`Revoke ${token.name}`}
                            onclick={() => revoke(token)}>Revoke</Button
                        >
                    </div>
                </div>
                <p class="mt-1 text-xs text-fg-muted">
                    Expires {formatLocalDate(token.expires_at)}
                    {#if daysLeft(token) <= 14}
                        <span class="text-red-600 dark:text-red-400">· {daysLeft(token) <= 0 ? 'Expired' : 'Expiring soon'}</span>
                    {/if}
                    · Last used {token.last_used_at ? formatLocalDate(token.last_used_at) : 'never'}
                </p>
                <p class="mt-1 text-xs break-words text-fg-faint">{token.abilities.join(', ')}</p>
            </li>
        {/each}
    </ul>
</Card>
