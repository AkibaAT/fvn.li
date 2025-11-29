import GameLanguagePill from './GameLanguagePill';
import type {GameCardGame} from '@/hooks/useGameCard';

interface GameLanguageSectionProps {
    languages: GameCardGame['supported_languages'];
    selectedLanguages?: string[];
    hiddenLanguageCount: number;
    languagesExpanded: boolean;
    setLanguagesExpanded: (expanded: boolean) => void;
    languageContainerRef: React.RefObject<HTMLDivElement | null>;
    setLanguageRef: (index: number) => (element: HTMLButtonElement | null) => void;
    handleLanguage: (iso: string) => void;
}

export default function GameLanguageSection({
    languages,
    selectedLanguages = [],
    hiddenLanguageCount,
    languagesExpanded,
    setLanguagesExpanded,
    languageContainerRef,
    setLanguageRef,
    handleLanguage
}: GameLanguageSectionProps) {
    if (!languages || languages.length === 0) return null;

    return (
        <div className="h-auto border-t border-gray-100 pt-2 dark:border-gray-700/50">
            <div className="flex items-center gap-1">
                <div
                    ref={languageContainerRef}
                    className={`relative flex flex-wrap items-start gap-1 transition-all duration-300 flex-1 ${
                        languagesExpanded ? 'max-h-none' : 'h-6 overflow-hidden'
                    }`}
                >
                    {languages.map((language, index) => {
                        const isActive = selectedLanguages.includes(language.iso_code);
                        return (
                            <GameLanguagePill
                                key={language.iso_code}
                                ref={setLanguageRef(index)}
                                language={language}
                                isActive={isActive}
                                onClick={handleLanguage}
                            />
                        );
                    })}
                </div>
                {hiddenLanguageCount > 0 && (
                    <button
                        onClick={() => setLanguagesExpanded(!languagesExpanded)}
                        className="group flex items-center justify-center w-6 h-6 rounded-full hover:bg-gray-100 dark:hover:bg-gray-700 transition-colors duration-200 cursor-pointer flex-shrink-0"
                        title={languagesExpanded ? 'Show less' : `Show ${hiddenLanguageCount} more languages`}
                        aria-label={languagesExpanded ? 'Show less' : `Show ${hiddenLanguageCount} more languages`}
                    >
                        <svg
                            className={`w-4 h-4 text-gray-400 group-hover:text-gray-600 dark:text-gray-500 dark:group-hover:text-gray-300 transition-all duration-200 ${
                                languagesExpanded ? 'rotate-180' : 'rotate-0'
                            }`}
                            fill="none"
                            stroke="currentColor"
                            viewBox="0 0 24 24"
                            aria-hidden="true"
                        >
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2}
                                  d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>
                )}
            </div>
        </div>
    );
}
