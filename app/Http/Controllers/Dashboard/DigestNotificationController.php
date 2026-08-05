<?php

declare(strict_types=1);

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\NotificationHistory;
use App\Support\Seo\MetaTags;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class DigestNotificationController extends Controller
{
    public function showDigestNotifications(string $date)
    {
        try {
            $carbonDate = Carbon::createFromFormat('Y-m-d', $date);
        } catch (Exception $e) {
            abort(404, 'Invalid date format. Please use YYYY-MM-DD format.');
        }

        $authId = Auth::id();
        if (! $authId) {
            abort(401, 'Unauthenticated');
        }

        $notifications = NotificationHistory::where('user_id', $authId)
            ->whereDate('created_at', $carbonDate)
            ->orderBy('created_at', 'desc')
            ->get();

        if ($notifications->isEmpty()) {
            $hasAnyNotifications = NotificationHistory::whereDate('created_at', $carbonDate)->exists();

            return Inertia::render('dashboard/digest-notifications', [
                'date' => $date,
                'formattedDate' => $carbonDate->format('F j, Y'),
                'notifications' => [],
                'hasNotifications' => false,
                'hasAnyNotifications' => $hasAnyNotifications,
                'metaTags' => new MetaTags(
                    title: "Notification Digest - {$carbonDate->format('F j, Y')}",
                    description: $notifications->isNotEmpty()
                        ? "View your notification digest for {$carbonDate->format('F j, Y')}. ".
                          "Contains {$notifications->count()} notifications about your tracked visual novels."
                        : "No notifications found for {$carbonDate->format('F j, Y')}.",
                    structuredData: [
                        '@type' => 'WebPage',
                        'name' => "Notification Digest - {$carbonDate->format('F j, Y')}",
                        'description' => $notifications->isNotEmpty()
                            ? "Your notification digest for {$carbonDate->format('F j, Y')}"
                            : "No notifications for {$carbonDate->format('F j, Y')}",
                        'url' => route('user.notifications.digest', $date),
                    ],
                )->toArray(),
            ]);
        }

        return Inertia::render('dashboard/digest-notifications', [
            'date' => $date,
            'formattedDate' => $carbonDate->format('F j, Y'),
            'notifications' => $notifications,
            'hasNotifications' => true,
            'hasAnyNotifications' => true,
            'metaTags' => new MetaTags(
                title: "Notification Digest - {$carbonDate->format('F j, Y')}",
            )->toArray(),
        ]);
    }
}
