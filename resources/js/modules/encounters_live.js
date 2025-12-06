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

function renderEncounter(encounter, resolveTemplate) {
    const card = document.getElementById('encounter-card');
    const expiry = document.getElementById('encounter-expiry');

    if (!card) {
        return;
    }

    if (!encounter) {
        card.innerHTML = '<p class="text-gray-600">No encounters available. Update your location to search again.</p>';
        if (expiry) {
            expiry.textContent = '';
        }

        return;
    }

    const ticketId = encounter.id || encounter.ticket_id;
    const speciesName = encounter.species?.name || 'Unknown';
    const zoneName = encounter.zone?.name || 'Unknown';
    const rolledLevel = encounter.rolled_level || encounter.level;
    const expiresAt = encounter.expires_at ? new Date(encounter.expires_at) : null;
    const resolveUrl = resolveTemplate && ticketId ? resolveTemplate.replace('__ID__', ticketId) : null;

    const resolveForm = resolveUrl
        ? `<form method="POST" action="${resolveUrl}" data-resolve-form>
                <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.content || ''}">
                <button class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-500">Resolve Capture</button>
           </form>`
        : '';

    card.innerHTML = `<div class="flex items-center justify-between">
        <div>
            <p class="font-bold">${speciesName} (Lv ${rolledLevel ?? '?'})</p>
            <p class="text-gray-500 text-sm">Zone: ${zoneName}</p>
        </div>
        ${resolveForm}
    </div>`;

    if (expiry) {
        expiry.textContent = expiresAt ? `Expires ${expiresAt.toLocaleTimeString()}` : '';
    }
}

function showEncounterToast(encounterPageUrl, encounter) {
    if (!encounterPageUrl || document.getElementById('encounter-toast')) {
        return;
    }

    const toast = document.createElement('div');
    toast.id = 'encounter-toast';
    toast.className = 'fixed bottom-4 right-4 bg-teal-600 text-white px-4 py-3 rounded shadow-lg flex items-center space-x-3 z-50';
    toast.innerHTML = `<div>
            <p class="font-semibold">New encounter available!</p>
            <p class="text-sm">${encounter.species?.name ?? 'A wild monster'} in ${encounter.zone?.name ?? 'nearby area'}</p>
        </div>
        <a href="${encounterPageUrl}" class="bg-white text-teal-700 px-3 py-1 rounded font-semibold">View</a>`;

    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 8000);
}

function handleEncounterEvent(encounter, resolveTemplate, isEncounterPage, encounterPageUrl) {
    renderEncounter(encounter, resolveTemplate);

    if (!isEncounterPage) {
        showEncounterToast(encounterPageUrl, encounter);
    }
}

function startEncounterChannel(userId, resolveTemplate, isEncounterPage, encounterPageUrl) {
    if (!window.Echo || !userId) {
        return;
    }

    window.Echo.private(`users.${userId}`).listen('.EncounterIssued', (event) => {
        const encounter = {
            id: event.ticket_id,
            rolled_level: event.level,
            level: event.level,
            species: event.species,
            zone: event.zone,
            expires_at: event.expires_at,
        };

        handleEncounterEvent(encounter, resolveTemplate, isEncounterPage, encounterPageUrl);
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
                const encounter = response.data?.encounter ?? null;
                if (response.data?.message) {
                    setStatus(response.data.message, STATUS_SUCCESS);
                }
                renderEncounter(encounter, container.dataset.resolveTemplate);
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

function initEncountersLive() {
    const container = document.getElementById('encounter-live');
    const resolveTemplate = container?.dataset.resolveTemplate;
    const encounterPageUrl = document.querySelector('meta[name="encounter-index-url"]')?.content || container?.dataset.encounterUrl;
    const userId = document.querySelector('meta[name="user-id"]')?.content || container?.dataset.userId;
    const initialEncounter = window.__INITIAL_ENCOUNTER__ ?? null;
    const isEncounterPage = !!container;

    if (container) {
        renderEncounter(initialEncounter, resolveTemplate);
        wireManualControls(container);
        startGeolocationSync(container);
    }

    startEncounterChannel(userId, resolveTemplate, isEncounterPage, encounterPageUrl);
}

document.addEventListener('DOMContentLoaded', initEncountersLive);
