import {useCallback, useEffect, useRef, useState} from 'react';

interface UseTagResizeObserverProps {
    enabled: boolean;
}

interface TagResizeObserverResult {
    containerRef: React.RefObject<HTMLDivElement | null>;
    visibleTagCount: number;
    hiddenTagCount: number;
    isTagVisible: (index: number) => boolean;
    setTagRef: (index: number) => (element: HTMLButtonElement | null) => void;
}

export function useTagResizeObserver({
                                         enabled,
                                     }: UseTagResizeObserverProps): TagResizeObserverResult {
    const containerRef = useRef<HTMLDivElement>(null);
    const [visibleTagCount, setVisibleTagCount] = useState(0);
    const [hiddenTagCount, setHiddenTagCount] = useState(0);
    const tagRefs = useRef<(HTMLButtonElement | null)[]>([]);
    const [visibleTagsSet, setVisibleTagsSet] = useState<Set<number>>(new Set());
    const isCalculatingRef = useRef(false);
    const hasInitializedRef = useRef(false);
    const lastContainerSizeRef = useRef<{ width: number; height: number } | null>(null);

    const calculateVisibleTags = useCallback(() => {
        if (!enabled || !containerRef.current || isCalculatingRef.current) {
            return;
        }

        // Check if container size actually changed
        const container = containerRef.current;
        const containerRect = container.getBoundingClientRect();
        const currentSize = {width: containerRect.width, height: containerRect.height};

        if (lastContainerSizeRef.current) {
            const {width: lastWidth, height: lastHeight} = lastContainerSizeRef.current;
            if (Math.abs(currentSize.width - lastWidth) < 1 && Math.abs(currentSize.height - lastHeight) < 1) {
                // Size hasn't changed meaningfully, skip calculation
                return;
            }
        }

        lastContainerSizeRef.current = currentSize;
        isCalculatingRef.current = true;

        // Simple measurement without changing state during calculation
        const newVisibleTags = new Set<number>();
        let visibleCount = 0;

        for (let i = 0; i < tagRefs.current.length; i++) {
            const tag = tagRefs.current[i];
            if (!tag) continue;

            const tagRect = tag.getBoundingClientRect();
            const isVisible = tagRect.right <= containerRect.right &&
                tagRect.left >= containerRect.left &&
                tagRect.bottom <= containerRect.bottom &&
                tagRect.top >= containerRect.top;

            if (isVisible) {
                newVisibleTags.add(i);
                visibleCount++;
            }
        }

        const totalTags = tagRefs.current.filter(Boolean).length;
        const hiddenCount = Math.max(0, totalTags - visibleCount);

        // Only update state if values actually changed
        setVisibleTagCount(prev => prev !== visibleCount ? visibleCount : prev);
        setHiddenTagCount(prev => prev !== hiddenCount ? hiddenCount : prev);
        setVisibleTagsSet(prev => {
            if (prev.size !== newVisibleTags.size) return newVisibleTags;
            for (const item of newVisibleTags) {
                if (!prev.has(item)) return newVisibleTags;
            }
            return prev;
        });

        isCalculatingRef.current = false;
    }, [enabled]);

    useEffect(() => {
        if (!enabled || !containerRef.current) {
            return;
        }

        const container = containerRef.current;

        const resizeObserver = new ResizeObserver(() => {
            calculateVisibleTags();
        });

        resizeObserver.observe(container);

        // Initial calculation - only if we haven't initialized yet
        if (!hasInitializedRef.current) {
            hasInitializedRef.current = true;
            calculateVisibleTags();
        }

        return () => {
            resizeObserver.disconnect();
        };
    }, [enabled, calculateVisibleTags]);

    const setTagRef = useCallback(
        (index: number) => (element: HTMLButtonElement | null) => {
            tagRefs.current[index] = element;
            if (element && enabled) {
                // Recalculate after the element is rendered
                requestAnimationFrame(() => calculateVisibleTags());
            }
        },
        [enabled, calculateVisibleTags],
    );

    const isTagVisible = useCallback((index: number) => {
        return visibleTagsSet.has(index);
    }, [visibleTagsSet]);


    return {
        containerRef,
        visibleTagCount,
        hiddenTagCount,
        isTagVisible,
        setTagRef,
    };
}
