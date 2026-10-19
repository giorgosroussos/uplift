import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

declare global {
    interface Window {
        Pusher: typeof Pusher;
        Echo?: Echo<'reverb'>;
    }
}

/**
 * Connects Laravel Echo to Reverb. Only private and presence channels that
 * carry the organisation id may be used (SEC-010). Connection settings come
 * from the VITE_REVERB_* environment variables, never from code.
 */
export function initializeEcho(): Echo<'reverb'> | undefined {
    const key = import.meta.env.VITE_REVERB_APP_KEY;

    if (typeof key !== 'string' || key === '') {
        return undefined;
    }

    window.Pusher = Pusher;

    const scheme = String(import.meta.env.VITE_REVERB_SCHEME ?? 'https');

    window.Echo = new Echo({
        broadcaster: 'reverb',
        key,
        wsHost: String(
            import.meta.env.VITE_REVERB_HOST ?? window.location.hostname,
        ),
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
        forceTLS: scheme === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    return window.Echo;
}
