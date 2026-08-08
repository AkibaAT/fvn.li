export interface SyncTinyMceAssetsOptions {
    sourceDir?: string;
    destinationDir?: string;
}

export interface SyncTinyMceAssetsResult {
    copied: boolean;
    version: string;
}

export function syncTinyMceAssets(options?: SyncTinyMceAssetsOptions): Promise<SyncTinyMceAssetsResult>;
