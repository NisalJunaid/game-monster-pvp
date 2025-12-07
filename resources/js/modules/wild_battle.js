import axios from 'axios';

const clampPercent = (current = 0, max = 1) => {
    const safeMax = Math.max(1, max || 1);
    return Math.max(0, Math.min(100, Math.floor((current / safeMax) * 100)));
};

const formatStatus = (battle = {}) => ((battle.active ?? true) ? 'Active' : 'Resolved');

const renderTeamList = (monsters = [], activeId = null) => {
    if (!monsters.length) {
        return '<p class="text-sm text-slate-200">No team members.</p>';
    }

    return monsters
        .map((monster) => {
            const isActive = monster.id === activeId;
            const hp = `HP ${monster.current_hp} / ${monster.max_hp}`;
            const activeBadge = isActive
                ? '<span class="ml-2 text-emerald-300 text-xs bg-emerald-900/40 px-2 py-0.5 rounded-full">Active</span>'
                : '';

            return `<div class="flex items-center justify-between bg-slate-800/60 rounded px-3 py-2 ${
                isActive ? 'ring-2 ring-emerald-400' : ''
            }">
                <span>${monster.name} (Lv ${monster.level}) ${activeBadge}</span>
                <span class="text-slate-200">${hp}</span>
            </div>`;
        })
        .join('');
};

const moveOptions = (activeMonster = null) => {
    const moves = activeMonster?.moves?.length ? activeMonster.moves : [];

    if (moves.length) {
        return moves.slice(0, 4).map((move) => ({
            key: move.slot || move.name,
            label: move.name,
            helper: move.description || move.type || 'Move',
            style: move.style || 'monster',
        }));
    }

    return [
        { key: 'monster', label: 'Monster Technique', helper: 'Elemental strike', style: 'monster' },
        { key: 'martial', label: 'Martial Arts', helper: 'Physical combo', style: 'martial' },
    ];
};

const renderSwitchList = (monsters = [], activeId = null) => {
    const eligible = monsters.filter((monster) => monster.id !== activeId && monster.current_hp > 0);

    if (!eligible.length) {
        return '<p class="text-sm text-gray-600">No healthy teammates to switch into.</p>';
    }

    return eligible
        .map(
            (monster) => `<button class="px-3 py-2 rounded border border-gray-200 bg-white hover:border-indigo-400 text-left" data-switch-id="${monster.id}">
                    <div class="font-semibold">${monster.name}</div>
                    <p class="text-sm text-gray-600">Lv ${monster.level} • HP ${monster.current_hp} / ${monster.max_hp}</p>
                </button>`,
        )
        .join('');
};

const renderLog = (entries = []) => {
    if (!entries.length) {
        return '<p class="text-gray-600">No actions yet.</p>';
    }

    return entries
        .slice()
        .reverse()
        .map(
            (entry, index) => `<div class="border rounded p-3 bg-gray-50">
                <p class="font-semibold">${(entry.actor || 'Unknown').toString().toUpperCase()} used ${entry.type || 'action'}</p>
                <p class="text-xs text-gray-600">${entry.style ? `Style: ${entry.style}` : ''} ${entry.damage ? `Damage: ${entry.damage}` : ''} ${
                entry.multiplier ? `(x${entry.multiplier})` : ''
            }</p>
                <p class="text-xs text-gray-500">Log #${entries.length - index}</p>
            </div>`,
        )
        .join('');
};

const parseInitialState = () => {
    const el = document.querySelector('[data-wild-battle-state]');

    if (!el) {
        return null;
    }

    try {
        return JSON.parse(el.textContent || '{}');
    } catch (error) {
        console.error('Could not parse wild battle state', error);
        return null;
    }
};

