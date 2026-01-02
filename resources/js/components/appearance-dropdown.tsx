import {type Appearance, useAppearance} from '@/hooks/use-appearance';
import {useEffect, useRef, useState} from 'react';

const SunIcon = () => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
    </svg>
);

const MoonIcon = () => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
    </svg>
);

const SystemIcon = () => (
    <svg className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth={2}>
        <path strokeLinecap="round" strokeLinejoin="round" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
    </svg>
);

export default function AppearanceDropdown() {
    const {appearance, updateAppearance} = useAppearance();
    const [showMenu, setShowMenu] = useState(false);
    const containerRef = useRef<HTMLDivElement>(null);

    const getThemeIcon = () => {
        if (typeof document !== 'undefined') {
            const isDarkApplied = document.documentElement.classList.contains('dark');
            return isDarkApplied ? <MoonIcon /> : <SunIcon />;
        }
        return <MoonIcon />;
    };

    const onSelectAppearance = (mode: Appearance) => {
        updateAppearance(mode);
        setShowMenu(false);
    };

    useEffect(() => {
        const handleClickOutside = (event: MouseEvent) => {
            if (!showMenu) return;
            const target = event.target as Element;
            if (containerRef.current && !containerRef.current.contains(target)) {
                setShowMenu(false);
            }
        };

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, [showMenu]);

    const optionBase = "flex w-full items-center gap-3 rounded-lg px-3 py-2.5 text-left text-sm transition-all duration-200";
    const optionActive = "bg-teal-50 text-teal-700 dark:bg-teal-900/30 dark:text-teal-300";
    const optionInactive = "text-gray-700 hover:bg-stone-100 dark:text-gray-300 dark:hover:bg-gray-700";

    return (
        <div className="theme-menu-container relative" ref={containerRef}>
            <button
                onClick={() => setShowMenu(!showMenu)}
                className="flex h-9 w-9 items-center justify-center rounded-lg bg-stone-100 text-stone-600 transition-colors duration-200 hover:bg-stone-200 hover:text-stone-900 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-gray-700 dark:hover:text-gray-200"
                title="Change appearance"
                aria-label="Change appearance"
                aria-expanded={showMenu}
                type="button"
            >
                <span aria-hidden="true">{getThemeIcon()}</span>
            </button>

            {showMenu && (
                <div className="absolute top-full right-0 z-50 mt-2 w-64 rounded-xl border border-stone-200 bg-white p-1.5 shadow-xl shadow-stone-200/50 dark:border-gray-700 dark:bg-gray-800 dark:shadow-black/20">
                    <div className="space-y-0.5">
                        <button
                            onClick={() => onSelectAppearance('light')}
                            className={`${optionBase} ${appearance === 'light' ? optionActive : optionInactive}`}
                            type="button"
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900/30 dark:text-amber-400">
                                <SunIcon />
                            </span>
                            <div>
                                <div className="font-medium">Light</div>
                                <div className="text-xs text-gray-500 dark:text-gray-400">Always use light mode</div>
                            </div>
                        </button>
                        <button
                            onClick={() => onSelectAppearance('dark')}
                            className={`${optionBase} ${appearance === 'dark' ? optionActive : optionInactive}`}
                            type="button"
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-indigo-100 text-indigo-600 dark:bg-indigo-900/30 dark:text-indigo-400">
                                <MoonIcon />
                            </span>
                            <div>
                                <div className="font-medium">Dark</div>
                                <div className="text-xs text-gray-500 dark:text-gray-400">Always use dark mode</div>
                            </div>
                        </button>
                        <button
                            onClick={() => onSelectAppearance('system')}
                            className={`${optionBase} ${appearance === 'system' ? optionActive : optionInactive}`}
                            type="button"
                        >
                            <span className="flex h-8 w-8 items-center justify-center rounded-lg bg-stone-200 text-stone-600 dark:bg-gray-700 dark:text-gray-400">
                                <SystemIcon />
                            </span>
                            <div>
                                <div className="font-medium">System</div>
                                <div className="text-xs text-gray-500 dark:text-gray-400">Follow system preference</div>
                            </div>
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
