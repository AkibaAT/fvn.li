import { JSX } from 'react';
import { useGameStats } from '@/hooks/api';

export function GameManagement(): JSX.Element {
  const { data: gameStats, isLoading: loading } = useGameStats();

  return (
    <div className="rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
      <div className="p-6">
        <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
          Game Management
        </h2>

        <p className="mb-4 text-sm text-gray-600 dark:text-gray-400">
          Manage your games published on itch.io. Add additional download links and update game
          information.
        </p>

        {loading ? (
          <div className="flex items-center justify-center py-4">
            <div className="h-6 w-6 animate-spin rounded-full border-b-2 border-blue-600"></div>
          </div>
        ) : (
          <>
            {gameStats?.itchioUsername ? (
              <div className="mb-4 flex items-center justify-between rounded-lg border border-blue-200 bg-blue-50 p-3 dark:border-blue-800 dark:bg-blue-900/20">
                <div className="flex items-center gap-3">
                  <svg
                    className="h-5 w-5 text-blue-600 dark:text-blue-400"
                    viewBox="0 0 245.371 220.736"
                  >
                    <path
                      fill="currentColor"
                      d="M31.99 1.365C21.287 7.72.2 18.5.2 18.5c-3.615 2.277-3.615 2.277-3.615 8.9v8.9s0 71.05 39.68 71.05c39.68 0 39.68-71.05 39.68-71.05V18.5s-21.086-10.78-31.79-17.135c-5.352-3.177-7.926-4.766-12.165 0z"
                    />
                  </svg>
                  <div>
                    <div className="text-sm font-medium text-blue-800 dark:text-blue-300">
                      Connected: {gameStats.itchioUsername}.itch.io
                    </div>
                    <div className="text-xs text-blue-600 dark:text-blue-400">
                      {gameStats.ownedGamesCount}{' '}
                      {gameStats.ownedGamesCount === 1 ? 'game' : 'games'} found
                      {gameStats.gamesWithLinksCount > 0 && (
                        <>
                          {' '}
                          • {gameStats.gamesWithLinksCount} with download links
                        </>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ) : (
              <div className="mb-4 flex items-center justify-between rounded-lg border border-yellow-200 bg-yellow-50 p-3 dark:border-yellow-800 dark:bg-yellow-900/20">
                <div className="flex items-center gap-3">
                  <svg
                    className="h-5 w-5 text-yellow-600 dark:text-yellow-400"
                    viewBox="0 0 245.371 220.736"
                  >
                    <path
                      fill="currentColor"
                      d="M31.99 1.365C21.287 7.72.2 18.5.2 18.5c-3.615 2.277-3.615 2.277-3.615 8.9v8.9s0 71.05 39.68 71.05c39.68 0 39.68-71.05 39.68-71.05V18.5s-21.086-10.78-31.79-17.135c-5.352-3.177-7.926-4.766-12.165 0z"
                    />
                  </svg>
                  <div className="text-sm text-yellow-800 dark:text-yellow-300">
                    Connect your itch.io account to manage your games
                  </div>
                </div>
              </div>
            )}
          </>
        )}

        <a
          href="/user/games"
          className="inline-flex items-center gap-2 rounded-lg bg-blue-600 px-4 py-2 text-white shadow-md transition-all duration-200 hover:bg-blue-700 hover:shadow-lg"
        >
          <svg
            xmlns="http://www.w3.org/2000/svg"
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
          >
            <path
              strokeLinecap="round"
              strokeLinejoin="round"
              strokeWidth={2}
              d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"
            />
          </svg>
          <span>Manage My Games</span>
        </a>
      </div>
    </div>
  );
}
