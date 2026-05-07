const MARK_OPEN_TOKEN = '\u0000FVN_MARK_OPEN\u0000';
const MARK_CLOSE_TOKEN = '\u0000FVN_MARK_CLOSE\u0000';

export function escapeHtml(value: string): string {
    return value.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

export function highlightPlainText(text: string, query: string): string {
    const escapedText = escapeHtml(text);
    const escapedQuery = escapeHtml(query);

    if (!escapedQuery) {
        return escapedText;
    }

    return escapedText.replace(new RegExp(`(${escapeRegExp(escapedQuery)})`, 'gi'), '<mark>$1</mark>');
}

export function renderTrustedMarksOnly(html: string): string {
    return escapeHtml(html.replaceAll('<mark>', MARK_OPEN_TOKEN).replaceAll('</mark>', MARK_CLOSE_TOKEN))
        .replaceAll(MARK_OPEN_TOKEN, '<mark>')
        .replaceAll(MARK_CLOSE_TOKEN, '</mark>');
}
