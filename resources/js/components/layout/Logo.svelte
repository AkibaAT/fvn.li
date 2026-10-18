<script lang="ts">
    import { Link } from '@inertiajs/svelte';

    const LOGO_VERSION = 6;
</script>

<Link href={route('home')} class="inline-flex shrink-0 items-center rounded-md" aria-label="FVN.li home page">
    <svg viewBox="0 0 200 56" class="h-8 w-auto text-fg" aria-hidden="true">
        <use href="/logo.svg?v={LOGO_VERSION}#fvn-logo" />
    </svg>
</Link>
