import { SvelteSet } from 'svelte/reactivity';

interface UseTagResizeObserverProps {
    enabled: boolean;
}

interface TagResizeObserverResult {
    containerRef: HTMLDivElement | null;
    visibleTagCount: number;
    hiddenTagCount: number;
    isTagVisible: (index: number) => boolean;
    setTagRef: (index: number) => (element: HTMLButtonElement | null) => void;
    setContainer: (el: HTMLDivElement | null) => void;
}

export function useTagResizeObserver({ enabled }: UseTagResizeObserverProps): TagResizeObserverResult {
    let container = $state<HTMLDivElement | null>(null);
    let visibleTagCount = $state(0);
    let hiddenTagCount = $state(0);
    let visibleTagsSet = new SvelteSet<number>();
    const tagRefs: (HTMLButtonElement | null)[] = [];
    let isCalculating = false;
    let hasInitialized = false;
    let lastContainerSize: { width: number; height: number } | null = null;

    const calculateVisibleTags = () => {
        if (!enabled || !container || isCalculating) {
            return;
        }

        const containerRect = container.getBoundingClientRect();
        const currentSize = { width: containerRect.width, height: containerRect.height };

        if (lastContainerSize) {
            const { width: lastWidth, height: lastHeight } = lastContainerSize;
            if (Math.abs(currentSize.width - lastWidth) < 1 && Math.abs(currentSize.height - lastHeight) < 1) {
                return;
            }
        }

        lastContainerSize = currentSize;
        isCalculating = true;

        const newVisibleTags = new SvelteSet<number>();
        let visCount = 0;

        for (let i = 0; i < tagRefs.length; i++) {
            const tag = tagRefs[i];
            if (!tag) continue;

            const tagRect = tag.getBoundingClientRect();
            const isVisible =
                tagRect.right <= containerRect.right &&
                tagRect.left >= containerRect.left &&
                tagRect.bottom <= containerRect.bottom &&
                tagRect.top >= containerRect.top;

            if (isVisible) {
                newVisibleTags.add(i);
                visCount++;
            }
        }

        const totalTags = tagRefs.filter(Boolean).length;
        const hidCount = Math.max(0, totalTags - visCount);

        if (visibleTagCount !== visCount) visibleTagCount = visCount;
        if (hiddenTagCount !== hidCount) hiddenTagCount = hidCount;

        // Check if set actually changed
        let setChanged = visibleTagsSet.size !== newVisibleTags.size;
        if (!setChanged) {
            for (const item of newVisibleTags) {
                if (!visibleTagsSet.has(item)) {
                    setChanged = true;
                    break;
                }
            }
        }
        if (setChanged) visibleTagsSet = newVisibleTags;

        isCalculating = false;
    };

    $effect(() => {
        if (!enabled || !container) {
            return;
        }

        const el = container;

        const resizeObserver = new ResizeObserver(() => {
            calculateVisibleTags();
        });

        resizeObserver.observe(el);

        if (!hasInitialized) {
            hasInitialized = true;
            calculateVisibleTags();
        }

        return () => {
            resizeObserver.disconnect();
        };
    });

    const setTagRef = (index: number) => (element: HTMLButtonElement | null) => {
        tagRefs[index] = element;
        if (element && enabled) {
            requestAnimationFrame(() => calculateVisibleTags());
        }
    };

    const isTagVisible = (index: number) => {
        return visibleTagsSet.has(index);
    };

    return {
        get containerRef() {
            return container;
        },
        get visibleTagCount() {
            return visibleTagCount;
        },
        get hiddenTagCount() {
            return hiddenTagCount;
        },
        isTagVisible,
        setTagRef,
        setContainer(el: HTMLDivElement | null) {
            container = el;
        },
    };
}
