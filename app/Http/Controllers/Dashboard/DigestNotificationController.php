<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NotificationHistory;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DigestNotificationController extends Controller
{
    public function showDigestNotifications(string $date)
    {
        // Validate the date format
        try {
            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (Exception $e) {
            abort(404, 'Invalid date format. Please use YYYY-MM-DD format.');
        }

        // Get notifications for the specified date
        $authId = Auth::id();
        if (! $authId) {
            abort(401, 'Unauthenticated');
        }

        $notifications = NotificationHistory::where('user_id', $authId)
            ->whereDate('created_at', $carbonDate)
            ->orderBy('created_at', 'desc')
            ->get();

        // Check if user has any notifications for this date
        if ($notifications->isEmpty()) {
            // Check if there are any notifications for this date at all
            $hasAnyNotifications = NotificationHistory::whereDate('created_at', $carbonDate)->exists();

            return Inertia::render('dashboard/digest-notifications', [
                'date' => $date,
                'formattedDate' => $carbonDate->format('F j, Y'),
                'notifications' => [],
                'hasNotifications' => false,
                'hasAnyNotifications' => $hasAnyNotifications,
                'metaTags' => [
                    'title' => "Notification Digest - {$carbonDate->format('F j, Y')}",
                    'description' => $notifications->isNotEmpty()
                        ? "View your notification digest for {$carbonDate->format('F j, Y')}. ".
                          "Contains {$notifications->count()} notifications about your tracked visual novels."
                        : "No notifications found for {$carbonDate->format('F j, Y')}.",
                    'structuredData' => [
                        '@type' => 'WebPage',
                        'name' => "Notification Digest - {$carbonDate->format('F j, Y')}",
                        'description' => $notifications->isNotEmpty()
                            ? "Your notification digest for {$carbonDate->format('F j, Y')}"
                            : "No notifications for {$carbonDate->format('F j, Y')}",
                        'url' => route('user.notifications.digest', $date),
                    ],
                ],
            ]);
        }

        return Inertia::render('dashboard/digest-notifications', [
            'date' => $date,
            'formattedDate' => $carbonDate->format('F j, Y'),
            'notifications' => $notifications,
            'hasNotifications' => true,
            'hasAnyNotifications' => true,
            'metaTags' => [
                'title' => "Notification Digest - {$carbonDate->format('F j, Y')}",
            ],
        ]);
    }
}
