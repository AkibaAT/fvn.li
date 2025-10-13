// Test for filter button visibility logic
// Run this in browser console to verify the route-based logic

console.log('Testing filter button visibility logic...');

// Mock the route function to return a known games index URL
const mockRoute = (name) => {
    if (name === 'games.index') return 'https://example.com/games';
    return 'https://example.com/' + name;
};

// Test different URLs
const testUrls = [
    'https://example.com/games',           // Should show (✓)
    'https://example.com/games?search=test', // Should show (✓)
    'https://example.com/games?filters=1',   // Should show (✓)
    'https://example.com/games/some-slug',   // Should NOT show (❌)
    'https://example.com/my/games',          // Should NOT show (❌)
    'https://example.com/my/games/slug/edit', // Should NOT show (❌)
    'https://example.com/dashboard',         // Should NOT show (❌)
];

console.log('Testing with route-based logic (IMPROVED):');
testUrls.forEach(url => {
    const gamesIndexUrl = mockRoute('games.index');
    const shouldShow = url === gamesIndexUrl || url.startsWith(gamesIndexUrl + '?');
    console.log(`${url}: ${shouldShow ? '✅ SHOW' : '❌ HIDE'}`);
});

console.log('\nCompared to old hardcoded logic (PROBLEMATIC):');
testUrls.forEach(url => {
    const shouldShow = url.includes('/games');
    console.log(`${url}: ${shouldShow ? '✅ SHOW' : '❌ HIDE'}`);
});
