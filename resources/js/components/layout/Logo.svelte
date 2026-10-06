<script lang="ts">
    import { Link } from '@inertiajs/svelte';
</script>

<Link href={route('home')} class="inline-flex shrink-0 items-center rounded-md" aria-label="FVN.li home page">
    <svg viewBox="0 0 200 56" class="h-8 w-auto text-fg" aria-hidden="true">
        <use href="/logo.svg#fvn-logo" />
    </svg>
</Link>
