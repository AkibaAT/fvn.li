import React from 'react';

interface BadgeProps {
    children: React.ReactNode;
    variant?: 'default' | 'primary' | 'secondary' | 'success' | 'warning' | 'danger' | 'info';
    size?: 'sm' | 'md' | 'lg';
    className?: string;
}

export default function Badge({
    children,
    variant = 'default',
    size = 'md',
    className = '',
}: BadgeProps) {
    const baseClasses = 'inline-flex items-center justify-center rounded-full font-medium';
    
    const variantClasses = {
        default: 'bg-gray-100 text-gray-800 dark:bg-gray-800 dark:text-gray-200',
        primary: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-300',
        secondary: 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
        success: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-300',
        warning: 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900/30 dark:text-yellow-300',
        danger: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-300',
        info: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-300',
    };

    const sizeClasses = {
        sm: 'px-2 py-1 text-xs',
        md: 'px-2.5 py-1.5 text-xs',
        lg: 'px-3 py-2 text-sm',
    };

    return (
        <span className={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}>
            {children}
        </span>
    );
}