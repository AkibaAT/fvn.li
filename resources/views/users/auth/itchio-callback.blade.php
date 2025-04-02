<x-layouts.app centered="true">
    <div class="p-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
        <h2 class="text-xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100">
            Completing itch.io Login
        </h2>

        <div class="flex flex-col items-center justify-center space-y-4">
            <x-ui.icons.itchio class="h-12 w-12" />
            <div class="flex items-center justify-center space-x-3">
                <svg class="animate-spin h-5 w-5 text-blue-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <span class="text-gray-700 dark:text-gray-300">Processing authentication...</span>
            </div>
        </div>

        <div class="mt-6 text-center">
            <a href="{{ url()->previous() }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                Cancel and go back
            </a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Get the hash fragment from the URL
            const hash = window.location.hash.substring(1);
            const params = new URLSearchParams(hash);
            const accessToken = params.get('access_token');

            if (accessToken) {
                // Redirect to the server's callback URL with the hash as a query parameter
                window.location.href = '{{ route('auth.itchio.process') }}?hash=' + encodeURIComponent(hash);
            } else {
                // If no hash fragment is found, show an error
                const container = document.querySelector('.bg-white');
                container.innerHTML = `
                    <h2 class="text-xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100">
                        Authentication Error
                    </h2>
                    <div class="flex items-center justify-center space-x-3 text-red-600 dark:text-red-400">
                        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                        <span>We couldn't complete your itch.io login. Please try again.</span>
                    </div>
                    <div class="mt-6 text-center">
                        <a href="{{ url()->previous() }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                            Go back and try again
                        </a>
                    </div>
                `;
            }
        });
    </script>
</x-layouts.app>
