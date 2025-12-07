import axios from 'axios';

const escapeHtml = (value = '') => `${value}`.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');

const hpPercent = (monster) => {
    if (!monster) return 0;
    const max = Math.max(1, monster.max_hp || 0);
    return Math.max(0, Math.min(100, Math.floor(((monster.current_hp || 0) / max) * 100)));
};

const formatTypes = (monster) => {
    const types = monster?.type_names || [];

    return types.length ? types.join(', ') : 'Neutral';
};

const renderSide = (side, role) => {
    if (!side) {
        return `<p class="text-sm text-gray-500">No combatant available.</p>`;
    }

    const active = side.monsters?.[side.active_index ?? 0];
    const bench = (side.monsters || []).filter((_, idx) => idx !== (side.active_index ?? 0));
    const hp = hpPercent(active);
    const hpText = active ? `HP ${active.current_hp} / ${active.max_hp}` : 'HP 0 / 0';
    const status = active?.status?.name ? `Status: ${escapeHtml(active.status.name)}` : '';
    const statusClass = role === 'you' ? 'text-amber-300' : 'text-amber-700';
    const typeText = active ? formatTypes(active) : 'Neutral';
    const nameClass = role === 'you' ? '' : 'text-gray-900';
    const labelClass = role === 'you' ? 'text-slate-300' : 'text-gray-600';
    const bgBar = role === 'you' ? 'bg-emerald-400' : 'bg-rose-400';
    const barTrack = role === 'you' ? 'bg-slate-700' : 'bg-gray-200';

    return `
        <div class="flex items-center justify-between">
            <div>
                <h2 class="text-xl font-semibold ${nameClass}" data-monster-name="${role}">${escapeHtml(active?.name || 'No fighter')}</h2>
                <p class="text-sm ${labelClass}" data-monster-types="${role}">Types: ${escapeHtml(typeText)}</p>
                <p class="text-sm ${statusClass} ${active?.status ? '' : 'hidden'}" data-monster-status="${role}">${escapeHtml(status)}</p>
            </div>
            <div class="w-48" data-monster-hp-container="${role}">
                <div class="text-right text-xs ${labelClass}" data-monster-hp-text="${role}">${escapeHtml(hpText)}</div>
                <div class="w-full ${barTrack} rounded-full h-3">
                    <div class="h-3 rounded-full ${bgBar}" data-monster-hp-bar="${role}" style="width: ${hp}%"></div>
                </div>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-2 text-xs" data-bench-list="${role}">
            ${bench
                .map((monster) => `<span class="px-2 py-1 rounded-full ${role === 'you' ? 'bg-slate-800 border border-slate-700' : 'bg-gray-200 border border-gray-300'}">${escapeHtml(monster.name)} (HP ${monster.current_hp})</span>`)
                .join('')}
        </div>
    `;
};

const renderMoves = (moves = []) => {
    return moves
        .map(
            (move) => `
                <form method="POST" data-battle-action-form>
                    <input type="hidden" name="_token" value="${escapeHtml(document.head.querySelector('meta[name="csrf-token"]')?.content || '')}" />
                    <input type="hidden" name="type" value="move">
                    <input type="hidden" name="slot" value="${move.slot}">
                    <button class="w-full px-3 py-3 rounded-lg border border-gray-200 hover:border-emerald-400 hover:shadow text-left" data-move-slot="${move.slot}">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">${escapeHtml(move.name)}</span>
                            <span class="text-xs uppercase text-gray-500">Slot ${move.slot}</span>
                        </div>
                        <p class="text-sm text-gray-600">${escapeHtml(move.category ? move.category.charAt(0).toUpperCase() + move.category.slice(1) : 'Physical')} • ${escapeHtml(move.type || 'Neutral')} • Power ${move.power}</p>
                    </button>
                </form>
            `,
        )
        .join('');
};

