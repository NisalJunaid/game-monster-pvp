<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Zone Map</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; padding: 0; }
        header { padding: 1rem; background: #111827; color: #fff; }
        main { display: flex; gap: 1rem; padding: 1rem; }
        #map { width: 70%; height: 80vh; border: 1px solid #e5e7eb; border-radius: 8px; }
        .panel { width: 30%; border: 1px solid #e5e7eb; border-radius: 8px; padding: 1rem; box-shadow: 0 1px 2px rgba(0,0,0,0.08); overflow-y: auto; height: 80vh; }
        .field { margin-bottom: 0.75rem; }
        label { display: block; font-weight: 600; margin-bottom: 0.35rem; }
        input[type="text"], input[type="number"] { width: 100%; padding: 0.5rem; border: 1px solid #d1d5db; border-radius: 4px; }
        .zones-list { margin-top: 1rem; }
        .zones-list button { width: 100%; text-align: left; padding: 0.5rem; margin-bottom: 0.4rem; border: 1px solid #d1d5db; border-radius: 4px; background: #f3f4f6; cursor: pointer; }
        .zones-list button:hover { background: #e5e7eb; }
        .actions { display: flex; gap: 0.5rem; align-items: center; }
        .status { margin-bottom: 0.75rem; padding: 0.5rem 0.75rem; background: #ecfdf3; color: #047857; border: 1px solid #bbf7d0; border-radius: 6px; }
        .form-actions { display: flex; gap: 0.5rem; }
    </style>
</head>
<body>
<header>
    <h1>Zone Management</h1>
</header>
<main>
    <div id="map"></div>
    <div class="panel">
        @if(session('status'))
            <div class="status">{{ session('status') }}</div>
        @endif
        <form id="zone-form" method="POST" action="{{ route('admin.zones.store') }}">
            @csrf
            <input type="hidden" id="zone-form-method" name="_method" value="POST">
            <input type="hidden" name="shape_type" id="shape_type" value="polygon">
            <input type="hidden" name="shape_json" id="shape_json">

            <div class="field">
                <label for="name">Zone Name</label>
                <input type="text" id="name" name="name" required>
            </div>

            <div class="field">
                <label for="priority">Priority</label>
                <input type="number" id="priority" name="priority" value="0">
            </div>

            <div class="field actions">
                <input type="checkbox" id="is_active" name="is_active" value="1" checked>
                <label for="is_active">Active</label>
            </div>

            <div class="field">
                <label>Shape</label>
                <p>Draw a polygon or circle on the map, or click an existing zone to edit.</p>
            </div>

            <div class="form-actions">
                <button type="submit">Save Zone</button>
                <button type="button" id="new-zone">New Zone</button>
            </div>
        </form>

        <div class="zones-list">
            <h3>Existing Zones</h3>
            <div id="zones-buttons"></div>
        </div>
    </div>
</main>
<script>
    const zones = @json($zones);
    const storeUrl = @json(route('admin.zones.store'));

    let map;
    let drawingManager;
    let activeShape = null;
    let activeZoneId = null;
    let drawnOverlay = null;
    const overlaysByZone = new Map();
    let shapeListeners = [];

    function initMap() {
        map = new google.maps.Map(document.getElementById('map'), {
            center: { lat: 37.7749, lng: -122.4194 },
            zoom: 10,
        });

        drawingManager = new google.maps.drawing.DrawingManager({
            drawingControl: true,
            drawingControlOptions: {
                position: google.maps.ControlPosition.TOP_CENTER,
                drawingModes: ['polygon', 'circle'],
            },
            polygonOptions: {
                editable: true,
                fillOpacity: 0.2,
                strokeColor: '#2563eb',
            },
            circleOptions: {
                editable: true,
                fillOpacity: 0.2,
                strokeColor: '#dc2626',
            },
        });

        drawingManager.setMap(map);

        google.maps.event.addListener(drawingManager, 'overlaycomplete', (event) => {
            if (drawnOverlay) {
                drawnOverlay.setMap(null);
            }

            drawnOverlay = event.overlay;
            drawnOverlay.isUserDrawn = true;
            setActiveShape(event.overlay, null, event.type);
            drawingManager.setDrawingMode(null);
        });

        renderExistingZones();
        renderZoneButtons();
        document.getElementById('new-zone').addEventListener('click', resetForm);
    }

    function renderExistingZones() {
        zones.forEach((zone) => {
            const overlay = createOverlayForZone(zone);

            overlay.addListener('click', () => {
                setActiveShape(overlay, zone.id, zone.shape_type);
                populateForm(zone);
            });

            overlaysByZone.set(zone.id, overlay);
        });
    }

    function createOverlayForZone(zone) {
        if (zone.shape_type === 'polygon' && zone.shape && zone.shape.path) {
            return new google.maps.Polygon({
                paths: zone.shape.path,
                map,
                fillOpacity: 0.12,
                strokeColor: '#2563eb',
                editable: false,
            });
        }

        if (zone.shape_type === 'circle' && zone.shape && zone.shape.center) {
            return new google.maps.Circle({
                center: zone.shape.center,
                radius: zone.shape.radius_m,
                map,
                fillOpacity: 0.12,
                strokeColor: '#dc2626',
                editable: false,
            });
        }

        return new google.maps.Polygon({ map });
    }

    function setActiveShape(shape, zoneId = null, type = null) {
        if (activeShape && activeShape !== shape && activeShape.isUserDrawn) {
            activeShape.setMap(null);
        }

        activeShape = shape;
        activeZoneId = zoneId;

        const shapeType = type || (shape instanceof google.maps.Circle ? 'circle' : 'polygon');
        document.getElementById('shape_type').value = shapeType;

        if (shape instanceof google.maps.Polygon) {
            shape.setEditable(true);
        }

        if (shape instanceof google.maps.Circle) {
            shape.setEditable(true);
        }

        attachShapeListeners(shape, shapeType);
        syncShapeFields();
    }

    function attachShapeListeners(shape, shapeType) {
        clearShapeListeners();

        if (shapeType === 'polygon' && shape.getPath) {
            shapeListeners.push(shape.getPath().addListener('insert_at', syncShapeFields));
            shapeListeners.push(shape.getPath().addListener('set_at', syncShapeFields));
            shapeListeners.push(shape.getPath().addListener('remove_at', syncShapeFields));
        }

        if (shapeType === 'circle') {
            shapeListeners.push(shape.addListener('center_changed', syncShapeFields));
            shapeListeners.push(shape.addListener('radius_changed', syncShapeFields));
        }
    }

    function clearShapeListeners() {
        shapeListeners.forEach((listener) => listener.remove());
        shapeListeners = [];
    }

    function populateForm(zone) {
        const form = document.getElementById('zone-form');
        document.getElementById('name').value = zone.name;
        document.getElementById('priority').value = zone.priority;
        document.getElementById('is_active').checked = zone.is_active;
        document.getElementById('shape_type').value = zone.shape_type;

        form.action = @json(url('/admin/zones')) + '/' + zone.id;
        document.getElementById('zone-form-method').value = 'PUT';

        const overlay = overlaysByZone.get(zone.id);
        if (overlay) {
            setActiveShape(overlay, zone.id, zone.shape_type);
        }
    }

    function resetForm() {
        const form = document.getElementById('zone-form');
        form.reset();
        form.action = storeUrl;
        document.getElementById('zone-form-method').value = 'POST';
        document.getElementById('shape_type').value = 'polygon';
        document.getElementById('shape_json').value = '';
        activeZoneId = null;
        if (drawnOverlay) {
            drawnOverlay.setMap(null);
            drawnOverlay = null;
        }
        activeShape = null;
    }

    function renderZoneButtons() {
        const container = document.getElementById('zones-buttons');
        container.innerHTML = '';
        zones.forEach((zone) => {
            const button = document.createElement('button');
            button.textContent = `${zone.name} (priority ${zone.priority})`;
            button.addEventListener('click', () => populateForm(zone));
            container.appendChild(button);
        });
    }

    function syncShapeFields() {
        if (!activeShape) {
            return;
        }

        const shapeType = document.getElementById('shape_type').value;
        let shapePayload = {};

        if (shapeType === 'polygon' && activeShape.getPath) {
            const path = activeShape.getPath().getArray().map((latLng) => ({
                lat: latLng.lat(),
                lng: latLng.lng(),
            }));
            shapePayload = { path };
        } else if (shapeType === 'circle' && activeShape.getCenter) {
            shapePayload = {
                center: {
                    lat: activeShape.getCenter().lat(),
                    lng: activeShape.getCenter().lng(),
                },
                radius_m: activeShape.getRadius(),
            };
        }

        document.getElementById('shape_json').value = JSON.stringify(shapePayload);
    }

    window.initMap = initMap;
</script>
<script src="https://maps.googleapis.com/maps/api/js?key={{ $googleMapsApiKey }}&libraries=drawing&callback=initMap" async defer></script>
</body>
</html>
