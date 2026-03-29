<script lang="ts">
    import { isDialogBackdropClick } from '@/utils/dialog';

    interface Props {
        ratingId: number;
        reviewerName: string;
        isOpen: boolean;
        onClose: () => void;
    }

    let { ratingId, reviewerName, isOpen, onClose }: Props = $props();

    let dialogEl: HTMLDialogElement;
    let reason = $state('');
    let details = $state('');
    let isSubmitting = $state(false);
    let message = $state<{ type: 'success' | 'error'; text: string } | null>(null);

    const REPORT_REASONS = [
        { value: 'hate_speech', label: 'Hate speech or discrimination' },
        { value: 'spam', label: 'Spam or advertising' },
        { value: 'harassment', label: 'Harassment or personal attacks' },
        { value: 'spoilers', label: 'Unmarked spoilers' },
        { value: 'off_topic', label: 'Off-topic or irrelevant' },
        { value: 'other', label: 'Other' },
    ];

    $effect(() => {
        if (!dialogEl) return;
        if (isOpen) {
            dialogEl.showModal();
        } else {
            dialogEl.close();
        }
    });

    async function handleSubmit(e: Event) {
        e.preventDefault();
        if (!reason) return;

        isSubmitting = true;
        try {
            const response = await (window as any).axios.post(route('react-api.review-reports.store', { rating: ratingId }), {
                reason,
                details: details.trim() || null,
            });

            if (response.data.success) {
                message = { type: 'success', text: response.data.message };
                setTimeout(() => {
                    onClose();
                    message = null;
                    reason = '';
                    details = '';
                }, 2000);
            }
        } catch (error: any) {
            const msg = error?.response?.data?.message || 'Failed to submit report';
            message = { type: 'error', text: msg };
        } finally {
            isSubmitting = false;
        }
    }

    function handleBackdropClick(e: MouseEvent) {
        if (isDialogBackdropClick(dialogEl, e)) {
            onClose();
        }
    }

    function handleCancel(event: Event) {
        event.preventDefault();
        onClose();
    }
</script>

<dialog
    bind:this={dialogEl}
    class="m-auto w-full max-w-md rounded-lg bg-white p-6 shadow-xl backdrop:backdrop-blur-md dark:bg-gray-800 dark:text-gray-100"
    onclick={handleBackdropClick}
    oncancel={handleCancel}
>
    <div class="mb-4 flex items-center justify-between">
        <h3 class="text-lg font-medium text-gray-900 dark:text-white">Report Review</h3>
        <button onclick={onClose} class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-200" aria-label="Close dialog">
            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>

    <p class="mb-4 text-sm text-gray-600 dark:text-gray-400">
        Report the review by <strong>{reviewerName}</strong> for violating community guidelines.
    </p>

    <form onsubmit={handleSubmit}>
        <fieldset class="mb-4">
            <legend class="mb-2 block text-sm font-medium text-gray-700 dark:text-gray-300">Reason *</legend>
            <div class="space-y-2">
                {#each REPORT_REASONS as r (r.value)}
                    <label class="flex cursor-pointer items-center gap-2">
                        <input
                            type="radio"
                            name="reason"
                            value={r.value}
                            checked={reason === r.value}
                            onchange={(e) => (reason = (e.target as HTMLInputElement).value)}
                            class="text-blue-600 focus:ring-blue-500 dark:border-gray-600"
                        />
                        <span class="text-sm text-gray-700 dark:text-gray-300">{r.label}</span>
                    </label>
                {/each}
            </div>
        </fieldset>

        <div class="mb-4">
            <label for="report-details" class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-300"
                >Additional details (optional)</label
            >
            <textarea
                id="report-details"
                bind:value={details}
                placeholder="Provide any additional context..."
                rows="3"
                maxlength="1000"
                class="w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm focus:border-blue-500 focus:ring-1 focus:ring-blue-500 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-100"
            ></textarea>
        </div>

        {#if message}
            <div class="mb-3 text-sm {message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                {message.text}
            </div>
        {/if}

        <div class="flex items-center gap-2">
            <button
                type="submit"
                disabled={!reason || isSubmitting}
                class="rounded-md bg-red-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {isSubmitting ? 'Submitting...' : 'Submit Report'}
            </button>
            <button
                type="button"
                onclick={onClose}
                class="rounded-md bg-gray-200 px-4 py-2 text-sm text-gray-700 hover:bg-gray-300 dark:bg-gray-700 dark:text-gray-300 dark:hover:bg-gray-600"
            >
                Cancel
            </button>
        </div>
    </form>
</dialog>
