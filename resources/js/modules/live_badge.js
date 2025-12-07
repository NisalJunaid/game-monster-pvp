const badgeStates = {
    connected: {
        text: 'Live: connected',
        classes: 'bg-green-100 text-green-800',
        dot: 'bg-green-500',
    },
    connecting: {
        text: 'Live: connecting',
        classes: 'bg-yellow-100 text-yellow-800',
        dot: 'bg-yellow-500',
    },
    disconnected: {
        text: 'Live: disconnected',
        classes: 'bg-red-100 text-red-800',
        dot: 'bg-red-500',
    },
    error: {
        text: 'Live: disconnected',
        classes: 'bg-red-100 text-red-800',
        dot: 'bg-red-500',
    },
};

const applyState = (badge, state = 'connecting') => {
    const config = badgeStates[state] || badgeStates.connecting;
    const { classes, text, dot } = config;
    const classList = classes.split(' ');
    const dotClasses = dot.split(' ');

    badge.dataset.liveState = state;
    badge.querySelector('[data-live-text]').textContent = text;

    badge.className = `inline-flex items-center gap-1 px-2 py-1 text-xs font-semibold rounded-full ${classList.join(' ')}`;

    const dotEl = badge.querySelector('[data-live-dot]');
    if (dotEl) {
        dotEl.className = `inline-block h-2 w-2 rounded-full ${dotClasses.join(' ')}`;
    }
};

const initLiveBadges = () => {
    const badges = Array.from(document.querySelectorAll('[data-live-badge]'));
    if (!badges.length) {
        return;
    }

    const initialState = window.__echoConnectionState || (window.Echo ? 'connecting' : 'disconnected');
    badges.forEach((badge) => applyState(badge, badge.dataset.liveState || initialState));

    window.addEventListener('echo:status', (event) => {
        const state = event.detail?.state || 'connecting';
        window.__echoConnectionState = state;
        badges.forEach((badge) => applyState(badge, state));
    });
};

document.addEventListener('DOMContentLoaded', initLiveBadges);
