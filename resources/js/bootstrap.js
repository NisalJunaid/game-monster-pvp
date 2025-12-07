/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

const csrfToken = document.head.querySelector('meta[name="csrf-token"]');
if (csrfToken) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = csrfToken.content;
}

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

if (import.meta.env.VITE_PUSHER_APP_KEY) {
    window.Pusher = Pusher;

    window.Echo = new Echo({
        broadcaster: 'pusher',
        key: import.meta.env.VITE_PUSHER_APP_KEY,
        cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER ?? 'mt1',
        wsHost: import.meta.env.VITE_PUSHER_HOST ?? window.location.hostname,
        wsPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
        wssPort: import.meta.env.VITE_PUSHER_PORT ?? 6001,
        forceTLS: (import.meta.env.VITE_PUSHER_SCHEME ?? 'https') === 'https',
        enabledTransports: ['ws', 'wss'],
    });

    const updateEchoStatus = (state, error = null) => {
        window.__echoConnectionState = state;
        window.dispatchEvent(new CustomEvent('echo:status', { detail: { state, error } }));
        console.info('[echo]', state, error || '');
    };

    const pusherConnection = window.Echo.connector?.pusher?.connection;

    if (pusherConnection) {
        updateEchoStatus('connecting');

        pusherConnection.bind('connected', () => updateEchoStatus('connected'));
        pusherConnection.bind('disconnected', () => updateEchoStatus('disconnected'));
        pusherConnection.bind('error', (event) => updateEchoStatus('error', event));
        pusherConnection.bind('state_change', (states) => updateEchoStatus(states.current));
    }
} else {
    window.dispatchEvent(new CustomEvent('echo:status', { detail: { state: 'disconnected', error: null } }));
}
