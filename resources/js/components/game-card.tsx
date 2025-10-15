import GameCardUserSection from './game-card-user-section';
import GameImage from './game-card/GameImage';
import GameTitle from './game-card/GameTitle';
import GameMetadata from './game-card/GameMetadata';
import GamePlatformPill from './game-card/GamePlatformPill';
import GameLanguagePill from './game-card/GameLanguagePill';
import GameTagSection from './game-card/GameTagSection';
import GameStatusBadge from './game-card/GameStatusBadge';
import GameContentBadge from './game-card/GameContentBadge';
import {useGameCard, type GameCardProps} from '@/hooks/useGameCard';
import {usePlatformIcons} from '@/hooks/usePlatformIcons';
import type {Game} from '@/types';

export default function GameCard(props: GameCardProps) {
    const {
        thumbnailUrl,
        authorsInlineHtml,
        handleTag,
        handlePlatform,
        handleLanguage,
        handleStatus,
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
    } = useGameCard(props);

    const {game, selectedTags, selectedPlatforms, selectedLanguages, selectedStatuses, nsfw, showPaid, showDemo, showSale} = props;
    const {getSupportedPlatforms, getPlatformIcon} = usePlatformIcons();

    const supportedPlatforms = getSupportedPlatforms(game);

    return (
        <div
            className="group relative flex h-full flex-col overflow-hidden rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl transition-all duration-300 hover:-translate-y-1 hover:scale-[1.02] hover:shadow-2xl dark:border-gray-700/50 dark:bg-gray-800/70">
            {/* Cover Image */}
            <GameImage game={game} thumbnailUrl={thumbnailUrl} />

            {/* Content */}
            <div className="flex flex-1 flex-col p-4">
                <div className="grid flex-1 auto-rows-min gap-y-3">
                    {/* Title */}
                    <GameTitle game={game} authorsInlineHtml={authorsInlineHtml} />

                    {/* Metadata */}
                    <GameMetadata game={game} />

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
                    <div className="h-8 border-t border-gray-100 pt-2 dark:border-gray-700/50">
                        <div className="flex h-6 flex-nowrap items-center gap-1 overflow-hidden">
                            {game.supported_languages?.map((language) => {
                                const isActive = selectedLanguages?.includes(language.iso_code);
                                return (
                                    <GameLanguagePill
                                        key={language.iso_code}
                                        language={language}
                                        isActive={isActive}
                                        onClick={handleLanguage}
                                    />
                                );
                            })}
                        </div>
                    </div>

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
                        userProgress={game.user_progress?.[0] || null}
                        userListMemberships={game.user_list_memberships || []}
                    />
                </div>
            </div>
        </div>
    );
}
