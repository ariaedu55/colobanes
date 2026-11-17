<?php
/**
 * Formulaire produit — bloc « Vente en gros » (prix dégressifs par quantité, type Alibaba).
 * Pré-rempli depuis le POST (erreur de validation) ou le produit édité ($fap_edit_produit).
 */
require_once dirname(__DIR__, 2) . '/includes/produit_vente_gros.php';
vente_gros_ensure_schema();

$vg_rows = [];
$vg_on = false;
$vg_uniquement = false;
if (isset($_POST['vente_gros_form'])) {
    $vg_on = !empty($_POST['vente_gros']);
    $vg_uniquement = !empty($_POST['gros_uniquement']);
    foreach ((array) ($_POST['gros_q'] ?? []) as $i => $q) {
        $vg_rows[] = ['q' => (string) $q, 'p' => (string) (($_POST['gros_p'] ?? [])[$i] ?? '')];
    }
} elseif (!empty($fap_is_edit) && !empty($fap_edit_produit) && is_array($fap_edit_produit)) {
    $edit_full = get_produit_by_id((int) ($fap_edit_produit['id'] ?? 0)) ?: $fap_edit_produit;
    foreach (vente_gros_paliers($edit_full) as $palier) {
        $vg_rows[] = ['q' => (string) $palier['q'], 'p' => rtrim(rtrim(number_format($palier['p'], 2, '.', ''), '0'), '.')];
    }
    $vg_on = $vg_rows !== [];
    $vg_uniquement = $vg_on && !empty($edit_full['gros_uniquement']);
}
$vg_rows = array_values(array_filter($vg_rows, static function ($r) {
    return trim($r['q']) !== '' || trim($r['p']) !== '';
}));
if ($vg_rows === []) {
    $vg_rows = [['q' => '', 'p' => '']];
}
?>
<div class="fap-card fap-card-gros<?php echo $vg_on ? ' is-on' : ''; ?>" id="fap-gros-card">
    <input type="hidden" name="vente_gros_form" value="1">
    <label class="fap-gros-toggle">
        <input type="checkbox" name="vente_gros" value="1" id="fap-gros-on"<?php echo $vg_on ? ' checked' : ''; ?>>
        <span class="fap-gros-toggle__switch" aria-hidden="true"></span>
        <span class="fap-gros-toggle__text">
            <strong><i class="fas fa-boxes-stacked" aria-hidden="true"></i> Vente en gros</strong>
            <small>Prix par pièce plus bas quand le client achète en quantité</small>
        </span>
    </label>

    <div class="fap-gros-body" id="fap-gros-body"<?php echo $vg_on ? '' : ' hidden'; ?>>
        <div class="fap-gros-head" aria-hidden="true">
            <span>À partir de</span>
            <span>Prix par pièce</span>
            <span></span>
        </div>
        <div class="fap-gros-rows" id="fap-gros-rows">
            <?php foreach ($vg_rows as $row): ?>
            <div class="fap-gros-row">
                <div class="fap-gros-cell">
                    <input type="number" name="gros_q[]" min="2" step="1" placeholder="10" aria-label="Quantité minimum"
                        value="<?php echo htmlspecialchars($row['q'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="fap-gros-unit">pièces</span>
                </div>
                <div class="fap-gros-cell">
                    <input type="number" name="gros_p[]" min="0" step="0.01" placeholder="4500" aria-label="Prix par pièce"
                        value="<?php echo htmlspecialchars($row['p'], ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="fap-gros-unit">FCFA</span>
                </div>
                <button type="button" class="fap-gros-remove" aria-label="Retirer ce palier" title="Retirer"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="fap-gros-add" id="fap-gros-add">
            <i class="fas fa-plus" aria-hidden="true"></i> Ajouter un palier
        </button>
        <p class="fap-gros-hint">Ex. 10 pièces → 4 500 FCFA, 50 pièces → 4 000 FCFA. Le prix doit baisser à chaque palier (<?php echo (int) VENTE_GROS_MAX_PALIERS; ?> maximum).</p>
        <p class="fap-gros-error" id="fap-gros-error" hidden role="alert"></p>

        <div class="fap-gros-preview" id="fap-gros-preview" aria-live="polite">
            <p class="fap-gros-preview__title"><i class="fas fa-eye" aria-hidden="true"></i> Aperçu client</p>
            <ol class="fap-gros-preview__ladder" id="fap-gros-preview-ladder"></ol>
        </div>

        <label class="fap-gros-only">
            <input type="checkbox" name="gros_uniquement" value="1"<?php echo $vg_uniquement ? ' checked' : ''; ?>>
            <span><strong>Vente en gros uniquement</strong> — pas de vente à l’unité, le client commande au moins la quantité du 1er palier.</span>
        </label>
    </div>
