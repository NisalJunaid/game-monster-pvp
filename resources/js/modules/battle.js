import axios from 'axios';

const escapeHtml = (value = '') => `${value}`.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

const parseTime = (value) => {
    if (!value && value !== 0) return null;

    if (typeof value === 'number') {
        return value;
    }

    const asNumber = Number(value);
    if (!Number.isNaN(asNumber)) {
        return asNumber;
    }

    const parsed = Date.parse(value);
    return Number.isNaN(parsed) ? null : parsed;
};

const hpPercent = (monster) => {
    if (!monster) return 0;
    const max = Math.max(1, monster.max_hp || 0);
    return Math.max(0, Math.min(100, Math.floor(((monster.current_hp || 0) / max) * 100)));
};

const formatTypes = (monster) => {
    const types = monster?.type_names || [];

    return types.length ? types.join(', ') : 'Neutral';
};

const renderMoves = (moves = [], actUrl = '') => {
    return moves
        .map(
            (move) => `
                <form method="POST" action="${escapeHtml(actUrl)}" data-battle-action-form>
                    <input type="hidden" name="_token" value="${escapeHtml(document.head.querySelector('meta[name="csrf-token"]')?.content || '')}" />
                    <input type="hidden" name="type" value="move">
                    <input type="hidden" name="slot" value="${move.slot}">
                    <button class="w-full h-full min-h-[64px] sm:min-h-[84px] lg:min-h-[92px] px-3 py-3 rounded-xl border border-slate-800 bg-gradient-to-br from-slate-800/80 to-slate-900/80 hover:border-emerald-300 hover:bg-emerald-300/10 hover:shadow-md text-left transition-transform duration-150 active:scale-[0.98]" data-move-slot="${move.slot}">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-semibold">${escapeHtml(move.name)}</span>
                            <span class="text-[11px] uppercase text-slate-200/80">Slot ${move.slot}</span>
                        </div>
                        <p class="text-sm text-slate-200/80">${escapeHtml(move.category ? move.category.charAt(0).toUpperCase() + move.category.slice(1) : 'Physical')} • ${escapeHtml(move.type || 'Neutral')} • Power ${move.power}</p>
                    </button>
                </form>
            `,
        )
        .join('');
};

