import React from 'react';

interface CardProps {
    children: React.ReactNode;
    className?: string;
    hover?: boolean;
    padding?: 'none' | 'sm' | 'md' | 'lg';
}

export default function Card({
    children,
    className = '',
    hover = false,
    padding = 'md',
}: CardProps) {
    const baseClasses = 'rounded-xl border border-stone-200/80 bg-white shadow-sm dark:bg-gray-800 dark:border-gray-700';

    const hoverClasses = hover ? 'transition-all duration-200 hover:shadow-md hover:shadow-teal-500/5 hover:-translate-y-0.5' : '';

    const paddingClasses = {
        none: '',
        sm: 'p-3',
        md: 'p-4',
        lg: 'p-6',
    };

    return (
        <div className={`${baseClasses} ${hoverClasses} ${paddingClasses[padding]} ${className}`}>
            {children}
        </div>
    );
}

interface CardHeaderProps {
    children: React.ReactNode;
    className?: string;
}

export function CardHeader({ children, className = '' }: CardHeaderProps) {
    return (
        <div className={`mb-4 ${className}`}>
            {children}
        </div>
    );
}

interface CardTitleProps {
    children: React.ReactNode;
    className?: string;
}

export function CardTitle({ children, className = '' }: CardTitleProps) {
    return (
        <h3 className={`text-lg font-semibold text-gray-900 dark:text-white ${className}`}>
            {children}
        </h3>
    );
}

interface CardDescriptionProps {
    children: React.ReactNode;
    className?: string;
}

export function CardDescription({ children, className = '' }: CardDescriptionProps) {
    return (
        <p className={`text-sm text-gray-600 dark:text-gray-400 ${className}`}>
            {children}
        </p>
    );
}

interface CardContentProps {
    children: React.ReactNode;
    className?: string;
}

export function CardContent({ children, className = '' }: CardContentProps) {
    return (
        <div className={className}>
            {children}
        </div>
    );
}

interface CardFooterProps {
    children: React.ReactNode;
    className?: string;
}

export function CardFooter({ children, className = '' }: CardFooterProps) {
    return (
        <div className={`mt-4 flex items-center ${className}`}>
            {children}
        </div>
    );
}