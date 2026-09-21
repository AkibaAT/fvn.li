<script lang="ts">
    import { formatLocalDate, formatLocalDateTime } from '@/utils/date-formatting';
    import ChevronRightIcon from '@/components/icons/ChevronRight.svelte';
    import { addBugReportComment, closeBugReport, fetchBugReport, type BugReportComment, type BugReportDetail, type BugReportSummary } from '@/api';
    import { notify } from '@/components/Toast.svelte';
    import LoadingSpinner from '@/components/LoadingSpinner.svelte';
    import { Badge, Button, Dialog, Textarea } from '@/components/ui';
    import type { BadgeTone } from '@/components/ui/Badge.svelte';
    import { untrack } from 'svelte';

    interface Props {
        initialReports: BugReportSummary[];
        openReportId?: number | null;
    }

    let { initialReports, openReportId = null }: Props = $props();

    let bugReports = $state<BugReportSummary[]>(untrack(() => initialReports || []));
    let selectedBugReport = $state<BugReportDetail | null>(null);
    let bugReportComments = $state<BugReportComment[]>([]);
    let loadingBugReport = $state(false);
    let newComment = $state('');
    let submittingComment = $state(false);
    let closingTicket = $state(false);
    let bugReportModalOpen = $state(false);
    let reportGeneration = 0;

    function getStatusBadgeTone(color: string): BadgeTone {
        switch (color) {
            case 'warning':
                return 'warning';
            case 'info':
                return 'primary';
            case 'success':
                return 'success';
            case 'danger':
                return 'danger';
            default:
                return 'neutral';
        }
    }

    async function openBugReport(reportId: number) {
        closeBugReportModal();
        const generation = reportGeneration;
        loadingBugReport = true;
        bugReportModalOpen = true;
        try {
            const data = await fetchBugReport(reportId);
            if (generation !== reportGeneration) return;
            selectedBugReport = data.report;
            bugReportComments = data.comments;
            bugReports = bugReports.map((r) => (r.id === reportId ? { ...r, unread_count: 0 } : r));
        } catch (error) {
            if (generation !== reportGeneration) return;
            notify(error instanceof Error ? error.message : 'Failed to load bug report', 'error');
            bugReportModalOpen = false;
        } finally {
            if (generation === reportGeneration) loadingBugReport = false;
        }
    }

    function closeBugReportModal() {
        reportGeneration++;
        submittingComment = false;
        closingTicket = false;
        bugReportModalOpen = false;
        selectedBugReport = null;
        bugReportComments = [];
        newComment = '';
    }

    async function submitBugReportComment() {
        if (!selectedBugReport || !newComment.trim() || submittingComment) return;
        const reportId = selectedBugReport.id;
        const generation = reportGeneration;
        const draft = newComment;

        submittingComment = true;
        try {
            const comment = await addBugReportComment(reportId, draft.trim());
            if (generation !== reportGeneration) return;
            bugReportComments = [...bugReportComments, comment];
            if (newComment === draft) newComment = '';
            notify('Comment added', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Failed to add comment', 'error');
        } finally {
            if (generation === reportGeneration) submittingComment = false;
        }
    }

    async function closeTicket() {
        if (!selectedBugReport || closingTicket) return;
        const reportId = selectedBugReport.id;
        const generation = reportGeneration;

        closingTicket = true;
        try {
            await closeBugReport(reportId);
            bugReports = bugReports.filter((r) => r.id !== reportId);
            if (generation === reportGeneration) closeBugReportModal();
            notify('Ticket closed', 'success');
        } catch (error) {
            notify(error instanceof Error ? error.message : 'Failed to close ticket', 'error');
        } finally {
            if (generation === reportGeneration) closingTicket = false;
        }
    }

    $effect(() => {
        if (openReportId) {
            openBugReport(openReportId);
        }
    });

    const totalUnread = $derived(bugReports.reduce((sum, r) => sum + r.unread_count, 0));
</script>

{#if bugReports.length > 0}
    <div class="rounded-lg border border-amber-600/40 bg-amber-50 dark:border-amber-800/60 dark:bg-amber-950/30">
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-amber-900 dark:text-amber-200">
                    Your Bug Reports
                    {#if totalUnread > 0}
                        <Badge tone="danger" variant="solid" size="sm" class="ml-2">
                            {totalUnread} new
                        </Badge>
                    {/if}
                </h2>
                <span class="text-sm text-amber-800 dark:text-amber-300">
                    {bugReports.length} active
                </span>
            </div>

            <div class="space-y-3">
                {#each bugReports as report (report.id)}
                    <Button
                        type="button"
                        variant="outline"
                        tone="warning"
                        class="w-full justify-start p-4 text-left"
                        onclick={() => openBugReport(report.id)}
                    >
                        <div class="flex items-start justify-between">
                            <div class="min-w-0 flex-1">
                                <div class="mb-2 flex items-center gap-2">
                                    <Badge tone={getStatusBadgeTone(report.status_color)} size="sm">
                                        {report.status_label}
                                    </Badge>
                                    {#if report.unread_count > 0}
                                        <Badge tone="danger" size="sm">
                                            {report.unread_count} new {report.unread_count === 1 ? 'reply' : 'replies'}
                                        </Badge>
                                    {/if}
                                </div>
                                <p class="line-clamp-2 text-sm text-fg-muted">
                                    {report.description}
                                </p>
                                <p class="mt-1 text-xs text-fg-faint">
                                    Reported {formatLocalDate(report.created_at)}
                                </p>
                            </div>
                            <ChevronRightIcon class="ml-2 h-5 w-5 flex-shrink-0 text-fg-faint" />
                        </div>
                    </Button>
                {/each}
            </div>
        </div>
    </div>
{/if}

<Dialog
    open={bugReportModalOpen}
    onClose={closeBugReportModal}
    title={`Bug Report #${selectedBugReport?.id ?? ''}`}
    size="lg"
    bodyClass="max-h-[calc(90vh-180px)] p-6"
>
    {#if loadingBugReport}
        <div class="flex items-center justify-center py-8">
            <LoadingSpinner size="lg" class="text-fg-muted" currentColor label="Loading bug report" />
        </div>
    {:else if selectedBugReport}
        <div class="mb-6 rounded-lg border border-border bg-surface-alt p-4">
            <div class="mb-3 flex items-center gap-2">
                <Badge tone={getStatusBadgeTone(selectedBugReport.status_color)} size="sm">
                    {selectedBugReport.status_label}
                </Badge>
                <!-- `--text-faint` only clears AA on `--surface`, not on `--surface-alt`. -->
                <span class="text-xs text-fg-muted">
                    Submitted {formatLocalDate(selectedBugReport.created_at)}
                </span>
            </div>

            <p class="mb-3 text-sm text-fg-muted">
                {selectedBugReport.description}
            </p>

            <div class="text-xs text-fg-muted">
                <strong>Page:</strong>
                <a href={selectedBugReport.page_url} target="_blank" rel="noopener" class="text-fg-muted hover:text-fg hover:underline">
                    {selectedBugReport.page_title || selectedBugReport.page_url}
                </a>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="mb-3 font-medium text-fg">
                Conversation ({bugReportComments.length})
            </h4>

            {#if bugReportComments.length === 0}
                <p class="text-sm text-fg-muted">No comments yet. Add additional information below.</p>
            {:else}
                <div class="space-y-3">
                    {#each bugReportComments as comment (comment.id)}
                        <div
                            class="rounded-lg p-3 {comment.is_from_admin ? 'border-l-4 border-fg bg-surface-alt' : 'border border-border bg-surface'}"
                        >
                            <div class="mb-1 flex items-center gap-2">
                                <span class="text-sm font-medium text-fg">
                                    {comment.user.name}
                                </span>
                                {#if comment.is_from_admin}
                                    <Badge tone="primary" size="sm">Staff</Badge>
                                {/if}
                                <span class="text-xs {comment.is_from_admin ? 'text-fg-muted' : 'text-fg-faint'}">
                                    {formatLocalDateTime(comment.created_at)}
                                </span>
                            </div>
                            <p class="text-sm whitespace-pre-wrap text-fg-muted">
                                {comment.message}
                            </p>
                        </div>
                    {/each}
                </div>
            {/if}
        </div>

        {#if !selectedBugReport.is_closed}
            <div>
                <Textarea
                    id="new-comment"
                    label="Add Information"
                    bind:value={newComment}
                    rows={3}
                    placeholder="Provide additional details or respond to staff..."
                />
                <div class="mt-2 flex justify-end">
                    <Button
                        type="button"
                        onclick={submitBugReportComment}
                        disabled={submittingComment || newComment.trim().length < 5}
                        loading={submittingComment}
                    >
                        {submittingComment ? 'Sending...' : 'Send'}
                    </Button>
                </div>
            </div>
        {:else}
            <div class="rounded-lg border border-border bg-surface-alt p-4 text-center text-sm text-fg-muted">You have closed this report.</div>
        {/if}
    {/if}
    {#snippet footer()}
        <div class="flex w-full gap-3">
            {#if selectedBugReport && !selectedBugReport.is_closed}
                <Button
                    type="button"
                    onclick={closeTicket}
                    disabled={closingTicket}
                    variant="soft"
                    tone="danger"
                    loading={closingTicket}
                    class="flex-1"
                >
                    {closingTicket ? 'Closing...' : 'Close Ticket'}
                </Button>
            {/if}
            <Button type="button" onclick={closeBugReportModal} variant="soft" tone="neutral" class="flex-1">
                {selectedBugReport?.is_closed ? 'Close' : 'Keep Open'}
            </Button>
        </div>
    {/snippet}
</Dialog>
