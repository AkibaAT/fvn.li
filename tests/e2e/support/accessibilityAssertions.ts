import { expect, type Page } from '@playwright/test';

export async function assertStructuredHeadings(page: Page, reportName: string) {
    const headings = await page.locator('h1, h2, h3, h4, h5, h6').evaluateAll((elements) =>
        elements
            .filter((element) => {
                const style = window.getComputedStyle(element);
                const rect = element.getBoundingClientRect();

                return (
                    element.getAttribute('aria-hidden') !== 'true' &&
                    style.display !== 'none' &&
                    style.visibility !== 'hidden' &&
                    rect.width > 0 &&
                    rect.height > 0
                );
            })
            .map((element) => ({
                level: Number(element.tagName.substring(1)),
                text: element.textContent?.trim().replace(/\s+/g, ' ') ?? '',
            })),
    );

    expect(headings.length, `${reportName} should have at least one visible heading`).toBeGreaterThan(0);

    const h1s = headings.filter((heading) => heading.level === 1);
    expect(
        h1s,
        `${reportName} should have exactly one visible h1. Found: ${headings.map((heading) => `h${heading.level} "${heading.text}"`).join(', ')}`,
    ).toHaveLength(1);

    for (let i = 1; i < headings.length; i++) {
        const previous = headings[i - 1];
        const current = headings[i];

        expect(
            current.level - previous.level,
            `${reportName} skips heading levels from h${previous.level} "${previous.text}" to h${current.level} "${current.text}"`,
        ).toBeLessThanOrEqual(1);
    }
}

export async function assertVisibleImagesHaveAltText(page: Page, reportName: string) {
    const imagesWithoutMeaningfulAlt = await page.locator('img').evaluateAll((elements) =>
        elements
            .filter((element) => {
                const style = window.getComputedStyle(element);
                const rect = element.getBoundingClientRect();
                const alt = element.getAttribute('alt');

                return (
                    element.getAttribute('aria-hidden') !== 'true' &&
                    element.getAttribute('role') !== 'presentation' &&
                    style.display !== 'none' &&
                    style.visibility !== 'hidden' &&
                    rect.width > 0 &&
                    rect.height > 0 &&
                    (!element.hasAttribute('alt') || !alt?.trim())
                );
            })
            .map((element) => element.outerHTML.substring(0, 160)),
    );

    expect(imagesWithoutMeaningfulAlt, `${reportName} has visible images without meaningful alt text`).toHaveLength(0);
}
