<script lang="ts">
    import ExclamationCircleIcon from '@/components/icons/ExclamationCircle.svelte';
    import { submitBugReport } from '@/api';
    import { usePage } from '@inertiajs/svelte';
    import { notify } from '@/components/Toast.svelte';
    import { Alert, Button, Dialog, TextInput, Textarea } from '@/components/ui';

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
            const message = await submitBugReport({
                page_url: pageInfo.url,
                page_title: pageInfo.title,
                description: description.trim(),
                request_parameters: pageInfo.params,
            });
            notify(message, 'success');
            description = '';
            isOpen = false;
        } catch (error) {
            notify(error instanceof Error ? error.message : 'An error occurred while submitting the bug report.', 'error');
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
    <ExclamationCircleIcon class="h-5 w-5" />
    <span>Report a Bug</span>
</Button>

<Dialog open={isOpen} onClose={closeDialog} title="Report a Bug">
    <form onsubmit={handleSubmit}>
        {#if !user}
            <Alert class="mb-4" role="status">
                You must be logged in to submit a bug report. Please
                <a href={route('login')} class="font-medium underline hover:no-underline"> log in </a>
                to continue.
            </Alert>
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
