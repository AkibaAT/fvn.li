<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\BugReport;
use App\Models\BugReportComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BugReportAdminReplyNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        public BugReport $bugReport,
        public BugReportComment $comment
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'bug_report_reply',
            'message' => 'Staff replied to your bug report #' . $this->bugReport->id,
            'bug_report_id' => $this->bugReport->id,
            'comment_id' => $this->comment->id,
            'status' => $this->bugReport->status,
            'status_label' => $this->bugReport->status_label,
        ];
    }
}
