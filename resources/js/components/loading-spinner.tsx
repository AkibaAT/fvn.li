import { useEffect, useRef } from 'react';
import { announceLoading, setBusy } from '@/utils/accessibility';

interface LoadingSpinnerProps {
    size?: 'sm' | 'md' | 'lg';
    className?: string;
    label?: string;
    announcement?: string;
    isBusy?: boolean;
}

export default function LoadingSpinner({
    size = 'md',
    className = '',
    label = 'Loading',
    announcement,
    isBusy = true,
}: LoadingSpinnerProps) {
    const spinnerRef = useRef<HTMLDivElement>(null);

    const sizeClasses = {
        sm: 'h-4 w-4',
        md: 'h-6 w-6',
        lg: 'h-8 w-8',
    };

    useEffect(() => {
        const currentSpinner = spinnerRef.current;
        if (currentSpinner) {
            // Set accessibility attributes
            currentSpinner.setAttribute('role', 'status');
            currentSpinner.setAttribute('aria-label', label);
            
            // Set aria-busy if this spinner indicates a container is busy
            if (isBusy) {
                setBusy(currentSpinner, true);
            }
            
            // Announce loading state if custom message provided
            if (announcement) {
                announceLoading(announcement);
            }
        }

        return () => {
            // Clean up aria-busy state
            if (currentSpinner && isBusy) {
                setBusy(currentSpinner, false);
            }
        };
    }, [label, announcement, isBusy]);

    return (
        <div
            ref={spinnerRef}
            className={`animate-spin rounded-full border-2 border-gray-300 border-t-blue-600 ${sizeClasses[size]} ${className}`}
            aria-live="polite"
            aria-atomic="true"
        >
            <span className="sr-only">{label}</span>
        </div>
    );
}
