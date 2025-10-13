import {type Appearance, useAppearance} from '@/hooks/use-appearance';
import {useEffect, useRef, useState} from 'react';

export default function AppearanceDropdown() {
    const {appearance, updateAppearance} = useAppearance();
    const [showMenu, setShowMenu] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const getThemeIcon = () => {
        if (typeof document !== 'undefined') {
            const isDarkApplied =
                document.documentElement.classList.contains('dark');
            return isDarkApplied ? '🌙' : '☀️';
        }
        return '🌙';
    };

    const onSelectAppearance = (mode: Appearance) => {
        updateAppearance(mode);
        setShowMenu(false);
    };

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (!showMenu) return;
            const target = event.target as Element;
            if (
                containerRef.current &&
                !containerRef.current.contains(target)
            ) {
                setShowMenu(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, [showMenu]);

    return (
        <div className="theme-menu-container relative" ref={containerRef}>
            <button
                onClick={() => setShowMenu(!showMenu)}
                className="flex items-center rounded-lg bg-gray-100 px-3 py-2 transition-colors duration-200 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                title="Change appearance"
                aria-label="Change appearance"
                type="button"
            >
                <span className="h-6 w-6" aria-hidden="true">{getThemeIcon()}</span>
            </button>

            {showMenu && (
                <div
                    className="absolute top-full right-0 z-50 mt-2 w-64 rounded-lg border border-gray-200 bg-white shadow-lg dark:border-gray-700 dark:bg-gray-800">
                    <div className="p-2">
                        <div className="space-y-1">
                            <button
                                onClick={() => onSelectAppearance('light')}
                                className={`flex w-full items-center space-x-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 ${
                                    appearance === 'light'
                                        ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                        : 'text-gray-700 dark:text-gray-300'
                                }`}
                                type="button"
                            >
                                <span className="text-lg">☀️</span>
                                <div>
                                    <div className="font-medium">Light</div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">Always use light mode
                                    </div>
                                </div>
                            </button>
                            <button
                                onClick={() => onSelectAppearance('dark')}
                                className={`flex w-full items-center space-x-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 ${
                                    appearance === 'dark'
                                        ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                        : 'text-gray-700 dark:text-gray-300'
                                }`}
                                type="button"
                            >
                                <span className="text-lg">🌙</span>
                                <div>
                                    <div className="font-medium">Dark</div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">Always use dark mode</div>
                                </div>
                            </button>
                            <button
                                onClick={() => onSelectAppearance('system')}
                                className={`flex w-full items-center space-x-3 rounded-md px-3 py-2 text-left text-sm transition-colors hover:bg-gray-100 dark:hover:bg-gray-700 ${
                                    appearance === 'system'
                                        ? 'bg-blue-50 text-blue-600 dark:bg-blue-900/20 dark:text-blue-400'
                                        : 'text-gray-700 dark:text-gray-300'
                                }`}
                                type="button"
                            >
                                <span className="text-lg">🔄</span>
                                <div>
                                    <div className="font-medium">System</div>
                                    <div className="text-xs text-gray-500 dark:text-gray-400">Follow system preference
                                    </div>
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}
