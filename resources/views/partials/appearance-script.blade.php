<script>
    (function () {
        try {
            var doc = document.documentElement;
            var cookieMatch = document.cookie.match(/(?:^|;\s*)appearance=([^;]+)/);
            var cookieAppearance = cookieMatch ? decodeURIComponent(cookieMatch[1]) : null;
            var storedAppearance = localStorage.getItem('appearance');
            var valid = function (value) {
                return value === 'light' || value === 'dark' || value === 'system';
            };
            var appearance = valid(cookieAppearance) ? cookieAppearance : (valid(storedAppearance) ? storedAppearance : 'system');

            if (!valid(cookieAppearance)) {
                document.cookie = 'appearance=' + encodeURIComponent(appearance) + ';path=/;max-age=31536000;SameSite=Lax';
            }

            var isDark = appearance === 'dark' || (appearance === 'system' && window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches);

            if (isDark) {
                doc.classList.add('dark');
            } else {
                doc.classList.remove('dark');
            }
        } catch (e) {
            // no-op
        }
    })();
</script>
