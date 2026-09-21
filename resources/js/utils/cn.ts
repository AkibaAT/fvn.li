import clsx, { type ClassValue } from 'clsx';
import { extendTailwindMerge } from 'tailwind-merge';

const twMerge = extendTailwindMerge({
    extend: {
        theme: {
            text: ['micro', '2xs', 'ui', 'md', 'title', 'display'],
        },
    },
});

export function cn(...inputs: ClassValue[]): string {
    return twMerge(clsx(inputs));
}
