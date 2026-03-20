import { useProgressTracking } from '@/hooks/useAccessibility.svelte';

// Hook for file upload accessibility
export function useFileUploadAccessibility() {
    const { announce, startProgress, updateProgress, completeProgress, failProgress } = useProgressTracking();

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
