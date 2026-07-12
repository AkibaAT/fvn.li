export function escapeStyleElementText(css: string): string {
    // <style> content is RAWTEXT: the HTML parser never decodes entities, so
    // entity-escaping would corrupt valid CSS (e.g. `.a > .b` selectors). The
    // only dangerous sequence is `</` (it can terminate the element via
    // `</style`), and `<\/` decodes back to `</` inside CSS strings.
    return css.replace(/<\//g, '<\\/');
}
