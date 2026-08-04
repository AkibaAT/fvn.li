<script lang="ts">
    import { usePage } from '@inertiajs/svelte';
    import { getCsrfToken } from '@/utils/http';
    import { notify } from '@/components/Toast.svelte';
    import { Button, Dialog, TextInput, Textarea } from '@/components/ui';

    interface User {
        id: number;
        name: string;
    }

    let isOpen = $state(false);
    let description = $state('');
    let isSubmitting = $state(false);
    let pageInfo = $state({
        url: '',
        title: '',
        params: {} as Record<string, string>,
    });

    const inertiaPage = usePage();
    const user = $derived((inertiaPage.props?.auth?.user ?? null) as User | null);

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
                    'X-CSRF-TOKEN': getCsrfToken(),
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

    function closeDialog() {
        isOpen = false;
        description = '';
    }
</script>

<Button
    onclick={() => (isOpen = true)}
    variant="ghost"
    tone="neutral"
    class="text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-300"
    aria-label="Report a bug"
    title="Report a bug"
>
    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
    </svg>
    <span>Report a Bug</span>
</Button>

<Dialog open={isOpen} onClose={closeDialog} title="Report a Bug">
    <form onsubmit={handleSubmit}>
        {#if !user}
            <div class="mb-4 rounded-lg bg-amber-50 p-4 text-amber-800 dark:bg-amber-900/20 dark:text-amber-200">
                <p class="text-sm">
                    You must be logged in to submit a bug report. Please
                    <a href={route('login')} class="font-medium underline hover:no-underline"> log in </a>
                    to continue.
                </p>
            </div>
        {/if}

        <TextInput
            id="bug-page-url"
            type="text"
            value={pageInfo.url}
            readonly
            label="Page URL"
            fieldClass="mb-4"
            class="bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300"
        />

        {#if Object.keys(pageInfo.params).length > 0}
            <div class="mb-4">
                <p class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300">Page Parameters</p>
                <div class="rounded-lg border border-gray-300 bg-gray-100 px-3 py-2 text-sm dark:border-gray-600 dark:bg-gray-700">
                    {#each Object.entries(pageInfo.params) as [key, value] (key)}
                        <div class="text-gray-600 dark:text-gray-300">
                            <span class="font-medium">{key}:</span>
                            {value}
                        </div>
                    {/each}
                </div>
            </div>
        {/if}

        <Textarea
            id="bug-description"
            bind:value={description}
            label="Description"
            placeholder="Please describe what happened, what you expected to happen, and any steps to reproduce the issue..."
            rows={5}
            required
            minlength={10}
            disabled={!user}
            help="Minimum 10 characters. Be as specific as possible."
            fieldClass="mb-4"
        />

        <div class="flex justify-end gap-3">
            <Button type="button" onclick={closeDialog} variant="outline" tone="neutral">Cancel</Button>
            <Button type="submit" disabled={isSubmitting || !user} loading={isSubmitting}>
                {isSubmitting ? 'Submitting...' : 'Submit Report'}
            </Button>
        </div>
    </form>
</Dialog>
