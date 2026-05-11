<script lang="ts">
    interface Props {
        message?: string;
        error?: string;
    }

    let { message, error }: Props = $props();
</script>

{#if message}
    <div class="mx-4 mt-4">
        <div
            class="rounded-xl border border-green-200 bg-green-50 px-6 py-4 text-green-800 shadow-sm backdrop-blur-sm dark:border-green-700 dark:bg-green-900/20 dark:text-green-200"
        >
            <div class="flex items-center space-x-3">
                <i class="icon-check-circle text-green-500" aria-hidden="true"></i>
                <span class="font-medium">
                    {message}
                </span>
            </div>
        </div>
    </div>
{/if}

{#if error}
    <div class="mx-4 mt-4">
        <div
            class="rounded-xl border border-red-200 bg-red-50 px-6 py-4 text-red-800 shadow-sm backdrop-blur-sm dark:border-red-700 dark:bg-red-900/20 dark:text-red-200"
        >
            <div class="flex items-center space-x-3">
                <i class="icon-cross-circle text-red-500" aria-hidden="true"></i>
                <span class="font-medium">
                    {error}
                </span>
            </div>
        </div>
    </div>
{/if}
