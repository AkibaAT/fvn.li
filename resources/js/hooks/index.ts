// Custom Hooks (Svelte 5 rune-based)
export { useGameFilters } from './useGameFilters.svelte';
export { usePlatformIcons } from './usePlatformIcons';
export { useStorePlatformIcons } from './useStorePlatformIcons';
export { useGameCard } from './useGameCard.svelte';
export { useSearch } from './useSearch.svelte';
export {
    useToggle,
    useLocalStorage,
    useWindowSize,
    useClickOutside,
    useKeyboardShortcut,
    useCopyToClipboard,
    useDebounce,
} from './useUtilities.svelte';
export { useAppearance, initializeAppearance } from './use-appearance.svelte';
export { useDebounce as useDebounceCallback, useDebouncedValue } from './use-debounce.svelte';
export { useEnhancedSearch } from './useEnhancedSearch.svelte';
export { useKeyboardNavigation } from './useKeyboardNavigation.svelte';
export { usePrefetch } from './usePrefetch.svelte';
export {
    useWindow,
    useDocument,
    useLocalStorage as useSSRLocalStorage,
    useSessionStorage,
    useNavigator,
    useIsMounted,
    useOrigin,
    useMediaQuery,
    useDarkMode,
} from './useSSR.svelte';
export { useStablePageInfo } from './useStablePageInfo.svelte';
export { useStableRoutes } from './useStableRoutes.svelte';
export { useTagResizeObserver } from './useTagResizeObserver.svelte';
export {
    useAnnouncement,
    useFocusTrap,
    useAria,
    useAccessibilityKeyboardNavigation,
    useLiveRegion,
    useTabs,
    useRouteAccessibility,
    useFormSubmission,
    useProgressTracking,
} from './useAccessibility.svelte';
