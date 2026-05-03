<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import { notify } from '@/components/Toast.svelte';
    import { isDialogBackdropClick } from '@/utils/dialog';

    interface User {
        id: number;
        name: string;
    }

    let dialogEl: HTMLDialogElement;
    let closeButtonEl: HTMLButtonElement;
    let openerEl: HTMLElement | null = null;

    let isOpen = $state(false);
    let description = $state('');
    let isSubmitting = $state(false);
    let pageInfo = $state({
        url: '',
        title: '',
        params: {} as Record<string, string>,
    });

    const user = $derived(((page as any)?.props?.auth?.user ?? null) as User | null);

    // Handle dialog open/close
    $effect(() => {
        if (!dialogEl) return;

        if (isOpen) {
            openerEl = (document.activeElement as HTMLElement) || null;
            if (!dialogEl.open) dialogEl.showModal();
            requestAnimationFrame(() => {
                closeButtonEl?.focus();
            });
        } else if (dialogEl.open) {
            dialogEl.close();
            openerEl?.focus();
        }
    });

    // Handle native dialog close event (ESC key, etc.)
    $effect(() => {
        if (!dialogEl) return;

        const handleClose = () => {
            isOpen = false;
            description = '';
            openerEl?.focus?.();
            openerEl = null;
        };

        dialogEl.addEventListener('close', handleClose);
        return () => dialogEl.removeEventListener('close', handleClose);
    });

    // Capture page info when modal opens
    $effect(() => {
        if (isOpen) {
            const url = new URL(window.location.href);
            const params: Record<string, string> = {};
            url.searchParams.forEach((value, key) => {
                params[key] = value;
            });

            pageInfo = {
                url: window.location.href,
                title: document.title,
                params,
            };
        }
    });

    async function handleSubmit(e: SubmitEvent) {
        e.preventDefault();

        if (!user) {
            notify('You must be logged in to submit a bug report.', 'error');
            return;
        }

        if (description.trim().length < 10) {
            notify('Please provide a more detailed description (at least 10 characters).', 'error');
            return;
        }

        isSubmitting = true;

        try {
            const response = await fetch(route('browser-api.bug-reports.store'), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '',
                },
                body: JSON.stringify({
                    page_url: pageInfo.url,
                    page_title: pageInfo.title,
                    description: description.trim(),
                    request_parameters: pageInfo.params,
                }),
            });

            const data = await response.json();

            if (data.success) {
                notify(data.message, 'success');
                description = '';
                isOpen = false;
            } else {
                notify(data.message || 'Failed to submit bug report.', 'error');
            }
        } catch {
            notify('An error occurred while submitting the bug report.', 'error');
        } finally {
            isSubmitting = false;
        }
    }
</script>

<!-- Bug Report Button -->
<button
    onclick={() => (isOpen = true)}
    class="flex items-center gap-2 text-gray-500 transition-colors hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
    aria-label="Report a bug"
    title="Report a bug"
>
    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>Report a Bug</span>
</button>

<!-- Bug Report Dialog -->
<dialog
    bind:this={dialogEl}
    aria-modal="true"
    aria-labelledby="bug-report-title"
    oncancel={(event) => {
        event.preventDefault();
        isOpen = false;
    }}
    class="m-auto w-full max-w-lg rounded-lg border border-gray-200 bg-white p-0 shadow-xl backdrop:bg-black/50 backdrop:backdrop-blur-sm dark:border-gray-700 dark:bg-gray-800"
    onclick={(e) => {
        if (isDialogBackdropClick(dialogEl, e)) isOpen = false;
    }}
>
    <!-- Header -->
    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
        <h2 id="bug-report-title" class="text-lg font-semibold text-gray-900 dark:text-white">Report a Bug</h2>
        <button
            bind:this={closeButtonEl}
            type="button"
            onclick={() => (isOpen = false)}
            class="rounded-md text-gray-400 hover:text-gray-600 focus:ring-2 focus:ring-blue-500 focus:outline-none dark:hover:text-gray-300"
            aria-label="Close dialog"
        >
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <!-- Content -->
    <form onsubmit={handleSubmit} class="px-6 py-4">
        {#if !user}
            <div class="mb-4 rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                <p class="text-sm">
                    You must be logged in to submit a bug report. Please
                    <a href={route('login')} class="font-medium underline hover:no-underline"> log in </a>
                    to continue.
                </p>
            </div>
        {/if}

        <div class="mb-4">
            <label for="bug-page-url" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Page URL </label>
            <input
                id="bug-page-url"
                type="text"
                value={pageInfo.url}
                readonly
                class="w-full rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-600 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-400"
            />
        </div>

        {#if Object.keys(pageInfo.params).length > 0}
            <div class="mb-4">
                <p class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Page Parameters</p>
                <div class="rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700">
                    {#each Object.entries(pageInfo.params) as [key, value] (key)}
                        <div class="text-gray-600 dark:text-gray-400">
                            <span class="font-medium">{key}:</span>
                            {value}
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <div class="mb-4">
            <label for="bug-description" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"> Description * </label>
            <textarea
                id="bug-description"
                bind:value={description}
                placeholder="Please describe what happened, what you expected to happen, and any steps to reproduce the issue..."
                rows="5"
                class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 focus:outline-none dark:border-gray-600 dark:bg-gray-700 dark:text-white dark:placeholder-gray-400"
                required
                minlength="10"
                disabled={!user}
            ></textarea>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Minimum 10 characters. Be as specific as possible.</p>
        </div>

        <div class="flex justify-end gap-3">
            <button
                type="button"
                onclick={() => (isOpen = false)}
                class="rounded-lg border border-gray-300 px-4 py-2 text-sm font-medium text-gray-700 transition-colors hover:bg-gray-50 dark:border-gray-600 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                Cancel
            </button>
            <button
                type="submit"
                disabled={isSubmitting || !user}
                class="rounded-lg bg-blue-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {isSubmitting ? 'Submitting...' : 'Submit Report'}
            </button>
        </div>
    </form>
</dialog>
