function initBattleLive() {
    const container = document.querySelector('[data-battle-live]');

    if (!container) {
        return;
    }

    const battleId = container.dataset.battleId;
    const refreshUrl = container.dataset.refreshUrl;
    const statusEl = container.querySelector('[data-battle-live-status]');

    const setStatus = (message) => {
        if (statusEl) {
            statusEl.textContent = message;
        }
    };

    if (!window.Echo || !battleId) {
        setStatus('Live updates unavailable. Actions will appear after refresh.');

        return;
    }

    setStatus('Listening for opponent actions...');

    window.Echo.private(`battles.${battleId}`).listen('.BattleUpdated', () => {
        setStatus('Updating with the latest turn...');
        if (refreshUrl) {
            window.location.href = refreshUrl;
        } else {
            window.location.reload();
        }
    });
}

document.addEventListener('DOMContentLoaded', initBattleLive);
