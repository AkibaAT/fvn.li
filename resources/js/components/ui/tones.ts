import type { BadgeTone } from './Badge.svelte';
import BookIcon from '@/components/icons/Book.svelte';
import BookmarkIcon from '@/components/icons/Bookmark.svelte';
import CheckCircleIcon from '@/components/icons/CheckCircle.svelte';
import ClipboardIcon from '@/components/icons/Clipboard.svelte';
import XCircleIcon from '@/components/icons/XCircle.svelte';
import type { Component } from 'svelte';

const listTypeMetadata = {
    reading: { tone: 'primary', label: 'Reading', icon: BookIcon },
    completed: { tone: 'success', label: 'Completed', icon: CheckCircleIcon },
    plan_to_read: { tone: 'warning', label: 'Plan to Read', icon: ClipboardIcon },
    on_hold: { tone: 'orange', label: 'On Hold', icon: BookmarkIcon },
    dropped: { tone: 'danger', label: 'Dropped', icon: XCircleIcon },
    custom: { tone: 'neutral', label: 'Custom', icon: ClipboardIcon },
} as const satisfies Record<string, { tone: BadgeTone; label: string; icon: Component }>;

const listTypeBorderClasses = {
    reading: 'border-blue-500',
    completed: 'border-green-500',
    plan_to_read: 'border-amber-500',
    on_hold: 'border-orange-500',
    dropped: 'border-red-500',
    default: 'border-gray-500',
} as const;

const listTypeDotClasses = {
    reading: 'bg-blue-500',
    completed: 'bg-green-500',
    plan_to_read: 'bg-amber-500',
    on_hold: 'bg-orange-500',
    dropped: 'bg-red-500',
    default: 'bg-gray-500',
} as const;

export function listTypeTone(type: string | undefined): BadgeTone {
    if (!type) return 'neutral';
    return listTypeMetadata[type as keyof typeof listTypeMetadata]?.tone ?? listTypeMetadata.custom.tone;
}

export function listTypeLabel(type: string | undefined): string {
    if (!type) return listTypeMetadata.custom.label;
    return listTypeMetadata[type as keyof typeof listTypeMetadata]?.label ?? listTypeMetadata.custom.label;
}

export function listTypeIcon(type: string | undefined): Component {
    if (!type) return listTypeMetadata.custom.icon;
    return listTypeMetadata[type as keyof typeof listTypeMetadata]?.icon ?? listTypeMetadata.custom.icon;
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
