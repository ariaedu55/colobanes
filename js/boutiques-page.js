/**
 * Page boutiques — géoloc + filtres (léger, sans dépendance Leaflet).
 */
(function (win, doc) {
    'use strict';

    function $(id) {
        return doc.getElementById(id);
    }

    function hideGeoLoading() {
        var box = $('mpBtGeoLoading');
        if (!box) {
            return;
        }
        box.hidden = true;
        box.setAttribute('aria-hidden', 'true');
        doc.body.classList.remove('mp-bt-geo-loading-open');
        var bar = $('mpBtGeoLoadingBar');
        var pct = $('mpBtGeoLoadingPct');
        if (bar) {
            bar.style.width = '0%';
        }
        if (pct) {
            pct.textContent = '0 %';
        }
        if (win.__mpBtGeoTimer) {
            clearInterval(win.__mpBtGeoTimer);
            win.__mpBtGeoTimer = null;
        }
    }

    function showGeoLoading() {
        var box = $('mpBtGeoLoading');
        if (!box) {
            return;
        }
        var bar = $('mpBtGeoLoadingBar');
        var pct = $('mpBtGeoLoadingPct');
        var value = 0;
        hideGeoLoading();
        box.hidden = false;
        box.setAttribute('aria-hidden', 'false');
        doc.body.classList.add('mp-bt-geo-loading-open');
        win.__mpBtGeoTimer = setInterval(function () {
            if (value >= 90) {
                return;
            }
            value += value < 50 ? 5 : 2;
            if (bar) {
                bar.style.width = value + '%';
            }
            if (pct) {
                pct.textContent = value + ' %';
            }
        }, 200);
    }

    function finishGeoLoading(done) {
        var bar = $('mpBtGeoLoadingBar');
        var pct = $('mpBtGeoLoadingPct');
        if (win.__mpBtGeoTimer) {
            clearInterval(win.__mpBtGeoTimer);
            win.__mpBtGeoTimer = null;
        }
        if (bar) {
            bar.style.width = '100%';
        }
        if (pct) {
            pct.textContent = '100 %';
        }
        setTimeout(function () {
            hideGeoLoading();
            if (typeof done === 'function') {
                done();
            }
        }, 220);
    }

    function prepareRedirect(form) {
        var redirectInput = form.querySelector('[name="redirect"]');
        if (!redirectInput) {
            return;
        }
        try {
            var u = new URL(redirectInput.value, win.location.origin);
            var typeSel = $('mpBtNearbyType');
            if (typeSel && typeSel.value) {
                u.searchParams.set('type', typeSel.value);
            } else {
                u.searchParams.delete('type');
            }
            u.searchParams.set('dist', '15');
            u.searchParams.set('open_map', '1');
            redirectInput.value = u.pathname + u.search;
        } catch (e) { /* ignore */ }
    }

    function bindGeo() {
        var form = $('geo-locate-form');
        var btn = $('btn-geo-locate');
        if (!form || !btn) {
            return;
        }

        function startCapture() {
            if (!win.GeoLocationCapture || typeof win.GeoLocationCapture.capture !== 'function') {
                hideGeoLoading();
                var status = $('geo-status');
                if (status) {
                    status.hidden = false;
                    status.removeAttribute('hidden');
                    status.innerHTML = '<i class="fas fa-triangle-exclamation"></i> Localisation indisponible. Rechargez la page.';
                }
                return;
            }
            showGeoLoading();
            win.GeoLocationCapture.capture(false, { fresh: true });
        }

        function initCapture() {
            if (!win.GeoLocationCapture || typeof win.GeoLocationCapture.init !== 'function') {
                return false;
            }
            win.GeoLocationCapture.init({
                latInput: 'geo_lat',
                lngInput: 'geo_lng',
                precisionInput: 'geo_precision',
                sourceInput: 'geo_source',
                statusEl: 'geo-status',
                auto: false,
                onSuccess: function () {
                    finishGeoLoading(function () {
                        prepareRedirect(form);
                        form.submit();
                    });
                },
                onError: function () {
                    hideGeoLoading();
                }
            });
            return true;
        }

        if (!initCapture()) {
            var tries = 0;
            var timer = setInterval(function () {
                tries += 1;
                if (initCapture() || tries > 40) {
                    clearInterval(timer);
                }
            }, 50);
        }

        btn.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            startCapture();
        });

        var loading = $('mpBtGeoLoading');
        if (loading) {
            loading.addEventListener('click', function (event) {
                if (event.target === loading || event.target.classList.contains('mp-bt-geo-loading__backdrop')) {
                    hideGeoLoading();
                }
            });
        }
        doc.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && doc.body.classList.contains('mp-bt-geo-loading-open')) {
                hideGeoLoading();
            }
        });
    }

    function openMapFromQuery() {
        try {
            var params = new URLSearchParams(win.location.search || '');
            if (params.get('open_map') !== '1') {
                return;
            }
            var mapBtn = $('mpBtOpenMap');
            if (mapBtn && mapBtn.disabled) {
                return;
            }
            var tries = 0;
            function tryOpen() {
                if (typeof win.openBoutiquesMapModal === 'function') {
                    win.openBoutiquesMapModal();
                    params.delete('open_map');
                    var qs = params.toString();
                    win.history.replaceState({}, '', win.location.pathname + (qs ? '?' + qs : ''));
                    return;
                }
                tries += 1;
                if (tries < 30) {
                    setTimeout(tryOpen, 40);
                }
            }
            tryOpen();
        } catch (e) { /* ignore */ }
    }

    function bindAutoSubmit() {
        doc.querySelectorAll('.js-bq-autosubmit').forEach(function (el) {
            el.addEventListener('change', function () {
                if (el.form) {
                    el.form.submit();
                }
            });
        });
    }

    function boot() {
        bindGeo();
        bindAutoSubmit();
        openMapFromQuery();
    }

    if (doc.readyState === 'loading') {
        doc.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})(window, document);
