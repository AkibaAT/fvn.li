import React from 'react';

interface ModalProps {
    isOpen: boolean;
    onClose: () => void;
    title?: string;
    children: React.ReactNode;
    size?: 'sm' | 'md' | 'lg' | 'xl';
    className?: string;
}

export default function Modal({
    isOpen,
    onClose,
    title,
    children,
    size = 'md',
    className = '',
}: ModalProps) {
    const sizeClasses = {
        sm: 'max-w-md',
        md: 'max-w-lg',
        lg: 'max-w-2xl',
        xl: 'max-w-4xl',
    };

    // Handle escape key
    React.useEffect(() => {
        const handleEscape = (event: KeyboardEvent) => {
            if (event.key === 'Escape') {
                onClose();
            }
        };

        document.addEventListener('keydown', handleEscape);
        return () => document.removeEventListener('keydown', handleEscape);
    }, [onClose]);

    if (!isOpen) return null;

    // Handle click outside
    const handleBackdropClick = (event: React.MouseEvent<HTMLDivElement>) => {
        if (event.target === event.currentTarget) {
            onClose();
        }
    };

    return (
        <div className="fixed inset-0 z-50 overflow-y-auto">
            {/* Backdrop */}
            <div
                className="fixed inset-0 bg-black bg-opacity-50 transition-opacity"
                onClick={handleBackdropClick}
            />
            
            {/* Modal */}
            <div className="flex min-h-screen items-center justify-center p-4">
                <div
                    className={`relative w-full rounded-lg bg-white shadow-xl dark:bg-gray-800 ${sizeClasses[size]} ${className}`}
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby={title ? 'modal-title' : undefined}
                >
                    {/* Header */}
                    {title && (
                        <div className="flex items-center justify-between border-b border-gray-200 px-6 py-4 dark:border-gray-700">
                            <h3
                                id="modal-title"
                                className="text-lg font-semibold text-gray-900 dark:text-white"
                            >
                                {title}
                            </h3>
                            <button
                                onClick={onClose}
                                className="rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-300"
                                aria-label="Close modal"
                            >
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    )}
                    
                    {/* Content */}
                    <div className="px-6 py-4">
                        {children}
                    </div>
                    
                    {/* Footer (if no custom footer is provided in children) */}
                    {!title && (
                        <div className="absolute right-4 top-4">
                            <button
                                onClick={onClose}
                                className="rounded-md text-gray-400 hover:text-gray-600 focus:outline-none focus:ring-2 focus:ring-blue-500 dark:hover:text-gray-300"
                                aria-label="Close modal"
                            >
                                <svg className="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                                </svg>
                            </button>
                        </div>
                    )}
                </div>
            </div>
        </div>
    );
}