const renderCommands = (state, viewerId, actUrl = '') => {
    const participant = state.participants?.[viewerId];
    const isActive = (state?.status || 'active') === 'active';
    const isYourTurn = isActive && (state.next_actor_id ?? null) === viewerId;
    const forcedSwitchUserId = state?.forced_switch_user_id ?? null;
    const isForcedSwap = forcedSwitchUserId !== null && forcedSwitchUserId === viewerId;
    const opponentMustSwap = forcedSwitchUserId !== null && forcedSwitchUserId !== viewerId;
    const active = participant?.monsters?.[participant.active_index ?? 0];
    const bench = (participant?.monsters || []).filter((_, idx) => idx !== (participant?.active_index ?? 0));

    const turnLabel = isForcedSwap
        ? 'Swap required'
        : isYourTurn
            ? 'Your turn'
            : isActive
                ? 'Waiting for opponent'
                : 'Battle complete';
    const turnColor = isYourTurn ? 'text-emerald-200' : 'text-slate-200/80';
    const header = `
        <div class="flex items-center justify-between gap-3">\n            <div class="flex items-center gap-2">\n                <h3 class="text-lg font-semibold">Choose an action</h3>\n                <span class="text-xs text-emerald-200 rounded-full border border-emerald-400/60 px-2 py-0.5 hidden" data-battle-commands-locked-hint>Locked</span>\n            </div>\n            <span class="text-sm ${turnColor}" data-turn-indicator>${escapeHtml(turnLabel)}</span>\n        </div>
    `;

    const timer = `
        <div class="mt-2 hidden" data-turn-timer>\n            <div class="flex items-center justify-between text-xs text-slate-200/80 mb-1">\n                <span data-turn-timer-label>Opponent turn timer</span>\n            </div>\n            <div class="w-full h-2 bg-slate-800 rounded-full overflow-hidden">\n                <div class="h-2 bg-amber-400 transition-[width] duration-100" style="width: 100%;" data-turn-timer-bar></div>\n            </div>\n            <p class="mt-1 text-xs text-amber-200 hidden" data-turn-timer-expired>\n                Time expired — waiting for server…\n            </p>\n        </div>
    `;

    if (!participant) {
        return `${header}<p class="text-sm text-slate-200/80">Battle state unavailable.</p>`;
    }

    if (!isActive) {
        return `${header}<p class="text-sm text-slate-200/80">Battle complete.</p>`;
    }

    if (opponentMustSwap) {
        return `${header}${timer}<p class="text-sm text-slate-200/80">Waiting for opponent to swap.</p>`;
    }

    if (!isYourTurn || !active) {
        return `${header}${timer}<p class="text-sm text-slate-200/80">Waiting for opponent action...</p>`;
    }

    const moveButtons = isForcedSwap
        ? '<p class="text-sm text-amber-200">Your active monster fainted. Choose a replacement to continue.</p>'
        : renderMoves(active.moves || [], actUrl);
    const csrf = document.head.querySelector('meta[name=\"csrf-token\"]')?.content || '';
    const swapSection = bench.length
        ? `
            <form method="POST" action="${escapeHtml(actUrl)}" class="flex flex-col sm:flex-row gap-2 items-stretch sm:items-center" data-battle-action-form data-battle-swap-form>
                <input type="hidden" name="_token" value="${escapeHtml(csrf)}" />
                <input type="hidden" name="type" value="swap">\n                <select name="monster_instance_id" class="border-slate-700 bg-slate-800 text-white rounded-lg px-2 py-2 text-sm flex-1">\n                    ${bench
                        .map((monster) => `<option value=\"${monster.id}\">Swap to ${escapeHtml(monster.name)} (HP ${monster.current_hp})</option>`)
                        .join('')}\n                </select>
                <button class="px-3 py-2 bg-emerald-600 text-white rounded-lg hover:bg-emerald-500 transition-transform duration-150 active:scale-[0.98]">Swap</button>
            </form>
        `
        : `<p class="text-xs text-slate-200/80">No reserve monsters available${(active.id ?? null) === 0 ? '—using martial arts move set.' : '.'}</p>`;

    return `
        ${header}
        ${timer}
        <div class="grid grid-cols-2 gap-3">${moveButtons}</div>
        ${swapSection}
    `;
};

const renderLog = (log = [], players = {}) => {
    if (!log.length) {
        return '<h2 class="text-xl font-semibold mb-3">Turn Log</h2><p class="text-gray-600">No turns recorded yet.</p>';
    }

    const items = log
        .map(
            (entry) => `
                <div class="border rounded p-3 bg-gray-50">
                    <div class="flex items-center justify-between">
                        <p class="font-semibold">Turn ${entry.turn} by ${escapeHtml(players[entry.actor_user_id] || `User ${entry.actor_user_id}`)}</p>
                        <span class="text-xs text-gray-500">Action: ${escapeHtml(entry.action?.type || 'unknown')}${entry.action?.type === 'move' ? ` (Slot ${entry.action.slot})` : ''}</span>
                    </div>
                    <ul class="list-disc list-inside text-gray-600">
                        ${(entry.events || [])
                            .map((event) => `<li>${escapeHtml(event.type ? event.type.charAt(0).toUpperCase() + event.type.slice(1) : 'Event')} - ${escapeHtml(JSON.stringify(event))}</li>`) 
                            .join('')}
                    </ul>
                </div>
            `,
        )
        .join('');

    return `<h2 class="text-xl font-semibold mb-3">Turn Log</h2><div class="space-y-3 text-sm">${items}</div>`;
};

const parseInitialState = (container) => {
    const stateEl = container.querySelector('[data-battle-initial-state]');
    if (!stateEl) return null;

    try {
        return JSON.parse(stateEl.textContent || '{}');
    } catch (error) {
        console.error('Unable to parse battle initial state', error);

        return null;
    }
};

export function initBattleLive(root = document) {
    const container = root.querySelector('[data-battle-live]');

    if (!container) {
        return;
    }

    const initial = parseInitialState(container);
    if (!initial) {
        return;
    }

    let battleState = initial.state || {};
    let battleStatus = initial.battle?.status || 'active';
    let winnerId = initial.battle?.winner_user_id || null;
    let players = initial.players || {};
    const viewerId = Number(initial.viewer_id || container.dataset.userId || 0);
    const battleId = container.dataset.battleId;
    const actUrl = container.dataset.actUrl;
    const statusEl = container.querySelector('[data-battle-live-status]');
    const statusTextEl = container.querySelector('[data-battle-status-text]');
    const winnerEl = container.querySelector('[data-battle-winner]');
    const nextActorEl = container.querySelector('[data-next-actor]');
    const modeEl = container.querySelector('[data-battle-mode]');
    const yourSideContainer = container.querySelector('[data-side="you"]');
    const opponentSideContainer = container.querySelector('[data-side="opponent"]');
    const commandsContainer = container.querySelector('[data-battle-commands]');
    const commandsBody = commandsContainer?.querySelector('[data-battle-commands-body]');
    const lockedHint = commandsContainer?.querySelector('[data-battle-commands-locked-hint]');
    const logContainer = container.querySelector('[data-battle-log]');
    const logWrapper = container.querySelector('[data-battle-log-wrapper]');
    const logToggle = container.querySelector('[data-battle-log-toggle]');
    const waitingOverlay = container.querySelector('[data-battle-waiting-overlay]');
    const waitingMessageEl = waitingOverlay?.querySelector('[data-battle-waiting-message]');
    const timerContainer = container.querySelector('[data-turn-timer]');
    const timerBar = timerContainer?.querySelector('[data-turn-timer-bar]');
    const timerExpiredText = timerContainer?.querySelector('[data-turn-timer-expired]');
    const timerLabel = timerContainer?.querySelector('[data-turn-timer-label]');
    const menuButton = container.querySelector('[data-battle-menu-button]');
    const menuDrawer = container.querySelector('[data-battle-menu-drawer]');
    const menuBackdrop = container.querySelector('[data-battle-menu-backdrop]');
    const menuClose = container.querySelector('[data-battle-menu-close]');

    const opponentId = initial.battle?.player1_id === viewerId ? initial.battle?.player2_id : initial.battle?.player1_id;
    let pollHandle = null;
    let watchdogHandle = null;
    let awaitingEvent = false;
    let eventReceived = false;
    let subscriptionSucceeded = false;
    let subscriptionErrored = false;
    let lastUpdateAt = Date.now();
    let hasScheduledCompletion = false;
    let currentEchoState = window.__echoConnectionState || (window.Echo ? 'connecting' : 'disconnected');
    let initialEventTimeout = null;
    let waitingForResolution = false;
    let waitingReason = null;
    let resolutionAllowsSwap = false;
    let timerHandle = null;
    let timerEndsAtMs = null;
    let timerDurationMs = null;
    let lastTurnExpiresAt = battleState?.turn_expires_at ?? null;
    let lastLogLength = battleState?.log?.length || 0;
    const defaultWaitingMessage = waitingMessageEl?.textContent?.trim() || 'Waiting for opponent...';
    let toastContainer = null;

    const updateLogButtonState = () => {
        if (!logToggle || !logWrapper) return;

        const media = window.matchMedia('(min-width: 1024px)');
        const isHidden = logWrapper.classList.contains('hidden') && !media.matches;
        const label = logToggle.querySelector('span:last-child');
        logToggle.setAttribute('aria-expanded', (!isHidden).toString());

        if (label) {
            label.textContent = isHidden ? 'Show Log' : 'Hide Log';
        }
    };

    if (logToggle && logWrapper) {
        const mq = window.matchMedia('(min-width: 1024px)');

        logToggle.addEventListener('click', () => {
            logWrapper.classList.toggle('hidden');
            updateLogButtonState();
        });

        mq.addEventListener('change', (event) => {
            if (event.matches) {
                logWrapper.classList.remove('hidden');
            }
            updateLogButtonState();
        });

        updateLogButtonState();
    }

    const setMenuOpen = (open) => {
        if (menuDrawer) {
            menuDrawer.classList.toggle('translate-x-full', !open);
        }

        if (menuBackdrop) {
            menuBackdrop.classList.toggle('opacity-0', !open);
            menuBackdrop.classList.toggle('opacity-100', open);
            menuBackdrop.classList.toggle('pointer-events-none', !open);
        }
    };

    if (menuButton) {
        menuButton.addEventListener('click', () => setMenuOpen(true));
    }

    if (menuBackdrop) {
        menuBackdrop.addEventListener('click', () => setMenuOpen(false));
    }

    if (menuClose) {
        menuClose.addEventListener('click', () => setMenuOpen(false));
    }

    const updateWaitingMessage = (message = defaultWaitingMessage) => {
        if (!waitingMessageEl) return;

        waitingMessageEl.textContent = message;
    };

    const getToastContainer = () => {
        if (toastContainer) return toastContainer;

        const el = document.createElement('div');
        el.dataset.toastContainer = 'true';
        el.className = 'fixed bottom-4 right-4 space-y-2 z-50';
        document.body.appendChild(el);
        toastContainer = el;

        return el;
    };

    const showToast = (message = '') => {
        if (!message) return;

        const containerEl = getToastContainer();
        const toast = document.createElement('div');
        toast.className = 'bg-gray-900 text-white px-4 py-2 rounded shadow-lg opacity-0 translate-y-2 transition duration-300';
        toast.textContent = message;
        containerEl.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('opacity-0', 'translate-y-2');
        });

        window.setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            window.setTimeout(() => toast.remove(), 300);
        }, 3500);
    };

    const shouldAllowSwapWhileWaiting = () => {
        const forcedSwitchUserId = battleState?.forced_switch_user_id ?? null;

        return (forcedSwitchUserId !== null && forcedSwitchUserId === viewerId) || (battleState.next_actor_id ?? null) === viewerId;
    };

    const resetTimerUi = () => {
        if (timerBar) {
            timerBar.style.width = '100%';
        }

        if (timerExpiredText) {
            timerExpiredText.classList.add('hidden');
        }
    };

    const hideTurnTimer = () => {
        if (timerHandle) {
            clearInterval(timerHandle);
            timerHandle = null;
        }

        timerEndsAtMs = null;
        timerDurationMs = null;
        resetTimerUi();

        if (timerContainer) {
            timerContainer.classList.add('hidden');
        }
    };

    const tickTurnTimer = () => {
        if (!timerBar || timerEndsAtMs === null || timerDurationMs === null) {
            return;
        }

        const remaining = Math.max(0, timerEndsAtMs - Date.now());
        const pct = timerDurationMs ? clamp(remaining / timerDurationMs, 0, 1) : 0;
        timerBar.style.width = `${pct * 100}%`;

        if (timerExpiredText) {
            timerExpiredText.classList.toggle('hidden', remaining > 0);
        }
    };

    const startTurnTimer = (state) => {
        const isActive = battleStatus === 'active';
        const forcedSwitchUserId = state?.forced_switch_user_id ?? null;
        const opponentMustSwap = forcedSwitchUserId !== null && forcedSwitchUserId !== viewerId;
        const isYourTurn = isActive && (state?.next_actor_id ?? null) === viewerId && !opponentMustSwap;

        if (!isActive || (!opponentMustSwap && isYourTurn)) {
            hideTurnTimer();
            return;
        }

        const expiresAt = state?.turn_expires_at ?? null;
        const startedAt = state?.turn_started_at ?? null;
        const timeoutSeconds = state?.turn_timeout_seconds ?? null;
        const endsAtMs = parseTime(expiresAt);
        let durationMs = typeof timeoutSeconds === 'number' && timeoutSeconds > 0 ? timeoutSeconds * 1000 : null;

        if (!durationMs) {
            const startMs = parseTime(startedAt);
            if (endsAtMs !== null && startMs !== null) {
                durationMs = Math.max(endsAtMs - startMs, 0);
            }
        }

        if (endsAtMs === null || !durationMs || durationMs <= 0) {
            hideTurnTimer();
            return;
        }

        const hasNewDeadline = expiresAt !== lastTurnExpiresAt;
        lastTurnExpiresAt = expiresAt;
        timerEndsAtMs = endsAtMs;
        timerDurationMs = durationMs;

        if (timerContainer) {
            timerContainer.classList.remove('hidden');
        }

        if (timerLabel) {
            timerLabel.textContent = opponentMustSwap ? 'Opponent swap timer' : 'Opponent turn timer';
        }

        if (hasNewDeadline) {
            resetTimerUi();
        }

        if (timerHandle) {
            clearInterval(timerHandle);
        }

        tickTurnTimer();
        timerHandle = window.setInterval(tickTurnTimer, 100);
    };

    const updateHeader = () => {
        if (statusTextEl) {
            statusTextEl.textContent = battleStatus.charAt(0).toUpperCase() + battleStatus.slice(1);
        }

        if (winnerEl) {
            if (winnerId) {
                winnerEl.classList.remove('hidden');
                winnerEl.textContent = `Winner: ${players[winnerId] || `User ${winnerId}`}`;
            } else {
                winnerEl.classList.add('hidden');
                winnerEl.textContent = '';
            }
        }

        if (nextActorEl) {
            nextActorEl.textContent = battleState.next_actor_id ?? 'Unknown';
        }

        if (modeEl) {
            modeEl.textContent = (initial.battle?.mode || 'ranked').charAt(0).toUpperCase() + (initial.battle?.mode || 'ranked').slice(1);
        }
    };

    const patchHud = (role, side) => {
        const active = side?.monsters?.[side.active_index ?? 0];
        const nameEl = container.querySelector(`[data-monster-name="${role}"]`);
        const typesEl = container.querySelector(`[data-monster-types="${role}"]`);
        const statusEl = container.querySelector(`[data-monster-status="${role}"]`);
        const hpTextEl = container.querySelector(`[data-monster-hp-text="${role}"]`);
        const hpBarEl = container.querySelector(`[data-monster-hp-bar="${role}"]`);

        const typeText = active ? formatTypes(active) : 'Neutral';
        const hpText = active ? `HP ${active.current_hp} / ${active.max_hp}` : 'HP 0 / 0';
        const statusText = active?.status?.name ? `Status: ${active.status.name}` : '';
        const hp = hpPercent(active);

        if (nameEl) {
            nameEl.textContent = active?.name || 'No fighter';
        }

        if (typesEl) {
            typesEl.textContent = `Types: ${typeText}`;
        }

        if (statusEl) {
            statusEl.textContent = statusText;
            statusEl.classList.toggle('hidden', !active?.status?.name);
        }

        if (hpTextEl) {
            hpTextEl.textContent = hpText;
        }

        if (hpBarEl) {
            hpBarEl.style.width = `${hp}%`;
        }
    };

    const updateInteractionState = () => {
        const isActive = battleStatus === 'active';
        const forcedSwitchUserId = battleState?.forced_switch_user_id ?? null;
        const opponentMustSwap = forcedSwitchUserId !== null && forcedSwitchUserId !== viewerId;
        const isYourTurn = isActive && (battleState.next_actor_id ?? null) === viewerId && !opponentMustSwap;
        const shouldWaitForTurn = isActive && !isYourTurn && !shouldAllowSwapWhileWaiting();

        if ((!waitingForResolution || waitingReason !== 'resolution') && shouldWaitForTurn) {
            setWaitingState(true, { allowSwap: false, message: 'Waiting for opponent...', reason: 'turn' });
        } else if (waitingReason === 'turn' && (!shouldWaitForTurn || !isActive)) {
            setWaitingState(false);
        }

        const controlsLocked = waitingForResolution || shouldWaitForTurn;
        const allowSwap = waitingReason === 'resolution' ? resolutionAllowsSwap && shouldAllowSwapWhileWaiting() : false;
        toggleControls(controlsLocked, { allowSwap });
    };

    const render = () => {
        const yourSide = battleState.participants?.[viewerId];
        const opponentSide = opponentId ? battleState.participants?.[opponentId] : null;

        patchHud('you', yourSide);
        patchHud('opponent', opponentSide);

        if (commandsBody) {
            commandsBody.innerHTML = renderCommands({ ...battleState, status: battleStatus }, viewerId, actUrl);
        } else if (commandsContainer) {
            commandsContainer.innerHTML = renderCommands({ ...battleState, status: battleStatus }, viewerId, actUrl);
        }

        if (logContainer) {
            logContainer.innerHTML = renderLog(battleState.log || [], players);
        }

        updateHeader();
        updateInteractionState();
    };

    const setStatus = (message) => {
        if (statusEl) {
            statusEl.textContent = message;
        }
    };

    const toggleControls = (disabled, { allowSwap = false } = {}) => {
        const controlsRoot = commandsBody || commandsContainer;
        if (!controlsRoot) {
            return;
        }

        controlsRoot.querySelectorAll('button, select').forEach((control) => {
            const isSwapControl = Boolean(control.closest('[data-battle-swap-form]'));
            const shouldDisable = disabled && !(allowSwap && isSwapControl);
            control.disabled = shouldDisable;
        });

        const isLocked = disabled && !allowSwap;
        if (commandsContainer) {
            commandsContainer.classList.toggle('opacity-60', disabled);
            commandsContainer.classList.toggle('grayscale', isLocked);
            commandsContainer.classList.toggle('is-locked', isLocked);
        }

        if (lockedHint) {
            lockedHint.classList.toggle('hidden', !isLocked);
        }
    };

    const setWaitingState = (waiting, { allowSwap = false, message, reason = 'resolution' } = {}) => {
        waitingForResolution = waiting;
        waitingReason = waiting ? reason : null;
        resolutionAllowsSwap = allowSwap;
        const canInteractWithSwap = allowSwap && shouldAllowSwapWhileWaiting();
        toggleControls(waiting, { allowSwap: canInteractWithSwap });

        if (waitingOverlay) {
            if (message) {
                updateWaitingMessage(message);
            }
            const showOverlay = waiting && !canInteractWithSwap;
            waitingOverlay.classList.toggle('opacity-100', showOverlay);
            waitingOverlay.classList.toggle('scale-100', showOverlay);
            waitingOverlay.classList.toggle('pointer-events-auto', showOverlay);
            waitingOverlay.classList.toggle('opacity-0', !showOverlay);
            waitingOverlay.classList.toggle('scale-95', !showOverlay);
            waitingOverlay.classList.toggle('pointer-events-none', !showOverlay);
        }

        if (!waiting) {
            updateWaitingMessage(defaultWaitingMessage);
        }
    };

    const entryContainsTimeout = (entry) => {
        const text = JSON.stringify(entry || {}).toLowerCase();
        return text.includes('timed out') || text.includes('timeout');
    };

    const notifyTimeoutLogs = (previousLength, log = []) => {
        if (!log.length || log.length <= previousLength) return;

        const newEntries = log.slice(previousLength);
        if (newEntries.some((entry) => entryContainsTimeout(entry))) {
            showToast('Turn timed out.');
        }
    };

    const scheduleCompletion = () => {
        if (hasScheduledCompletion || battleStatus === 'active') {
            return;
        }

        hasScheduledCompletion = true;

        window.setTimeout(() => {
            if (window.location.pathname.startsWith('/pvp')) {
                if (typeof window.refreshPvpPanel === 'function') {
                    window.refreshPvpPanel();
                } else {
                    window.location.href = '/pvp';
                }
            } else {
                window.location.href = '/pvp';
            }
        }, 1500);
    };

    const submitAction = (form) => {
        if (!actUrl) {
            return;
        }

        const formData = new FormData(form);

        axios
            .post(actUrl, formData)
            .then(() => setStatus('Action sent. Waiting for result...'))
            .catch((error) => {
                const isValidationError = error?.response?.status === 422;
                const message = error?.response?.data?.message || (isValidationError ? 'Invalid action. Please try again.' : 'Could not submit action right now.');

                setStatus(message);
                setWaitingState(false);
            });
    };

    const applyUpdate = (payload, { fromEvent = false } = {}) => {
        if (!payload) {
            return;
        }

        const previousLogLength = lastLogLength;

        lastUpdateAt = Date.now();

        if (fromEvent && awaitingEvent) {
            awaitingEvent = false;
            eventReceived = true;
        }

        if (fromEvent) {
            eventReceived = true;
            console.info('Battle update received via broadcast', {
                battle_id: payload?.battle_id,
                status: payload?.status,
                next_actor_id: payload?.next_actor_id,
            });
            attemptStopPolling();
        }

        if (payload.players) {
            players = payload.players;
        }

        battleState = payload.state || battleState;
        battleStatus = payload.status || payload.battle?.status || battleStatus;
        winnerId = payload.winner_user_id ?? payload.battle?.winner_user_id ?? winnerId;
        startTurnTimer(battleState);
        notifyTimeoutLogs(previousLogLength, battleState.log || []);
        lastLogLength = (battleState.log || []).length;
        render();

        const isActive = battleStatus === 'active';
        const isYourTurn = isActive && (battleState.next_actor_id ?? null) === viewerId;
        const isForcedSwap = isActive && shouldAllowSwapWhileWaiting();
        setStatus(isActive ? 'Live: waiting for next move.' : 'Battle finished.');

        if (waitingForResolution && waitingReason === 'resolution' && (isYourTurn || isForcedSwap || !isActive)) {
            setWaitingState(false);
        }

        if (!isActive) {
            scheduleCompletion();
        }
    };

    const fetchBattleState = () =>
        axios
            .get(`/battles/${battleId}/state`, { headers: { Accept: 'application/json' } })
            .then((response) => {
                const data = response.data || {};

                applyUpdate({
                    state: data.state,
                    status: data.battle?.status,
                    winner_user_id: data.battle?.winner_user_id,
                    players: data.players,
                });
            })
            .catch((error) => {
                console.error('Battle polling failed', error);
                setStatus('Unable to sync battle right now.');
            });

    const attemptStopPolling = (force = false) => {
        const canStop = force || (subscriptionSucceeded && eventReceived);

        if (!canStop) {
            return;
        }

        if (pollHandle) {
            clearInterval(pollHandle);
            pollHandle = null;
        }

        awaitingEvent = false;
    };

    const startPolling = ({ expectEvent = false } = {}) => {
        if (!battleId || pollHandle) {
            return;
        }

        awaitingEvent = expectEvent;
        setStatus('Live updates (polling)...');
        fetchBattleState();
        pollHandle = window.setInterval(fetchBattleState, 2000);
    };

    const startWatchdog = () => {
        if (watchdogHandle || !battleId) {
            return;
        }

        watchdogHandle = window.setInterval(() => {
            const isEchoConnected = window.Echo && currentEchoState === 'connected';

            if (!isEchoConnected) {
                return;
            }

            if (Date.now() - lastUpdateAt > 8000) {
                startPolling({ expectEvent: true });
            }
        }, 3000);
    };

    container.addEventListener('submit', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLFormElement) || !target.matches('[data-battle-action-form]')) {
            return;
        }

        event.preventDefault();
        const isSwapForm = target.matches('[data-battle-swap-form]') || Boolean(target.querySelector('input[name="type"][value="swap"]'));
        const allowSwapWhileWaiting = shouldAllowSwapWhileWaiting();

        if (waitingForResolution && !(allowSwapWhileWaiting && isSwapForm)) {
            return;
        }

        const waitingMessage = isSwapForm ? 'Switching...' : 'Resolving...';
        setWaitingState(true, { allowSwap: false, message: waitingMessage, reason: 'resolution' });
        setStatus(waitingMessage);
        submitAction(target);
    });

    render();
    startTurnTimer(battleState);

    if (battleStatus !== 'active') {
        scheduleCompletion();
    }

    const shouldPoll = () => !window.Echo || currentEchoState === 'disconnected' || subscriptionErrored;

    if (window.Echo && battleId) {
        setStatus('Listening for opponent actions...');
        const channel = window.Echo.private(`battles.${battleId}`);
        const subscription = channel?.subscription;

        if (subscription?.bind) {
            subscription.bind('pusher:subscription_succeeded', () => {
                console.info('Battle live subscription succeeded', { channel: subscription.name });
                subscriptionSucceeded = true;
                subscriptionErrored = false;
                setStatus('Live: subscribed to battle channel.');
                attemptStopPolling();
            });

            subscription.bind('pusher:subscription_error', (status) => {
                console.error('Battle live subscription error', status);
                subscriptionErrored = true;
                setStatus('Live updates unavailable (subscription error).');
                startPolling({ expectEvent: true });
            });
        }

        channel.listen('.BattleUpdated', (payload) => {
            applyUpdate(payload, { fromEvent: true });
        });
    }

    if (shouldPoll()) {
        startPolling();
    }

    if (!initialEventTimeout && battleStatus === 'active') {
        initialEventTimeout = window.setTimeout(() => {
            if (!eventReceived && battleStatus === 'active') {
                console.warn('No BattleUpdated event received within 6 seconds; starting fallback polling.');
                startPolling({ expectEvent: true });
            }
        }, 6000);
    }

    startWatchdog();

    window.addEventListener('echo:status', (event) => {
        const state = event.detail?.state;
        if (!state) return;

        currentEchoState = state;
        if (state === 'connected') {
            attemptStopPolling();
            setStatus('Listening for opponent actions...');
        } else if (shouldPoll()) {
            startPolling();
        }
    });
}

document.addEventListener('DOMContentLoaded', () => initBattleLive());
