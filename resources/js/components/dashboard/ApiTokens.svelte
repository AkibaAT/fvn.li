<script lang="ts">
    import { createApiToken, extendApiToken, listApiTokens, revokeApiToken, type ApiToken } from '@/api/api-tokens';
    import { getErrorMessage } from '@/utils/async-action.svelte';
    import { toast } from '@/utils/toast';
    import { Button, Card, Checkbox, TextInput } from '@/components/ui';
    import { Link } from '@inertiajs/svelte';
    import { onMount } from 'svelte';

    const DAY_MS = 86400000;

    let tokens = $state<ApiToken[]>([]);
    let scopes = $state<string[]>([]);
    let name = $state('');
    let abilities = $state<string[]>(['catalog:read', 'tags:read', 'lists:read']);
    let secret = $state('');
    let busy = $state(false);
    let loading = $state(true);

    onMount(async () => {
        try {
            ({ tokens, abilities: scopes } = await listApiTokens());
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not load API tokens'));
        } finally {
            loading = false;
        }
    });

    function toggle(scope: string) {
        abilities = abilities.includes(scope) ? abilities.filter((item) => item !== scope) : [...abilities, scope];
    }

    async function create() {
        if (!name.trim() || abilities.length === 0 || busy) return;
        busy = true;
        try {
            const created = await createApiToken(name.trim(), abilities);
            tokens = [created.token, ...tokens];
            secret = created.secret;
            name = '';
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not create API token'));
        } finally {
            busy = false;
        }
    }

    async function extend(token: ApiToken) {
        try {
            const extended = await extendApiToken(token.id);
            tokens = tokens.map((item) => (item.id === token.id ? extended : item));
            toast.success('Token extended');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not extend token'));
        }
    }

    async function revoke(token: ApiToken) {
        if (!confirm(`Revoke “${token.name}”? Scripts using it will stop working.`)) return;
        try {
            await revokeApiToken(token.id);
            tokens = tokens.filter((item) => item.id !== token.id);
            toast.success('Token revoked');
        } catch (error) {
            toast.error(getErrorMessage(error, 'Could not revoke token'));
        }
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
        <Button type="submit" disabled={busy || !name.trim() || abilities.length === 0}>Create token</Button>
    </form>

    <h3 class="mt-6 mb-2 font-semibold text-fg">Your tokens</h3>
    {#if loading}<p class="text-sm text-fg-muted">Loading…</p>{/if}
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
                            disabled={!token.is_extendable}
                            ariaLabel={`Extend ${token.name}`}
                            onclick={() => extend(token)}>Extend</Button
                        >
                        <Button
                            type="button"
                            size="xs"
                            variant="outline"
                            tone="danger"
                            ariaLabel={`Revoke ${token.name}`}
                            onclick={() => revoke(token)}>Revoke</Button
                        >
                    </div>
                </div>
                <p class="mt-1 text-xs text-fg-muted">
                    Expires {new Date(token.expires_at).toLocaleDateString()}
                    {#if daysLeft(token) <= 14}
                        <span class="text-red-600 dark:text-red-400">· {daysLeft(token) <= 0 ? 'Expired' : 'Expiring soon'}</span>
                    {/if}
                    · Last used {token.last_used_at ? new Date(token.last_used_at).toLocaleDateString() : 'never'}
                </p>
                <p class="mt-1 text-xs break-words text-fg-faint">{token.abilities.join(', ')}</p>
            </li>
        {/each}
    </ul>
</Card>
