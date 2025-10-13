// Adapter to the React Notification system (no inline styles)
import {notify} from '@/components/toast';

export const toast = {
    success: (message: string) => notify(message, 'success'),
    error: (message: string) => notify(message, 'error'),
    info: (message: string) => notify(message, 'info'),
};
