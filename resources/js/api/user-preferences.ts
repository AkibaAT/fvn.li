import http from '@/utils/http';

export interface NotificationPreferences {
    browser_notifications_enabled: boolean;
    discord_notifications_enabled: boolean;
    notification_digest: string;
}

export async function unignoreGame(gameId: number): Promise<void> {
    const { data } = await http.delete<{ success: boolean; message?: string }>(route('user.ignored-games.destroy'), {
        data: { game_id: gameId },
    });
    if (!data.success) throw new Error(data.message || 'Failed to remove game from ignore list');
}

export async function updateLanguagePreferences(preferredLanguages: string[]): Promise<void> {
    const { data } = await http.put<{ success: boolean; message?: string }>(route('user.language-preferences.update'), {
        preferred_languages: preferredLanguages,
    });
    if (!data.success) throw new Error(data.message || 'Failed to save language preferences');
}

export async function updateExcludedTags(excludedTags: number[]): Promise<void> {
    const { data } = await http.put<{ success: boolean; message?: string }>(route('user.excluded-tags.update'), {
        excluded_tags: excludedTags,
    });
    if (!data.success) throw new Error(data.message || 'Failed to save excluded tags');
}

export async function updateNotificationPreferences(preferences: NotificationPreferences): Promise<void> {
    const { data } = await http.post<{ success?: boolean; message?: string }>(route('browser-api.dashboard.notifications.update'), preferences);
    if (!data.success) throw new Error(data.message || 'Request failed');
}
