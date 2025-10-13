import React from 'react';
import {DragHandleProps} from './sortable-list';

interface DragHandleComponentProps {
    dragHandleProps?: DragHandleProps;
    disabled?: boolean;
    className?: string;
    size?: 'sm' | 'md' | 'lg';
}

export default function DragHandle({
                                       dragHandleProps,
                                       disabled = false,
                                       className = '',
                                       size = 'md',
                                   }: DragHandleComponentProps) {
    const sizeClasses = {
        sm: 'h-3 w-3',
        md: 'h-4 w-4',
        lg: 'h-5 w-5',
    };

    const containerSizeClasses = {
        sm: 'p-1',
        md: 'p-2',
        lg: 'p-2',
    };

    if (disabled || !dragHandleProps?.listeners) {
        return (
            <div className={`rounded-lg bg-gray-100 ${containerSizeClasses[size]} dark:bg-gray-700 ${className}`}>
                <svg
                    className={`${sizeClasses[size]} text-gray-300 dark:text-gray-600`}
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M4 8h16M4 16h16"
                    />
                </svg>
            </div>
        );
    }

    return (
        <button
            {...dragHandleProps.attributes}
            {...dragHandleProps.listeners}
            className={`cursor-move rounded-lg bg-gray-200 ${containerSizeClasses[size]} text-gray-700 transition-colors hover:bg-gray-300 disabled:opacity-50 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600 ${className}`}
            aria-label="Drag to reorder"
            title="Drag to reorder"
            disabled={disabled}
        >
            <svg
                className={`${sizeClasses[size]}`}
                fill="none"
                stroke="currentColor"
                viewBox="0 0 24 24"
            >
                <path
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    strokeWidth={2}
                    d="M4 8h16M4 16h16"
                />
            </svg>
        </button>
    );
}
