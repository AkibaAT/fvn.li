import { useEffect, useRef } from 'react';
import { useProgressTracking } from '@/hooks/useAccessibility';
import { createProgressBar } from '@/utils/accessibility';

interface AccessibleProgressProps {
    value: number;
    min?: number;
    max?: number;
    message?: string;
    showVisual?: boolean;
    className?: string;
    announceChanges?: boolean;
}

export default function AccessibleProgress({
    value,
    min = 0,
    max = 100,
    message = 'Progress',
    showVisual = true,
    className = '',
    announceChanges = true,
}: AccessibleProgressProps) {
    const progressRef = useRef<HTMLDivElement>(null);
    const progressBarRef = useRef<ReturnType<typeof createProgressBar> | null>(null);
    const { startProgress, updateProgress, completeProgress } = useProgressTracking();

    useEffect(() => {
        if (progressRef.current) {
            // Create accessible progress bar for screen readers
            progressBarRef.current = createProgressBar(progressRef.current, min, max);
            
            // Start progress tracking
            startProgress(min, max, message);
        }

        return () => {
            if (progressBarRef.current) {
                progressBarRef.current.remove();
            }
        };
    }, [min, max, message, startProgress]);

    useEffect(() => {
        if (announceChanges) {
            updateProgress(value, message);
        }
        
        // Update ARIA attributes
        if (progressBarRef.current) {
            progressBarRef.current.update(value, message);
        }
    }, [value, message, announceChanges, updateProgress]);

    // Check if progress is complete
    useEffect(() => {
        if (value >= max) {
            completeProgress(`${message} completed`);
        }
    }, [value, max, message, completeProgress]);

    const percentage = Math.round(((value - min) / (max - min)) * 100);

    return (
        <div ref={progressRef} className={`relative ${className}`}>
            {/* Visual progress bar (optional) */}
            {showVisual && (
                <div className="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700">
                    <div
                        className="bg-blue-600 h-2.5 rounded-full transition-all duration-300 ease-in-out"
                        style={{ width: `${percentage}%` }}
                        role="progressbar"
                        aria-valuenow={value}
                        aria-valuemin={min}
                        aria-valuemax={max}
                        aria-label={message}
                    ></div>
                </div>
            )}
            
            {/* Screen reader only progress info */}
            <div className="sr-only" aria-live="polite" aria-atomic="true">
                {message}: {percentage}%
            </div>
        </div>
    );
}

// Hook for file upload accessibility
export function useFileUploadAccessibility() {
    const { announce } = useProgressTracking();
    const { startProgress, updateProgress, completeProgress, failProgress } = useProgressTracking();

    const announceUploadStart = (fileName: string, fileSize?: string) => {
        const message = fileSize ? `Uploading ${fileName} (${fileSize})` : `Uploading ${fileName}`;
        announce(message, 'polite');
        startProgress(0, 100, message);
    };

    const announceUploadProgress = (fileName: string, loaded: number, total: number) => {
        const percentage = Math.round((loaded / total) * 100);
        updateProgress(percentage, `Uploading ${fileName}`);
    };

    const announceUploadComplete = (fileName: string) => {
        completeProgress(`${fileName} uploaded successfully`);
    };

    const announceUploadError = (fileName: string, error: string) => {
        failProgress(`Failed to upload ${fileName}: ${error}`);
    };

    return {
        announceUploadStart,
        announceUploadProgress,
        announceUploadComplete,
        announceUploadError,
    };
}

// Component for accessible file upload input
interface AccessibleFileUploadProps {
    onFileSelect: (file: File) => void;
    onUploadStart?: (fileName: string) => void;
    onUploadError?: (fileName: string, error: string) => void;
    acceptedTypes?: string;
    maxSize?: number; // in bytes
    className?: string;
    label?: string;
}

export function AccessibleFileUpload({
    onFileSelect,
    onUploadStart,
    onUploadError,
    acceptedTypes = '*/*',
    maxSize,
    className = '',
    label = 'Choose file',
}: AccessibleFileUploadProps) {
    const fileInputRef = useRef<HTMLInputElement>(null);
    const { announceUploadStart, announceUploadError } = useFileUploadAccessibility();

    const handleFileSelect = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) return;

        // Validate file size
        if (maxSize && file.size > maxSize) {
            const error = `File size exceeds maximum allowed size of ${formatFileSize(maxSize)}`;
            announceUploadError(file.name, error);
            if (onUploadError) onUploadError(file.name, error);
            return;
        }

        // Validate file type
        if (acceptedTypes !== '*/*') {
            const acceptedTypesArray = acceptedTypes.split(',').map(type => type.trim());
            const isAccepted = acceptedTypesArray.some(type => {
                if (type.startsWith('.')) {
                    return file.name.toLowerCase().endsWith(type.toLowerCase());
                }
                if (type.includes('/*')) {
                    return file.type.startsWith(type.split('/*')[0]);
                }
                return file.type === type;
            });

            if (!isAccepted) {
                const error = `File type ${file.type} is not accepted`;
                announceUploadError(file.name, error);
                if (onUploadError) onUploadError(file.name, error);
                return;
            }
        }

        onFileSelect(file);
        announceUploadStart(file.name, formatFileSize(file.size));
        if (onUploadStart) onUploadStart(file.name);
    };

    const formatFileSize = (bytes: number): string => {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    };

    return (
        <div className={className}>
            <input
                ref={fileInputRef}
                type="file"
                accept={acceptedTypes}
                onChange={handleFileSelect}
                className="hidden"
                id="file-upload"
                aria-label={label}
            />
            <label
                htmlFor="file-upload"
                className="cursor-pointer inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500"
            >
                {label}
            </label>
            {maxSize && (
                <span className="ml-2 text-sm text-gray-500">
                    Max size: {formatFileSize(maxSize)}
                </span>
            )}
        </div>
    );
}