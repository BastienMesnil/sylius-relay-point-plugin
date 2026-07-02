/* global L */
import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for the relay point picker widget.
 *
 * Values:
 *   searchUrl       (String)  - URL of RelayPointSearchController::search
 *   geocodeUrl      (String)  - URL of RelayPointSearchController::geocode
 *   methodCodes     (Array)   - JSON array of Sylius shipping method codes to search
 *   addressStreet   (String)  - Pre-filled from checkout shipping address
 *   addressCity     (String)
 *   addressPostcode (String)
 *   addressCountry  (String)
 *
 * Events dispatched:
 *   relay-point-picker:selected  — detail: { point, shippingMethodCode }
 *   relay-point-picker:cleared   — dispatched when the selection is reset
 *
 * The host app listens to relay-point-picker:selected to persist the selection
 * (e.g. POST to a session endpoint, update a hidden form field, etc.).
 */
export default class extends Controller {
    static targets = [
        'searchInput',
        'list',
        'modal',
        'filterContainer',
        'pointName', 'pointAddress', 'pointCarrier', 'pointDistance',
        'selectedPointInfo',
        'openingHoursContainer',
    ];

    static values = {
        searchUrl: String,
        geocodeUrl: { type: String, default: '/relay-points/geocode' },
        selectUrl: { type: String, default: '' },  // POST endpoint; if empty, only the JS event is dispatched
        cartToken: { type: String, default: '' },
        methodCodes: { type: Array, default: [] },
        addressStreet: String,
        addressCity: String,
        addressPostcode: String,
        addressCountry: String,
        sortBy: { type: String, default: 'distance' },
    };

    // Carrier color palette — index is assigned per unique carrierCode
    static PALETTE = [
        { bg: '#95124B26', text: '#95124B' },
        { bg: '#009BDE26', text: '#009BDE' },
        { bg: '#e27c0826', text: '#e27c08' },
        { bg: '#2563EB26', text: '#2563EB' },
        { bg: '#16A34A26', text: '#16A34A' },
    ];

    connect() {
        this.map = null;
        this.markers = {};
        this.points = [];
        this.selectedPointKey = null;
        this.currentCoords = null;
        this.activeCarrierFilter = null; // null = all
        this.carrierColorMap = {};

        if (this.hasSearchInputTarget) {
            this.searchInputTarget.value =
                [this.addressStreetValue, this.addressPostcodeValue, this.addressCityValue]
                    .filter(Boolean).join(', ');
        }

        if (!this.element.classList.contains('hidden')) {
            this._loadLeafletAndInit();
        }

        this._onPointSelected = this._handleExternalSelect.bind(this);
        window.addEventListener('relay-point-picker:selected', this._onPointSelected);
    }

    disconnect() {
        if (this.map) { this.map.remove(); this.map = null; }
        window.removeEventListener('relay-point-picker:selected', this._onPointSelected);
    }

    // Called externally when the container is revealed (e.g. relay radio selected)
    ensureInitialized() {
        if (this.map) {
            setTimeout(() => this.map.invalidateSize(), 100);
        } else {
            this._loadLeafletAndInit();
        }
    }

    // ── Search form ──────────────────────────────────────────────────────────

    async handleSearch(event) {
        if (event) event.preventDefault();
        const query = this.hasSearchInputTarget ? this.searchInputTarget.value.trim() : '';
        if (!query) return;
        await this._geocodeAndLoad(query);
    }

    // ── Filter / sort ────────────────────────────────────────────────────────

    handleSortChange(event) {
        this.sortByValue = event.target.value;
        this._renderPoints();
    }

    handleCarrierFilter(event) {
        const val = event.target.value;
        this.activeCarrierFilter = val === 'all' ? null : val;
        this._renderPoints();
    }

    // ── Modal (mobile) ───────────────────────────────────────────────────────

