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
            <header
                className="sticky top-0 z-50 nav-glass"
                role="banner"
                aria-label="Main navigation"
            >
                {/* Subtle accent bar */}
                <div className="h-0.5 bg-[var(--color-brand-primary)]" />

                <Container>
                    <div className="flex items-center justify-between py-3">
                        {/* Logo & Brand */}
                        <Logo />

                        {/* Navigation */}
                        <Navigation />

                        {/* Search Bar */}
                        <div className="mx-6 hidden max-w-md flex-1 lg:flex" role="search">
                            <SearchBar />
                        </div>

                        {/* Right side actions */}
                        <div className="flex items-center gap-2">
                            {/* Mobile Search Button */}
                            <button
                                onClick={toggleMobileSearch}
                                aria-expanded={showMobileSearch}
                                aria-controls="mobile-search-bar"
                                aria-label={showMobileSearch ? 'Hide search' : 'Show search'}
                                className="flex h-9 w-9 cursor-pointer items-center justify-center rounded-lg bg-[var(--color-ui-surface-alt)] text-[var(--color-brand-primary)] transition-colors hover:bg-[var(--color-surface-peach)] hover:text-[var(--color-brand-primary-dark)] lg:hidden"
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
                                            d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"
                                        />
                                    </svg>
                                )}
                            </button>

                            {/* Notifications */}
                            <NotificationsDropdown />

                            {/* User Menu */}
                            <UserMenu />

                            {/* Theme Toggle */}
                            <AppearanceDropdown />
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
