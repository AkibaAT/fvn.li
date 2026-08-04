<script lang="ts">
    import type { Snippet } from 'svelte';

    let { title, tone = 'warning', children, actions }: { title: string; tone?: 'warning' | 'danger'; children: Snippet; actions?: Snippet } = $props();

    const toneClasses = {
        warning: {
            box: 'border-yellow-200/50 bg-yellow-50/80 dark:border-yellow-800/50 dark:bg-yellow-900/20',
            icon: 'text-yellow-600 dark:text-yellow-400',
            title: 'text-yellow-800 dark:text-yellow-300',
            body: 'text-yellow-700 dark:text-yellow-400',
        },
        danger: {
            box: 'border-red-200/50 bg-red-50/80 dark:border-red-800/50 dark:bg-red-900/20',
            icon: 'text-red-600 dark:text-red-400',
            title: 'text-red-800 dark:text-red-300',
            body: 'text-red-700 dark:text-red-400',
        },
    };
</script>

<div class="rounded-xl border p-6 backdrop-blur-xl {toneClasses[tone].box}" role="alert">
    <div class="flex items-start gap-3">
        <svg class="mt-0.5 h-5 w-5 shrink-0 {toneClasses[tone].icon}" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
        </svg>
        <div>
            <h3 class="font-semibold {toneClasses[tone].title}">{title}</h3>
            <div class="mt-1 text-sm {toneClasses[tone].body}">{@render children()}</div>
            {@render actions?.()}
        </div>
    </div>
</div>
