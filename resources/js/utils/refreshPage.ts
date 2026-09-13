import { router } from '@inertiajs/svelte';

export function refreshPage(only?: string[]): Promise<boolean> {
    return new Promise((resolve, reject) =>
        router.reload({
            ...(only ? { only } : {}),
            onSuccess: () => resolve(true),
            onCancel: () => resolve(false),
            onFinish: () => reject(new Error('The page could not be refreshed. Reload it to see the latest data.')),
        }),
    );
}
