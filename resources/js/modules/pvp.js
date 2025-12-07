import axios from 'axios';
import { initBattleLive } from './battle';

let echoState = window.__echoConnectionState || (window.Echo ? 'connecting' : 'disconnected');
let queuePollHandle = null;

const getPanel = (panel) => panel ?? document.getElementById('matchmaking-panel');

const stopQueuePolling = () => {
    if (queuePollHandle) {
        clearInterval(queuePollHandle);
        queuePollHandle = null;
    }
};

const shouldUsePolling = () => !window.Echo || echoState === 'disconnected';

const loadBattleUi = async (battleId, panel) => {
    const activePanel = getPanel(panel);
    const fallbackBattleId = battleId || activePanel?.dataset.activeBattleId;

    if (!fallbackBattleId) return;

    const fragmentUrlTemplate = activePanel?.dataset.battleFragmentUrl;
    const battleUrlTemplate = activePanel?.dataset.battleUrl;

    if (fragmentUrlTemplate) {
        const url = fragmentUrlTemplate.replace('__BATTLE_ID__', fallbackBattleId);

        try {
            const response = await axios.get(url, { headers: { Accept: 'text/html' } });
            const wrapper = document.createElement('div');
            wrapper.innerHTML = response.data;
            const newPanel = wrapper.firstElementChild;

            if (newPanel && activePanel) {
                activePanel.replaceWith(newPanel);
                initBattleLive(newPanel);
                return newPanel;
            }
        } catch (error) {
            console.error('Failed to load battle fragment', error);
        }
    }

    if (battleUrlTemplate) {
        const url = battleUrlTemplate.replace('__BATTLE_ID__', fallbackBattleId);
        window.location.href = url;
    } else {
        window.location.href = '/pvp';
    }

    return null;
};

export async function refreshPvpPanel(battleId = null) {
    const panel = getPanel();

    if (!panel) return null;

    try {
        const response = await axios.get('/pvp/fragment', { headers: { Accept: 'text/html' } });
        const wrapper = document.createElement('div');
        wrapper.innerHTML = response.data;
        const newPanel = wrapper.firstElementChild;

        if (newPanel) {
            panel.replaceWith(newPanel);
            initMatchmakingPanel(newPanel);
            const battleContainer = newPanel.querySelector('[data-battle-live]');
            if (battleContainer) {
                initBattleLive(newPanel);

                if ((battleContainer.dataset.battleStatus || 'active') !== 'active') {
                    const banner = newPanel.querySelector('[data-battle-finished-banner]');
                    if (banner) {
                        banner.classList.remove('hidden');
                    }
                    window.setTimeout(() => refreshPvpPanel(), 1500);
                }
            } else {
                stopQueuePolling();
            }

            return newPanel;
        }
    } catch (error) {
        console.error('Failed to refresh PvP panel', error);
    }

    await loadBattleUi(battleId, panel);

    return null;
}

window.refreshPvpPanel = refreshPvpPanel;

const startQueuePolling = () => {
    if (!shouldUsePolling() || queuePollHandle) {
        return;
    }

    queuePollHandle = window.setInterval(async () => {
        try {
            const response = await axios.get('/pvp/status', { headers: { Accept: 'application/json' } });
            const data = response.data || {};
            if (data.active_battle_id) {
                stopQueuePolling();
                await refreshPvpPanel(data.active_battle_id);
            }
        } catch (error) {
            console.error('Queue polling failed', error);
        }
    }, 2000);
};

window.addEventListener('echo:status', (event) => {
    const state = event.detail?.state;
    if (!state) return;
    echoState = state;
    if (state === 'connected') {
        stopQueuePolling();
    } else if (shouldUsePolling()) {
        startQueuePolling();
    }
});

function initMatchmakingPanel(panel = getPanel()) {
    const activePanel = getPanel(panel);

    if (!activePanel) {
        return;
    }

    const queueUrl = activePanel.dataset.queueUrl;
    const dequeueUrl = activePanel.dataset.dequeueUrl;
    const userId = Number(activePanel.dataset.userId || 0);
    const searchTimeout = Number(activePanel.dataset.searchTimeout || 45) * 1000;

    const statusEl = activePanel.querySelector('[data-status-text]');
    const ladderWindowEl = activePanel.querySelector('[data-ladder-window]');
    const timerEl = activePanel.querySelector('[data-countdown]');
    const queueSizeEl = activePanel.querySelector('[data-queue-size]');
    const modeBadge = activePanel.querySelector('[data-queue-mode-label]');
    const searchingBanner = activePanel.querySelector('[data-searching-banner]');

    if (queueSizeEl && activePanel.dataset.queueSize) {
        queueSizeEl.textContent = activePanel.dataset.queueSize;
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
        stopQueuePolling();
        if (searchingBanner) {
            searchingBanner.classList.add('hidden');
        }

        if (!dequeueUrl) {
            return Promise.resolve();
        }

        return axios
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

    const handleMatchFound = async (battleId) => {
        clearInterval(countdownHandle ?? undefined);
        countdownHandle = null;
        stopQueuePolling();

        const refreshed = await refreshPvpPanel(battleId);
        if (!refreshed && battleId) {
            await loadBattleUi(battleId, activePanel);
        }
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

        if (!queueUrl) {
            setStatus('Matchmaking is unavailable right now.');
            return;
        }

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
                setLadderWindow(data.ladder_window || activePanel.dataset.ladderWindow);
                startCountdown();

                if (data.status === 'matched' && data.battle_id) {
                    handleMatchFound(data.battle_id);
                } else if (shouldUsePolling()) {
                    startQueuePolling();
                }
            })
            .catch(() => {
                setStatus('Could not join the queue right now.');
            });
    };

    const queueButtons = activePanel.querySelectorAll('[data-queue-mode]');
    queueButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            event.preventDefault();
            const mode = button.dataset.queueMode;
            if (mode) {
                startSearch(mode);
            }
        });
    });

    const leaveButton = activePanel.querySelector('[data-leave-queue]');
    if (leaveButton) {
        leaveButton.addEventListener('click', (event) => {
            event.preventDefault();
            leaveQueue();
        });
    }

    if (activePanel.dataset.isQueued === '1' && activePanel.dataset.currentMode && queueUrl) {
        startCountdown();
        setStatus('Reconnecting to live search...');
        if (modeBadge) {
            modeBadge.textContent = activePanel.dataset.currentMode;
            modeBadge.classList.remove('hidden');
        }
        if (searchingBanner) {
            searchingBanner.classList.remove('hidden');
        }
        setLadderWindow(activePanel.dataset.ladderWindow);

        if (shouldUsePolling()) {
            startQueuePolling();
        }
    }

    if (activePanel.dataset.activeBattleId) {
        refreshPvpPanel(activePanel.dataset.activeBattleId);
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
                setLadderWindow(payload.ladder_window || activePanel.dataset.ladderWindow);
                if (payload.message) {
                    setStatus(payload.message);
                }
            });
    } else if (shouldUsePolling()) {
        startQueuePolling();
    }
}

document.addEventListener('DOMContentLoaded', () => initMatchmakingPanel());
