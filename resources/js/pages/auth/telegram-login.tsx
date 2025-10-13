import {Head, Link} from '@inertiajs/react';
import {useEffect, useRef} from 'react';

interface TelegramLoginProps {
    metaTags?: {
        title?: string;
    };
}

export default function TelegramLogin({metaTags}: TelegramLoginProps) {
    const widgetContainerRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        // Inject Telegram login widget script with required data- attributes
        const scriptEl = document.createElement('script');
        scriptEl.async = true;
        scriptEl.src = 'https://telegram.org/js/telegram-widget.js?22';
        scriptEl.setAttribute('data-telegram-login', 'fvnli_bot');
        scriptEl.setAttribute('data-size', 'large');
        scriptEl.setAttribute('data-userpic', 'true');
        scriptEl.setAttribute(
            'data-auth-url',
            route('auth.callback', {provider: 'telegram'}),
        );
        scriptEl.setAttribute('data-request-access', 'write');

        const container = widgetContainerRef.current;
        if (container) {
            container.innerHTML = '';
            container.appendChild(scriptEl);
        }

        return () => {
            // Cleanup injected script and any rendered widget
            if (container) {
                container.innerHTML = '';
            }
        };
    }, []);

    const title = metaTags?.title || 'Login with Telegram';

    return (
        <>
            <Head title={title}/>

            <div className="flex min-h-[70vh] items-center justify-center">
                <div className="w-full max-w-md rounded-lg bg-white p-8 text-center shadow-md dark:bg-gray-800">
                    <h2 className="mb-6 text-xl font-bold text-gray-900 dark:text-gray-100">
                        {title}
                    </h2>

                    <div
                        className="flex justify-center"
                        ref={widgetContainerRef}
                    />

                    <div className="mt-6">
                        <Link
                            href={route('home')}
                            className="text-blue-600 hover:underline dark:text-blue-400"
                        >
                            Cancel and go back
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
