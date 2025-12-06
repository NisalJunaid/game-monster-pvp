import axios from 'axios';

function initMatchmakingPanel() {
    const panel = document.getElementById('matchmaking-panel');

    if (!panel) {
        return;
    }

    const queueUrl = panel.dataset.queueUrl;
    const dequeueUrl = panel.dataset.dequeueUrl;
    const battleUrlTemplate = panel.dataset.battleUrl;
    const userId = Number(panel.dataset.userId || 0);
    const searchTimeout = Number(panel.dataset.searchTimeout || 45) * 1000;

    const statusEl = panel.querySelector('[data-status-text]');
    const ladderWindowEl = panel.querySelector('[data-ladder-window]');
    const timerEl = panel.querySelector('[data-countdown]');
    const queueSizeEl = panel.querySelector('[data-queue-size]');
    const modeBadge = panel.querySelector('[data-queue-mode-label]');
    const searchingBanner = panel.querySelector('[data-searching-banner]');

    if (queueSizeEl && panel.dataset.queueSize) {
        queueSizeEl.textContent = panel.dataset.queueSize;
    }

    let countdownHandle = null;

    const setStatus = (message) => {
        if (statusEl) {
            statusEl.textContent = message;
        }
    };

    const setLadderWindow = (value) => {
        if (ladderWindowEl && value) {
            ladderWindowEl.textContent = `${value} MMR window`;
        }
    };

    const leaveQueue = (silent = false) => {
        clearInterval(countdownHandle ?? undefined);
        countdownHandle = null;
        if (searchingBanner) {
            searchingBanner.classList.add('hidden');
        }

        axios
            .delete(dequeueUrl, { headers: { Accept: 'application/json' } })
            .then(() => {
                if (!silent) {
                    setStatus('Removed from queue.');
                }
            })
            .catch(() => {
                if (!silent) {
                    setStatus('Unable to update queue right now.');
                }
            });
    };

    const handleMatchFound = (battleId) => {
        clearInterval(countdownHandle ?? undefined);
        countdownHandle = null;

        const url = battleUrlTemplate.replace('__BATTLE_ID__', battleId);
        window.location.href = url;
    };

    const updateCountdown = (endTime) => {
        if (!timerEl) {
            return;
        }

        const remainingMs = Math.max(0, endTime - Date.now());
        const seconds = Math.ceil(remainingMs / 1000);
        timerEl.textContent = `${seconds}s`;

        if (remainingMs <= 0) {
            leaveQueue(true);
            setStatus('No match found in time. Please try again shortly.');
        }
    };

    const startCountdown = () => {
        const endTime = Date.now() + searchTimeout;
        clearInterval(countdownHandle ?? undefined);
        updateCountdown(endTime);
        countdownHandle = window.setInterval(() => updateCountdown(endTime), 500);
    };

    const startSearch = (mode) => {
        if (modeBadge) {
            modeBadge.textContent = mode;
            modeBadge.classList.remove('hidden');
        }

        if (searchingBanner) {
            searchingBanner.classList.remove('hidden');
        }

        setStatus('Searching for an opponent...');

        axios
            .post(
                queueUrl,
                { mode },
                {
                    headers: { Accept: 'application/json' },
                },
            )
            .then((response) => {
                const data = response.data || {};
                setLadderWindow(data.ladder_window || panel.dataset.ladderWindow);
                startCountdown();

                if (data.status === 'matched' && data.battle_id) {
                    handleMatchFound(data.battle_id);
                }
            })
            .catch(() => {
                setStatus('Could not join the queue right now.');
            });
    };

    const queueButtons = panel.querySelectorAll('[data-queue-mode]');
    queueButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const mode = button.dataset.queueMode;
            if (mode) {
                startSearch(mode);
            }
        });
    });

    const leaveButton = panel.querySelector('[data-leave-queue]');
    if (leaveButton) {
        leaveButton.addEventListener('click', (event) => {
            event.preventDefault();
            leaveQueue();
        });
    }

    if (panel.dataset.isQueued === '1' && panel.dataset.currentMode) {
        startCountdown();
        setStatus('Reconnecting to live search...');
        if (modeBadge) {
            modeBadge.textContent = panel.dataset.currentMode;
            modeBadge.classList.remove('hidden');
        }
        if (searchingBanner) {
            searchingBanner.classList.remove('hidden');
        }
        setLadderWindow(panel.dataset.ladderWindow);
    }

    if (window.Echo && userId) {
        window.Echo.private(`users.${userId}`)
            .listen('.PvpMatchFound', (event) => {
                handleMatchFound(event.battle_id);
            })
            .listen('.PvpSearchStatus', (event) => {
                const payload = event.payload || {};
                if (payload.queue_size && queueSizeEl) {
                    queueSizeEl.textContent = payload.queue_size;
                }
                setLadderWindow(payload.ladder_window || panel.dataset.ladderWindow);
                if (payload.message) {
                    setStatus(payload.message);
                }
            });
    }
}

document.addEventListener('DOMContentLoaded', initMatchmakingPanel);
