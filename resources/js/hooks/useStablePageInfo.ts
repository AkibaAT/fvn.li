import { useEffect, useState, useCallback } from 'react';

export function useStablePageInfo() {
    const [currentPath, setCurrentPath] = useState('');
    const [pendingInvites, setPendingInvites] = useState(0);

    const updatePageInfo = useCallback(() => {
        if (typeof window !== 'undefined') {
            setCurrentPath(window.location.pathname);
            
            // Get pending invites from the DOM or a global state
            const invitesElement = document.querySelector('[data-pending-invites]');
            if (invitesElement) {
                const count = parseInt(invitesElement.getAttribute('data-pending-invites') || '0', 10);
                setPendingInvites(count);
            }
        }
    }, []);

    useEffect(() => {
        // Initial update
        updatePageInfo();

        // Listen for Inertia navigation events
        const handleInertiaComplete = () => {
            updatePageInfo();
        };

        // Listen for popstate events (browser back/forward)
        const handlePopState = () => {
            updatePageInfo();
        };

        document.addEventListener('inertia:complete', handleInertiaComplete);
        window.addEventListener('popstate', handlePopState);

        return () => {
            document.removeEventListener('inertia:complete', handleInertiaComplete);
            window.removeEventListener('popstate', handlePopState);
        };
    }, [updatePageInfo]);

    return {
        currentPath,
        pendingInvites,
        updatePageInfo,
    };
}