    openModal() {
        if (!this.hasModalTarget) return;
        this.modalTarget.classList.remove('hidden');
        this.modalTarget.classList.add('flex');
        document.body.classList.add('overflow-hidden');
        setTimeout(() => this.map?.invalidateSize(), 100);
    }

    closeModal() {
        if (!this.hasModalTarget) return;
        this.modalTarget.classList.add('hidden');
        this.modalTarget.classList.remove('flex');
        document.body.classList.remove('overflow-hidden');
    }

    toggleHours(event) {
        event.stopPropagation();
        const btn = event.currentTarget;
        const content = btn.closest('[data-hours-root]')?.querySelector('[data-hours-content]');
        const arrow = btn.querySelector('[data-hours-arrow]');
        if (!content) return;
        const hidden = content.classList.toggle('hidden');
        if (arrow) arrow.style.transform = hidden ? '' : 'rotate(180deg)';
        btn.querySelector('[data-hours-label]').textContent = hidden ? 'Voir les horaires' : 'Fermer';
    }

    // ── Selection ────────────────────────────────────────────────────────────

    selectPoint(event) {
        const key = event.currentTarget.dataset.pointKey;
        const point = this.points.find(p => this._key(p) === key);
        if (!point) return;
        this._selectPoint(point);
    }

    async confirmSelection() {
        const point = this.points.find(p => this._key(p) === this.selectedPointKey);
        if (!point) return;

        // Persist to session via plugin endpoint when selectUrl is configured
        if (this.selectUrlValue) {
            try {
                const res = await fetch(this.selectUrlValue, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({
                        shippingMethodCode: point.shippingMethodCode ?? '',
                        cartToken: this.cartTokenValue || undefined,
                        point,
                    }),
                });
                if (!res.ok) {
                    console.error('[relay-point-picker] select endpoint returned', res.status);
                }
            } catch (e) {
                console.error('[relay-point-picker] persist error', e);
            }
        }