export function initWildBattle() {
    const container = document.getElementById('wild-battle-page');

    if (!container) {
        return;
    }

    const moveUrl = container.dataset.moveUrl;
    const switchUrl = container.dataset.switchUrl;
    const tameUrl = container.dataset.tameUrl;
    const runUrl = container.dataset.runUrl;
    const encountersUrl = container.dataset.encountersUrl;
    const userId = container.dataset.userId;
    const backButton = container.querySelector('[data-back-button]');
    const actionStatus = container.querySelector('[data-action-status]');
    const tameResult = container.querySelector('[data-tame-result]');
    const turnIndicator = container.querySelector('[data-turn-indicator]');
    const statusLabel = container.querySelector('[data-battle-status]');
    const turnLabel = container.querySelector('[data-turn]');
    const playerName = container.querySelector('[data-player-name]');
    const playerLevel = container.querySelector('[data-player-level]');
    const playerStatus = container.querySelector('[data-player-status]');
    const playerHpText = container.querySelector('[data-player-hp-text]');
    const playerHpBar = container.querySelector('[data-player-hp-bar]');
    const playerTeam = container.querySelector('[data-player-team]');
    const wildName = container.querySelector('[data-wild-name]');
    const wildLevel = container.querySelector('[data-wild-level]');
    const wildStatus = container.querySelector('[data-wild-status]');
    const wildHpText = container.querySelector('[data-wild-hp-text]');
    const wildHpBar = container.querySelector('[data-wild-hp-bar]');
    const logEntries = container.querySelector('[data-log-entries]');
    const moveList = container.querySelector('[data-move-list]');
    const switchList = container.querySelector('[data-switch-list]');
    const actionPanels = container.querySelectorAll('[data-action-panel]');
    const actionTabs = container.querySelectorAll('[data-action-tab]');

    const initial = parseInitialState();

    if (!initial) {
        return;
    }

    let battle = initial.battle || {};
    let ticket = initial.ticket || {};
    let redirectTimeout = null;

    const setActionStatus = (message) => {
        if (actionStatus) {
            actionStatus.textContent = message || '';
        }
    };

    const checkResolution = () => {
        const active = battle?.active ?? true;
        if (active || redirectTimeout) {
            return;
        }

        if (turnIndicator) {
            turnIndicator.textContent = 'Battle resolved';
        }

        redirectTimeout = window.setTimeout(() => {
            if (backButton) {
                backButton.click();
            } else {
                window.location.href = encountersUrl || '/encounters';
            }
        }, 1200);
    };

    const renderMoves = (activeMonster) => {
        const moves = moveOptions(activeMonster);

        if (!moveList) return;

        moveList.innerHTML = moves
            .map(
                (move) => `<button class="px-3 py-3 rounded-lg border border-gray-200 bg-white hover:border-emerald-400" data-move-style="${move.style}" data-move-key="${move.key}">
                        <div class="flex items-center justify-between">
                            <span class="font-semibold">${move.label}</span>
                            <span class="text-xs text-gray-500">${move.helper}</span>
                        </div>
                    </button>`,
            )
            .join('');
    };

    const applyState = (nextBattle = {}, nextTicket = {}) => {
        battle = nextBattle || battle;
        ticket = { ...ticket, ...nextTicket };

        const activeMonster = (battle.player_monsters || []).find((m) => m.id === battle.player_active_monster_id) ||
            (battle.player_monsters || [])[0];
        const wild = battle.wild || {};

        const playerHpPercent = clampPercent(activeMonster?.current_hp ?? 0, activeMonster?.max_hp ?? 1);
        const wildHpPercent = clampPercent(wild.current_hp ?? 0, wild.max_hp ?? 1);

        if (playerName) playerName.textContent = activeMonster?.name || 'Unknown';
        if (playerLevel) playerLevel.textContent = `Level ${activeMonster?.level ?? '?'}`;
        if (playerStatus) playerStatus.textContent = activeMonster?.current_hp > 0 ? 'Ready' : 'Fainted';
        if (playerHpText) playerHpText.textContent = `HP ${activeMonster?.current_hp ?? 0} / ${activeMonster?.max_hp ?? 0}`;
        if (playerHpBar) playerHpBar.style.width = `${playerHpPercent}%`;
        if (playerTeam) playerTeam.innerHTML = renderTeamList(battle.player_monsters || [], battle.player_active_monster_id);

        if (wildName) wildName.textContent = ticket.species?.name || wild.name || 'Wild';
        if (wildLevel) wildLevel.textContent = `Level ${ticket.species?.level ?? battle?.wild?.level ?? '?'}`;
        if (wildStatus) wildStatus.textContent = wild.current_hp <= 0 ? 'Fainted' : 'Alert';
        if (wildHpText) wildHpText.textContent = `HP ${wild.current_hp ?? 0} / ${wild.max_hp ?? 0}`;
        if (wildHpBar) wildHpBar.style.width = `${wildHpPercent}%`;

        if (logEntries) logEntries.innerHTML = renderLog(battle.last_action_log || []);
        if (moveList) renderMoves(activeMonster);
        if (switchList) switchList.innerHTML = renderSwitchList(battle.player_monsters || [], battle.player_active_monster_id);
        if (turnIndicator) turnIndicator.textContent = (battle.active ?? true) ? 'Choose your move' : 'Battle resolved';
        if (statusLabel) statusLabel.textContent = `State: ${formatStatus(battle)}`;
        if (turnLabel) turnLabel.textContent = battle.turn ?? 1;

        checkResolution();
    };

    const setActivePanel = (name) => {
        actionPanels.forEach((panel) => {
            const isActive = panel.dataset.actionPanel === name;
            panel.classList.toggle('hidden', !isActive);
        });

        actionTabs.forEach((tab) => {
            const isActive = tab.dataset.actionTab === name;
            tab.classList.toggle('bg-slate-900', isActive);
            tab.classList.toggle('text-white', isActive);
            tab.classList.toggle('bg-gray-200', !isActive);
            tab.classList.toggle('text-gray-800', !isActive);
        });
    };

    const submitAction = (url, payload = {}) => {
        if (!url) return Promise.resolve();
        setActionStatus('Sending action...');
        return axios
            .post(url, payload, { headers: { Accept: 'application/json' } })
            .then((response) => {
                const data = response.data || {};
                applyState(data.battle || battle, data.ticket || ticket);
                setActionStatus('Action applied.');
                return data;
            })
            .catch((error) => {
                console.error('Wild battle action failed', error);
                const message = error.response?.data?.message || 'Unable to process action right now.';
                setActionStatus(message);
            });
    };

    container.addEventListener('click', (event) => {
        const target = event.target;

        if (target instanceof HTMLElement && target.closest('[data-action-tab]')) {
            const tab = target.closest('[data-action-tab]');
            setActivePanel(tab.dataset.actionTab);
            return;
        }

        if (target instanceof HTMLElement && target.closest('[data-move-style]')) {
            const moveBtn = target.closest('[data-move-style]');
            const style = moveBtn.dataset.moveStyle;
            submitAction(moveUrl, { style });
            return;
        }

        if (target instanceof HTMLElement && target.closest('[data-switch-id]')) {
            const switchBtn = target.closest('[data-switch-id]');
            const monsterId = switchBtn.dataset.switchId;
            submitAction(switchUrl, { player_monster_id: monsterId });
            return;
        }

        if (target instanceof HTMLElement && target.matches('[data-action-tame]')) {
            tameResult.textContent = '';
            submitAction(tameUrl).then((data) => {
                if (!data) return;
                if (typeof data.roll !== 'undefined' && typeof data.chance !== 'undefined') {
                    const rollPercent = Math.round((data.roll || 0) * 100);
                    const chancePercent = Math.round((data.chance || 0) * 100);
                    tameResult.textContent = `Tame roll ${rollPercent}% vs chance ${chancePercent}%`;
                }
            });
            return;
        }

        if (target instanceof HTMLElement && target.matches('[data-action-run]')) {
            submitAction(runUrl);
        }
    });

    backButton?.addEventListener('click', (event) => {
        event.preventDefault();
        window.location.href = encountersUrl || '/encounters';
    });

    setActivePanel('move');
    applyState(battle, ticket);

    if (window.Echo && userId) {
        window.Echo.private(`users.${userId}`).listen('.WildBattleUpdated', (payload) => {
            if (!payload || `${payload.ticket_id}` !== `${ticket.id}`) {
                return;
            }

            applyState(payload.battle || battle, { status: payload.status || ticket.status });
            setActionStatus('Live update received.');
        });
    }
}

if (document.readyState !== 'loading') {
    initWildBattle();
} else {
    document.addEventListener('DOMContentLoaded', initWildBattle);
}
