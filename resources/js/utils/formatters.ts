export const formatNumber = (num: number | undefined | null): string => {
    if (typeof num === 'number') {
        return new Intl.NumberFormat().format(num);
    }
    return '-'
};

export const formatDate = (dateString: string): string => {
    return new Date(dateString).toLocaleString();
};

export const timeDiff = (dateString: string): string => {
    const now = new Date();
    const date = new Date(dateString);
    const diff = Math.abs(now.getTime() - date.getTime());
    const seconds = Math.floor(diff / 1000);

    if (seconds < 60) return `${seconds} seconds ago`;
    if (seconds < 3600) return `${Math.floor(seconds / 60)} minutes ago`;
    if (seconds < 86400) return `${Math.floor(seconds / 3600)} hours ago`;
    return `${Math.floor(seconds / 86400)} days ago`;
};

export const calculateDurationSeconds = (
    start: string,
    end: string,
): number => {
    return Math.floor(
        (new Date(end).getTime() - new Date(start).getTime()) / 1000,
    );
};
