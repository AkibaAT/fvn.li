import React from 'react';

export interface StatusConfig {
    color: 'blue' | 'green' | 'yellow' | 'orange' | 'red' | 'gray' | 'indigo';
    icon: string;
    label: string;
    pattern?: 'solid' | 'dashed' | 'dotted';
    shape?: 'rounded' | 'square' | 'pill';
}

export const listStatusConfig: Record<string, StatusConfig> = {
    reading: {
        color: 'blue',
        icon: '📖',
        label: 'Reading',
        pattern: 'solid',
        shape: 'rounded'
    },
    completed: {
        color: 'green',
        icon: '✅',
        label: 'Completed',
        pattern: 'solid',
        shape: 'rounded'
    },
    plan_to_read: {
        color: 'yellow',
        icon: '📋',
        label: 'Plan to Read',
        pattern: 'dashed',
        shape: 'rounded'
    },
    on_hold: {
        color: 'orange',
        icon: '⏸️',
        label: 'On Hold',
        pattern: 'dotted',
        shape: 'rounded'
    },
    dropped: {
        color: 'red',
        icon: '❌',
        label: 'Dropped',
        pattern: 'solid',
        shape: 'square'
    },
    custom: {
        color: 'gray',
        icon: '📝',
        label: 'Custom',
        pattern: 'solid',
        shape: 'rounded'
    }
};

export const notificationStatusConfig = {
    enabled: {
        color: 'blue' as const,
        icon: '🔔',
        label: 'Notifications enabled'
    },
    disabled: {
        color: 'gray' as const,
        icon: '🔕',
        label: 'Notifications disabled'
    }
};

export const gameStatusConfig = {
    nsfw: {
        color: 'red' as const,
        icon: '🔞',
        label: 'NSFW Content',
        pattern: 'solid' as const,
        shape: 'pill' as const
    },
    paid: {
        color: 'indigo' as const,
        icon: '💰',
        label: 'Paid Game',
        pattern: 'solid' as const,
        shape: 'pill' as const
    },
    demo: {
        color: 'blue' as const,
        icon: '🎮',
        label: 'Demo Available',
        pattern: 'dashed' as const,
        shape: 'pill' as const
    },
    update_available: {
        color: 'yellow' as const,
        icon: '🆙',
        label: 'Update Available',
        pattern: 'solid' as const,
        shape: 'rounded' as const
    }
};

export const alertStatusConfig = {
    success: {
        color: 'green' as const,
        icon: '✅',
        label: 'Success'
    },
    error: {
        color: 'red' as const,
        icon: '❌',
        label: 'Error'
    },
    warning: {
        color: 'yellow' as const,
        icon: '⚠️',
        label: 'Warning'
    },
    info: {
        color: 'blue' as const,
        icon: 'ℹ️',
        label: 'Information'
    }
};

// Utility function to get color classes with better contrast and patterns
export const getStatusClasses = (config: StatusConfig) => {
    const {color, pattern, shape} = config;

    // Base color classes with enhanced contrast
    const colorClasses = {
        blue: 'bg-blue-100 text-blue-900 border-blue-300 dark:bg-blue-900/30 dark:text-blue-200 dark:border-blue-600',
        green: 'bg-green-100 text-green-900 border-green-300 dark:bg-green-900/30 dark:text-green-200 dark:border-green-600',
        yellow: 'bg-yellow-100 text-yellow-900 border-yellow-400 dark:bg-yellow-900/30 dark:text-yellow-200 dark:border-yellow-500',
        orange: 'bg-orange-100 text-orange-900 border-orange-300 dark:bg-orange-900/30 dark:text-orange-200 dark:border-orange-600',
        red: 'bg-red-100 text-red-900 border-red-300 dark:bg-red-900/30 dark:text-red-200 dark:border-red-600',
        gray: 'bg-gray-100 text-gray-900 border-gray-300 dark:bg-gray-900/30 dark:text-gray-200 dark:border-gray-600',
        indigo: 'bg-indigo-100 text-indigo-900 border-indigo-300 dark:bg-indigo-900/30 dark:text-indigo-200 dark:border-indigo-600'
    };

    // Pattern classes for better distinction
    const patternClasses = {
        solid: 'border-2',
        dashed: 'border-2 border-dashed',
        dotted: 'border-2 border-dotted'
    };

    // Shape classes
    const shapeClasses = {
        rounded: 'rounded-md',
        square: 'rounded-none',
        pill: 'rounded-full'
    };

    return `${colorClasses[color]} ${patternClasses[pattern || 'solid']} ${shapeClasses[shape || 'rounded']} px-2 py-1 text-xs font-semibold inline-flex items-center gap-1`;
};

// Component for rendering status badges
export interface StatusBadgeProps {
    status: string;
    config: Record<string, StatusConfig>;
    size?: 'sm' | 'md' | 'lg';
    showIcon?: boolean;
    showText?: boolean;
    className?: string;
}

export const StatusBadge: React.FC<StatusBadgeProps> = ({
                                                            status,
                                                            config,
                                                            size = 'md',
                                                            showIcon = true,
                                                            showText = true,
                                                            className = ''
                                                        }) => {
    const statusConfig = config[status];
    if (!statusConfig) return null;

    const sizeClasses = {
        sm: 'text-xs px-1.5 py-0.5',
        md: 'text-xs px-2 py-1',
        lg: 'text-sm px-3 py-1.5'
    };

    return (
        <span
            className={`${getStatusClasses(statusConfig)} ${sizeClasses[size]} ${className}`}
            aria-label={statusConfig.label}
            title={statusConfig.label}
        >
            {showIcon && <span role="img" aria-hidden="true">{statusConfig.icon}</span>}
            {showText && <span>{statusConfig.label}</span>}
        </span>
    );
};

// Enhanced getListTypeColor function with accessibility
export const getListTypeColor = (type: string): string => {
    return listStatusConfig[type]?.color || 'gray';
};

export const getListTypeConfig = (type: string): StatusConfig => {
    return listStatusConfig[type] || listStatusConfig.custom;
};
