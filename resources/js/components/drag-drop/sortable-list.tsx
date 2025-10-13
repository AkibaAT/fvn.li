import React from 'react';
import {
    closestCenter,
    DndContext,
    DragEndEvent,
    KeyboardSensor,
    PointerSensor,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import {
    arrayMove,
    SortableContext,
    sortableKeyboardCoordinates,
    useSortable,
    verticalListSortingStrategy,
} from '@dnd-kit/sortable';
import {CSS} from '@dnd-kit/utilities';

interface SortableListProps<T> {
    items: T[];
    onReorder: (items: T[]) => void | Promise<void>;
    getItemId: (item: T) => string | number;
    renderItem: (item: T, index: number, dragHandleProps: DragHandleProps) => React.ReactNode;
    className?: string;
    disabled?: boolean;
    debounceMs?: number; // Optional debouncing delay
}

interface DragHandleProps {
    attributes: Record<string, unknown>;
    listeners: Record<string, (event: React.SyntheticEvent) => void> | undefined;
}

interface SortableItemProps {
    id: string | number;
    children: React.ReactNode;
}

const SortableItem = React.memo(function SortableItem({id, children}: SortableItemProps) {
    const {
        attributes,
        listeners,
        setNodeRef,
        transform,
        transition,
        isDragging,
    } = useSortable({id});

    const style = React.useMemo(() => ({
        transform: CSS.Transform.toString(transform),
        transition,
    }), [transform, transition]);

    return (
        <div
            ref={setNodeRef}
            style={style}
            className={isDragging ? 'opacity-50' : ''}
        >
            {React.cloneElement(children as React.ReactElement<Record<string, unknown>>, {
                dragHandleProps: {attributes, listeners},
            })}
        </div>
    );
});

export default function SortableList<T>({
                                            items,
                                            onReorder,
                                            getItemId,
                                            renderItem,
                                            className = '',
                                            disabled = false,
                                            debounceMs = 0,
                                        }: SortableListProps<T>) {
    const timeoutRef = React.useRef<NodeJS.Timeout | null>(null);

    const sensors = useSensors(
        useSensor(PointerSensor),
        useSensor(KeyboardSensor, {
            coordinateGetter: sortableKeyboardCoordinates,
        }),
    );

    // Cleanup timeout on unmount
    React.useEffect(() => {
        return () => {
            if (timeoutRef.current) {
                clearTimeout(timeoutRef.current);
            }
        };
    }, []);

    const handleDragEnd = (event: DragEndEvent) => {
        const {active, over} = event;

        if (active.id !== over?.id) {
            const oldIndex = items.findIndex(
                (item) => getItemId(item) === active.id,
            );
            const newIndex = items.findIndex(
                (item) => getItemId(item) === over?.id,
            );

            // Only proceed if we found valid indices
            if (oldIndex !== -1 && newIndex !== -1 && oldIndex !== newIndex) {
                const newItems = arrayMove(items, oldIndex, newIndex);

                if (debounceMs > 0) {
                    // Clear existing timeout
                    if (timeoutRef.current) {
                        clearTimeout(timeoutRef.current);
                    }

                    // Set new timeout to debounce the API call
                    timeoutRef.current = setTimeout(() => {
                        onReorder(newItems);
                    }, debounceMs);
                } else {
                    // Call immediately if no debouncing
                    onReorder(newItems);
                }
            }
        }
    };

    if (disabled) {
        return (
            <div className={className}>
                {items.map((item, index) => (
                    <div key={getItemId(item)}>
                        {renderItem(item, index, {attributes: {}, listeners: undefined})}
                    </div>
                ))}
            </div>
        );
    }

    return (
        <DndContext
            sensors={sensors}
            collisionDetection={closestCenter}
            onDragEnd={handleDragEnd}
        >
            <SortableContext
                items={items.map(getItemId)}
                strategy={verticalListSortingStrategy}
            >
                <div className={className}>
                    {items.map((item, index) => (
                        <SortableItem key={getItemId(item)} id={getItemId(item)}>
                            {renderItem(item, index, {attributes: {}, listeners: undefined})}
                        </SortableItem>
                    ))}
                </div>
            </SortableContext>
        </DndContext>
    );
}

export type {DragHandleProps};
