<x-layouts.app>
    <div class="flex items-center justify-center min-h-screen bg-gray-100 dark:bg-gray-900">
        <div class="p-8 bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <h2 class="text-xl font-bold mb-6 text-center text-gray-900 dark:text-gray-100">
                Login with Telegram
            </h2>

            <div class="flex justify-center">
                <script async src="https://telegram.org/js/telegram-widget.js?22"
                        data-telegram-login="fvnli_bot"
                        data-size="large"
                        data-userpic="true"
                        data-auth-url="{{ route('auth.callback', ['provider' => 'telegram']) }}"
                        data-request-access="write">
                </script>
            </div>

            <div class="mt-6 text-center">
                <a href="{{ url()->previous() }}" class="text-blue-600 dark:text-blue-400 hover:underline">
                    Cancel and go back
                </a>
            </div>
        </div>
    </div>
</x-layouts.app>
