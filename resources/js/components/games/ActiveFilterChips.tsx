import React from 'react';

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
}

export default function ActiveFilterChips({
    chips,
    onClearAll,
    getChipColorClass,
    getPlatformIcon,
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