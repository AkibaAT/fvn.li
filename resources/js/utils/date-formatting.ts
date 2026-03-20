/**
 * Date and time formatting utilities
 *
 * All dates from the backend are in UTC. These utilities help format them
 * in the user's local timezone with proper timezone indication.
 */

/**
 * Format a UTC date string to the user's local timezone
 *
 * @param dateString - ISO 8601 date string in UTC
 * @param options - Intl.DateTimeFormatOptions for customization
 * @returns Formatted date string in user's local timezone
 */
export function formatLocalDateTime(dateString: string | null | undefined, options?: Intl.DateTimeFormatOptions): string | null {
    if (!dateString) return null;

    // Parse the date string - if it doesn't end with 'Z', assume it's UTC and add it
    let dateStr = dateString.trim();

    // Handle SQL datetime format (YYYY-MM-DD HH:MM:SS) - convert space to T
    if (dateStr.match(/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}/)) {
        dateStr = dateStr.replace(' ', 'T') + 'Z';
    } else if (!dateStr.endsWith('Z') && !dateStr.includes('+') && !dateStr.includes('T')) {
        // If it's just a date without time, treat as UTC midnight
        dateStr = dateStr + 'T00:00:00Z';
    } else if (!dateStr.endsWith('Z') && !dateStr.includes('+') && dateStr.includes('T')) {
        // If it has time but no timezone, assume UTC
        dateStr = dateStr + 'Z';
    }

    const date = new Date(dateStr);

    // Check if date is valid
    if (isNaN(date.getTime())) return null;

    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false,
        ...options,
    };

    return date.toLocaleString(undefined, defaultOptions);
}

/**
 * Format a UTC date string to the user's local date only (no time)
 *
 * @param dateString - ISO 8601 date string in UTC
 * @param options - Intl.DateTimeFormatOptions for customization
 * @returns Formatted date string in user's local timezone
 */
export function formatLocalDate(dateString: string | null | undefined, options?: Intl.DateTimeFormatOptions): string | null {
    if (!dateString) return null;

    const date = new Date(dateString);

    // Check if date is valid
    if (isNaN(date.getTime())) return null;

    const defaultOptions: Intl.DateTimeFormatOptions = {
        year: 'numeric',
        month: 'short',
        day: 'numeric',
        ...options,
    };

    return date.toLocaleDateString('en-US', defaultOptions);
}

/**
 * Get the user's timezone abbreviation (e.g., "PST", "EST", "UTC")
 *
 * @returns Timezone abbreviation or offset
 */
export function getUserTimezone(): string {
    try {
        // Get the timezone name
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        // Try to get the short timezone abbreviation
        const date = new Date();
        const shortFormat = date.toLocaleTimeString('en-US', {
            timeZoneName: 'short',
            timeZone: timezone,
        });

        // Extract the timezone abbreviation (e.g., "PST", "EST")
        const match = shortFormat.match(/\b([A-Z]{2,5})\b$/);
        if (match) {
            return match[1];
        }

        // Fallback to timezone name
        return timezone;
    } catch {
        // Fallback to UTC offset
        const offset = -new Date().getTimezoneOffset();
        const hours = Math.floor(Math.abs(offset) / 60);
        const minutes = Math.abs(offset) % 60;
        const sign = offset >= 0 ? '+' : '-';
        return `UTC${sign}${hours.toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}`;
    }
}

/**
 * Format a date with relative time (e.g., "2 hours ago") and absolute time
 *
 * @param dateString - ISO 8601 date string in UTC
 * @returns Object with relative time and formatted date, or null if invalid
 */
export function formatRelativeDateTime(dateString: string | null | undefined): { timeAgo: string; formattedDate: string } | null {
    if (!dateString) return null;

    const date = new Date(dateString);

    // Check if date is valid
    if (isNaN(date.getTime())) return null;

    const now = new Date();
    const diffInMs = now.getTime() - date.getTime();
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
    const diffInDays = Math.floor(diffInHours / 24);

    const formattedDate = formatLocalDateTime(dateString) || '';
    let timeAgo: string;

    if (diffInDays > 0) {
        timeAgo = `${diffInDays} day${diffInDays > 1 ? 's' : ''} ago`;
    } else if (diffInHours > 0) {
        timeAgo = `${diffInHours} hour${diffInHours > 1 ? 's' : ''} ago`;
    } else {
        const diffInMinutes = Math.floor(diffInMs / (1000 * 60));
        if (diffInMinutes > 0) {
            timeAgo = `${diffInMinutes} minute${diffInMinutes > 1 ? 's' : ''} ago`;
        } else {
            timeAgo = 'Just now';
        }
    }

    return { timeAgo, formattedDate };
}

/**
 * Format a date for future events (e.g., "in 2 hours", "in 3 days")
 *
 * @param dateString - ISO 8601 date string in UTC
 * @returns Object with relative time and formatted date, or null if invalid
 */
export function formatFutureDateTime(dateString: string | null | undefined): { timeUntil: string; formattedDate: string } | null {
    if (!dateString) return null;

    const date = new Date(dateString);

    // Check if date is valid
    if (isNaN(date.getTime())) return null;

    const now = new Date();
    const diffInMs = date.getTime() - now.getTime();
    const absMs = Math.abs(diffInMs);
    const absHours = Math.floor(absMs / (1000 * 60 * 60));
    const absDays = Math.floor(absHours / 24);

    const formattedDate = formatLocalDateTime(dateString) || '';
    const isFuture = diffInMs > 0;
    let timeUntil: string;

    if (absDays > 0) {
        timeUntil = `${isFuture ? 'in ' : ''}${absDays} day${absDays > 1 ? 's' : ''}${isFuture ? '' : ' ago'}`;
    } else if (absHours > 0) {
        timeUntil = `${isFuture ? 'in ' : ''}${absHours} hour${absHours > 1 ? 's' : ''}${isFuture ? '' : ' ago'}`;
    } else {
        const absMinutes = Math.max(1, Math.floor(absMs / (1000 * 60)));
        timeUntil = `${isFuture ? 'in ' : ''}${absMinutes} minute${absMinutes > 1 ? 's' : ''}${isFuture ? '' : ' ago'}`;
    }

    return { timeUntil, formattedDate };
}

/**
 * Format a date for display with timezone indicator
 *
 * @param dateString - ISO 8601 date string in UTC
 * @param showTimezone - Whether to show timezone abbreviation (default: true)
 * @returns Formatted date string with timezone, or null if invalid
 */
export function formatDateTimeWithTimezone(dateString: string | null | undefined, showTimezone: boolean = true): string | null {
    const formatted = formatLocalDateTime(dateString);
    if (!formatted) return null;

    if (showTimezone) {
        const timezone = getUserTimezone();
        return `${formatted} ${timezone}`;
    }

    return formatted;
}