const renderCommands = (state, viewerId) => {
    const participant = state.participants?.[viewerId];
    const isActive = (state?.status || 'active') === 'active';
    const isYourTurn = isActive && (state.next_actor_id ?? null) === viewerId;
    const active = participant?.monsters?.[participant.active_index ?? 0];
    const bench = (participant?.monsters || []).filter((_, idx) => idx !== (participant?.active_index ?? 0));

    const turnLabel = isYourTurn ? 'Your turn' : isActive ? 'Waiting for opponent' : 'Battle complete';
    const turnColor = isYourTurn ? 'text-emerald-600' : 'text-gray-500';

    if (!participant) {
        return `<div class="flex items-center justify-between"><h3 class="text-lg font-semibold">Battle commands</h3><span class="text-sm ${turnColor}" data-turn-indicator>${escapeHtml(turnLabel)}</span></div><p class="text-sm text-gray-600">Battle state unavailable.</p>`;
    }

    if (!isActive) {
        return `<div class="flex items-center justify-between"><h3 class="text-lg font-semibold">Battle commands</h3><span class="text-sm ${turnColor}" data-turn-indicator>${escapeHtml(turnLabel)}</span></div><p class="text-sm text-gray-600">Battle complete.</p>`;
    }

    if (!isYourTurn || !active) {
        return `<div class="flex items-center justify-between"><h3 class="text-lg font-semibold">Battle commands</h3><span class="text-sm ${turnColor}" data-turn-indicator>${escapeHtml(turnLabel)}</span></div><p class="text-sm text-gray-600">Waiting for opponent action...</p>`;
    }

    const moveButtons = renderMoves(active.moves || []);
    const csrf = document.head.querySelector('meta[name="csrf-token"]')?.content || '';
    const swapSection = bench.length
        ? `
            <form method="POST" class="flex items-center gap-2" data-battle-action-form>
                <input type="hidden" name="_token" value="${escapeHtml(csrf)}" />
                <input type="hidden" name="type" value="swap">
                <select name="monster_instance_id" class="border-gray-300 rounded">
                    ${bench
                        .map((monster) => `<option value="${monster.id}">Swap to ${escapeHtml(monster.name)} (HP ${monster.current_hp})</option>`)
                        .join('')}
                </select>
                <button class="px-3 py-2 bg-blue-600 text-white rounded hover:bg-blue-500">Swap</button>
            </form>
        `
        : `<p class="text-xs text-gray-500">No reserve monsters available${(active.id ?? null) === 0 ? '—using martial arts move set.' : '.'}</p>`;

    return `
        <div class="flex items-center justify-between">
            <h3 class="text-lg font-semibold">Battle commands</h3>
            <span class="text-sm ${turnColor}" data-turn-indicator>${escapeHtml(turnLabel)}</span>
        </div>
        <div class="grid md:grid-cols-2 gap-3">${moveButtons}</div>
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
    const players = initial.players || {};
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
    const logContainer = container.querySelector('[data-battle-log]');

    const opponentId = initial.battle?.player1_id === viewerId ? initial.battle?.player2_id : initial.battle?.player1_id;

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

    const render = () => {
        const yourSide = battleState.participants?.[viewerId];
        const opponentSide = opponentId ? battleState.participants?.[opponentId] : null;

        if (yourSideContainer) {
            yourSideContainer.innerHTML = '<p class="text-xs uppercase tracking-wide text-slate-300">You</p>' + renderSide(yourSide, 'you');
        }

        if (opponentSideContainer) {
            opponentSideContainer.innerHTML = '<p class="text-xs uppercase tracking-wide text-gray-500">Opponent</p>' + renderSide(opponentSide, 'opponent');
        }

        if (commandsContainer) {
            commandsContainer.innerHTML = renderCommands({ ...battleState, status: battleStatus }, viewerId);
        }

        if (logContainer) {
            logContainer.innerHTML = renderLog(battleState.log || [], players);
        }

        updateHeader();
    };

    const setStatus = (message) => {
        if (statusEl) {
            statusEl.textContent = message;
        }
    };

    const submitAction = (form) => {
        if (!actUrl) {
            return;
        }

        const formData = new FormData(form);

        axios
            .post(actUrl, formData)
            .then(() => setStatus('Action sent. Waiting for result...'))
            .catch(() => setStatus('Could not submit action right now.'));
    };

    container.addEventListener('submit', (event) => {
        const target = event.target;
        if (!(target instanceof HTMLFormElement) || !target.matches('[data-battle-action-form]')) {
            return;
        }

        event.preventDefault();
        submitAction(target);
    });

    render();

    if (!window.Echo || !battleId) {
        setStatus('Live updates unavailable. Actions will appear after refresh.');

        return;
    }

    setStatus('Listening for opponent actions...');

    window.Echo.private(`battles.${battleId}`).listen('.BattleUpdated', (payload) => {
        if (!payload || !payload.state) {
            return;
        }

        battleState = payload.state || battleState;
        battleStatus = payload.status || battleStatus;
        winnerId = payload.winner_user_id ?? winnerId;
        render();

        const isActive = battleStatus === 'active';
        setStatus(isActive ? 'Live: waiting for next move.' : 'Battle finished.');
    });
}

document.addEventListener('DOMContentLoaded', () => initBattleLive());
