/**
 * Carte interactive — boutiques proches (Leaflet) + filtres type / distance.
 */
(function (win, doc) {
    'use strict';

    var map = null;
    var markersLayer = null;
    var userMarker = null;
    var markerById = {};
    var selectedId = null;
    var state = {
        user: null,
        allBoutiques: [],
        boutiques: [],
        mapMax: 200,
        anchor: { lat: 14.7167, lng: -17.4342 }
    };
    var sidePanel = null;
    var listPanel = null;
    var listToggle = null;
    var filterTypeEl = null;
    var filterLimitEl = null;

    function readPayload() {
        var el = doc.getElementById('mpBtMapData');
        if (!el) {
            return;
        }
        try {
            var data = JSON.parse(el.textContent || '{}');
            state.user = data.user || null;
            state.allBoutiques = Array.isArray(data.boutiques) ? data.boutiques : [];
            state.mapMax = parseInt(data.map_max, 10);
            if (isNaN(state.mapMax) || state.mapMax <= 0) {
                state.mapMax = 200;
            }
            if (data.anchor && data.anchor.lat && data.anchor.lng) {
                state.anchor = {
                    lat: Number(data.anchor.lat),
                    lng: Number(data.anchor.lng)
                };
            }
            var filters = data.filters || {};
            if (filterTypeEl && filters.type_id != null) {
                filterTypeEl.value = String(filters.type_id || '');
            }
            if (filters.limit != null) {
                setLimitValue(filters.limit);
            }
            applyMapFilters(false);
        } catch (e) {
            state.allBoutiques = [];
            state.boutiques = [];
        }
    }

    function setLimitValue(limit) {
        var value = parseInt(limit, 10);
        if (value !== 20 && value !== 50 && value !== 200) {
            value = 200;
        }
        if (filterLimitEl) {
            filterLimitEl.value = String(value);
        }
        doc.querySelectorAll('.mp-bt-dist-chips__btn[data-limit]').forEach(function (btn) {
            var active = String(btn.getAttribute('data-limit')) === String(value);
            btn.classList.toggle('is-active', active);
            btn.setAttribute('aria-pressed', active ? 'true' : 'false');
        });
    }

    function getFilterValues() {
        var typeId = filterTypeEl ? parseInt(filterTypeEl.value, 10) : 0;
        var limit = filterLimitEl ? parseInt(filterLimitEl.value, 10) : 200;
        if (isNaN(typeId) || typeId < 0) {
            typeId = 0;
        }
        if (limit !== 20 && limit !== 50 && limit !== 200) {
            limit = 200;
        }
        return { typeId: typeId, limit: limit };
    }

    function sortByDistance(items) {
        return items.slice().sort(function (a, b) {
            var da = a.distance_km != null && !isNaN(Number(a.distance_km)) ? Number(a.distance_km) : 9999;
            var db = b.distance_km != null && !isNaN(Number(b.distance_km)) ? Number(b.distance_km) : 9999;
            return da - db;
        });
    }

    function haversineKm(lat1, lng1, lat2, lng2) {
        var toRad = Math.PI / 180;
        var dLat = (lat2 - lat1) * toRad;
        var dLng = (lng2 - lng1) * toRad;
        var a = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(lat1 * toRad) * Math.cos(lat2 * toRad)
            * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 6371 * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
    }

    function getMapAnchor() {
        if (state.user && state.user.lat && state.user.lng) {
            return { lat: Number(state.user.lat), lng: Number(state.user.lng) };
        }
        return state.anchor;
    }

    function applyMapFilters(updateUrl) {
        if (updateUrl === undefined) {
            updateUrl = true;
        }
        var f = getFilterValues();
        var showLimit = Math.min(f.limit, state.mapMax);
        state.boutiques = sortByDistance(state.allBoutiques.filter(function (item) {
            if (f.typeId > 0 && Number(item.type_id || 0) !== f.typeId) {
                return false;
            }
            return true;
        })).slice(0, showLimit);

        if (selectedId && !state.boutiques.some(function (b) { return String(b.id) === String(selectedId); })) {
            hideDetailPanel();
        }

        if (map && markersLayer) {
            rebuildMarkers();
        }
        buildList();

        if (updateUrl) {
            syncUrlParams(f.typeId, f.limit);
        }
    }

    function syncUrlParams(typeId, limit) {
        try {
            var params = new URLSearchParams(win.location.search || '');
            if (typeId > 0) {
                params.set('type', String(typeId));
            } else {
                params.delete('type');
            }
            if (limit && limit !== 200) {
                params.set('limit', String(limit));
            } else {
                params.delete('limit');
            }
            params.delete('page');
            params.delete('open_map');
            var qs = params.toString();
            win.history.replaceState({}, '', win.location.pathname + (qs ? '?' + qs : ''));
        } catch (e) { /* ignore */ }
    }

    function reloadWithTypeFilter(typeId) {
        try {
            var params = new URLSearchParams(win.location.search || '');
            var f = getFilterValues();
            if (typeId > 0) {
                params.set('type', String(typeId));
            } else {
                params.delete('type');
            }
            if (f.limit && f.limit !== 200) {
                params.set('limit', String(f.limit));
            } else {
                params.delete('limit');
            }
            params.delete('page');
            params.set('open_map', '1');
            var qs = params.toString();
            win.location.href = win.location.pathname + (qs ? '?' + qs : '');
        } catch (e) { /* ignore */ }
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function formatDistance(km) {
        if (km == null || isNaN(km)) {
            return '';
        }
        var n = Number(km);
        if (n < 1) {
            return Math.round(n * 1000) + ' m';
        }
        return n.toFixed(n < 10 ? 1 : 0).replace('.', ',') + ' km';
    }

    function buildMarkerIcon(item) {
        var color = item.theme_main || '#3564a6';
        var initial = esc(String(item.nom || 'B').charAt(0).toUpperCase());
        var logo = item.logo
            ? '<img src="' + esc(item.logo) + '" alt="" onerror="this.remove()">'
            : '';
        var label = esc(item.nom || 'Boutique');
        return L.divIcon({
            className: 'bq-marker-wrap',
            html: ''
                + '<div class="bq-marker" style="--mk-color:' + esc(color) + '">'
                + '<span class="bq-marker__pin"><span class="bq-marker__initial">' + initial + '</span>' + logo + '</span>'
                + '<span class="bq-marker__label">' + label + '</span>'
                + '</div>',
            iconSize: [44, 52],
            iconAnchor: [22, 50]
        });
    }

    function setActiveMarker(id) {
        Object.keys(markerById).forEach(function (key) {
            var el = markerById[key] && markerById[key].getElement ? markerById[key].getElement() : null;
            if (el) {
                el.classList.toggle('is-active', id !== null && key === String(id));
            }
            if (markerById[key] && markerById[key].setZIndexOffset) {
                markerById[key].setZIndexOffset(id !== null && key === String(id) ? 1000 : 0);
            }
        });
    }

    function loadLeaflet() {
        return new Promise(function (resolve, reject) {
            if (win.L) {
                resolve(win.L);
                return;
            }
            if (win.__mpBtLeafletPromise) {
                win.__mpBtLeafletPromise.then(resolve).catch(reject);
                return;
            }
            win.__mpBtLeafletPromise = new Promise(function (res, rej) {
                var cssId = 'mpBtLeafletCss';
                if (!doc.getElementById(cssId)) {
                    var link = doc.createElement('link');
                    link.id = cssId;
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
                    link.integrity = 'sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=';
                    link.crossOrigin = '';
                    doc.head.appendChild(link);
                }
                var script = doc.createElement('script');
                script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
                script.integrity = 'sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=';
                script.crossOrigin = '';
                script.async = true;
                script.onload = function () {
                    if (win.L) {
                        res(win.L);
                    } else {
                        rej(new Error('Leaflet unavailable'));
                    }
                };
                script.onerror = function () {
                    rej(new Error('Leaflet load failed'));
                };
                doc.head.appendChild(script);
            });
            win.__mpBtLeafletPromise.then(resolve).catch(reject);
        });
    }

    function openModal() {
        var modal = doc.getElementById('mpBtMapModal');
        if (!modal) {
            return;
        }
        modal.hidden = false;
        modal.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('mp-bt-map-open');
        loadLeaflet().then(function () {
            win.setTimeout(initMap, 40);
        }).catch(function () { /* ignore */ });
    }

    function closeModal() {
        var modal = doc.getElementById('mpBtMapModal');
        if (!modal) {
            return;
        }
        modal.hidden = true;
        modal.setAttribute('aria-hidden', 'true');
        doc.body.classList.remove('mp-bt-map-open');
        hideDetailPanel();
        closeListPanel();
    }

    function hideDetailPanel() {
        selectedId = null;
        setActiveMarker(null);
        if (!sidePanel) {
            return;
        }
        sidePanel.hidden = true;
        sidePanel.classList.remove('is-open');
        doc.querySelectorAll('.mp-bt-map-list__item.is-active').forEach(function (el) {
            el.classList.remove('is-active');
        });
    }

    function showDetailPanel() {
        if (!sidePanel) {
            return;
        }
        sidePanel.hidden = false;
        sidePanel.classList.add('is-open');
    }

    function closeListPanel() {
        if (!listPanel) {
            return;
        }
        listPanel.classList.remove('is-open');
        if (listToggle) {
            listToggle.setAttribute('aria-expanded', 'false');
        }
    }

    function toggleListPanel() {
        if (!listPanel) {
            return;
        }
        var open = !listPanel.classList.contains('is-open');
        listPanel.classList.toggle('is-open', open);
        if (listToggle) {
            listToggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }

    function selectBoutique(item, options) {
        options = options || {};
        selectedId = item.id;
        showDetailPanel();
        setActiveMarker(item.id);
        if (sidePanel) {
            sidePanel.style.setProperty('--mk-color', item.theme_main || '#3564a6');
        }

        doc.querySelectorAll('.mp-bt-map-list__item.is-active').forEach(function (el) {
            el.classList.remove('is-active');
        });
        var activeLi = doc.querySelector('.mp-bt-map-list__item[data-id="' + String(item.id) + '"]');
        if (activeLi) {
            activeLi.classList.add('is-active');
        }

        var nameEl = doc.getElementById('mpBtMapSideName');
        var distEl = doc.getElementById('mpBtMapSideDist');
        var addrEl = doc.getElementById('mpBtMapSideAddr');
        var logoEl = doc.getElementById('mpBtMapSideLogo');
        var visitEl = doc.getElementById('mpBtMapSideVisit');
        var mapsEl = doc.getElementById('mpBtMapSideMaps');

        if (nameEl) {
            nameEl.textContent = item.nom || 'Boutique';
        }
        if (distEl) {
            var d = formatDistance(item.distance_km);
            distEl.textContent = d ? ('À ' + d) : '';
            distEl.hidden = !d;
        }
        if (addrEl) {
            addrEl.textContent = item.adresse || item.region || '';
        }
        if (logoEl) {
            logoEl.innerHTML = '<span>' + esc(String(item.nom || 'B').charAt(0).toUpperCase()) + '</span>'
                + (item.logo ? '<img src="' + esc(item.logo) + '" alt="" onerror="this.remove()">' : '');
        }
        if (visitEl) {
            visitEl.href = item.vitrine_href || '#';
        }
        if (mapsEl) {
            mapsEl.href = item.maps_url || '#';
            mapsEl.hidden = !item.maps_url;
        }

        var shareShop = doc.getElementById('mpBtMapShareShop');
        if (shareShop) {
            shareShop.onclick = function () {
                if (!win.openPlatformShareModal) {
                    return;
                }
                win.openPlatformShareModal({
                    modalTitle: item.share_modal_title || 'Partager cette boutique',
                    title: item.share_title || item.nom,
                    url: item.share_url || item.vitrine_href || '',
                    message: item.share_text || item.nom,
                    hint: item.share_hint || ''
                });
            };
        }

        var shareGeo = doc.getElementById('mpBtMapShareGeo');
        if (shareGeo) {
            shareGeo.onclick = function () {
                if (!win.openPlatformShareModal) {
                    return;
                }
                var geoUrl = item.geo_share_url || item.maps_url || '';
                if (!geoUrl) {
                    return;
                }
                win.openPlatformShareModal({
                    modalTitle: 'Partager la localisation',
                    title: item.geo_share_title || ('Point de retrait — ' + item.nom),
                    url: geoUrl,
                    message: item.geo_share_title || item.nom,
                    hint: 'Partagez le point de retrait de la boutique.'
                });
            };
            shareGeo.hidden = !(item.geo_share_url || item.maps_url);
        }

        if (options.pan !== false && map && item.lat && item.lng) {
            map.setView([item.lat, item.lng], Math.max(map.getZoom(), 15), { animate: true });
        }
    }

    function buildList() {
        var listEl = doc.getElementById('mpBtMapListItems');
        var countEl = doc.getElementById('mpBtMapListCount');
        if (!listEl) {
            return;
        }

        listEl.innerHTML = '';
        var sorted = state.boutiques.slice();

        if (!sorted.length) {
            var emptyLi = doc.createElement('li');
            emptyLi.className = 'mp-bt-map-list__item mp-bt-map-list__item--empty';
            emptyLi.textContent = 'Aucune boutique pour ces filtres.';
            listEl.appendChild(emptyLi);
        }

        sorted.forEach(function (item) {
            var li = doc.createElement('li');
            li.className = 'mp-bt-map-list__item';
            li.setAttribute('data-id', String(item.id));
            li.setAttribute('role', 'listitem');

            var logoHtml = '<span class="mp-bt-map-list__initial">' + esc(String(item.nom || 'B').charAt(0).toUpperCase()) + '</span>'
                + (item.logo ? '<img src="' + esc(item.logo) + '" alt="" onerror="this.remove()">' : '');
            var dist = formatDistance(item.distance_km);
            var sub = [];
            if (item.nb_produits) {
                sub.push(item.nb_produits + ' produit' + (item.nb_produits > 1 ? 's' : ''));
            }
            if (item.adresse || item.region) {
                sub.push(item.adresse || item.region);
            }

            li.innerHTML = ''
                + '<button type="button" class="mp-bt-map-list__btn" style="--mk-color:' + esc(item.theme_main || '#3564a6') + '">'
                + '<span class="mp-bt-map-list__logo">' + logoHtml + '</span>'
                + '<span class="mp-bt-map-list__meta">'
                + '<strong>' + esc(item.nom || 'Boutique') + '</strong>'
                + (sub.length ? '<span class="mp-bt-map-list__sub">' + esc(sub.join(' · ')) + '</span>' : '')
                + '</span>'
                + (dist ? '<span class="mp-bt-map-list__dist">' + esc(dist) + '</span>' : '')
                + '</button>';

            li.querySelector('.mp-bt-map-list__btn').addEventListener('click', function () {
                selectBoutique(item);
                if (win.matchMedia('(max-width: 1023px)').matches) {
                    closeListPanel();
                }
            });

            listEl.appendChild(li);
        });

        if (countEl) {
            countEl.textContent = String(sorted.length);
        }
    }

    function fitMapToVisible() {
        if (!map) {
            return;
        }
        var anchor = getMapAnchor();
        var fitRadiusKm = 80;
        var bounds = [];

        if (state.user && state.user.lat && state.user.lng) {
            bounds.push([state.user.lat, state.user.lng]);
        }

        state.boutiques.forEach(function (item) {
            if (!item.lat || !item.lng) {
                return;
            }
            var d = haversineKm(anchor.lat, anchor.lng, Number(item.lat), Number(item.lng));
            if (d <= fitRadiusKm) {
                bounds.push([item.lat, item.lng]);
            }
        });

        /* Si trop peu de points proches, élargir à toutes les boutiques visibles. */
        if (bounds.length < 2) {
            state.boutiques.forEach(function (item) {
                if (item.lat && item.lng) {
                    bounds.push([item.lat, item.lng]);
                }
            });
        }

        if (bounds.length > 1) {
            map.fitBounds(bounds, { padding: [56, 56], maxZoom: 15 });
            return;
        }
        if (bounds.length === 1) {
            map.setView(bounds[0], 14);
            return;
        }
        map.setView([anchor.lat, anchor.lng], 13);
    }

    function rebuildMarkers() {
        if (!map || !markersLayer) {
            return;
        }
        markersLayer.clearLayers();
        markerById = {};

        state.boutiques.forEach(function (item) {
            if (!item.lat || !item.lng) {
                return;
            }
            var marker = L.marker([item.lat, item.lng], { icon: buildMarkerIcon(item) });
            marker.on('click', function (e) {
                if (e && e.originalEvent) {
                    L.DomEvent.stopPropagation(e.originalEvent);
                }
                selectBoutique(item);
            });
            marker.addTo(markersLayer);
            markerById[String(item.id)] = marker;
        });

        fitMapToVisible();
    }

    function initMap() {
        if (!win.L) {
            return;
        }
        var canvas = doc.getElementById('mpBtMapCanvas');
        if (!canvas) {
            return;
        }

        if (map) {
            map.invalidateSize();
            rebuildMarkers();
            buildList();
            return;
        }

        var anchor = getMapAnchor();

        map = L.map(canvas, { zoomControl: true }).setView([anchor.lat, anchor.lng], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap'
        }).addTo(map);

        markersLayer = L.layerGroup().addTo(map);

        if (state.user && state.user.lat && state.user.lng) {
            userMarker = L.circleMarker([state.user.lat, state.user.lng], {
                radius: 9,
                color: '#fff',
                fillColor: '#3564a6',
                fillOpacity: 1,
                weight: 3
            }).addTo(map);
            userMarker.bindTooltip('Vous', { permanent: false, direction: 'top' });
        }

        map.on('click', function () {
            hideDetailPanel();
        });

        rebuildMarkers();
        buildList();
        hideDetailPanel();

        if (win.matchMedia('(min-width: 1024px)').matches && listPanel) {
            listPanel.classList.add('is-open');
            if (listToggle) {
                listToggle.setAttribute('aria-expanded', 'true');
            }
        }

        win.setTimeout(function () {
            if (map) {
                map.invalidateSize();
                fitMapToVisible();
            }
        }, 120);
    }

    function bindLimitChips() {
        doc.querySelectorAll('.mp-bt-dist-chips__btn[data-limit]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var limit = parseInt(btn.getAttribute('data-limit'), 10);
                if (isNaN(limit)) {
                    return;
                }
                setLimitValue(limit);
                applyMapFilters(true);
            });
        });
    }

    function bindUi() {
        sidePanel = doc.getElementById('mpBtMapSide');
        listPanel = doc.getElementById('mpBtMapList');
        listToggle = doc.getElementById('mpBtMapListToggle');
        filterTypeEl = doc.getElementById('mpBtMapFilterType');
        filterLimitEl = doc.getElementById('mpBtMapFilterLimit');

        readPayload();
        bindLimitChips();

        if (filterTypeEl) {
            filterTypeEl.addEventListener('change', function () {
                reloadWithTypeFilter(parseInt(filterTypeEl.value, 10) || 0);
            });
        }

        var openBtn = doc.getElementById('mpBtOpenMap');
        if (openBtn) {
            openBtn.addEventListener('click', openModal);
        }

        if (listToggle) {
            listToggle.addEventListener('click', toggleListPanel);
        }

        var listClose = doc.getElementById('mpBtMapListClose');
        if (listClose) {
            listClose.addEventListener('click', closeListPanel);
        }

        if (listPanel) {
            listPanel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        if (sidePanel) {
            sidePanel.addEventListener('click', function (e) {
                e.stopPropagation();
            });
        }

        doc.querySelectorAll('[data-map-detail-close]').forEach(function (el) {
            el.addEventListener('click', hideDetailPanel);
        });

        doc.querySelectorAll('[data-map-close]').forEach(function (el) {
            el.addEventListener('click', closeModal);
        });

        doc.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                if (sidePanel && sidePanel.classList.contains('is-open')) {
                    hideDetailPanel();
                    return;
                }
                if (listPanel && listPanel.classList.contains('is-open')
                    && win.matchMedia('(max-width: 1023px)').matches) {
                    closeListPanel();
                    return;
                }
                closeModal();
            }
        });
    }

    win.openBoutiquesMapModal = openModal;

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', bindUi);
    } else {
        bindUi();
    }
})(window, document);
