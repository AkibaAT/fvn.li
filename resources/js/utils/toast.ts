import { writable } from 'svelte/store';

type ToastType = 'success' | 'error' | 'info';

interface ToastMessage {
    id: number;
    message: string;
    type: ToastType;
}

let nextId = 0;

function createToastStore() {
    const { subscribe, update } = writable<ToastMessage[]>([]);

    function add(message: string, type: ToastType) {
        const id = nextId++;
        update((toasts) => [...toasts, { id, message, type }]);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            dismiss(id);
        }, 5000);
    }

    function dismiss(id: number) {
        update((toasts) => toasts.filter((t) => t.id !== id));
    }

    return {
        subscribe,
        dismiss,
        success: (message: string) => add(message, 'success'),
        error: (message: string) => add(message, 'error'),
        info: (message: string) => add(message, 'info'),
    };
}

export const toastStore = createToastStore();

// Convenience export matching the old API
export const toast = {
    success: (message: string) => toastStore.success(message),
    error: (message: string) => toastStore.error(message),
    info: (message: string) => toastStore.info(message),
};
