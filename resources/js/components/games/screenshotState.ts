export interface Screenshot {
    url: string;
    thumbnail_url?: string;
    original_url?: string;
}

export function normalizeScreenshots(value: unknown): Screenshot[] | null {
    if (Array.isArray(value)) {
        return value as Screenshot[];
    }

    if (value && typeof value === 'object') {
        return Object.values(value) as Screenshot[];
    }

    return null;
}

export function resolveUploadedScreenshots(
    currentScreenshots: Screenshot[],
    responseScreenshotsValue: unknown,
    responseNewScreenshotsValue: unknown,
): Screenshot[] {
    const responseScreenshots = normalizeScreenshots(responseScreenshotsValue);
    const responseNewScreenshots = normalizeScreenshots(responseNewScreenshotsValue);

    if (responseScreenshots && responseScreenshots.length >= currentScreenshots.length) {
        return responseScreenshots;
    }

    if (responseNewScreenshots) {
        return [...currentScreenshots, ...responseNewScreenshots];
    }

    return currentScreenshots;
}

export function resolveDeletedScreenshots(currentScreenshots: Screenshot[], index: number, responseScreenshotsValue: unknown): Screenshot[] {
    const expectedCount = Math.max(0, currentScreenshots.length - 1);
    const responseScreenshots = normalizeScreenshots(responseScreenshotsValue);

    if (responseScreenshots && responseScreenshots.length === expectedCount) {
        return responseScreenshots;
    }

    return currentScreenshots.filter((_, screenshotIndex) => screenshotIndex !== index);
}
