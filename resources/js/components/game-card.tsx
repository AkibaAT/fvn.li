import GameCardUserSection from './game-card-user-section';
import GameImage from './game-card/GameImage';
import GameTitle from './game-card/GameTitle';
import GameMetadata from './game-card/GameMetadata';
import GamePlatformPill from './game-card/GamePlatformPill';
import GameLanguageSection from './game-card/GameLanguageSection';
import GameTagSection from './game-card/GameTagSection';
import GameStatusBadge from './game-card/GameStatusBadge';
import GameContentBadge from './game-card/GameContentBadge';
import StorePlatformBadge from './game-card/StorePlatformBadge';
import {useGameCard, type GameCardProps} from '@/hooks/useGameCard';
import {usePlatformIcons} from '@/hooks/usePlatformIcons';
import {useStorePlatformIcons} from '@/hooks/useStorePlatformIcons';
import type {Game} from '@/types';
import {usePage} from '@inertiajs/react';
import {useState, useEffect} from 'react';
import axios from 'axios';

export default function GameCard(props: GameCardProps) {
    const {
        thumbnailUrl,
        authorsInlineHtml,
        handleTag,
        handlePlatform,
        handleLanguage,
        handleStatus,
        handleStorePlatform,
        handleNsfwToggle,
        handlePaidToggle,
        handleDemoToggle,
        handleSaleToggle,
        orderedTags,
        tagContainerRef,
        hiddenTagCount,
        setTagRef,
        tagsExpanded,
        setTagsExpanded,
        languageContainerRef,
        hiddenLanguageCount,
        setLanguageRef,
        languagesExpanded,
        setLanguagesExpanded,
    } = useGameCard(props);

    const {game, selectedTags, selectedPlatforms, selectedLanguages, selectedStatuses, nsfw, showPaid, showDemo, showSale, ignoredGameIds, onIgnoreToggle} = props;
    const {getSupportedPlatforms, getPlatformIcon} = usePlatformIcons();
    const {getStorePlatformIcon, getStorePlatformFromString} = useStorePlatformIcons();
    const {auth} = usePage().props as any;

    const [isIgnored, setIsIgnored] = useState(ignoredGameIds?.includes(game.id) || false);
    const [isTogglingIgnore, setIsTogglingIgnore] = useState(false);

    // Sync isIgnored state when ignoredGameIds prop changes
    useEffect(() => {
        setIsIgnored(ignoredGameIds?.includes(game.id) || false);
    }, [ignoredGameIds, game.id]);

    const supportedPlatforms = getSupportedPlatforms(game);
    const storePlatform = game.platform ? getStorePlatformFromString(game.platform) : 'itch_io';

    const handleIgnoreToggle = async (e: React.MouseEvent) => {
        e.preventDefault();
        e.stopPropagation();

        if (!auth?.user || isTogglingIgnore) return;

        setIsTogglingIgnore(true);
        try {
            const response = await axios.post(route('user.ignored-games.toggle'), {
                game_id: game.id,
            });

            if (response.data.success) {
                setIsIgnored(response.data.is_ignored);
                // Call parent callback if provided
                if (onIgnoreToggle) {
                    onIgnoreToggle(game.id, response.data.is_ignored, response.data.ignored_game_ids);
                }
            }
        } catch (error) {
            console.error('Failed to toggle ignore status:', error);
        } finally {
            setIsTogglingIgnore(false);
        }
    };

    return (
        <div
            className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] hover:shadow-2xl dark:border-gray-700/50 dark:bg-gray-800/70">
            {/* Ignore Button - Only show for authenticated users */}
            {auth?.user && (
                <button
                    onClick={handleIgnoreToggle}
                    disabled={isTogglingIgnore}
                    className="absolute right-2 top-2 z-10 rounded-full bg-white/90 p-2 shadow-lg backdrop-blur-sm transition-all duration-200 hover:bg-white hover:scale-110 dark:bg-gray-800/90 dark:hover:bg-gray-800"
                    title={isIgnored ? 'Remove from ignore list' : 'Add to ignore list'}
                    aria-label={isIgnored ? 'Remove from ignore list' : 'Add to ignore list'}
                >
                    {isIgnored ? (
                        <svg className="h-5 w-5 text-red-600 dark:text-red-400" fill="currentColor" viewBox="0 0 20 20">
                            <path fillRule="evenodd" d="M13.477 14.89A6 6 0 015.11 6.524l8.367 8.368zm1.414-1.414L6.524 5.11a6 6 0 018.367 8.367zM18 10a8 8 0 11-16 0 8 8 0 0116 0z" clipRule="evenodd" />
                        </svg>
                    ) : (
                        <svg className="h-5 w-5 text-gray-600 dark:text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                        </svg>
                    )}
                </button>
            )}

            {/* Cover Image */}
            <GameImage game={game} thumbnailUrl={thumbnailUrl} />

            {/* Content */}
            <div className="flex flex-1 flex-col p-4">
                <div className="grid flex-1 auto-rows-min gap-y-3">
                    {/* Title */}
                    <GameTitle game={game} authorsInlineHtml={authorsInlineHtml} />

                    {/* Metadata */}
                    <GameMetadata game={game} />

                    {/* Store Platform Badge */}
                    {storePlatform && (
                        <div className="flex items-center gap-2">
                            <StorePlatformBadge
                                platform={storePlatform}
                                iconMeta={getStorePlatformIcon(storePlatform)}
                                isActive={props.selectedStorePlatforms?.includes(storePlatform)}
                                onClick={handleStorePlatform}
                            />
                        </div>
                    )}

                    {/* Platforms */}
                    <div className="h-8 border-t border-gray-100 pt-2 dark:border-gray-700/50">
                        <div className="flex h-6 flex-nowrap items-center gap-1 overflow-hidden">
                            {supportedPlatforms.map((platform) => {
                                const isActive = selectedPlatforms?.includes(platform);
                                return (
                                    <GamePlatformPill
                                        key={platform}
                                        platform={platform}
                                        isActive={isActive}
                                        iconMeta={getPlatformIcon(platform)}
                                        onClick={handlePlatform}
                                    />
                                );
                            })}
                        </div>
                    </div>

                    {/* Languages */}
                    <GameLanguageSection
                        languages={game.supported_languages}
                        selectedLanguages={selectedLanguages}
                        hiddenLanguageCount={hiddenLanguageCount}
                        languagesExpanded={languagesExpanded}
                        setLanguagesExpanded={setLanguagesExpanded}
                        languageContainerRef={languageContainerRef}
                        setLanguageRef={setLanguageRef}
                        handleLanguage={handleLanguage}
                    />

                    {/* Tags */}
                    <GameTagSection
                        orderedTags={orderedTags}
                        selectedTags={selectedTags}
                        hiddenTagCount={hiddenTagCount}
                        tagsExpanded={tagsExpanded}
                        setTagsExpanded={setTagsExpanded}
                        tagContainerRef={tagContainerRef}
                        setTagRef={setTagRef}
                        handleTag={handleTag}
                    />

                    {/* Footer badges */}
                    {(game.is_nsfw ||
                        Boolean((game as Game).is_on_sale) ||
                        game.is_paid ||
                        game.has_demo ||
                        Boolean(game.status)) && (
                        <div
                            className="flex flex-wrap items-center gap-2 border-t border-gray-100 pt-3 dark:border-gray-700/50">
                            <GameStatusBadge
                                game={game}
                                isActive={selectedStatuses?.includes(String(game.status))}
                                onClick={handleStatus}
                            />
                            <GameContentBadge
                                game={game}
                                nsfw={nsfw}
                                showPaid={showPaid}
                                showDemo={showDemo}
                                showSale={showSale}
                                onNsfwToggle={handleNsfwToggle}
                                onPaidToggle={handlePaidToggle}
                                onDemoToggle={handleDemoToggle}
                                onSaleToggle={handleSaleToggle}
                            />
                        </div>
                    )}

                    {/* User Management Section */}
                    <GameCardUserSection
                        gameId={game.id}
                        gameName={game.name}
                        isPaid={game.is_paid}
                        userProgress={game.user_progress?.[0] ?? null}
                        userListMemberships={game.user_list_memberships || []}
                    />
                </div>
            </div>
        </div>
    );
}
