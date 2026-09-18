/**
 * NutriVision AI - Web Push Notifications Module
 *
 * This module handles the entire client-side push notification flow:
 *
 *   1. Initialize Firebase with the public web config.
 *   2. Register the firebase-messaging-sw.js service worker.
 *   3. Request notification permission (only if not already granted/denied).
 *   4. Generate the FCM registration token.
 *   5. Send the token to the backend (api/save_token.php).
 *   6. Listen for token refresh and update the backend.
 *   7. Handle permission denied gracefully (no repeated prompts).
 *
 * Usage (in any PHP page after including the Firebase SDK):
 *   <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-app-compat.js"></script>
 *   <script src="https://www.gstatic.com/firebasejs/10.12.2/firebase-messaging-compat.js"></script>
 *   <script src="js/push-notifications.js"></script>
 *   <script>PushNotifications.init();</script>
 *
 * The window.FIREBASE_CONFIG and window.FIREBASE_VAPID_PUBLIC_KEY
 * variables must be set by the PHP page before calling init().
 */

const PushNotifications = (function () {

    let messaging = null;
    let initialized = false;

    // --- LocalStorage keys for tracking permission state ---
    const STORAGE_KEY_PERMISSION_ASKED = 'nutrivision_fcm_permission_asked';
    const STORAGE_KEY_LAST_TOKEN       = 'nutrivision_fcm_last_token';

    /**
     * Check if the browser supports service workers and push.
     */
    function isSupported() {
        return ('serviceWorker' in navigator) &&
               ('PushManager' in window) &&
               ('Notification' in window) &&
               (typeof firebase !== 'undefined');
    }

    /**
     * Check if Firebase config is available.
     */
    function hasConfig() {
        return window.FIREBASE_CONFIG &&
               window.FIREBASE_CONFIG.apiKey &&
               window.FIREBASE_VAPID_PUBLIC_KEY;
    }

    /**
     * Register the service worker.
     */
    async function registerServiceWorker() {
        if (!('serviceWorker' in navigator)) {
            console.warn('[Push] Service workers not supported.');
            return null;
        }

        try {
            const registration = await navigator.serviceWorker.register(
                '/firebase-messaging-sw.js',
                { scope: '/' }
            );
            return registration;
        } catch (err) {
            console.error('[Push] Service worker registration failed:', err);
            return null;
        }
    }

    /**
     * Request notification permission from the user.
     * Returns 'granted', 'denied', or 'default'.
     */
    async function requestPermission() {
        // Mark that we've asked (so we don't ask again if denied)
        localStorage.setItem(STORAGE_KEY_PERMISSION_ASKED, '1');

        const permission = await Notification.requestPermission();
        return permission;
    }

    /**
     * Get the FCM token and send it to the backend.
     */
    async function getTokenAndSave() {
        if (!messaging) return;

        try {
            const registration = await navigator.serviceWorker.getRegistration();

            const currentToken = await messaging.getToken({
                vapidKey: window.FIREBASE_VAPID_PUBLIC_KEY,
                serviceWorkerRegistration: registration,
            });

            if (currentToken) {
                // Check if token has changed
                const lastToken = localStorage.getItem(STORAGE_KEY_LAST_TOKEN);
                if (lastToken !== currentToken) {
                    await sendTokenToBackend(currentToken);
                    localStorage.setItem(STORAGE_KEY_LAST_TOKEN, currentToken);
                    console.log('[Push] FCM token saved to server.');
                }
            } else {
                console.warn('[Push] No FCM token available. Permission may not be granted.');
            }
        } catch (err) {
            console.error('[Push] Error getting FCM token:', err);
        }
    }

    /**
     * Send the FCM token to the backend.
     */
    async function sendTokenToBackend(token) {
        const deviceInfo = getDeviceInfo();

        try {
            const response = await fetch('/api/save_token.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token: token,
                    device_info: deviceInfo,
                }),
            });

            const data = await response.json();
            if (!data.success) {
                console.error('[Push] Backend rejected token:', data.error);
            }
            return data;
        } catch (err) {
            console.error('[Push] Failed to send token to backend:', err);
            return { success: false, error: err.message };
        }
    }

    /**
     * Remove the FCM token from the backend (when permission is revoked).
     */
    async function removeTokenFromBackend(token) {
        try {
            await fetch('/api/remove_token.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ token: token }),
            });
        } catch (err) {
            console.error('[Push] Failed to remove token from backend:', err);
        }
    }

    /**
     * Get a human-readable device info string.
     */
    function getDeviceInfo() {
        const ua = navigator.userAgent;
        let browser = 'Unknown';
        let os = 'Unknown';

        // Detect browser
        if (ua.includes('Firefox/')) browser = 'Firefox';
        else if (ua.includes('Edg/')) browser = 'Edge';
        else if (ua.includes('Chrome/')) browser = 'Chrome';
        else if (ua.includes('Safari/')) browser = 'Safari';

        // Detect OS
        if (ua.includes('Windows')) os = 'Windows';
        else if (ua.includes('Mac OS')) os = 'macOS';
        else if (ua.includes('Android')) os = 'Android';
        else if (ua.includes('iPhone') || ua.includes('iPad')) os = 'iOS';
        else if (ua.includes('Linux')) os = 'Linux';

        return `${browser} / ${os}`;
    }

    /**
     * Show a small in-page notification banner (visual feedback only).
     */
    function showBanner(message, type) {
        // type: 'success' | 'info' | 'warning' | 'error'
        const colors = {
            success: '#00D66F',
            info: '#00C2FF',
            warning: '#FFC93C',
            error: '#FF3D7F',
        };

        const banner = document.createElement('div');
        banner.style.cssText = `
            position: fixed; top: 20px; right: 20px; z-index: 10000;
            background: ${colors[type] || colors.info}; color: white;
            padding: 14px 24px; border-radius: 10px; font-family: sans-serif;
            font-size: 0.95rem; font-weight: 600; box-shadow: 0 4px 20px rgba(0,0,0,0.2);
            animation: slideInRight 0.3s ease; max-width: 360px;
        `;
        banner.textContent = message;

        // Add animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInRight {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
        `;
        if (!document.getElementById('push-banner-style')) {
            style.id = 'push-banner-style';
            document.head.appendChild(style);
        }

        document.body.appendChild(banner);

        setTimeout(() => {
            banner.style.transition = 'all 0.3s ease';
            banner.style.opacity = '0';
            banner.style.transform = 'translateX(100%)';
            setTimeout(() => banner.remove(), 300);
        }, 4000);
    }

    /**
     * Initialize the push notification system.
     * This is the main entry point called by the page.
     */
    async function init() {
        // --- Check browser support ---
        if (!isSupported()) {
            console.log('[Push] Push notifications not supported in this browser.');
            return;
        }

        // --- Check Firebase config ---
        if (!hasConfig()) {
            console.log('[Push] Firebase not configured. Skipping push notifications.');
            return;
        }

        // --- Initialize Firebase ---
        if (!initialized) {
            firebase.initializeApp(window.FIREBASE_CONFIG);
            messaging = firebase.messaging();
            initialized = true;
        }

        // --- Register service worker ---
        await registerServiceWorker();

        // --- Check current permission state ---
        const currentPermission = Notification.permission;

        if (currentPermission === 'granted') {
            // Already granted — get token and save
            await getTokenAndSave();

            // Listen for foreground messages
            messaging.onMessage(async (payload) => {
                console.log('[Push] Foreground message received:', payload);

                // Show in-page notification banner
                const title = payload.notification?.title || payload.data?.title || 'NutriVision AI';
                const body  = payload.notification?.body  || payload.data?.body  || '';
                showBanner(`${title}: ${body}`, 'info');

                // Also try to show a system notification
                if (Notification.permission === 'granted') {
                    try {
                        const reg = await navigator.serviceWorker.getRegistration();
                        reg.showNotification(title, {
                            body: body,
                            icon: '/notification-icon.png',
                            badge: '/notification-badge.png',
                            tag: 'nutrivision-notification',
                            data: payload.data || {},
                        });
                    } catch (e) {
                        // Fallback to banner only
                    }
                }
            });

            return;
        }

        if (currentPermission === 'denied') {
            // User blocked notifications — do not prompt again
            console.log('[Push] Notifications blocked by user. Not prompting.');
            return;
        }

        // --- Permission is 'default' (not asked yet) ---
        // Check if we've already asked before (stored in localStorage)
        const hasAskedBefore = localStorage.getItem(STORAGE_KEY_PERMISSION_ASKED) === '1';

        if (hasAskedBefore) {
            // We asked before but user dismissed the prompt (permission is still 'default')
            // Don't keep bugging them — wait for them to take action
            console.log('[Push] Permission prompt previously dismissed. Not re-prompting.');
            return;
        }

        // --- Request permission ---
        const permission = await requestPermission();

        if (permission === 'granted') {
            showBanner('🔔 Notifications enabled! You\'ll receive meal reminders.', 'success');
            await getTokenAndSave();

            // Listen for foreground messages
            messaging.onMessage((payload) => {
                console.log('[Push] Foreground message received:', payload);
                const title = payload.notification?.title || payload.data?.title || 'NutriVision AI';
                const body  = payload.notification?.body  || payload.data?.body  || '';
                showBanner(`${title}: ${body}`, 'info');
            });

        } else if (permission === 'denied') {
            showBanner('🔕 Notifications blocked. You can enable them later in browser settings.', 'warning');
            // Token is no longer valid — remove from backend
            const lastToken = localStorage.getItem(STORAGE_KEY_LAST_TOKEN);
            if (lastToken) {
                await removeTokenFromBackend(lastToken);
                localStorage.removeItem(STORAGE_KEY_LAST_TOKEN);
            }
        } else {
            // 'default' — user dismissed the prompt
            console.log('[Push] Permission prompt dismissed.');
        }
    }

    /**
     * Manually request permission (called from a button click).
     */
    async function requestPermissionManually() {
        if (!isSupported() || !hasConfig()) {
            showBanner('Push notifications are not available.', 'error');
            return;
        }

        if (!initialized) {
            firebase.initializeApp(window.FIREBASE_CONFIG);
            messaging = firebase.messaging();
            initialized = true;
        }

        await registerServiceWorker();
        const permission = await requestPermission();

        if (permission === 'granted') {
            showBanner('🔔 Notifications enabled!', 'success');
            await getTokenAndSave();

            messaging.onMessage((payload) => {
                const title = payload.notification?.title || payload.data?.title || 'NutriVision AI';
                const body  = payload.notification?.body  || payload.data?.body  || '';
                showBanner(`${title}: ${body}`, 'info');
            });
        } else {
            showBanner('🔕 Notifications not enabled.', 'warning');
        }
    }

    // --- Public API ---
    return {
        init: init,
        requestPermission: requestPermissionManually,
        isSupported: isSupported,
    };
})();