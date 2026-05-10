export function escapeStyleElementText(css: string): string {
    return css.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}
