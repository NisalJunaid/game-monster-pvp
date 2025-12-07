import axios from 'axios';

const STATUS_SUCCESS = 'success';
const STATUS_ERROR = 'error';

function setStatus(message, type = STATUS_SUCCESS) {
    const statusEl = document.getElementById('location-status');
    if (!statusEl) {
        return;
    }

    statusEl.textContent = message;
    statusEl.classList.toggle('text-red-600', type === STATUS_ERROR);
    statusEl.classList.toggle('text-green-600', type === STATUS_SUCCESS);
}

function encounterCard(encounter, battleTemplate) {
    const speciesName = encounter.species?.name || 'Unknown';
    const level = encounter.rolled_level ?? encounter.level ?? '?';
    const maxHp = encounter.max_hp ?? encounter.maxHp ?? 0;
    const currentHp = encounter.current_hp ?? encounter.currentHp ?? maxHp;
    const hpPercent = maxHp ? Math.floor((currentHp / maxHp) * 100) : 100;
    const expiresAt = encounter.expires_at ? new Date(encounter.expires_at) : null;
    const zoneName = encounter.zone?.name || 'Unknown';
    const battleUrl = battleTemplate ? battleTemplate.replace('__BATTLE__', encounter.id ?? 'pending') : '#';

    return `<div class="border rounded p-4 shadow-sm flex flex-col space-y-3">
        <div class="flex items-center justify-between">
            <div>
                <p class="font-bold">${speciesName} (Lv ${level})</p>
                <p class="text-gray-500 text-sm">Zone: ${zoneName}</p>
            </div>
            <a href="${battleUrl}" class="px-3 py-2 bg-indigo-600 text-white rounded hover:bg-indigo-500">Battle</a>
        </div>
        <div>
            <div class="flex items-center justify-between text-sm text-gray-600 mb-1">
                <span>HP</span>
                <span>${currentHp ?? '?'} / ${maxHp || '?'}</span>
            </div>
            <div class="w-full bg-gray-200 rounded h-2">
                <div class="bg-green-500 h-2 rounded" style="width: ${hpPercent}%"></div>
            </div>
        </div>
        <div class="text-sm text-gray-500">Expires at ${expiresAt ? expiresAt.toLocaleTimeString() : 'soon'}</div>
    </div>`;
}

function renderEncounters(encounters, container) {
    const listEl = document.getElementById('encounter-list');
    const zoneName = document.getElementById('zone-name');

    if (!listEl) {
        return;
    }

    const battleTemplate = container?.dataset.battleTemplate;

    if (!encounters || encounters.length === 0) {
        listEl.innerHTML = '<p class="text-gray-600">No encounters available. Update your location to search again.</p>';
        if (zoneName) {
            zoneName.textContent = 'Unknown zone';
        }
        return;
    }

    const html = encounters.map((encounter) => encounterCard(encounter, battleTemplate)).join('');
    listEl.innerHTML = html;

    if (zoneName) {
        const currentZone = encounters[0]?.zone?.name || 'Unknown zone';
        zoneName.textContent = currentZone;
    }
}

function startEncounterChannel(userId, container) {
    if (!window.Echo || !userId) {
        return;
    }

    window.Echo.private(`users.${userId}`).listen('.WildEncountersUpdated', (event) => {
        renderEncounters(event.encounters ?? [], container);
    });
}

function startGeolocationSync(container) {
    if (!container || !navigator.geolocation) {
        return;
    }

    const updateUrl = container.dataset.updateUrl;
    if (!updateUrl) {
        return;
    }

    let lastDispatch = 0;
    let lastPosition = null;
    const minInterval = 8000;

    const sendUpdate = (position) => {
        if (!position) {
            return;
        }

        lastDispatch = Date.now();

        const payload = {
            lat: position.coords.latitude,
            lng: position.coords.longitude,
            accuracy_m: position.coords.accuracy ?? 10,
            speed_mps: position.coords.speed ?? undefined,
            recorded_at: new Date(position.timestamp || Date.now()).toISOString(),
        };

        axios
            .post(updateUrl, payload, { headers: { Accept: 'application/json' } })
            .then((response) => {
                const encounters = response.data?.encounters ?? [];
                if (response.data?.message) {
                    setStatus(response.data.message, STATUS_SUCCESS);
                }
                renderEncounters(encounters, container);
            })
            .catch((error) => {
                const message = error.response?.data?.message || 'Unable to update location right now.';
                setStatus(message, STATUS_ERROR);
            });
    };

    navigator.geolocation.watchPosition(
        (position) => {
            lastPosition = position;
            if (Date.now() - lastDispatch >= minInterval) {
                sendUpdate(position);
            }
        },
        () => setStatus('Unable to retrieve location.', STATUS_ERROR),
        { enableHighAccuracy: true, maximumAge: 5000, timeout: 10000 },
    );

    setInterval(() => {
        if (lastPosition && Date.now() - lastDispatch >= minInterval * 1.5) {
            sendUpdate(lastPosition);
        }
    }, 12000);
}

function wireManualControls(container) {
    const useLocation = document.getElementById('use-location');
    const form = document.getElementById('location-form');
    const latField = document.getElementById('lat');
    const lngField = document.getElementById('lng');
    const accuracyField = document.getElementById('accuracy');
    const recordedAtField = document.getElementById('recorded_at');
    const speedField = document.getElementById('speed_mps');

    useLocation?.addEventListener('click', () => {
        if (!navigator.geolocation) {
            setStatus('Geolocation is not supported in this browser.', STATUS_ERROR);
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) => {
                if (latField) latField.value = position.coords.latitude;
                if (lngField) lngField.value = position.coords.longitude;
                if (accuracyField) accuracyField.value = position.coords.accuracy ?? accuracyField?.value ?? 10;
                if (speedField && position.coords.speed !== null) speedField.value = position.coords.speed;
                if (recordedAtField) recordedAtField.value = new Date(position.timestamp || Date.now()).toISOString();
            },
            () => setStatus('Unable to retrieve location.', STATUS_ERROR),
        );
    });

    form?.addEventListener('submit', () => {
        if (recordedAtField && !recordedAtField.value) {
            recordedAtField.value = new Date().toISOString();
        }
    });
}

function initEncounters() {
    const container = document.getElementById('encounters-page');
    if (!container) {
        return;
    }

    const userId = document.querySelector('meta[name="user-id"]')?.content || container.dataset.userId;
    const initialEncounters = window.__INITIAL_ENCOUNTERS__ ?? [];

    renderEncounters(initialEncounters, container);
    wireManualControls(container);
    startGeolocationSync(container);
    startEncounterChannel(userId, container);
}

document.addEventListener('DOMContentLoaded', initEncounters);