</div>
<style>
.fap-card-gros { padding-top: 16px; padding-bottom: 16px; }
.fap-card-gros.is-on { border-color: rgba(255, 107, 53, 0.45); box-shadow: 0 0 0 3px rgba(255, 107, 53, 0.08); }
.fap-gros-toggle { display: flex; align-items: center; gap: 12px; margin: 0; cursor: pointer; }
.fap-gros-toggle input { position: absolute; opacity: 0; pointer-events: none; }
.fap-gros-toggle__switch {
    position: relative; flex: none; width: 44px; height: 26px; border-radius: 999px;
    background: #d0d5dd; transition: background .2s;
}
.fap-gros-toggle__switch::after {
    content: ""; position: absolute; top: 3px; left: 3px; width: 20px; height: 20px; border-radius: 50%;
    background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,.2); transition: transform .2s;
}
.fap-gros-toggle input:checked + .fap-gros-toggle__switch { background: var(--accent-promo, #ff6b35); }
.fap-gros-toggle input:checked + .fap-gros-toggle__switch::after { transform: translateX(18px); }
.fap-gros-toggle input:focus-visible + .fap-gros-toggle__switch { outline: 2px solid var(--couleur-dominante, #3564a6); outline-offset: 2px; }
.fap-gros-toggle__text { display: flex; flex-direction: column; gap: 2px; }
.fap-gros-toggle__text strong { font-size: 1rem; color: var(--titres, #0d0d0d); }
.fap-gros-toggle__text strong i { color: var(--accent-promo, #ff6b35); margin-right: 4px; }
.fap-gros-toggle__text small { font-size: 12.5px; color: var(--gris-moyen, #737373); }
.fap-gros-body { margin-top: 16px; padding-top: 14px; border-top: 1px dashed rgba(53, 100, 166, 0.2); }
.fap-gros-head, .fap-gros-row { display: grid; grid-template-columns: 1fr 1fr 38px; gap: 10px; align-items: center; }
.fap-gros-head { margin-bottom: 6px; font-size: 12px; font-weight: 600; color: var(--gris-moyen, #737373); }
.fap-gros-rows { display: flex; flex-direction: column; gap: 8px; }
.fap-gros-cell { position: relative; }
.fap-gros-cell input {
    width: 100%; padding: 11px 64px 11px 12px; border: 1.5px solid rgba(0,0,0,0.12); border-radius: 10px;
    font: inherit; font-size: 15px; background: #fafbfc; box-sizing: border-box;
}
.fap-gros-cell input:focus { outline: none; border-color: var(--couleur-dominante, #3564a6); background: #fff; }
.fap-gros-unit { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); font-size: 12px; color: var(--gris-moyen, #737373); pointer-events: none; }
.fap-gros-remove {
    width: 38px; height: 38px; border: 1px solid rgba(0,0,0,0.1); border-radius: 10px; background: #fff;
    color: #98a2b3; cursor: pointer;
}
.fap-gros-remove:hover { color: #d92d20; border-color: #fda29b; }
.fap-gros-add {
    margin-top: 10px; padding: 8px 12px; border: 1.5px dashed rgba(53, 100, 166, 0.35); border-radius: 10px;
    background: transparent; color: var(--couleur-dominante, #3564a6); font: inherit; font-size: 13px; font-weight: 600; cursor: pointer;
}
.fap-gros-add[hidden] { display: none; }
.fap-gros-hint { margin: 10px 0 12px; font-size: 12.5px; color: var(--gris-moyen, #737373); }
.fap-gros-error { margin: -4px 0 12px; padding: 8px 12px; border-radius: 9px; background: #fef3f2; color: #b42318; font-size: 12.5px; font-weight: 600; }
.fap-gros-preview { margin: 0 0 12px; border: 1px solid #ececf0; border-radius: 12px; overflow: hidden; }
.fap-gros-preview[hidden] { display: none; }
.fap-gros-preview__title { margin: 0; padding: 8px 12px; background: #fafafb; border-bottom: 1px solid #ececf0; font-size: 12px; font-weight: 700; color: var(--gris-moyen, #737373); }
.fap-gros-preview__ladder { display: grid; grid-template-columns: repeat(auto-fit, minmax(110px, 1fr)); margin: 0; padding: 0; list-style: none; }
.fap-gros-preview__ladder li { display: flex; flex-direction: column; gap: 2px; padding: 10px 12px; }
.fap-gros-preview__ladder li + li { border-left: 1px solid #ececf0; }
.fap-gros-preview__ladder b { font-size: 15px; color: var(--titres, #0d0d0d); white-space: nowrap; }
.fap-gros-preview__ladder b small { font-size: 10.5px; color: var(--gris-moyen, #737373); }
.fap-gros-preview__ladder span { font-size: 11.5px; color: var(--gris-moyen, #737373); white-space: nowrap; }
.fap-gros-preview__ladder em { align-self: flex-start; padding: 0 6px; border-radius: 5px; background: #ecfdf3; color: #067647; font-size: 11px; font-weight: 700; font-style: normal; }
.fap-gros-preview__ladder li.is-bad b { color: #b42318; }
.fap-gros-only { display: flex; gap: 10px; align-items: flex-start; padding: 10px 12px; border-radius: 10px; background: #fff7f2; font-size: 13px; cursor: pointer; }
.fap-gros-only input { margin-top: 3px; accent-color: var(--accent-promo, #ff6b35); }
</style>
<script>
(function () {
    var card = document.getElementById('fap-gros-card');
    if (!card) return;
    var on = document.getElementById('fap-gros-on');
    var body = document.getElementById('fap-gros-body');
    var rows = document.getElementById('fap-gros-rows');
    var add = document.getElementById('fap-gros-add');
    var MAX = <?php echo (int) VENTE_GROS_MAX_PALIERS; ?>;

    function refresh() {
        body.hidden = !on.checked;
        card.classList.toggle('is-on', on.checked);
        add.hidden = rows.children.length >= MAX;
        rows.querySelectorAll('.fap-gros-remove').forEach(function (b) {
            b.style.visibility = rows.children.length > 1 ? 'visible' : 'hidden';
        });
    }
    on.addEventListener('change', function () {
        refresh();
        if (on.checked) {
            var first = rows.querySelector('input');
            if (first) first.focus();
        }
    });
    add.addEventListener('click', function () {
        if (rows.children.length >= MAX) return;
        var clone = rows.lastElementChild.cloneNode(true);
        clone.querySelectorAll('input').forEach(function (i) { i.value = ''; });
        rows.appendChild(clone);
        clone.querySelector('input').focus();
        refresh();
    });
    /* Aperçu client : même échelle que sur la fiche produit + contrôle « le prix doit baisser » */
    var ladder = document.getElementById('fap-gros-preview-ladder');
    var preview = document.getElementById('fap-gros-preview');
    var errEl = document.getElementById('fap-gros-error');
    var only = card.querySelector('input[name="gros_uniquement"]');
    function fmt(n) { return Math.round(n).toLocaleString('fr-FR'); }
    function prixDetail() {
        var p = parseFloat((document.getElementById('prix') || {}).value) || 0;
        var pp = parseFloat((document.getElementById('prix_promotion') || {}).value) || 0;
        return pp > 0 && pp < p ? pp : p;
    }
    function lire() {
        var out = [];
        rows.querySelectorAll('.fap-gros-row').forEach(function (r) {
            var q = parseInt(r.querySelector('input[name="gros_q[]"]').value, 10) || 0;
            var p = parseFloat(r.querySelector('input[name="gros_p[]"]').value) || 0;
            if (q >= 2 && p > 0) out.push({ q: q, p: p });
        });
        return out.sort(function (a, b) { return a.q - b.q; });
    }
    function li(prix, label, pct, bad) {
        return '<li' + (bad ? ' class="is-bad"' : '') + '><b>' + fmt(prix) + ' <small>FCFA</small></b><span>' + label + '</span>'
            + (pct > 0 ? '<em>−' + pct + ' %</em>' : '') + '</li>';
    }
    function apercu() {
        var paliers = lire();
        var base = prixDetail();
        preview.hidden = !paliers.length;
        var html = '';
        var erreur = '';
        if (paliers.length && !(only && only.checked) && base > 0) {
            var fin = paliers[0].q - 1;
            html += li(base, fin > 1 ? '1 – ' + fin + ' pièces' : '1 pièce', 0, false);
        }
        var prev = base > 0 ? base : Infinity;
        paliers.forEach(function (pl, i) {
            var next = paliers[i + 1];
            var label = next ? (next.q - 1 > pl.q ? pl.q + ' – ' + (next.q - 1) + ' pièces' : pl.q + ' pièces') : '≥ ' + pl.q + ' pièces';
            var bad = pl.p >= prev;
            if (bad && !erreur) erreur = 'Le prix doit baisser à chaque palier : ' + fmt(pl.p) + ' FCFA dès ' + pl.q + ' pièces n’est pas moins cher que le palier précédent.';
            html += li(pl.p, label, base > 0 ? Math.round((base - pl.p) / base * 100) : 0, bad);
            prev = pl.p;
        });
        ladder.innerHTML = html;
        errEl.hidden = !erreur;
        errEl.textContent = erreur;
    }
    card.addEventListener('input', apercu);
    card.addEventListener('change', apercu);
    ['prix', 'prix_promotion'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) el.addEventListener('input', apercu);
    });

    rows.addEventListener('click', function (e) {
        var btn = e.target.closest('.fap-gros-remove');
        if (!btn || rows.children.length <= 1) return;
        btn.closest('.fap-gros-row').remove();
        refresh();
        apercu();
    });
    refresh();
    apercu();
})();
</script>
