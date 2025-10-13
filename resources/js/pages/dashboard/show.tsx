import {AdditionRequestForm} from '@/components/dashboard/addition-request-form';
import {ConnectedAccounts} from '@/components/dashboard/connected-accounts';
import {DangerZone} from '@/components/dashboard/danger-zone';
import {GameManagement} from '@/components/dashboard/game-management';
import {NotificationSettings} from '@/components/dashboard/notification-settings';
import {UserAdditionRequests} from '@/components/dashboard/user-addition-requests';
import {SocialAccount, User} from '@/types';
import {Head} from '@inertiajs/react';
import {useState} from 'react';

interface Props {
    metaTags: {
        title: string;
        description: string;
        image?: string;
    };
    user: User;
    connectedProviders: string[];
    socialAccounts: Record<string, SocialAccount>;
    flash: {
        success?: string;
        error?: string;
    };
}

export default function Dashboard({
                                      metaTags,
                                      user,
                                      connectedProviders,
                                      socialAccounts,
                                      flash,
                                  }: Props) {
    const [flashMessage, setFlashMessage] = useState(flash);

    const clearFlash = () => {
        setFlashMessage({success: undefined, error: undefined});
    };

    return (
        <>
            <Head title={metaTags.title}/>

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-bold text-gray-900 dark:text-white">
                    {metaTags.title || 'User Dashboard'}
                </h1>
            </div>

            {/* Flash Messages */}
            {flashMessage.success && (
                <div
                    className="relative mb-4 rounded-lg border border-green-200 bg-green-100 p-4 text-green-700 dark:border-green-800 dark:bg-green-900/20 dark:text-green-300">
                    {flashMessage.success}
                    <button
                        onClick={clearFlash}
                        className="absolute top-2 right-2 text-green-500 hover:text-green-700"
                    >
                        ×
                    </button>
                </div>
            )}

            {flashMessage.error && (
                <div
                    className="relative mb-4 rounded-lg border border-red-200 bg-red-100 p-4 text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
                    {flashMessage.error}
                    <button
                        onClick={clearFlash}
                        className="absolute top-2 right-2 text-red-500 hover:text-red-700"
                    >
                        ×
                    </button>
                </div>
            )}

            <div className="grid grid-cols-1 gap-6 lg:grid-cols-5">
                {/* Left Column - User Settings */}
                <div className="space-y-6 lg:col-span-3">
                    {/* Profile Section */}
                    <div
                        className="rounded-2xl border border-gray-200/50 bg-white/70 shadow-lg backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-800/70">
                        <div className="p-6">
                            <h2 className="mb-4 text-lg font-semibold text-gray-900 dark:text-white">
                                Profile Information
                            </h2>
                            <div className="flex items-center gap-4">
                                {user.avatar ? (
                                    <img
                                        src={user.avatar}
                                        alt={user.name}
                                        className="h-16 w-16 rounded-full ring-2 ring-blue-500/20"
                                    />
                                ) : (
                                    <div
                                        className="flex h-16 w-16 items-center justify-center rounded-full bg-blue-600 text-2xl font-bold text-white ring-2 ring-blue-500/20">
                                        {user.name.charAt(0)}
                                    </div>
                                )}
                                <div>
                                    <div className="text-xl font-medium text-gray-900 dark:text-white">
                                        {user.name}
                                    </div>
                                    {user.email && (
                                        <div className="text-gray-500 dark:text-gray-400">
                                            {user.email}
                                        </div>
                                    )}
                                </div>
                            </div>

                            <div className="mt-4">
                                <a
                                    href="/export"
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
                                            d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"
                                        />
                                    </svg>
                                    <span>Export My Data</span>
                                </a>
                            </div>
                        </div>
                    </div>

                    {/* Notification Settings Section */}
                    <div className="lg:mt-9">
                        <NotificationSettings/>
                    </div>

                    {/* Addition Request Form Section */}
                    <div className="lg:mt-9">
                        <AdditionRequestForm/>
                    </div>

                    {/* User Addition Requests Section */}
                    <div className="lg:mt-9">
                        <UserAdditionRequests/>
                    </div>

                    {/* Game Management Section */}
                    <div className="lg:mt-9">
                        <GameManagement/>
                    </div>
                </div>

                {/* Right Column - Account Connections */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Connected Accounts Section */}
                    <ConnectedAccounts
                        user={user}
                        connectedProviders={connectedProviders}
                        socialAccounts={socialAccounts}
                    />

                    {/* Danger Zone */}
                    <DangerZone/>
                </div>
            </div>
        </>
    );
}
