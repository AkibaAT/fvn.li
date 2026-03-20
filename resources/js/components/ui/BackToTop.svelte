<script lang="ts">
    import { onMount } from 'svelte';

    let isVisible = $state(false);

    function scrollToTop() {
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    onMount(() => {
        const handleScroll = () => {
            isVisible = window.scrollY > 400;
        };

        window.addEventListener('scroll', handleScroll, { passive: true });
        return () => window.removeEventListener('scroll', handleScroll);
    });
</script>

{#if isVisible}
    <button
        onclick={scrollToTop}
        class="fixed bottom-6 right-6 z-40 rounded-full bg-gray-800 p-3 text-white shadow-lg transition-all hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:bg-gray-700 dark:hover:bg-gray-600"
        aria-label="Back to top"
    >
        <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18" />
        </svg>
    </button>
{/if}
