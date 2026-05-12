function cleanName(name: string | null | undefined): string {
    const cleaned = name?.trim();

    return cleaned && cleaned.length > 0 ? cleaned : 'this visual novel';
}

export function gameCoverAltText(gameName: string | null | undefined): string {
    return `${cleanName(gameName)} cover image`;
}

export function gameScreenshotAltText(gameName: string | null | undefined, index: number, total?: number): string {
    const position = index + 1;
    const suffix = total && total > 1 ? ` of ${total}` : '';

    return `${cleanName(gameName)} screenshot ${position}${suffix}`;
}

export function gameScreenshotThumbnailAltText(gameName: string | null | undefined, index: number, total?: number): string {
    const position = index + 1;
    const suffix = total && total > 1 ? ` of ${total}` : '';

    return `Thumbnail for ${cleanName(gameName)} screenshot ${position}${suffix}`;
}
