<script lang="ts">
    import { Button, Dialog, Textarea } from '@/components/ui';

    interface Props {
        ratingId: number;
        reviewerName: string;
        isOpen: boolean;
        onClose: () => void;
    }

    let { ratingId, reviewerName, isOpen, onClose }: Props = $props();

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

    async function handleSubmit(e: Event) {
        e.preventDefault();
        if (!reason) return;

        isSubmitting = true;
        try {
            const response = await (window as any).axios.post(route('browser-api.review-reports.store', { rating: ratingId }), {
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

    function closeDialog() {
        onClose();
    }
</script>

<Dialog open={isOpen} onClose={closeDialog} title="Report Review" size="sm">
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

        <Textarea
            id="report-details"
            bind:value={details}
            label="Additional details (optional)"
            placeholder="Provide any additional context..."
            rows={3}
            maxlength={1000}
            fieldClass="mb-4"
        />

        {#if message}
            <div class="mb-3 text-sm {message.type === 'success' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'}">
                {message.text}
            </div>
        {/if}

        <div class="flex items-center gap-2">
            <Button type="submit" disabled={!reason || isSubmitting} tone="danger">
                {isSubmitting ? 'Submitting...' : 'Submit Report'}
            </Button>
            <Button type="button" onclick={onClose} variant="soft" tone="neutral">Cancel</Button>
        </div>
    </form>
</Dialog>
