export function formatBytes(bytes: number): string {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
}

// Zero renders as a dash so unchanged cells stay visually quiet in the comparison tables.
export function formatCount(num: number): string {
    return num === 0 ? '-' : num.toLocaleString();
}

export function getDiffColor(diff: number): string {
    if (diff > 0) return 'text-green-400';
    if (diff < 0) return 'text-red-400';
    return 'text-gray-400';
}

export function formatDiff(diff: number): string {
    if (diff === 0) return '-';
    return (diff > 0 ? '+' : '') + formatCount(diff);
}

export function formatBytesDiff(diff: number): string {
    if (diff === 0) return '-';
    return (diff > 0 ? '+' : '') + formatBytes(Math.abs(diff));
}
