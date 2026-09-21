import { toast } from './toast';

/** Uniform error extraction: real Error message when present, fallback otherwise. */
export function getErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
    return error instanceof Error && error.message ? error.message : fallback;
}

/**
 * The canonical try/catch/finally wrapper for one-off async actions: sets a
 * loading flag, toasts a success message when given, and surfaces failures via
 * the shared toast store. `onError` replaces the default error toast for sites
 * that display failures inline instead. Returns the action's result, or
 * undefined on failure.
 */
export function useAsyncAction() {
    let isLoading = $state(false);

    async function run<T>(
        action: () => Promise<T>,
        options: { success?: string; fallbackError?: string; onError?: (message: string) => void } = {},
    ): Promise<T | undefined> {
        isLoading = true;
        try {
            const result = await action();
            if (options.success) toast.success(options.success);
            return result;
        } catch (error) {
            console.error(error);
            const message = getErrorMessage(error, options.fallbackError);
            if (options.onError) options.onError(message);
            else toast.error(message);
            return undefined;
        } finally {
            isLoading = false;
        }
    }

    return {
        get isLoading() {
            return isLoading;
        },
        run,
    };
}
