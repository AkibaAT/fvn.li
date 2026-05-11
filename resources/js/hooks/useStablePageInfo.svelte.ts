export function useStablePageInfo() {
    let currentPath = $state('');
    let pendingInvites = $state(0);

    const updatePageInfo = () => {
        if (typeof window !== 'undefined') {
            currentPath = window.location.pathname;

            const invitesElement = document.querySelector('[data-pending-invites]');
            if (invitesElement) {
                const count = parseInt(invitesElement.getAttribute('data-pending-invites') || '0', 10);
                pendingInvites = count;
            }
        }
    };

    $effect(() => {
        // Initial update
        updatePageInfo();

        const handlePopState = () => {
            updatePageInfo();
        };

        window.addEventListener('popstate', handlePopState);

        return () => {
            window.removeEventListener('popstate', handlePopState);
        };
    });

    return {
        get currentPath() {
            return currentPath;
        },
        get pendingInvites() {
            return pendingInvites;
        },
        updatePageInfo,
    };
}
