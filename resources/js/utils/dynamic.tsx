import dynamic from 'next/dynamic';

/**
 * Helper function to dynamically import components with SSR disabled
 */
export function dynamicNoLoad<T extends React.ComponentType<Record<string, unknown>>>(
    importFn: () => Promise<{ default: T }>,
    fallback?: (() => React.ReactNode)
) {
    return dynamic(importFn, {
        ssr: false,
        loading: fallback || (() => (
            <div className="flex items-center justify-center p-4">
                <div className="text-gray-500 dark:text-gray-400">Loading...</div>
            </div>
        ))
    });
}

/**
 * Pre-configured dynamic imports for common client-only components
 */
export const DynamicTinyMCEEditor = dynamicNoLoad(
    () => import('@/components/editor/TinyMCEEditor') as unknown as Promise<{ default: React.ComponentType<Record<string, unknown>> }>,
    () => (
        <div className="min-h-[400px] border border-gray-300 rounded-md bg-gray-50 dark:border-gray-600 dark:bg-gray-800 flex items-center justify-center">
            <div className="text-gray-500 dark:text-gray-400">Loading editor...</div>
        </div>
    )
);