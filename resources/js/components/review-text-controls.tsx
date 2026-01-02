import { useEffect, useState, useRef } from 'react';

/**
 * ReviewTextControls Component
 *
 * Provides typography controls (width, font size, line height) for review text.
 *
 * Cross-Page Synchronization:
 * Settings automatically sync across all pages in the application using shared localStorage keys.
 * When a user adjusts their reading preferences on any page, those preferences apply site-wide.
 * This creates a consistent user experience - set once, use everywhere.
 */
type ReviewTextControlsProps = {
    className?: string;
};

export default function ReviewTextControls({ className = '' }: ReviewTextControlsProps) {
    // Flag to prevent localStorage writes when responding to storage events
    const isUpdatingFromStorage = useRef(false);

    // Review text width control
    const [reviewWidth, setReviewWidth] = useState(() => {
        // Get saved preference from localStorage or default to null (no custom width)
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('reviewWidthPreference');
            return saved ? parseInt(saved) : null;
        }
        return null;
    });

    // Review font size control
    const [reviewFontSize, setReviewFontSize] = useState(() => {
        // Get saved preference from localStorage or default to null (no custom font size)
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('reviewFontSizePreference');
            return saved ? parseInt(saved) : null;
        }
        return null;
    });

    // Review line height control
    const [reviewLineHeight, setReviewLineHeight] = useState(() => {
        // Get saved preference from localStorage or default to null (no custom line height)
        if (typeof window !== 'undefined') {
            const saved = localStorage.getItem('reviewLineHeightPreference');
            return saved ? parseInt(saved) : null;
        }
        return null;
    });

    // Listen for storage changes to update UI state when other tabs change settings
    useEffect(() => {
        let storageTimeoutId: number | null = null;

        const handleStorageChange = (e: StorageEvent) => {
            // Clear any existing timeout
            if (storageTimeoutId !== null) {
                clearTimeout(storageTimeoutId);
            }

            // Set flag to prevent localStorage writes during this update
            isUpdatingFromStorage.current = true;

            if (e.key === 'reviewWidthPreference') {
                const value = e.newValue;
                setReviewWidth(value ? parseInt(value) : null);
            } else if (e.key === 'reviewFontSizePreference') {
                const value = e.newValue;
                setReviewFontSize(value ? parseInt(value) : null);
            } else if (e.key === 'reviewLineHeightPreference') {
                const value = e.newValue;
                setReviewLineHeight(value ? parseInt(value) : null);
            }

            // Clear flag after a longer delay to allow all state updates to complete
            storageTimeoutId = window.setTimeout(() => {
                isUpdatingFromStorage.current = false;
                storageTimeoutId = null;
            }, 50); // Increased delay to 50ms
        };

        window.addEventListener('storage', handleStorageChange);
        return () => {
            window.removeEventListener('storage', handleStorageChange);
            if (storageTimeoutId !== null) {
                clearTimeout(storageTimeoutId);
            }
        };
    }, []);

    // State to track if any custom settings are active (for reset button state)
    const [hasCustomSettings, setHasCustomSettings] = useState(() => {
        if (typeof window !== 'undefined') {
            return !!(localStorage.getItem('reviewWidthPreference') === null &&
                     localStorage.getItem('reviewFontSizePreference') === null &&
                     localStorage.getItem('reviewLineHeightPreference') === null);
        }
        return false;
    });

    // Update hasCustomSettings when any setting changes
    useEffect(() => {
        const hasAnyCustom = reviewWidth !== null || reviewFontSize !== null || reviewLineHeight !== null;
        setHasCustomSettings(hasAnyCustom);
    }, [reviewWidth, reviewFontSize, reviewLineHeight]);

  

    // Save review width preference to localStorage
    useEffect(() => {
        // Only write to localStorage if we're not updating from a storage event
        if (!isUpdatingFromStorage.current && typeof window !== 'undefined') {
            if (reviewWidth !== null) {
                localStorage.setItem('reviewWidthPreference', reviewWidth.toString());
            } else {
                localStorage.removeItem('reviewWidthPreference');
            }
            // Dispatch custom event to notify hook on current page
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    }, [reviewWidth]);

    // Save review font size preference to localStorage
    useEffect(() => {
        // Only write to localStorage if we're not updating from a storage event
        if (!isUpdatingFromStorage.current && typeof window !== 'undefined') {
            if (reviewFontSize !== null) {
                localStorage.setItem('reviewFontSizePreference', reviewFontSize.toString());
            } else {
                localStorage.removeItem('reviewFontSizePreference');
            }
            // Dispatch custom event to notify hook on current page
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    }, [reviewFontSize]);

    // Save review line height preference to localStorage
    useEffect(() => {
        // Only write to localStorage if we're not updating from a storage event
        if (!isUpdatingFromStorage.current && typeof window !== 'undefined') {
            if (reviewLineHeight !== null) {
                localStorage.setItem('reviewLineHeightPreference', reviewLineHeight.toString());
            } else {
                localStorage.removeItem('reviewLineHeightPreference');
            }
            // Dispatch custom event to notify hook on current page
            window.dispatchEvent(new Event('reviewTextStylesChanged'));
        }
    }, [reviewLineHeight]);

    
    // Function to get the custom styles for review containers
    const getReviewStyles = () => {
        const styles = {
            maxWidth: `${reviewWidth !== null ? reviewWidth : 100}%`,
            fontSize: `${reviewFontSize !== null ? reviewFontSize : 100}%`,
            lineHeight: `${reviewLineHeight !== null ? reviewLineHeight : 150}%`,
            margin: '0 auto' // Add margin auto for centering
        };
        return styles;
    };

    return (
        <div className={`section-surface rounded-2xl p-6 ${className}`}>
            <div className="mb-6 flex items-center justify-between">
                <h3 className="text-base font-semibold text-[var(--color-ui-text)]">Review Text Controls</h3>
                <button
                    onClick={() => {
                        setReviewWidth(100);
                        setReviewFontSize(100);
                        setReviewLineHeight(150);
                        if (typeof window !== 'undefined') {
                            localStorage.setItem('reviewWidthPreference', '100');
                            localStorage.setItem('reviewFontSizePreference', '100');
                            localStorage.setItem('reviewLineHeightPreference', '150');
                            // Dispatch custom event to notify hook on current page
                            window.dispatchEvent(new Event('reviewTextStylesChanged'));
                        }
                    }}
                    className="rounded-md px-3 py-1.5 text-sm font-medium text-[var(--color-ui-text-muted)] transition-colors hover:bg-[var(--color-ui-surface-alt)] hover:text-[var(--color-ui-text)]"
                >
                    Reset to Default
                </button>
            </div>
            <div className="grid grid-cols-1 gap-6">
                {/* Width Control */}
                <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-[var(--color-ui-text)]">Width</span>
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-[var(--color-ui-text-muted)]">50%</span>
                        <input
                            type="range"
                            min="50"
                            max="100"
                            value={reviewWidth || 100}
                            onChange={(e) => setReviewWidth(parseInt(e.target.value))}
                            className="h-2 w-24 cursor-pointer appearance-none rounded-lg border border-[var(--color-ui-border)] bg-[var(--color-track)]"
                            style={{
                                background: `linear-gradient(to right, var(--color-brand-primary) 0%, var(--color-brand-primary) ${(((reviewWidth || 100) - 50) / 50) * 100}%, var(--color-track) ${(((reviewWidth || 100) - 50) / 50) * 100}%, var(--color-track) 100%)`
                            }}
                        />
                        <span className="text-sm text-[var(--color-ui-text-muted)]">100%</span>
                        <span className="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-[var(--color-ui-text)]">
                            {reviewWidth || 100}%
                        </span>
                    </div>
                </div>

                {/* Font Size Control */}
                <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-[var(--color-ui-text)]">Font Size</span>
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-[var(--color-ui-text-muted)]">75%</span>
                        <input
                            type="range"
                            min="75"
                            max="150"
                            value={reviewFontSize || 100}
                            onChange={(e) => setReviewFontSize(parseInt(e.target.value))}
                            className="h-2 w-24 cursor-pointer appearance-none rounded-lg border border-[var(--color-ui-border)] bg-[var(--color-track)]"
                            style={{
                                background: `linear-gradient(to right, var(--color-brand-primary) 0%, var(--color-brand-primary) ${(((reviewFontSize || 100) - 75) / 75) * 100}%, var(--color-track) ${(((reviewFontSize || 100) - 75) / 75) * 100}%, var(--color-track) 100%)`
                            }}
                        />
                        <span className="text-sm text-[var(--color-ui-text-muted)]">150%</span>
                        <span className="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-[var(--color-ui-text)]">
                            {reviewFontSize || 100}%
                        </span>
                    </div>
                </div>

                {/* Line Height Control */}
                <div className="flex items-center justify-between">
                    <span className="text-sm font-medium text-[var(--color-ui-text)]">Line Height</span>
                    <div className="flex items-center gap-3">
                        <span className="text-sm text-[var(--color-ui-text-muted)]">100%</span>
                        <input
                            type="range"
                            min="100"
                            max="300"
                            value={reviewLineHeight || 150}
                            onChange={(e) => setReviewLineHeight(parseInt(e.target.value))}
                            className="h-2 w-24 cursor-pointer appearance-none rounded-lg border border-[var(--color-ui-border)] bg-[var(--color-track)]"
                            style={{
                                background: `linear-gradient(to right, var(--color-brand-primary) 0%, var(--color-brand-primary) ${(((reviewLineHeight || 150) - 100) / 200) * 100}%, var(--color-track) ${(((reviewLineHeight || 150) - 100) / 200) * 100}%, var(--color-track) 100%)`
                            }}
                        />
                        <span className="text-sm text-[var(--color-ui-text-muted)]">300%</span>
                        <span className="ml-3 min-w-[3.5rem] text-right text-sm font-semibold text-[var(--color-ui-text)]">
                            {reviewLineHeight || 150}%
                        </span>
                    </div>
                </div>
            </div>
        </div>
    );
}

// Export a hook to get the review styles for use in other components
export function useReviewTextStyles() {
    const [reviewStyles, setReviewStyles] = useState(() => {
        if (typeof window !== 'undefined') {
            const savedWidth = localStorage.getItem('reviewWidthPreference');
            const savedFontSize = localStorage.getItem('reviewFontSizePreference');
            const savedLineHeight = localStorage.getItem('reviewLineHeightPreference');

            return {
                maxWidth: `${savedWidth ? parseInt(savedWidth) : 100}%`,
                fontSize: `${savedFontSize ? parseInt(savedFontSize) : 100}%`,
                lineHeight: `${savedLineHeight ? parseInt(savedLineHeight) : 150}%`,
                margin: '0 auto'
            };
        }
        return {
            maxWidth: '100%',
            fontSize: '100%',
            lineHeight: '150%',
            margin: '0 auto'
        };
    });

    // Listen for localStorage changes and custom events
    useEffect(() => {
        const handleStorageChange = (e?: StorageEvent | Event) => {
            if (typeof window !== 'undefined') {
                const savedWidth = localStorage.getItem('reviewWidthPreference');
                const savedFontSize = localStorage.getItem('reviewFontSizePreference');
                const savedLineHeight = localStorage.getItem('reviewLineHeightPreference');

                const newStyles = {
                    maxWidth: `${savedWidth ? parseInt(savedWidth) : 100}%`,
                    fontSize: `${savedFontSize ? parseInt(savedFontSize) : 100}%`,
                    lineHeight: `${savedLineHeight ? parseInt(savedLineHeight) : 150}%`,
                    margin: '0 auto'
                };

                setReviewStyles(prevStyles => {
                    // Only update if styles actually changed to prevent re-renders
                    const hasChanged = JSON.stringify(prevStyles) !== JSON.stringify(newStyles);
                    return hasChanged ? newStyles : prevStyles;
                });
            }
        };

        // Listen for storage events (from other tabs)
        window.addEventListener('storage', handleStorageChange);

        // Listen for custom events (from the current page)
        window.addEventListener('reviewTextStylesChanged', handleStorageChange);

        return () => {
            window.removeEventListener('storage', handleStorageChange);
            window.removeEventListener('reviewTextStylesChanged', handleStorageChange);
        };
    }, []);

    return reviewStyles;
}
