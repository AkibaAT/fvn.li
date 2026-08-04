<script lang="ts">
    import { notify } from '@/components/Toast.svelte';
    import { Badge, Button, Dialog, Textarea } from '@/components/ui';
    import type { BadgeTone } from '@/components/ui/Badge.svelte';
    import { authenticatedFetch } from '@/utils/http';
    import { untrack } from 'svelte';

    interface BugReport {
        id: number;
        page_title?: string;
        description: string;
        status: string;
        status_label: string;
        status_color: string;
        unread_count: number;
        created_at: string;
    }

    interface BugReportComment {
        id: number;
        message: string;
        is_from_admin: boolean;
        user: {
            id: number;
            name: string;
        };
        created_at: string;
    }

    interface BugReportDetail {
        id: number;
        page_url: string;
        page_title?: string;
        description: string;
        status: string;
        status_label: string;
        status_color: string;
        is_closed: boolean;
        created_at: string;
        resolved_at?: string;
    }

    interface Props {
        initialReports: BugReport[];
        openReportId?: number | null;
    }

    let { initialReports, openReportId = null }: Props = $props();

    let bugReports = $state<BugReport[]>(untrack(() => initialReports || []));
    let selectedBugReport = $state<BugReportDetail | null>(null);
    let bugReportComments = $state<BugReportComment[]>([]);
    let loadingBugReport = $state(false);
    let newComment = $state('');
    let submittingComment = $state(false);
    let closingTicket = $state(false);
    let bugReportModalOpen = $state(false);

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
        loadingBugReport = true;
        bugReportModalOpen = true;
        try {
            const response = await fetch(route('browser-api.bug-reports.show', { bugReport: reportId }), {
                credentials: 'same-origin',
            });
            const data = await response.json();
            if (data.success) {
                selectedBugReport = data.report;
                bugReportComments = data.comments;
                bugReports = bugReports.map((r) => (r.id === reportId ? { ...r, unread_count: 0 } : r));
            } else {
                notify(data.message || 'Failed to load bug report', 'error');
                bugReportModalOpen = false;
            }
        } catch {
            notify('Failed to load bug report', 'error');
            bugReportModalOpen = false;
        } finally {
            loadingBugReport = false;
        }
    }

    function closeBugReportModal() {
        bugReportModalOpen = false;
        selectedBugReport = null;
        bugReportComments = [];
        newComment = '';
    }

    async function submitBugReportComment() {
        if (!selectedBugReport || !newComment.trim()) return;

        submittingComment = true;
        try {
            const response = await authenticatedFetch(route('browser-api.bug-reports.comments.store', { bugReport: selectedBugReport.id }), {
                method: 'POST',
                body: JSON.stringify({ message: newComment.trim() }),
            });
            const data = await response.json();
            if (data.success) {
                bugReportComments = [...bugReportComments, data.comment];
                newComment = '';
                notify('Comment added', 'success');
            } else {
                notify(data.message || 'Failed to add comment', 'error');
            }
        } catch {
            notify('Failed to add comment', 'error');
        } finally {
            submittingComment = false;
        }
    }

    async function closeTicket() {
        if (!selectedBugReport) return;

        closingTicket = true;
        try {
            const response = await authenticatedFetch(route('browser-api.bug-reports.close', { bugReport: selectedBugReport.id }), {
                method: 'POST',
            });
            const data = await response.json();
            if (data.success) {
                bugReports = bugReports.filter((r) => r.id !== selectedBugReport!.id);
                closeBugReportModal();
                notify('Ticket closed', 'success');
            } else {
                notify(data.message || 'Failed to close ticket', 'error');
            }
        } catch {
            notify('Failed to close ticket', 'error');
        } finally {
            closingTicket = false;
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
    <div class="rounded-lg border-2 border-amber-300 bg-amber-50 shadow-sm dark:border-amber-700 dark:bg-amber-900/20">
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-semibold text-amber-800 dark:text-amber-300">
                    Your Bug Reports
                    {#if totalUnread > 0}
                        <Badge tone="danger" variant="solid" size="sm" class="ml-2">
                            {totalUnread} new
                        </Badge>
                    {/if}
                </h2>
                <span class="text-sm text-amber-700 dark:text-amber-300">
                    {bugReports.length} active
                </span>
            </div>

            <div class="space-y-3">
                {#each bugReports as report (report.id)}
                    <Button
                        type="button"
                        variant="outline"
                        tone="warning"
                        class="w-full justify-start border-amber-200 bg-white p-4 text-left hover:border-amber-400 dark:border-amber-800 dark:bg-gray-800 dark:hover:border-amber-600"
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
                                <p class="line-clamp-2 text-sm text-gray-700 dark:text-gray-300">
                                    {report.description}
                                </p>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                    Reported {new Date(report.created_at).toLocaleDateString()}
                                </p>
                            </div>
                            <svg class="ml-2 h-5 w-5 flex-shrink-0 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                            </svg>
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
            <svg class="h-8 w-8 animate-spin text-blue-500" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
        </div>
    {:else if selectedBugReport}
        <div class="mb-6 rounded-lg border border-gray-200 bg-gray-50 p-4 dark:border-gray-700 dark:bg-gray-900/50">
            <div class="mb-3 flex items-center gap-2">
                <Badge tone={getStatusBadgeTone(selectedBugReport.status_color)} size="sm">
                    {selectedBugReport.status_label}
                </Badge>
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    Submitted {new Date(selectedBugReport.created_at).toLocaleDateString()}
                </span>
            </div>

            <p class="mb-3 text-sm text-gray-700 dark:text-gray-300">
                {selectedBugReport.description}
            </p>

            <div class="text-xs text-gray-500 dark:text-gray-400">
                <strong>Page:</strong>
                <a href={selectedBugReport.page_url} target="_blank" rel="noopener" class="text-blue-600 hover:underline dark:text-blue-400">
                    {selectedBugReport.page_title || selectedBugReport.page_url}
                </a>
            </div>
        </div>

        <div class="mb-6">
            <h4 class="mb-3 font-medium text-gray-900 dark:text-white">
                Conversation ({bugReportComments.length})
            </h4>

            {#if bugReportComments.length === 0}
                <p class="text-sm text-gray-500 dark:text-gray-400">No comments yet. Add additional information below.</p>
            {:else}
                <div class="space-y-3">
                    {#each bugReportComments as comment (comment.id)}
                        <div
                            class="rounded-lg p-3 {comment.is_from_admin
                                ? 'border-l-4 border-blue-500 bg-blue-50 dark:bg-blue-900/20'
                                : 'border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-800'}"
                        >
                            <div class="mb-1 flex items-center gap-2">
                                <span
                                    class="text-sm font-medium {comment.is_from_admin
                                        ? 'text-blue-700 dark:text-blue-400'
                                        : 'text-gray-900 dark:text-white'}"
                                >
                                    {comment.user.name}
                                </span>
                                {#if comment.is_from_admin}
                                    <Badge tone="primary" size="sm">Staff</Badge>
                                {/if}
                                <span
                                    class="text-xs {comment.is_from_admin ? 'text-gray-600 dark:text-gray-300' : 'text-gray-500 dark:text-gray-400'}"
                                >
                                    {new Date(comment.created_at).toLocaleDateString()} at {new Date(comment.created_at).toLocaleTimeString([], {
                                        hour: '2-digit',
                                        minute: '2-digit',
                                    })}
                                </span>
                            </div>
                            <p class="text-sm whitespace-pre-wrap text-gray-700 dark:text-gray-300">
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
            <div
                class="rounded-lg border border-gray-200 bg-gray-50 p-4 text-center text-sm text-gray-500 dark:border-gray-700 dark:bg-gray-900/50 dark:text-gray-400"
            >
                You have closed this report.
            </div>
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