        this.element.dispatchEvent(new CustomEvent('relay-point-picker:confirmed', {
            bubbles: true,
            detail: { point },
        }));
    }

    // ── Private ──────────────────────────────────────────────────────────────

    _loadLeafletAndInit() {
        if (typeof L !== 'undefined') { this._initMap(); return; }

        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        document.head.appendChild(link);

        const script = document.createElement('script');
        script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        script.onload = () => this._initMap();
        document.head.appendChild(script);
    }

    async _initMap() {
        if (this.map) return;

        const mapEl = this.element.querySelector('[data-relay-map]');
        if (!mapEl) { console.warn('[relay-point-picker] Missing [data-relay-map] element'); return; }

        this.map = L.map(mapEl).setView([46.603354, 1.888334], 6);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
        }).addTo(this.map);

        await this._geocodeAndLoad(
            [this.addressStreetValue, this.addressPostcodeValue, this.addressCityValue, this.addressCountryValue]
                .filter(Boolean).join(', '),
        );
    }

    async _geocodeAndLoad(query) {
        if (!query) return;

        try {
            const res = await fetch(`${this.geocodeUrlValue}?q=${encodeURIComponent(query)}`);
            if (!res.ok) return;
            const data = await res.json();
            if (!data) return;

            this.currentCoords = [data.latitude, data.longitude];
            this._placeSearchMarker(this.currentCoords);

            await this._loadPoints({
                latitude: data.latitude,
                longitude: data.longitude,
                postcode: data.postcode,
                city: data.city,
                countryCode: data.countryCode,
            });
        } catch (e) {
            console.error('[relay-point-picker] geocode error', e);
        }
    }

    _placeSearchMarker(coords) {
        if (!this.map) return;
        this.locationMarker?.remove();
        this.locationMarker = L.circleMarker(coords, {
            radius: 8, fillColor: '#3b82f6', color: '#fff',
            weight: 2, opacity: 1, fillOpacity: 0.8,
        }).addTo(this.map).bindTooltip('Votre position', { permanent: false, direction: 'top' });
        this.map.setView(coords, 13);
    }

    async _loadPoints(addressData) {
        if (!this.methodCodesValue.length) return;

        const fetches = this.methodCodesValue.map(code => {
            const params = new URLSearchParams({ shipping_method_code: code, limit: '10' });
            if (addressData.latitude) params.set('latitude', addressData.latitude);
            if (addressData.longitude) params.set('longitude', addressData.longitude);
            if (addressData.postcode) params.set('postcode', addressData.postcode);
            if (addressData.city) params.set('city', addressData.city);
            if (addressData.countryCode) params.set('country_code', addressData.countryCode);
            return fetch(`${this.searchUrlValue}?${params}`)
                .then(r => r.ok ? r.json() : [])
                .catch(() => []);
        });

        const results = await Promise.all(fetches);
        this.points = results.flat();

        this._buildCarrierColorMap();
        this._buildCarrierFilter();
        this._renderPoints();
    }

    _buildCarrierColorMap() {
        const codes = [...new Set(this.points.map(p => p.carrierCode))];
        codes.forEach((code, i) => {
            this.carrierColorMap[code] = this.constructor.PALETTE[i % this.constructor.PALETTE.length];
        });
    }

    _buildCarrierFilter() {
        if (!this.hasFilterContainerTarget) return;
        const codes = [...new Set(this.points.map(p => p.carrierCode))];
        if (codes.length <= 1) { this.filterContainerTarget.classList.add('hidden'); return; }

        this.filterContainerTarget.classList.remove('hidden');
        const list = this.filterContainerTarget.querySelector('[data-carrier-list]');
        if (!list) return;

        list.innerHTML = codes.map(code => {
            const color = this.carrierColorMap[code];
            return `
            <label class="flex items-center gap-2 py-1.5 cursor-pointer">
                <input type="radio" name="relay_carrier_filter" value="${code}"
                    data-action="change->relay-point-picker#handleCarrierFilter"
                    class="form-radio h-4 w-4" style="accent-color: ${color.text}">
                <span class="text-sm" style="color: ${color.text}">${code.replace(/_/g, ' ')}</span>
            </label>`;
        }).join('');

        // "All" option
        list.insertAdjacentHTML('afterbegin', `
            <label class="flex items-center gap-2 py-1.5 cursor-pointer">
                <input type="radio" name="relay_carrier_filter" value="all" checked
                    data-action="change->relay-point-picker#handleCarrierFilter"
                    class="form-radio h-4 w-4">
                <span class="text-sm font-medium">Tous les transporteurs</span>
            </label>`);
    }

    _renderPoints() {
        if (!this.map) return;

        Object.values(this.markers).forEach(m => m.remove());
        this.markers = {};

        if (this.hasListTarget) this.listTarget.innerHTML = '';

        let pts = this.activeCarrierFilter
            ? this.points.filter(p => p.carrierCode === this.activeCarrierFilter)
            : this.points;

        if (this.currentCoords) {
            pts = pts.map(p => ({
                ...p,
                distanceInMeters: p.distanceInMeters ?? this._haversine(
                    this.currentCoords[0], this.currentCoords[1],
                    p.latitude, p.longitude,
                ),
            }));
        }

        pts = this.sortByValue === 'distance'
            ? [...pts].sort((a, b) => (a.distanceInMeters ?? Infinity) - (b.distanceInMeters ?? Infinity))
            : pts;

        // Jitter duplicate coordinates
        const coordGroups = {};
        pts.forEach(p => {
            const k = `${p.latitude},${p.longitude}`;
            (coordGroups[k] = coordGroups[k] || []).push(p);
        });

        pts.forEach(point => {
            if (!point.latitude || !point.longitude) return;

            const key = this._key(point);
            const color = this.carrierColorMap[point.carrierCode] ?? this.constructor.PALETTE[0];

            let lat = parseFloat(point.latitude);
            let lng = parseFloat(point.longitude);
            const siblings = coordGroups[`${point.latitude},${point.longitude}`];
            if (siblings && siblings.length > 1) {
                const idx = siblings.indexOf(point);
                const angle = (idx / siblings.length) * Math.PI * 2;
                lat += Math.cos(angle) * 0.00015;
                lng += Math.sin(angle) * 0.00015;
            }

            const icon = L.divIcon({
                className: 'relay-map-pin',
                html: `<div style="background:${color.text};border:2px solid white;border-radius:50%;width:20px;height:20px;box-shadow:0 2px 4px rgba(0,0,0,.3)"></div>`,
                iconSize: [20, 20],
                iconAnchor: [10, 10],
            });

            const marker = L.marker([lat, lng], { icon })
                .addTo(this.map)
                .bindPopup(this._popupHtml(point, color, key));

            this.markers[key] = marker;

            if (this.hasListTarget) {
                this.listTarget.appendChild(this._buildListItem(point, color, key));
            }
        });

        if (!pts.length && this.hasListTarget) {
            this.listTarget.innerHTML =
                '<p class="p-6 text-center text-sm text-gray-500 italic">Aucun point relais trouvé.</p>';
        }
    }

    _popupHtml(point, color, key) {
        const dist = point.distanceInMeters
            ? `${(point.distanceInMeters / 1000).toFixed(1)} km`
            : '';
        return `
            <div class="min-w-[180px] p-2">
                <strong class="block text-gray-900 text-sm">${point.name}</strong>
                <span class="block text-xs text-gray-500">${point.street}</span>
                <span class="block text-xs text-gray-500">${point.postcode} ${point.city}</span>
                ${dist ? `<span class="block text-xs text-gray-400 mt-1">${dist}</span>` : ''}
                <span class="inline-block mt-1 text-[10px] font-bold uppercase px-2 py-0.5 rounded"
                    style="background:${color.bg};color:${color.text}">${point.carrierCode.replace(/_/g, ' ')}</span>
                <button type="button"
                    class="mt-3 w-full text-xs font-semibold py-1.5 rounded-full text-white"
                    style="background:${color.text}"
                    data-action="click->relay-point-picker#selectPoint"
                    data-point-key="${key}">
                    Choisir ce point
                </button>
            </div>`;
    }

    _buildListItem(point, color, key) {
        const isSelected = this.selectedPointKey === key;
        const dist = point.distanceInMeters
            ? `${(point.distanceInMeters / 1000).toFixed(1)} km`
            : '';

        const hoursHtml = point.openingHours?.length
            ? `<div data-hours-root>
                <button type="button" data-action="click->relay-point-picker#toggleHours"
                    class="text-[10px] text-blue-600 hover:underline flex items-center gap-1 mt-0.5">
                    <span data-hours-label>Voir les horaires</span>
                    <svg data-hours-arrow class="w-3 h-3 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div data-hours-content class="hidden mt-1.5 text-[10px] text-gray-500 space-y-0.5 border-t border-gray-100 pt-1.5">
                    ${point.openingHours.map(oh =>
                        `<div class="flex justify-between gap-4">
                            <span class="font-medium">${oh.day}</span>
                            <span>${oh.hours}</span>
                        </div>`
                    ).join('')}
                </div>
               </div>`
            : '';

        const item = document.createElement('div');
        item.className = [
            'group p-3 cursor-pointer border-b border-gray-100 transition-all',
            'hover:bg-gray-50',
            isSelected ? 'bg-blue-50 border-l-2 border-l-blue-500' : '',
        ].join(' ');
        item.dataset.pointKey = key;
        item.dataset.action = 'click->relay-point-picker#selectPoint';

        item.innerHTML = `
            <div class="flex justify-between items-start gap-2">
                <div class="flex-1 min-w-0">
                    <strong class="block text-xs text-gray-900 truncate">${point.name}</strong>
                    <span class="block text-[10px] text-gray-500 truncate">${point.street}, ${point.postcode} ${point.city}</span>
                    ${dist ? `<span class="text-[9px] text-gray-400">${dist}</span>` : ''}
                    ${hoursHtml}
                </div>
                <span class="flex-shrink-0 text-[10px] font-bold uppercase px-2 py-0.5 rounded whitespace-nowrap"
                    style="background:${color.bg};color:${color.text}">
                    ${point.carrierCode.replace(/_/g, ' ')}
                </span>
            </div>`;

        return item;
    }

    _selectPoint(point) {
        const key = this._key(point);
        this.selectedPointKey = key;

        // Highlight list
        this.listTarget?.querySelectorAll('[data-point-key]').forEach(el => {
            el.classList.toggle('bg-blue-50', el.dataset.pointKey === key);
            el.classList.toggle('border-l-2', el.dataset.pointKey === key);
            el.classList.toggle('border-l-blue-500', el.dataset.pointKey === key);
        });

        // Center map
        if (this.map) {
            const marker = this.markers[key];
            const pos = marker ? marker.getLatLng() : [point.latitude, point.longitude];
            this.map.setView(pos, 15, { animate: true });
            marker?.openPopup();
        }

        this.closeModal();
        this._showSelectedInfo(point);

        // Notify the host app
        this.element.dispatchEvent(new CustomEvent('relay-point-picker:selected', {
            bubbles: true,
            detail: { point },
        }));
    }

    _showSelectedInfo(point) {
        if (!this.hasSelectedPointInfoTarget) return;

        const color = this.carrierColorMap[point.carrierCode] ?? this.constructor.PALETTE[0];
        const dist = point.distanceInMeters
            ? ` · ${(point.distanceInMeters / 1000).toFixed(1)} km`
            : '';

        if (this.hasPointNameTarget) this.pointNameTarget.textContent = point.name;
        if (this.hasPointAddressTarget) this.pointAddressTarget.textContent =
            `${point.street}, ${point.postcode} ${point.city}`;
        if (this.hasPointCarrierTarget) {
            this.pointCarrierTarget.textContent = point.carrierCode.replace(/_/g, ' ');
            this.pointCarrierTarget.style.background = color.bg;
            this.pointCarrierTarget.style.color = color.text;
        }
        if (this.hasPointDistanceTarget) this.pointDistanceTarget.textContent = dist;

        if (this.hasOpeningHoursContainerTarget) {
            this.openingHoursContainerTarget.innerHTML = point.openingHours?.length
                ? point.openingHours.map(oh =>
                    `<div class="flex justify-between text-xs py-0.5 border-b border-gray-50 last:border-0">
                        <span class="font-medium text-gray-500">${oh.day}</span>
                        <span class="text-gray-900">${oh.hours}</span>
                    </div>`
                  ).join('')
                : '<p class="text-xs text-gray-400 italic">Horaires non disponibles</p>';
        }

        this.selectedPointInfoTarget.classList.remove('hidden');
        this.selectedPointInfoTarget.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    _handleExternalSelect(event) {
        // Allow triggering selection from outside (e.g. map popup button)
        const point = this.points.find(p => this._key(p) === event.detail?.key);
        if (point) this._selectPoint(point);
    }

    _key(point) {
        return `${point.id}__${point.carrierCode}`;
    }

    _haversine(lat1, lon1, lat2, lon2) {
        const R = 6371e3;
        const φ1 = lat1 * Math.PI / 180;
        const φ2 = lat2 * Math.PI / 180;
        const Δφ = (lat2 - lat1) * Math.PI / 180;
        const Δλ = (lon2 - lon1) * Math.PI / 180;
        const a = Math.sin(Δφ / 2) ** 2 + Math.cos(φ1) * Math.cos(φ2) * Math.sin(Δλ / 2) ** 2;
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }
}
