<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import BookIcon from '@/components/icons/Book.svelte';
</script>

<Link
    href={route('home')}
    class="group flex items-center space-x-3 rounded-lg focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 focus:outline-none"
    aria-label="FVN.li home page"
>
    <div
        class="flex h-10 w-10 items-center justify-center rounded-xl bg-blue-600 shadow-lg transition-all duration-200 group-hover:scale-105 group-hover:shadow-xl"
        aria-hidden="true"
    >
        <BookIcon class="h-5 w-5 text-white" />
    </div>
    <div>
        <div class="text-xl font-bold text-blue-600 dark:text-blue-400">FVN.li</div>
    </div>
</Link>
