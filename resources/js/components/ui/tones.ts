import type { BadgeTone } from './Badge.svelte';
import type { CardTone } from './Card.svelte';

export const listTypeTones = {
    reading: 'primary',
    completed: 'success',
    plan_to_read: 'warning',
    on_hold: 'orange',
    dropped: 'danger',
} as const satisfies Record<string, BadgeTone>;

export const listTypeBorderClasses = {
    reading: 'border-blue-500',
    completed: 'border-green-500',
    plan_to_read: 'border-amber-500',
    on_hold: 'border-orange-500',
    dropped: 'border-red-500',
    default: 'border-gray-500',
} as const;

export const listTypeDotClasses = {
    reading: 'bg-blue-500',
    completed: 'bg-green-500',
    plan_to_read: 'bg-amber-500',
    on_hold: 'bg-orange-500',
    dropped: 'bg-red-500',
    default: 'bg-gray-500',
} as const;

export function listTypeTone(type: string | undefined): BadgeTone {
    if (!type) return 'neutral';
    return listTypeTones[type as keyof typeof listTypeTones] ?? 'neutral';
}

export function listTypeCardTone(type: string | undefined): CardTone {
    const tone = listTypeTone(type);
    if (tone === 'orange' || tone === 'purple') return 'warning';
    return tone;
}

export function listTypeBorderClass(type: string | undefined): string {
    return listTypeBorderClasses[type as keyof typeof listTypeBorderClasses] ?? listTypeBorderClasses.default;
}

export function listTypeDotClass(type: string | undefined): string {
    return listTypeDotClasses[type as keyof typeof listTypeDotClasses] ?? listTypeDotClasses.default;
}

export function formatListType(type: string): string {
    return type.replace(/_/g, ' ').replace(/\b\w/g, (letter) => letter.toUpperCase());
}
