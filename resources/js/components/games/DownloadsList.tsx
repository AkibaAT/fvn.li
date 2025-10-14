import React from 'react';
import {formatLocalDate} from '@/utils/date-formatting';

export interface AdditionalLink {
  id: number | string;
  url: string;
  name: string;
  platform?: string | null;
  last_edited_at?: string | null;
}

export interface DownloadsListProps {
  gameId: number;
  links: AdditionalLink[];
  getPlatformIcon: (platform: string) => React.ReactNode;
}

export default function DownloadsList({ gameId, links, getPlatformIcon }: DownloadsListProps) {
  if (!links || links.length === 0) return null;

  return (
    <div id="downloads" className="mb-6 scroll-mt-28 rounded-lg bg-white p-6 shadow-sm dark:bg-gray-800">
      <h2 className="mb-6 text-xl font-semibold text-gray-900 dark:text-gray-100">Downloads</h2>
      <div className="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        {links.map((link) => (
          <a
            key={link.id}
            href={route('track.custom-link', { game_id: gameId, link_id: link.id, url: link.url })}
            target="_blank"
            rel="noopener noreferrer"
            className="group flex items-center gap-4 rounded-lg border border-gray-200 p-4 transition-all duration-200 hover:border-blue-300 hover:bg-gray-50 dark:border-gray-600 dark:hover:border-blue-500 dark:hover:bg-gray-700"
          >
            <div className="flex-shrink-0">
              <div className="rounded-lg bg-gray-50 p-2 text-xl dark:bg-gray-700">
                {getPlatformIcon(link.platform || 'external')}
              </div>
            </div>
            <div className="min-w-0 flex-1">
              <div className="mb-1 font-semibold text-gray-900 group-hover:text-blue-600 dark:text-white dark:group-hover:text-blue-400">
                {link.name}
              </div>
              <div className="flex items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                {link.platform && (
                  <span className="font-medium capitalize">{link.platform}</span>
                )}
                {link.last_edited_at && (
                  <>
                    {link.platform && <span>•</span>}
                    <span>
                      Updated {formatLocalDate(link.last_edited_at)}
                    </span>
                  </>
                )}
              </div>
            </div>
            <div className="flex-shrink-0">
              <svg className="h-5 w-5 text-gray-400 transition-colors group-hover:text-blue-500 dark:group-hover:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M17 8l4 4m0 0l-4 4m4-4H3" />
              </svg>
            </div>
          </a>
        ))}
      </div>
    </div>
  );
}

