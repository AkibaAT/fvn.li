import React from 'react';
import {Link} from '@inertiajs/react';

interface FormErrorProps {
    error?: string;
    show?: boolean;
    className?: string;
}

export const FormError: React.FC<FormErrorProps> = ({
                                                        error,
                                                        show = true,
                                                        className = ''
                                                    }) => {
    if (!error || !show) return null;

    return (
        <div
            className={`mt-1 flex items-center gap-1 text-xs text-red-600 dark:text-red-400 ${className}`}
            role="alert"
            aria-live="polite"
        >
            <span className="flex-shrink-0" role="img" aria-label="Error">❌</span>
            <span>{error}</span>
        </div>
    );
};

interface FormSuccessProps {
    message?: string;
    show?: boolean;
    className?: string;
}

export const FormSuccess: React.FC<FormSuccessProps> = ({
                                                            message,
                                                            show = true,
                                                            className = ''
                                                        }) => {
    if (!message || !show) return null;

    return (
        <div
            className={`mt-1 flex items-center gap-1 text-xs text-green-600 dark:text-green-400 ${className}`}
            role="status"
            aria-live="polite"
        >
            <span className="flex-shrink-0" role="img" aria-label="Success">✅</span>
            <span>{message}</span>
        </div>
    );
};

interface FormWarningProps {
    message?: string;
    show?: boolean;
    className?: string;
}

export const FormWarning: React.FC<FormWarningProps> = ({
                                                            message,
                                                            show = true,
                                                            className = ''
                                                        }) => {
    if (!message || !show) return null;

    return (
        <div
            className={`mt-1 flex items-center gap-1 text-xs text-yellow-600 dark:text-yellow-400 ${className}`}
            role="alert"
            aria-live="polite"
        >
            <span className="flex-shrink-0" role="img" aria-label="Warning">⚠️</span>
            <span>{message}</span>
        </div>
    );
};

interface FormInfoProps {
    message?: string;
    show?: boolean;
    className?: string;
}

export const FormInfo: React.FC<FormInfoProps> = ({
                                                      message,
                                                      show = true,
                                                      className = ''
                                                  }) => {
    if (!message || !show) return null;

    return (
        <div
            className={`mt-1 flex items-center gap-1 text-xs text-blue-600 dark:text-blue-400 ${className}`}
            role="status"
            aria-live="polite"
        >
            <span className="flex-shrink-0" role="img" aria-label="Information">ℹ️</span>
            <span>{message}</span>
        </div>
    );
};

// Enhanced input field with better accessibility
interface AccessibleInputProps extends React.InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
    success?: string;
    warning?: string;
    info?: string;
    required?: boolean;
    helpText?: string;
}

export const AccessibleInput: React.FC<AccessibleInputProps> = ({
                                                                    label,
                                                                    error,
                                                                    success,
                                                                    warning,
                                                                    info,
                                                                    required = false,
                                                                    helpText,
                                                                    className = '',
                                                                    ...props
                                                                }) => {
    const hasError = Boolean(error);
    const inputId = props.id || `input-${Math.random().toString(36).substr(2, 9)}`;
    const helpTextId = helpText ? `${inputId}-help` : undefined;
    const errorId = error ? `${inputId}-error` : undefined;

    return (
        <div className="space-y-1">
            <label
                htmlFor={inputId}
                className="block text-sm font-medium text-gray-700 dark:text-gray-300"
            >
                {label}
                {required && (
                    <span className="ml-1 text-red-500" aria-label="required">*</span>
                )}
            </label>

            <input
                {...props}
                id={inputId}
                className={`block w-full rounded-md border px-3 py-2 shadow-sm focus:ring-2 sm:text-sm ${
                    hasError
                        ? 'border-red-300 focus:border-red-500 focus:ring-red-500'
                        : 'border-gray-300 focus:border-blue-500 focus:ring-blue-500'
                } dark:border-gray-600 dark:bg-gray-800 dark:text-white ${className}`}
                aria-invalid={hasError}
                aria-describedby={[helpTextId, errorId].filter(Boolean).join(' ') || undefined}
                required={required}
            />

            {helpText && (
                <FormInfo message={helpText}/>
            )}

            <FormError error={error}/>
            <FormSuccess message={success}/>
            <FormWarning message={warning}/>
            <FormInfo message={info}/>
        </div>
    );
};

// Enhanced link component with better focus indicators
interface AccessibleLinkProps {
    href: string;
    children: React.ReactNode;
    external?: boolean;
    className?: string;
    ariaLabel?: string;
}

export const AccessibleLink: React.FC<AccessibleLinkProps> = ({
                                                                  href,
                                                                  children,
                                                                  external = false,
                                                                  className = '',
                                                                  ariaLabel,
                                                                  ...props
                                                              }) => {
    const baseClasses = 'inline-flex items-center gap-1 text-blue-600 hover:text-blue-800 dark:text-blue-400 dark:hover:text-blue-300 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 rounded-sm transition-colors underline decoration-1 underline-offset-2 hover:decoration-2';

    if (external) {
        return (
            <a
                href={href}
                target="_blank"
                rel="noopener noreferrer"
                className={`${baseClasses} ${className}`}
                aria-label={ariaLabel || `${children} (opens in new tab)`}
                {...props}
            >
                {children}
                <span className="flex-shrink-0 text-xs" role="img" aria-label="External link">🔗</span>
            </a>
        );
    }

    return (
        <Link
            href={href}
            className={`${baseClasses} ${className}`}
            aria-label={ariaLabel}
            {...props}
        >
            {children}
        </Link>
    );
};

// Button component with consistent focus states
interface AccessibleButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: 'primary' | 'secondary' | 'danger' | 'success' | 'warning';
    size?: 'sm' | 'md' | 'lg';
    loading?: boolean;
    icon?: string;
}

export const AccessibleButton: React.FC<AccessibleButtonProps> = ({
                                                                      variant = 'primary',
                                                                      size = 'md',
                                                                      loading = false,
                                                                      icon,
                                                                      children,
                                                                      className = '',
                                                                      disabled,
                                                                      ...props
                                                                  }) => {
    const baseClasses = 'inline-flex items-center justify-center gap-2 rounded-md font-medium transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed';

    const variantClasses = {
        primary: 'bg-blue-600 text-white hover:bg-blue-700 focus:ring-blue-500 dark:bg-blue-700 dark:hover:bg-blue-600',
        secondary: 'bg-gray-200 text-gray-900 hover:bg-gray-300 focus:ring-gray-500 dark:bg-gray-700 dark:text-gray-200 dark:hover:bg-gray-600',
        danger: 'bg-red-600 text-white hover:bg-red-700 focus:ring-red-500 dark:bg-red-700 dark:hover:bg-red-600',
        success: 'bg-green-600 text-white hover:bg-green-700 focus:ring-green-500 dark:bg-green-700 dark:hover:bg-green-600',
        warning: 'bg-yellow-600 text-white hover:bg-yellow-700 focus:ring-yellow-500 dark:bg-yellow-700 dark:hover:bg-yellow-600'
    };

    const sizeClasses = {
        sm: 'px-3 py-1.5 text-sm',
        md: 'px-4 py-2 text-sm',
        lg: 'px-6 py-3 text-base'
    };

    return (
        <button
            className={`${baseClasses} ${variantClasses[variant]} ${sizeClasses[size]} ${className}`}
            disabled={disabled || loading}
            {...props}
        >
            {loading && (
                <span className="animate-spin" role="img" aria-label="Loading">⏳</span>
            )}
            {!loading && icon && (
                <span role="img" aria-hidden="true">{icon}</span>
            )}
            {children}
        </button>
    );
};
