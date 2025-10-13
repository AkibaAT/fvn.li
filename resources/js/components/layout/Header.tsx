import React, {useState, memo, useCallback} from 'react';
import Container from '@/components/container';
import Logo from '@/components/layout/Logo';
import Navigation from '@/components/layout/Navigation';
import SearchBar from '@/components/layout/SearchBar';
import MobileSearch from '@/components/layout/MobileSearch';
import NotificationsDropdown from '@/components/layout/NotificationsDropdown';
import UserMenu from '@/components/layout/UserMenu';
import AppearanceDropdown from '@/components/appearance-dropdown';
const Header = memo(() => {
    const [showMobileSearch, setShowMobileSearch] = useState(false);

    const toggleMobileSearch = useCallback(() => {
        setShowMobileSearch(prev => !prev);
    }, []);

    const closeMobileSearch = useCallback(() => {
        setShowMobileSearch(false);
    }, []);

    return (
        <>
            {/* Modern Header */}
            <header
                className="sticky top-0 z-50 border-b border-gray-200/50 bg-white/80 shadow-sm backdrop-blur-xl dark:border-gray-700/50 dark:bg-gray-900/80"
                role="banner"
                aria-label="Main navigation">
                <Container>
                    <div className="flex items-center justify-between py-4">
                        {/* Logo & Brand */}
                        <Logo/>

                        {/* Navigation */}
                        <Navigation/>

                        {/* Search Bar */}
                        <div className="mx-8 hidden max-w-lg flex-1 lg:flex" role="search">
                            <SearchBar />
                        </div>

                        {/* Mobile Search Button (toggle) */}
                        <div className="flex items-center space-x-2 lg:hidden">
                            <button
                                onClick={toggleMobileSearch}
                                aria-expanded={showMobileSearch}
                                aria-controls="mobile-search-bar"
                                aria-label={showMobileSearch ? 'Hide search' : 'Show search'}
                                className="cursor-pointer rounded-lg bg-gray-100 p-2 transition-colors duration-200 hover:bg-gray-200 dark:bg-gray-800 dark:hover:bg-gray-700"
                            >
                                {showMobileSearch ? (
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
                                            d="M6 18L18 6M6 6l12 12"
                                        />
                                    </svg>
                                ) : (
                                    <span className="text-lg">🔍</span>
                                )}

	                        {/* Notification bell with unread count */}
	                        {/* Count is shown inside dropdown via fetched list; optional indicator could be added here later */}
                            </button>
                        </div>

                        {/* User Menu */}
                        <div className="flex items-center space-x-3">
                            <NotificationsDropdown />
                            <UserMenu />
                            <AppearanceDropdown/>
                        </div>
                    </div>
                </Container>
            </header>

            {/* Mobile Search Modal */}
            <MobileSearch
                isOpen={showMobileSearch}
                onClose={closeMobileSearch}
            />
        </>
    );
});

Header.displayName = 'Header';

export default Header;