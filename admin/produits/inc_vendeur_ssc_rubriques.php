<?php
/**
 * Tailles sous-sous-catégorie (après classification vendeur) — affichage minimal.
 */
if (!function_exists('get_plateforme_ssc_by_parent_for_vendeur_form')) {
    require_once __DIR__ . '/../../models/model_categories.php';
}
$fap_ssc_by_parent = get_plateforme_ssc_by_parent_for_vendeur_form();
if (empty($fap_ssc_by_parent)) {
    return;
}
$fap_ssc_json = json_encode($fap_ssc_by_parent, JSON_UNESCAPED_UNICODE);
$fap_ssc_prefill_ids = [];
if (!empty($vendeur_sous_categorie_ids_prefill) && is_array($vendeur_sous_categorie_ids_prefill)) {
    foreach ($vendeur_sous_categorie_ids_prefill as $cid) {
        $fap_ssc_prefill_ids[] = (int) $cid;
    }
}
$fap_ssc_prefill_json = json_encode($fap_ssc_prefill_ids, JSON_UNESCAPED_UNICODE);
?>
<div class="fap-card fap-card-ssc fap-card-ssc--minimal" id="fap-ssc-card" hidden
    data-ssc-map="<?php echo htmlspecialchars($fap_ssc_json, ENT_QUOTES, 'UTF-8'); ?>"
    data-ssc-prefill="<?php echo htmlspecialchars($fap_ssc_prefill_json, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="fap_ssc_tailles_required" id="fap-ssc-tailles-required" value="0">
    <p class="fap-ssc-tailles-title" id="fap-ssc-tailles-title">Sélectionner les tailles disponibles</p>
    <div id="fap-ssc-tailles-chips" class="fap-ssc-tailles-chips" role="group" aria-labelledby="fap-ssc-tailles-title"></div>
    <p id="fap-ssc-tailles-error" class="fap-ssc-tailles-error" hidden role="alert">Choisissez au moins une taille.</p>
    <div id="fap-ssc-hidden-ids" class="fap-ssc-hidden-ids" hidden></div>
