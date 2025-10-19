import React from 'react';
import ItchioIcon from '@/components/icons/itchio';
import SteamIcon from '@/components/icons/steam';

interface ActiveChip {
    key: string;
    type: string;
    value?: string;
    label: string;
    flagCode?: string;
    onClear?: () => void;
}

interface ActiveFilterChipsProps {
    chips: ActiveChip[];
    onClearAll: () => void;
    getChipColorClass: (type?: string) => string;
    getPlatformIcon: (platform: string) => { icon: string; color: string } | undefined;
    getStorePlatformIcon?: (platform: string) => { color: string; title: string; label: string } | undefined;
}

export default function ActiveFilterChips({
    chips,
    onClearAll,
    getChipColorClass,
    getPlatformIcon,
    getStorePlatformIcon,
}: ActiveFilterChipsProps) {
    if (chips.length === 0) {
        return (
            <span className="text-sm text-gray-600 dark:text-gray-400">
                No active filters
            </span>
        );
    }

    return (
        <>
            <span className="mr-1 text-sm font-medium text-gray-800 dark:text-gray-200">
                Active filters:
            </span>
            {chips.map((chip) => (
                <span
                    key={chip.key}
                    className={`inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs ${getChipColorClass(chip.type)}`}
                >
                    {chip.type === 'language' && chip.flagCode ? (
                        <span
                            className={`fi fi-${chip.flagCode} mr-0.5 rounded-xs`}
                        />
                    ) : null}
                    {chip.type === 'platform' && chip.value && getPlatformIcon(chip.value) ? (
                        <i
                            className={`${getPlatformIcon(chip.value)?.icon} ${getPlatformIcon(chip.value)?.color} mr-0.5`}
                        />
                    ) : null}
                    {chip.type === 'storePlatform' && chip.value && getStorePlatformIcon?.(chip.value) ? (
                        (() => {
                            const iconMeta = getStorePlatformIcon(chip.value);
                            const renderIcon = () => {
                                switch (chip.value) {
                                    case 'itch_io':
                                        return <ItchioIcon className={`h-4 w-4 ${iconMeta?.color} mr-0.5`} />;
                                    case 'steam':
                                        return <SteamIcon className={`h-4 w-4 ${iconMeta?.color} mr-0.5`} />;
                                    case 'other':
                                        return (
                                            <svg
                                                className={`h-4 w-4 ${iconMeta?.color} mr-0.5`}
                                                viewBox="0 0 24 24"
                                                fill="none"
                                                stroke="currentColor"
                                                strokeWidth="2"
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                aria-hidden="true"
                                            >
                                                <circle cx="12" cy="12" r="10" />
                                                <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
                                            </svg>
                                        );
                                    default:
                                        return null;
                                }
                            };
                            return renderIcon();
                        })()
                    ) : null}
                    {chip.label}
                    {chip.onClear && (
                        <button
                            aria-label={`Remove ${chip.label}`}
                            onClick={chip.onClear}
                            className="ml-1 hover:opacity-80"
                        >
                            ×
                        </button>
                    )}
                </span>
            ))}
            <button
                type="button"
                onClick={onClearAll}
                className="ml-1 text-xs text-red-600 hover:text-red-700 dark:text-red-400 dark:hover:text-red-300"
            >
                Reset all
            </button>
        </>
    );
}