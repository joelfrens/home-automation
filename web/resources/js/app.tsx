import { createInertiaApp } from '@inertiajs/react';
import { Toaster } from '@/components/ui/sonner';
import { TooltipProvider } from '@/components/ui/tooltip';
import { initializeTheme } from '@/hooks/use-appearance';
import AppLayout from '@/layouts/app-layout';
import AuthLayout from '@/layouts/auth-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { configureEcho } from '@laravel/echo-react';

/**
 * Private channels: before Reverb lets this browser listen, it asks Laravel
 * POST /broadcasting/auth "may I join private-readings?". Laravel checks the session (are you
 * logged in?) and routes/channels.php, then returns a signed "yes" that the browser hands to Reverb.
 *
 * That POST needs the CSRF token. We read Laravel's XSRF-TOKEN cookie at the moment of asking
 * (not once at start-up), because logging in renews the token without reloading the page.
 */
function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

// Echo = the browser side of Laravel broadcasting. It opens ONE WebSocket to Reverb and
// shares it between every useEcho() on the page.
configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    // Reverb runs on the same machine that served this page, so use the page's host name:
    // works for localhost on the Mac, 192.168.x.x from a phone, and the Pi's own screen.
    // (No window during server-side rendering; Echo only connects in the browser anyway.)
    wsHost:
        typeof window === 'undefined' ? 'localhost' : window.location.hostname,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8080),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],
    channelAuthorization: {
        endpoint: '/broadcasting/auth',
        transport: 'ajax',
        customHandler: ({ socketId, channelName }, callback) => {
            fetch('/broadcasting/auth', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-XSRF-TOKEN': xsrfToken(),
                },
                body: JSON.stringify({
                    socket_id: socketId,
                    channel_name: channelName,
                }),
            })
                .then((response) =>
                    response.ok
                        ? response.json()
                        : Promise.reject(
                              new Error(
                                  `Not allowed to listen to ${channelName} (HTTP ${response.status})`,
                              ),
                          ),
                )
                .then((data) => callback(null, data))
                .catch((error: Error) => callback(error, null));
        },
    },
});

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

void createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    layout: (name) => {
        switch (true) {
            case name.startsWith('auth/'):
                return AuthLayout;
            case name.startsWith('settings/'):
                return [AppLayout, SettingsLayout];
            default:
                return AppLayout;
        }
    },
    strictMode: true,
    withApp(app) {
        return (
            <TooltipProvider delayDuration={0}>
                {app}
                <Toaster />
            </TooltipProvider>
        );
    },
    progress: {
        color: '#4B5563',
    },
});

// This will set light / dark mode on load...
initializeTheme();
