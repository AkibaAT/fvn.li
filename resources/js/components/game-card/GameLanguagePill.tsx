import type {GameCardGame} from '@/hooks/useGameCard';
import {forwardRef} from 'react';

interface GameLanguagePillProps {
    language: NonNullable<GameCardGame['supported_languages']>[0];
    isActive?: boolean;
    onClick: (iso: string) => void;
}

const GameLanguagePill = forwardRef<HTMLButtonElement, GameLanguagePillProps>(
    ({language, isActive = false, onClick}, ref) => {
        return (
            <button
                ref={ref}
                key={language.iso_code}
            onClick={() => onClick(language.iso_code)}
            className={`inline-flex cursor-pointer items-center rounded border px-1.5 py-1 text-xs transition-colors ${
                isActive
                    ? 'border-teal-700 bg-teal-600 text-white dark:border-teal-500 dark:bg-teal-700'
                    : 'border-gray-200 bg-white text-gray-700 hover:bg-teal-50 dark:border-gray-600/50 dark:bg-gray-700/50 dark:text-gray-200 dark:hover:bg-teal-900/20'
            }`}
            title={language.ref_name}
            aria-label={language.ref_name}
            aria-pressed={isActive}
        >
            <span
                className={`fi fi-${language.flag_code} rounded-xs`}
            />
        </button>
    );
});

GameLanguagePill.displayName = 'GameLanguagePill';

export default GameLanguagePill;