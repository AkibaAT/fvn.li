<script lang="ts">
    import { Alert, Button, Dialog, Radio, Textarea } from '@/components/ui';
    import { submitReviewReport, type ReviewReportReason } from '@/api';
    import { getErrorMessage } from '@/utils/async-action.svelte';

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
            const responseMessage = await submitReviewReport(ratingId, reason as ReviewReportReason, details.trim() || null);
            message = { type: 'success', text: responseMessage };
            setTimeout(() => {
                onClose();
                message = null;
                reason = '';
                details = '';
            }, 2000);
        } catch (error) {
            message = { type: 'error', text: getErrorMessage(error, 'Failed to submit report') };
        } finally {
            isSubmitting = false;
        }
    }

    function closeDialog() {
        onClose();
    }
</script>

<Dialog open={isOpen} onClose={closeDialog} title="Report Review" size="sm">
    <p class="mb-4 text-sm text-fg-muted">
        Report the review by <strong>{reviewerName}</strong> for violating community guidelines.
    </p>

    <form onsubmit={handleSubmit}>
        <fieldset class="mb-4">
            <legend class="mb-2 block text-ui font-medium text-fg-muted">Reason *</legend>
            <div class="space-y-2">
                {#each REPORT_REASONS as r (r.value)}
                    <div>
                        <Radio
                            name="reason"
                            value={r.value}
                            label={r.label}
                            checked={reason === r.value}
                            onchange={(e) => (reason = (e.currentTarget as HTMLInputElement).value)}
                        />
                    </div>
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
            <Alert
                tone={message.type === 'success' ? 'success' : 'danger'}
                layout="inline"
                role={message.type === 'success' ? 'status' : 'alert'}
                class="mb-3"
            >
                {message.text}
            </Alert>
        {/if}

        <div class="flex items-center gap-2">
            <Button type="submit" disabled={!reason || isSubmitting} tone="danger">
                {isSubmitting ? 'Submitting...' : 'Submit Report'}
            </Button>
            <Button type="button" onclick={onClose} variant="soft" tone="neutral">Cancel</Button>
        </div>
    </form>
</Dialog>
