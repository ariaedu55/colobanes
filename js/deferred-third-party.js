/**
 * Chargement différé des scripts tiers (GTranslate) — ne bloque pas le rendu initial.
 */
(function (win, doc) {
  'use strict';

  var cfg = win.__COLOBANES_LAZY || {};
  var loaded = { gtranslate: false };
  var pending = { gtranslate: [] };

  function injectScript(src, id, onload, onerror) {
    if (!src) {
      return null;
    }
    if (id && doc.getElementById(id)) {
      var existing = doc.getElementById(id);
      if (onload) {
        if (existing.getAttribute('data-loaded') === '1') {
          onload();
        } else {
          existing.addEventListener('load', onload, { once: true });
        }
      }
      return existing;
    }
    var s = doc.createElement('script');
    s.src = src;
    s.async = true;
    if (id) {
      s.id = id;
    }
    s.addEventListener('load', function () {
      s.setAttribute('data-loaded', '1');
      if (onload) {
        onload();
      }
    }, { once: true });
    if (onerror) {
      s.addEventListener('error', onerror, { once: true });
    }
    doc.body.appendChild(s);
    return s;
  }

  function flushQueue(key) {
    var list = pending[key].slice();
    pending[key].length = 0;
    list.forEach(function (fn) {
      try {
        fn();
      } catch (e) { /* ignore */ }
    });
  }

  function whenIdle(fn, timeoutMs) {
    timeoutMs = timeoutMs || 4500;
    if (typeof win.requestIdleCallback === 'function') {
      win.requestIdleCallback(function () {
        fn();
      }, { timeout: timeoutMs });
      return;
    }
    win.setTimeout(fn, Math.min(timeoutMs, 2500));
  }

  function needsGtranslateEarly() {
    try {
      var stored = win.localStorage && win.localStorage.getItem('nav_selected_lang');
      if (stored && stored !== 'fr') {
        return true;
      }
    } catch (e) { /* ignore */ }
    var parts = ('; ' + doc.cookie).split('; googtrans=');
    if (parts.length < 2) {
      return false;
    }
    var raw = parts.pop().split(';').shift() || '';
    try {
      raw = decodeURIComponent(raw.replace(/\+/g, ' ')).trim();
    } catch (e1) {
      raw = raw.trim();
    }
    return raw !== '' && raw.indexOf('/fr/fr') === -1 && raw !== '/fr';
  }

  /*
   * Moteur Google Translate chargé directement : il lit le cookie googtrans (/fr/en…)
   * posé par le sélecteur de langue et traduit la page automatiquement.
   * (Le widget GTranslate dropdown.js n'appelait jamais ce moteur au chargement.)
   */
  function loadGtranslate(callback) {
    var url = cfg.gtranslate || 'https://translate.google.com/translate_a/element.js';
    if (loaded.gtranslate) {
      if (callback) {
        callback();
      }
      return;
    }
    if (callback) {
      pending.gtranslate.push(callback);
    }
    if (doc.getElementById('colobanes-gtranslate')) {
      return;
    }
    var holder = doc.getElementById('colobanes_gt_element');
    if (!holder) {
      holder = doc.createElement('div');
      holder.id = 'colobanes_gt_element';
      holder.style.display = 'none';
      doc.body.appendChild(holder);
    }
    win.colobanesGoogleTranslateInit = function () {
      try {
        new win.google.translate.TranslateElement({
          pageLanguage: 'fr',
          includedLanguages: cfg.languages || 'fr,en,es,pt,ar',
          autoDisplay: false
        }, 'colobanes_gt_element');
      } catch (e) { /* ignore */ }
      loaded.gtranslate = true;
      flushQueue('gtranslate');
    };
    injectScript(url + (url.indexOf('?') === -1 ? '?' : '&') + 'cb=colobanesGoogleTranslateInit', 'colobanes-gtranslate');
  }

  function scheduleGtranslateIdle() {
    /* Langue étrangère choisie : traduire tout de suite (pas d'attente idle, sinon la page reste en français) */
    if (needsGtranslateEarly()) {
      loadGtranslate();
    }
  }

  function bindNavLangPrefetch() {
    /* Plus de préchargement au clic : le moteur est chargé après rechargement, cookie googtrans posé */
  }

  win.ColobanesPerf = Object.assign({}, win.ColobanesPerf || {}, {
    loadGtranslate: loadGtranslate,
    needsGtranslateEarly: needsGtranslateEarly
  });

  function init() {
    scheduleGtranslateIdle();
    bindNavLangPrefetch();
  }

  if (doc.readyState === 'loading') {
    doc.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }
})(window, document);
