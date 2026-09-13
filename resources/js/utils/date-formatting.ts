type DateValue = string | null | undefined;

const dateOptions: Intl.DateTimeFormatOptions = { year: 'numeric', month: 'short', day: 'numeric' };

function parseTimestamp(value: DateValue): Date | null {
    if (!value?.trim()) return null;
    let timestamp = value.trim().replace(/^(\d{4}-\d{2}-\d{2}) /, '$1T');
    // Backend timestamps without an explicit offset are UTC.
    if (/^\d{4}-\d{2}-\d{2}T/.test(timestamp) && !/(Z|[+-]\d{2}:?\d{2})$/i.test(timestamp)) timestamp += 'Z';
    const date = new Date(timestamp);
    return Number.isNaN(date.getTime()) ? null : date;
}

export function formatCalendarDate(value: DateValue, options?: Intl.DateTimeFormatOptions): string | null {
    const day = value?.trim().slice(0, 10);
    if (!day || !/^\d{4}-\d{2}-\d{2}$/.test(day)) return null;
    const date = new Date(`${day}T00:00:00Z`);
    if (Number.isNaN(date.getTime()) || date.toISOString().slice(0, 10) !== day) return null;
    return date.toLocaleDateString(undefined, { ...dateOptions, ...options, timeZone: 'UTC' });
}

export function formatLocalDate(value: DateValue, options?: Intl.DateTimeFormatOptions): string | null {
    if (value && /^\d{4}-\d{2}-\d{2}$/.test(value.trim())) return formatCalendarDate(value, options);
    return parseTimestamp(value)?.toLocaleDateString(undefined, { ...dateOptions, ...options }) ?? null;
}

export function formatLocalDateTime(value: DateValue, options?: Intl.DateTimeFormatOptions): string | null {
    return (
        parseTimestamp(value)?.toLocaleString(undefined, {
            ...dateOptions,
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            ...options,
        }) ?? null
    );
}

export function formatDateTimeWithTimezone(value: DateValue, showTimezone: boolean = true): string | null {
    return formatLocalDateTime(value, { timeZoneName: showTimezone ? 'short' : undefined });
}

export function toLocalDateTimeInput(value: DateValue): string | null {
    const date = parseTimestamp(value);
    if (!date) return null;
    return new Date(date.getTime() - date.getTimezoneOffset() * 60_000).toISOString().slice(0, 16);
}

export function localDateTimeToUtc(value: DateValue): string | null {
    return value ? new Date(value).toISOString() : null;
}

export function formatRelativeDateTime(dateString: string | null | undefined): { timeAgo: string; formattedDate: string } | null {
    const date = parseTimestamp(dateString);
    if (!date) return null;

    const now = new Date();
    const diffInMs = now.getTime() - date.getTime();
    const diffInHours = Math.floor(diffInMs / (1000 * 60 * 60));
    const diffInDays = Math.floor(diffInHours / 24);

    const formattedDate = formatDateTimeWithTimezone(dateString) || '';
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

export function formatFutureDateTime(dateString: string | null | undefined): { timeUntil: string; formattedDate: string } | null {
    const date = parseTimestamp(dateString);
    if (!date) return null;

    const now = new Date();
    const diffInMs = date.getTime() - now.getTime();
    const absMs = Math.abs(diffInMs);
    const absHours = Math.floor(absMs / (1000 * 60 * 60));
    const absDays = Math.floor(absHours / 24);

    const formattedDate = formatDateTimeWithTimezone(dateString) || '';
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