</div>
<style>
.fap-card-ssc--minimal {
    padding: 0.75rem 0.85rem;
    margin-top: 0;
}
.fap-ssc-tailles-title {
    margin: 0 0 0.55rem;
    font-size: 0.82rem;
    font-weight: 600;
    color: var(--texte-fonce, #0d0d0d);
}
.fap-ssc-tailles-chips {
    display: flex;
    flex-wrap: wrap;
    gap: 0.45rem;
}
.fap-ssc-taille-chip {
    position: relative;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem 0.65rem;
    border-radius: 999px;
    border: 1px solid rgba(53, 100, 166, 0.22);
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    background: #fff;
    user-select: none;
}
.fap-ssc-taille-chip input {
    position: absolute;
    opacity: 0;
    width: 0;
    height: 0;
    pointer-events: none;
}
.fap-ssc-taille-chip.is-on {
    color: #fff;
    background: var(--couleur-dominante, #3564a6);
    border-color: var(--couleur-dominante, #3564a6);
}
.fap-ssc-tailles-error {
    margin: 0.55rem 0 0;
    font-size: 0.8rem;
    font-weight: 600;
    color: var(--accent-promo, #ff6b35);
}
.fap-card-ssc--invalid {
    border-color: rgba(255, 107, 53, 0.45);
    box-shadow: 0 0 0 2px rgba(255, 107, 53, 0.12);
}
</style>
<script>
(function () {
    var card = document.getElementById('fap-ssc-card');
    var taillesChips = document.getElementById('fap-ssc-tailles-chips');
    var hiddenIds = document.getElementById('fap-ssc-hidden-ids');
    var requiredFlag = document.getElementById('fap-ssc-tailles-required');
    var errEl = document.getElementById('fap-ssc-tailles-error');
    var sousWrap = document.getElementById('fap-souscats-wrap');
    var form = card ? card.closest('form') : null;
    if (!card || !taillesChips || !sousWrap) return;

    var map = {};
    try {
        map = JSON.parse(card.getAttribute('data-ssc-map') || '{}');
    } catch (e) {
        map = {};
    }

    var catalogTailles = [];

    function getCheckedParentIds() {
        var ids = [];
        sousWrap.querySelectorAll('.fap-sousc-label input[type="checkbox"]:checked').forEach(function (inp) {
            var id = parseInt(inp.value, 10) || 0;
            if (id > 0) ids.push(id);
        });
        return ids;
    }

    function escapeHtml(s) {
        return String(s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/"/g, '&quot;');
    }

    function syncHiddenSscIds(sscIds) {
        if (!hiddenIds) return;
        hiddenIds.innerHTML = '';
        (sscIds || []).forEach(function (id) {
            var n = parseInt(id, 10) || 0;
            if (n <= 0) return;
            var inp = document.createElement('input');
            inp.type = 'hidden';
            inp.name = 'sous_categorie_ids[]';
            inp.value = String(n);
            inp.setAttribute('data-fap-ssc-auto', '1');
            hiddenIds.appendChild(inp);
        });
    }

    function applyTaillesToProduct(checkedVals) {
        var hidden = document.getElementById('taille-hidden');
        if (!hidden) return;
        var checkedMap = {};
        (checkedVals || []).forEach(function (v) {
            checkedMap[String(v).toUpperCase()] = true;
        });
        var items = [];
        catalogTailles.forEach(function (sz) {
            var u = String(sz).toUpperCase();
            if (u && checkedMap[u]) {
                items.push({ v: u, s: 0 });
            }
        });
        hidden.value = items.length ? JSON.stringify(items) : '[]';
        hidden.dispatchEvent(new Event('input', { bubbles: true }));
        hidden.dispatchEvent(new Event('change', { bubbles: true }));
    }

    function setRequiredFlag(on) {
        if (requiredFlag) {
            requiredFlag.value = on ? '1' : '0';
        }
    }

    function countSelectedTailles() {
        return taillesChips.querySelectorAll('.fap-ssc-taille-chip input:checked').length;
    }

    function clearTaillesError() {
        if (errEl) errEl.hidden = true;
        card.classList.remove('fap-card-ssc--invalid');
    }

    function showTaillesError() {
        if (errEl) errEl.hidden = false;
        card.classList.add('fap-card-ssc--invalid');
        card.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    function renderTailleChips(tailles) {
        catalogTailles = tailles || [];
        taillesChips.innerHTML = '';
        clearTaillesError();
        if (!catalogTailles.length) {
            card.hidden = true;
            setRequiredFlag(false);
            applyTaillesToProduct([]);
            return;
        }
        card.hidden = false;
        setRequiredFlag(true);
        catalogTailles.forEach(function (sz) {
            var u = String(sz).toUpperCase();
            if (!u) return;
            var lab = document.createElement('label');
            lab.className = 'fap-ssc-taille-chip';
            lab.innerHTML = '<input type="checkbox" value="' + escapeHtml(u) + '"><span>' + escapeHtml(u) + '</span>';
            taillesChips.appendChild(lab);
        });
        applyTaillesToProduct([]);
    }

    function collectForParents(parentIds) {
        var taillesSeen = {};
        var taillesOrder = [];
        var sscIds = [];
        parentIds.forEach(function (pid) {
            var grp = map[String(pid)] || map[pid];
            if (!grp || !grp.items) return;
            grp.items.forEach(function (item) {
                var iid = parseInt(item.id, 10) || 0;
                if (iid > 0) sscIds.push(iid);
                (item.tailles || []).forEach(function (sz) {
                    var u = String(sz).toUpperCase();
                    if (!u || taillesSeen[u]) return;
                    taillesSeen[u] = true;
                    taillesOrder.push(u);
                });
            });
        });
        return { tailles: taillesOrder, sscIds: sscIds };
    }

    function notifyAttrsSync() {
        document.dispatchEvent(new CustomEvent('fap-ssc-tailles-changed'));
    }

    function syncFromSouscats() {
        var parents = getCheckedParentIds();
        if (!parents.length) {
            syncHiddenSscIds([]);
            renderTailleChips([]);
            notifyAttrsSync();
            return;
        }
        var data = collectForParents(parents);
        syncHiddenSscIds(data.sscIds);
        renderTailleChips(data.tailles);
        notifyAttrsSync();
    }

    taillesChips.addEventListener('change', function () {
        var sel = [];
        taillesChips.querySelectorAll('.fap-ssc-taille-chip input:checked').forEach(function (inp) {
            sel.push(inp.value);
            var lab = inp.closest('.fap-ssc-taille-chip');
            if (lab) lab.classList.add('is-on');
        });
        taillesChips.querySelectorAll('.fap-ssc-taille-chip input:not(:checked)').forEach(function (inp) {
            var lab = inp.closest('.fap-ssc-taille-chip');
            if (lab) lab.classList.remove('is-on');
        });
        if (sel.length > 0) {
            clearTaillesError();
        }
        applyTaillesToProduct(sel);
    });

    if (form) {
        form.addEventListener('submit', function (e) {
            if (card.hidden || !requiredFlag || requiredFlag.value !== '1') {
                return;
            }
            if (countSelectedTailles() < 1) {
                e.preventDefault();
                e.stopPropagation();
                showTaillesError();
            }
        });
    }

    sousWrap.addEventListener('change', function (e) {
        if (e.target && e.target.matches('.fap-sousc-label input[type="checkbox"]')) {
            syncFromSouscats();
        }
    });

    syncFromSouscats();
})();
</script>
