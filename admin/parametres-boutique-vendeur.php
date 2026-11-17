<?php
/**
 * Paramètres vitrine vendeur : logo, couleurs, adresse — redesign v2
 */
require_once __DIR__ . '/includes/require_admin_session.php';



require_once __DIR__ . '/includes/require_access.php';
require_once __DIR__ . '/../models/model_admin.php';
require_once __DIR__ . '/../includes/boutique_vendeur_display.php';
require_once __DIR__ . '/../includes/upload_image_limits.php';
require_once __DIR__ . '/../includes/senegal_regions.php';

$role = admin_normalize_role_for_route($_SESSION['admin_role'] ?? '');
if ($role !== 'vendeur') {
    header('Location: parametres.php');
    exit;
}

$admin_id = (int)$_SESSION['admin_id'];
$admin    = get_admin_by_id($admin_id);
if (!$admin || ($admin['role'] ?? '') !== 'vendeur') {
    header('Location: parametres.php');
    exit;
}

if (empty($_SESSION['admin_csrf'])) {
    $_SESSION['admin_csrf'] = bin2hex(random_bytes(32));
}

$error_message   = '';

require_once __DIR__ . '/../includes/flash_toast.php';
if (isset($_SESSION['success_message'])) {
    // géré par flash_toast_collect() dans le footer
    unset($_SESSION['success_message']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['boutique_branding_save'])) {
    $tok = (string)($_POST['csrf_token'] ?? '');
    if (!hash_equals((string)($_SESSION['admin_csrf'] ?? ''), $tok)) {
        $error_message = 'Session expirée. Veuillez recharger la page.';
    } else {
        $raw_c1 = trim((string)($_POST['couleur_principale'] ?? ''));
        $raw_c2 = trim((string)($_POST['couleur_accent'] ?? ''));
        if ($raw_c1 !== '' && boutique_normalize_hex_color($raw_c1) === '') {
            $error_message = "Couleur principale invalide (format #RRGGBB).";
        } elseif ($raw_c2 !== '' && boutique_normalize_hex_color($raw_c2) === '') {
            $error_message = "Couleur d'accent invalide (format #RRGGBB).";
        } else {
            $c1 = $raw_c1 !== '' ? boutique_normalize_hex_color($raw_c1) : '';
            $c2 = $raw_c2 !== '' ? boutique_normalize_hex_color($raw_c2) : '';

            $current_logo = trim((string)($admin['boutique_logo'] ?? ''));
            $logo_final   = $current_logo;
            $remove_logo  = !empty($_POST['retirer_logo']);

            if ($remove_logo) {
                $logo_final = '';
                if ($current_logo !== '') {
                    $old = __DIR__ . '/../upload/' . str_replace('\\', '/', $current_logo);
                    if (is_file($old)) { @unlink($old); }
                }
            } elseif (isset($_FILES['boutique_logo']) && (int)($_FILES['boutique_logo']['error'] ?? 0) === UPLOAD_ERR_OK) {
                $f       = $_FILES['boutique_logo'];
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
                if (!in_array($mime, $allowed, true)) {
                    $error_message = 'Logo : formats acceptés JPEG, PNG, GIF, WebP.';
                } elseif ((int)($f['size'] ?? 0) > UPLOAD_MAX_IMAGE_BYTES) {
                    $error_message = 'Logo trop volumineux (maximum 20 Mo).';
                } else {
                    $ext = strtolower(pathinfo((string)($f['name'] ?? ''), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'], true)) { $ext = 'jpg'; }
                    $dir  = __DIR__ . '/../upload/boutique_branding/';
                    if (!is_dir($dir)) { @mkdir($dir, 0755, true); }
                    $fname = 'v_' . $admin_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest  = $dir . $fname;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        if ($current_logo !== '') {
                            $old = __DIR__ . '/../upload/' . str_replace('\\', '/', $current_logo);
                            if (is_file($old)) { @unlink($old); }
                        }
                        $logo_final = 'boutique_branding/' . $fname;
                    } else {
                        $error_message = "Impossible d'enregistrer le fichier logo.";
                    }
                }
            }

            $current_cover = trim((string) ($admin['boutique_couverture'] ?? ''));
            $cover_final = $current_cover;
            $remove_cover = !empty($_POST['retirer_couverture']);
            if ($error_message === '' && $remove_cover) {
                $cover_final = '';
                if ($current_cover !== '') {
                    $old = __DIR__ . '/../upload/' . str_replace('\\', '/', $current_cover);
                    if (is_file($old)) {
                        @unlink($old);
                    }
                }
            } elseif ($error_message === '' && isset($_FILES['boutique_couverture']) && (int) ($_FILES['boutique_couverture']['error'] ?? 0) === UPLOAD_ERR_OK) {
                $f = $_FILES['boutique_couverture'];
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = $finfo ? finfo_file($finfo, $f['tmp_name']) : '';
                if ($finfo) {
                    finfo_close($finfo);
                }
                if (!in_array($mime, $allowed, true)) {
                    $error_message = 'Couverture : formats acceptés JPEG, PNG, GIF, WebP.';
                } elseif ((int) ($f['size'] ?? 0) > UPLOAD_MAX_IMAGE_BYTES) {
                    $error_message = 'Couverture trop volumineuse (maximum 20 Mo).';
                } else {
                    $ext = strtolower(pathinfo((string) ($f['name'] ?? ''), PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
                        $ext = 'jpg';
                    }
                    $dir = __DIR__ . '/../upload/boutique_branding/';
                    if (!is_dir($dir)) {
                        @mkdir($dir, 0755, true);
                    }
                    $fname = 'c_' . $admin_id . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    $dest = $dir . $fname;
                    if (move_uploaded_file($f['tmp_name'], $dest)) {
                        if ($current_cover !== '') {
                            $old = __DIR__ . '/../upload/' . str_replace('\\', '/', $current_cover);
                            if (is_file($old)) {
                                @unlink($old);
                            }
                        }
                        $cover_final = 'boutique_branding/' . $fname;
                    } else {
                        $error_message = "Impossible d'enregistrer l'image de couverture.";
                    }
                }
            }

            if ($error_message === '') {
                $branding_data = [
                    'boutique_logo'               => $logo_final !== '' ? $logo_final : null,
                    'boutique_couverture'         => $cover_final !== '' ? $cover_final : null,
                    'boutique_couleur_principale' => $c1 !== '' ? $c1 : null,
                    'boutique_couleur_accent'     => $c2 !== '' ? $c2 : null,
                ];
                $ok = update_admin_boutique_branding($admin_id, $branding_data);
                if ($ok) {
                    $admin = get_admin_by_id($admin_id);
                    if ($admin) {
                        require_once __DIR__ . '/../includes/admin_vendeur_theme.php';
                        admin_vendeur_theme_sync_session($admin);
                    }
                    $_SESSION['success_message'] = "L'apparence de votre boutique a été enregistrée.";
                    header('Location: parametres-boutique-vendeur.php');
                    exit;
                }
                $error_message = 'Enregistrement impossible. Vérifiez que la base de données contient les colonnes vitrine (migration alter_admin_boutique_branding.sql).';
            }
        }
    }
    if ($error_message !== '') {
        flash_toast_queue_page('error', $error_message);
        $admin = get_admin_by_id($admin_id);
    }
}

$logo_url    = '';
$clogo       = trim((string)($admin['boutique_logo'] ?? ''));
if ($clogo !== '') {
    $logo_url = htmlspecialchars((function_exists('upload_image_url') ? upload_image_url(str_replace('\\', '/', $clogo), 'sm') : '/upload/' . ltrim((string) str_replace('\\', '/', $clogo), '/')), ENT_QUOTES, 'UTF-8');
}
$c1_val        = htmlspecialchars(boutique_normalize_hex_color($admin['boutique_couleur_principale'] ?? '') ?: '#3564a6', ENT_QUOTES, 'UTF-8');
$c2_val        = htmlspecialchars(boutique_normalize_hex_color($admin['boutique_couleur_accent'] ?? '') ?: '#ff6b35', ENT_QUOTES, 'UTF-8');
$logo_url_attr = $logo_url !== '' ? htmlspecialchars($logo_url, ENT_QUOTES, 'UTF-8') : '';
$cover_url = '';
$ccover = trim((string) ($admin['boutique_couverture'] ?? ''));
if ($ccover !== '') {
    $cover_url = htmlspecialchars((function_exists('upload_image_url') ? upload_image_url(str_replace('\\', '/', $ccover), 'md') : '/upload/' . ltrim((string) str_replace('\\', '/', $ccover), '/')), ENT_QUOTES, 'UTF-8');
}

$boutique_nom = trim((string)($_SESSION['admin_boutique_nom'] ?? ''));
if ($boutique_nom === '') { $boutique_nom = 'Ma boutique'; }
$admin_prenom  = trim((string)($_SESSION['admin_prenom'] ?? ''));
$admin_initial = $admin_prenom !== '' ? mb_strtoupper(mb_substr($admin_prenom, 0, 1, 'UTF-8'), 'UTF-8') : 'V';

require_once __DIR__ . '/../models/model_produits.php';
require_once __DIR__ . '/../includes/image_optimizer.php';

$pbv_mock_defaults = [
    [
        'img_url' => '',
        'badge' => '-17%',
        'title' => 'Mèches bouclées premium…',
        'price' => '10 000 FCFA',
        'price_old' => '12 000 FCFA',
        'has_promo' => true,
    ],
    [
        'img_url' => '',
        'badge' => '-20%',
        'title' => 'Kit coiffure professionnel…',
        'price' => '8 500 FCFA',
        'price_old' => '10 500 FCFA',
        'has_promo' => true,
    ],
];

$pbv_mock_cards = [];
$produits_vendeur = get_all_produits('actif', $admin_id);
if (is_array($produits_vendeur)) {
    foreach ($produits_vendeur as $produit) {
        if (count($pbv_mock_cards) >= 2) {
            break;
        }
        $img_rel = trim((string) ($produit['image_principale'] ?? ''));
        if ($img_rel === '') {
            continue;
        }

        $prix = (float) ($produit['prix'] ?? 0);
        $prix_promo_raw = $produit['prix_promotion'] ?? null;
        $prix_promo = ($prix_promo_raw !== null && $prix_promo_raw !== '') ? (float) $prix_promo_raw : null;
        $has_promo = ($prix_promo !== null && $prix > 0 && $prix_promo < $prix);
        $prix_affiche = $has_promo ? $prix_promo : $prix;

        $nom = trim((string) ($produit['nom'] ?? ''));
        if ($nom === '') {
            $nom = 'Produit';
        }
        if (mb_strlen($nom) > 28) {
            $nom = mb_substr($nom, 0, 28) . '…';
        }

        $badge = '';
        if ($has_promo) {
            $badge = '-' . (int) round((1 - $prix_promo / $prix) * 100) . '%';
        }

        $pbv_mock_cards[] = [
            'img_url' => upload_image_url($img_rel, 'sm'),
            'badge' => $badge,
            'title' => $nom,
            'price' => number_format($prix_affiche, 0, ',', ' ') . ' FCFA',
            'price_old' => $has_promo ? number_format($prix, 0, ',', ' ') . ' FCFA' : '',
            'has_promo' => $has_promo,
        ];
    }
}

for ($i = 0; $i < 2; $i++) {
    if (!isset($pbv_mock_cards[$i])) {
        $pbv_mock_cards[$i] = $pbv_mock_defaults[$i];
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include __DIR__ . '/../includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apparence de ma boutique &mdash; Administration</title>
    <?php require_once __DIR__ . '/../includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 1) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <style>
        /* ===== PARAMÈTRES BOUTIQUE VENDEUR v2 ===== */

        .pbv-page {
            max-width: 900px;
            margin: 0 auto;
            padding: clamp(16px, 4vw, 36px) clamp(14px, 4vw, 24px) 90px;
            display: flex;
            flex-direction: column;
            gap: 22px;
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        /* ---- Header ---- */
        .pbv-page-header {
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pbv-page-header__row {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .pbv-page-header__back {
            flex-shrink: 0;
            width: 40px;
            height: 40px;
            display: grid;
            place-items: center;
            color: var(--couleur-dominante, #3564a6);
            text-decoration: none;
            border-radius: 12px;
            transition: background 0.2s ease;
        }

        .pbv-page-header__back:hover,
        .pbv-page-header__back:focus-visible {
            background: rgba(53, 100, 166, 0.1);
            outline: none;
        }

        .pbv-page-header__back i {
            font-size: 0.85rem;
        }

        .pbv-page-header__eyebrow {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.12em;
            color: var(--orange, #FF6B35);
            display: flex; align-items: center; gap: 5px;
        }

        .pbv-page-header__title {
            margin: 0;
            flex: 1;
            min-width: 0;
            font-size: clamp(1.3rem, 3vw, 1.75rem);
            font-weight: 800; color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
            line-height: 1.15; letter-spacing: -0.025em;
        }

        /* ---- Boutons ---- */
        .pbv-btn {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 9px 18px; border-radius: 11px;
            font-size: 0.81rem; font-weight: 700;
            cursor: pointer; border: none;
            text-decoration: none; font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.2s; white-space: nowrap;
        }

        .pbv-btn--primary { background: var(--orange, #FF6B35); color: #fff; box-shadow: 0 4px 14px rgba(255,107,53,0.25); }
        .pbv-btn--primary:hover { background: var(--orange-fonce, #E85A2A); transform: translateY(-1px); }
        .pbv-btn--outline { background: #fff; color: var(--couleur-dominante, #3564a6); border: 1.5px solid rgba(53,100,166,0.22); }
        .pbv-btn--outline:hover { background: rgba(53,100,166,0.05); }

        /* ---- Hero boutique ---- */
        .pbv-hero {
            background: var(--orange, #FF6B35);
            border-radius: 20px;
            padding: clamp(20px, 3.5vw, 34px);
            position: relative; overflow: hidden;
            box-shadow: 0 16px 40px color-mix(in srgb, var(--orange, #FF6B35) 34%, transparent);
        }

        .pbv-hero::before {
            content: ''; position: absolute; top: -60px; right: -40px;
            width: 220px; height: 220px;
            background: rgba(255,255,255,0.07);
            border-radius: 50%; pointer-events: none;
        }

        .pbv-hero::after {
            content: ''; position: absolute; bottom: -70px; right: 80px;
            width: 170px; height: 170px;
            background: rgba(255,255,255,0.04);
            border-radius: 50%; pointer-events: none;
        }

        .pbv-hero__inner {
            display: flex; align-items: center; gap: 18px;
            flex-wrap: wrap; position: relative;
        }

        .pbv-hero__logo-wrap {
            width: 68px; height: 68px; border-radius: 16px;
            background: rgba(255,255,255,0.18);
            border: 2px solid rgba(255,255,255,0.3);
            display: flex; align-items: center; justify-content: center;
            overflow: hidden; flex-shrink: 0;
            box-shadow: 0 6px 20px rgba(0,0,0,0.2);
        }

        .pbv-hero__logo-img { width: 100%; height: 100%; object-fit: contain; }
        .pbv-hero__logo-fallback { font-size: 1.8rem; font-weight: 900; color: #fff; font-family: var(--font-titres, 'Poppins', sans-serif); }

        .pbv-hero__body { flex: 1; min-width: 0; }
        .pbv-hero__name { font-size: clamp(1.1rem, 2.5vw, 1.45rem); font-weight: 900; color: #fff; font-family: var(--font-titres, 'Poppins', sans-serif); line-height: 1.1; }
        .pbv-hero__sub  { font-size: 0.79rem; color: rgba(255,255,255,0.65); margin-top: 4px; display: flex; align-items: center; gap: 6px; }

        .pbv-hero__colors { display: flex; gap: 8px; align-items: center; margin-top: 12px; }
        .pbv-hero__color-dot {
            width: 24px; height: 24px; border-radius: 50%;
            border: 2px solid rgba(255,255,255,0.4);
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
            transition: transform 0.2s;
        }
        .pbv-hero__color-dot:hover { transform: scale(1.15); }
        .pbv-hero__color-label { font-size: 0.73rem; color: rgba(255,255,255,0.55); }

        .pbv-hero__link {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 17px;
            background: rgba(255,255,255,0.14);
            border: 1.5px solid rgba(255,255,255,0.22);
            border-radius: 10px; color: #fff;
            font-size: 0.78rem; font-weight: 700;
            text-decoration: none; transition: background 0.2s;
            margin-left: auto; white-space: nowrap;
        }
        .pbv-hero__link:hover { background: rgba(255,255,255,0.24); }

        /* ---- Alertes ---- */
        .pbv-alert {
            display: flex; align-items: flex-start; gap: 11px;
            padding: 14px 18px; border-radius: 14px;
            font-size: 0.84rem; font-weight: 500;
            border: 1px solid transparent;
        }
        .pbv-alert--success { background: rgba(34,197,94,0.09); border-color: rgba(34,197,94,0.22); color: #15803d; }
        .pbv-alert--error   { background: rgba(239,68,68,0.07); border-color: rgba(239,68,68,0.2); color: #b91c1c; }
        .pbv-alert i { margin-top: 2px; font-size: 1rem; flex-shrink: 0; }

        /* ---- Grille principale ---- */
        .pbv-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 720px) { .pbv-grid { grid-template-columns: 1fr; } }

        .pbv-grid--wide { grid-column: 1 / -1; }

        /* ---- Cards ---- */
        .pbv-card {
            background: #fff;
            border-radius: 18px;
            border: 1px solid rgba(53,100,166,0.08);
            box-shadow: 0 2px 14px rgba(0,0,0,0.05);
            overflow: hidden;
            transition: box-shadow 0.2s, transform 0.2s;
        }

        .pbv-card:hover { box-shadow: 0 8px 28px rgba(53,100,166,0.1); }

        .pbv-card--orange {
            border-color: rgba(255,107,53,0.12);
            container-type: inline-size;
            container-name: pbv-logo-card;
        }
        .pbv-card--orange:hover { box-shadow: 0 8px 28px rgba(255,107,53,0.1); }

        .pbv-card__head {
            padding: 16px 20px 13px;
            border-bottom: 1px solid rgba(53,100,166,0.07);
            display: flex; align-items: center; gap: 12px;
        }

        .pbv-card--orange .pbv-card__head { border-bottom-color: rgba(255,107,53,0.08); }

        .pbv-card__head-icon {
            width: 40px; height: 40px; border-radius: 12px;
            display: flex; align-items: center; justify-content: center;
            font-size: 0.95rem; flex-shrink: 0;
        }

        .pbv-card__head-icon--blue   { background: rgba(53,100,166,0.1); color: var(--couleur-dominante, #3564a6); }
        .pbv-card__head-icon--orange { background: rgba(255,107,53,0.1); color: var(--orange, #FF6B35); }
        .pbv-card__head-icon--green  { background: rgba(34,197,94,0.1); color: #15803d; }
        .pbv-card__head-icon--violet { background: rgba(139,92,246,0.1); color: #7c3aed; }
        .pbv-card__head-icon--map    { background: rgba(234,179,8,0.1); color: #a16207; }

        .pbv-card__head-text h3 { font-size: 0.93rem; font-weight: 800; color: var(--titres, #0d0d0d); margin: 0; font-family: var(--font-titres, 'Poppins', sans-serif); }
        .pbv-card__head-text p  { font-size: 0.72rem; color: var(--gris-moyen, #737373); margin: 2px 0 0; }

        .pbv-card__body { padding: 18px 20px 20px; display: flex; flex-direction: column; gap: 14px; }

        /* ---- Logo upload ---- */
        .pbv-card--orange .pbv-card__body { gap: 0; padding: 0; }

        .pbv-logo-panel {
            display: flex;
            flex-direction: column;
            gap: 0;
            align-items: stretch;
        }

        @container pbv-logo-card (min-width: 520px) {
            .pbv-logo-panel {
                flex-direction: row;
                align-items: stretch;
            }
        }

        .pbv-logo-preview {
            flex: 1;
            min-width: 0;
            padding: clamp(12px, 3vw, 18px);
            background: rgba(255,107,53,0.05);
            border-bottom: 1px solid rgba(255,107,53,0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: clamp(6px, 1.5vw, 10px);
        }

        @container pbv-logo-card (min-width: 520px) {
            .pbv-logo-preview {
                border-bottom: none;
                border-right: 1px solid rgba(255,107,53,0.1);
            }
        }

        .pbv-logo-frame {
            width: 100%;
            max-width: min(200px, 72vw);
            min-height: clamp(72px, 18vw, 100px);
            padding: clamp(10px, 2.5vw, 14px);
            border-radius: clamp(10px, 2vw, 14px);
            background: #fff;
            border: 2px dashed rgba(255,107,53,0.35);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-sizing: border-box;
            transition: border-color 0.2s, background 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 16px rgba(255,107,53,0.08);
        }

        .pbv-logo-frame.is-preview-new {
            border-style: solid;
            border-color: var(--orange, #FF6B35);
            box-shadow: 0 6px 20px rgba(255,107,53,0.18);
        }

        .pbv-logo-frame.is-remove-pending { opacity: 0.5; filter: grayscale(0.4); }

        .pbv-logo-frame img {
            max-width: 100%;
            max-height: clamp(56px, 14vw, 80px);
            object-fit: contain;
        }

        .pbv-cover-frame {
            position: relative;
            width: 100%;
            height: 148px;
            border-radius: 14px;
            overflow: hidden;
            border: 2px dashed rgba(53, 100, 166, 0.35);
            background:
                radial-gradient(120% 140% at 100% 0%, rgba(255, 107, 53, 0.45), transparent 55%),
                linear-gradient(120deg, #2d5690, #3564a6);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .pbv-cover-frame img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pbv-cover-frame.is-remove-pending {
            opacity: 0.5;
        }

        .pbv-cover-placeholder {
            position: relative;
            z-index: 1;
            color: #fff;
            font-size: 0.82rem;
            font-weight: 600;
            text-align: center;
            padding: 0 16px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.25);
        }

        .pbv-logo-placeholder {
            text-align: center;
            color: var(--gris-moyen, #737373);
            font-size: clamp(0.72rem, 2vw, 0.8rem);
            line-height: 1.4;
            padding: 0 6px;
        }

        .pbv-logo-placeholder i {
            font-size: clamp(1.4rem, 4vw, 1.75rem);
            opacity: 0.35;
            display: block;
            margin-bottom: 6px;
            color: var(--orange, #FF6B35);
        }

        .pbv-logo-caption {
            font-size: clamp(0.65rem, 1.8vw, 0.72rem);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--gris-moyen, #737373);
            text-align: center;
            margin: 0;
        }

        .pbv-logo-caption--preview { color: var(--orange, #FF6B35); }

        .pbv-logo-actions {
            flex: 1;
            min-width: 0;
            padding: clamp(12px, 3vw, 18px);
            display: flex;
            flex-direction: column;
            gap: clamp(8px, 2vw, 12px);
            justify-content: center;
        }

        .pbv-file-label {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: clamp(6px, 1.5vw, 8px);
            padding: clamp(8px, 2vw, 10px) clamp(14px, 3vw, 18px);
            border-radius: 10px;
            background: var(--orange, #FF6B35);
            color: #fff;
            font-weight: 700;
            font-size: clamp(0.76rem, 2.2vw, 0.84rem);
            cursor: pointer;
            transition: all 0.2s;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            box-shadow: 0 4px 12px rgba(255,107,53,0.28);
            width: 100%;
            box-sizing: border-box;
        }

        .pbv-file-label:hover {
            background: var(--orange-fonce, #E85A2A);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(255,107,53,0.35);
        }

        .pbv-remove-logo {
            display: flex;
            align-items: flex-start;
            gap: clamp(8px, 2vw, 10px);
            padding: clamp(8px, 2vw, 11px) clamp(10px, 2.5vw, 12px);
            background: rgba(239,68,68,0.06);
            border: 1px solid rgba(239,68,68,0.15);
            border-radius: 10px;
            font-size: clamp(0.74rem, 2vw, 0.8rem);
            line-height: 1.4;
            color: var(--gris-fonce, #4a4a4a);
            cursor: pointer;
        }

        .pbv-remove-logo input {
            width: clamp(14px, 3.5vw, 16px);
            height: clamp(14px, 3.5vw, 16px);
            accent-color: #ef4444;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .pbv-logo-actions .pbv-section-save {
            width: 100%;
            margin-top: 2px;
        }

        /* ---- Couleurs — nouvelle section full-width ---- */
        .pbv-card--colors { }

        .pbv-colors-layout {
            display: flex;
            flex-direction: column;
            gap: clamp(10px, 2.5vw, 16px);
        }

        .pbv-colors-pickers-row {
            display: flex;
            flex-direction: row;
            flex-wrap: nowrap;
            gap: clamp(8px, 2vw, 12px);
            align-items: stretch;
        }

        .pbv-color-slot {
            flex: 1 1 0;
            min-width: 0;
            padding: clamp(10px, 2.5vw, 14px);
            border-radius: clamp(10px, 2vw, 14px);
            border: 1.5px solid rgba(53,100,166,0.1);
            background: rgba(53,100,166,0.025);
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .pbv-color-slot--accent {
            background: rgba(255,107,53,0.025);
            border-color: rgba(255,107,53,0.12);
        }

        /* Swatch (grand carré cliquable) */
        .pbv-color-swatch-label {
            display: flex;
            align-items: center;
            gap: 12px;
            cursor: pointer;
            min-width: 0;
        }

        .pbv-color-slot__info {
            min-width: 0;
            overflow: hidden;
        }

        .pbv-color-slot__title,
        .pbv-color-hex {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pbv-color-swatch {
            width: clamp(36px, 10vw, 48px);
            height: clamp(36px, 10vw, 48px);
            border-radius: clamp(9px, 2vw, 12px);
            flex-shrink: 0;
            position: relative;
            box-shadow: 0 4px 14px rgba(0,0,0,0.18);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
            display: block;
        }

        .pbv-color-swatch:hover { transform: scale(1.06); box-shadow: 0 6px 20px rgba(0,0,0,0.25); }

        .pbv-color-swatch input[type="color"] {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            opacity: 0;
            cursor: pointer;
            border: none;
            padding: 0;
        }

        .pbv-color-slot__info {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .pbv-color-slot__title {
            font-size: clamp(0.68rem, 2vw, 0.82rem);
            font-weight: 800;
            color: var(--titres, #0d0d0d);
            font-family: var(--font-titres, 'Poppins', sans-serif);
        }

        .pbv-color-hex {
            font-family: ui-monospace, Consolas, monospace;
            font-size: clamp(0.68rem, 2vw, 0.8rem);
            font-weight: 700;
            color: var(--gris-fonce, #4a4a4a);
            letter-spacing: 0.03em;
        }

        /* Liste des usages */
        .pbv-color-usages {
            list-style: none;
            margin: 0;
            padding: 0;
            display: flex;
            flex-direction: column;
            gap: 6px;
        }

        .pbv-color-usages li {
            display: flex;
            align-items: center;
            gap: 7px;
            font-size: 0.74rem;
            color: var(--gris-fonce, #4a4a4a);
            font-weight: 500;
        }

        .pbv-color-usages li i {
            font-size: 0.68rem;
            width: 16px;
            text-align: center;
            flex-shrink: 0;
        }

        .pbv-color-slot--main  .pbv-color-usages li i { color: var(--couleur-dominante, #3564a6); }
        .pbv-color-slot--accent .pbv-color-usages li i { color: var(--orange, #FF6B35); }

        /* Aperçu miniature boutique (vitrine réelle) */
        .pbv-color-preview-card {
            display: flex;
            flex-direction: column;
            gap: 10px;
            min-width: 0;
        }

        .pbv-color-preview-card__label {
            font-size: 0.68rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.07em;
            color: var(--gris-moyen, #737373);
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 0;
        }

        .pbv-vitrine {
            --shop-primary: var(--pbv-mock-c1, #3564a6);
            --shop-accent: var(--pbv-mock-c2, #ff6b35);
            background: #fff;
            border-radius: 16px;
            border: 1px solid rgba(13, 13, 13, 0.08);
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0, 0, 0, 0.08);
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .pbv-vitrine__cover {
            position: relative;
            height: 96px;
            background:
                radial-gradient(120% 140% at 100% 0%, color-mix(in srgb, var(--shop-accent) 55%, transparent) 0%, transparent 55%),
                linear-gradient(120deg, color-mix(in srgb, var(--shop-primary) 70%, #000) 0%, var(--shop-primary) 62%, color-mix(in srgb, var(--shop-primary) 68%, var(--shop-accent)) 100%);
        }

        .pbv-vitrine__cover img {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pbv-vitrine__id {
            display: grid;
            grid-template-columns: auto minmax(0, 1fr) auto;
            align-items: end;
            gap: 10px;
            padding: 0 12px 10px;
        }

        .pbv-vitrine__logo {
            width: 64px;
            height: 64px;
            margin-top: -32px;
            border-radius: 16px;
            background: #fff;
            border: 3px solid #fff;
            box-shadow: 0 6px 16px rgba(15, 23, 42, 0.12);
            display: grid;
            place-items: center;
            overflow: hidden;
        }

        .pbv-vitrine__logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
            padding: 4px;
            box-sizing: border-box;
        }

        .pbv-vitrine__logo span {
            font-size: 24px;
            font-weight: 700;
            color: var(--shop-primary);
        }

        .pbv-vitrine__name {
            display: block;
            margin-top: 8px;
            font-size: 16px;
            font-weight: 700;
            line-height: 1.2;
            color: #0d0d0d;
        }

        .pbv-vitrine__meta {
            display: block;
            margin-top: 2px;
            font-size: 12px;
            color: #667085;
        }

        .pbv-vitrine__meta strong {
            color: #0d0d0d;
        }

        .pbv-vitrine__actions {
            display: flex;
            gap: 6px;
            padding-bottom: 2px;
        }

        .pbv-vitrine__btn {
            display: inline-flex;
            align-items: center;
            height: 30px;
            padding: 0 10px;
            border-radius: 999px;
            border: 1px solid #e4e7ec;
            background: #fff;
            color: #344054;
            font-size: 12px;
            font-weight: 700;
        }

        .pbv-vitrine__btn--primary {
            border-color: transparent;
            background: var(--shop-primary);
            color: #fff;
        }

        .pbv-vitrine__bar {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 0 12px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #667085;
        }

        .pbv-vitrine__bar .is-active {
            color: var(--shop-primary);
            box-shadow: inset 0 -2px 0 var(--shop-primary);
            padding-bottom: 4px;
        }

        .pbv-vitrine__search {
            margin-left: auto;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f4f6fa;
            color: #98a2b3;
            font-weight: 500;
        }

        .pbv-vitrine__products {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
            padding: 0 12px 12px;
        }

        .pbv-vitrine__card {
            border: 1px solid #eef1f6;
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .pbv-vitrine__card-img {
            position: relative;
            height: 72px;
            background: #f4f6fa;
        }

        .pbv-vitrine__card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .pbv-vitrine__card-badge {
            position: absolute;
            top: 6px;
            left: 6px;
            padding: 2px 6px;
            border-radius: 6px;
            background: var(--shop-accent);
            color: #fff;
            font-size: 10px;
            font-weight: 800;
        }

        .pbv-vitrine__card-body {
            padding: 8px;
        }

        .pbv-vitrine__card-title {
            margin: 0 0 4px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pbv-vitrine__card-price {
            margin: 0;
            font-size: 12px;
            font-weight: 800;
            color: var(--shop-primary);
        }

        .pbv-vitrine__card-price-old {
            margin: 0;
            font-size: 10px;
            color: #98a2b3;
            text-decoration: line-through;
        }

        .pbv-vitrine__add {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            margin-top: 6px;
            padding: 4px 8px;
            border-radius: 8px;
            background: var(--shop-primary);
            color: #fff;
            font-size: 11px;
            font-weight: 700;
        }

        .pbv-mock-shop {
            --pbv-mock-c1: var(--couleur-dominante, #3564a6);
            --pbv-mock-c2: var(--orange, #FF6B35);
            background: #fff;
            border-radius: 12px;
            border: 1px solid rgba(13,13,13,0.08);
            overflow: hidden;
            box-shadow: 0 8px 28px rgba(0,0,0,0.08);
            font-family: var(--font-corps, 'Poppins', sans-serif);
        }

        .pbv-mock-shop__stripe {
            height: 6px;
            background: var(--pbv-mock-c1);
            transition: background 0.25s;
        }

        .pbv-mock-shop__nav {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 8px 10px;
            flex-wrap: wrap;
            background: #fff;
        }

        .pbv-mock-shop__nav-logo {
            width: 36px;
            height: 36px;
            border-radius: 6px;
            background: #0d0d0d;
            display: grid;
            place-items: center;
            overflow: hidden;
            flex-shrink: 0;
        }

        .pbv-mock-shop__nav-logo img {
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .pbv-mock-shop__nav-logo-fallback {
            font-size: 0.5rem;
            font-weight: 800;
            color: #fff;
            letter-spacing: 0.04em;
            line-height: 1;
            text-align: center;
            padding: 2px;
        }

        .pbv-mock-shop__search {
            flex: 1 1 120px;
            min-width: 0;
            display: flex;
            align-items: stretch;
            border-radius: 999px;
            overflow: hidden;
            border: 1px solid rgba(13,13,13,0.1);
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
        }

        .pbv-mock-shop__search-btn {
            width: 28px;
            flex-shrink: 0;
            display: grid;
            place-items: center;
            background: var(--pbv-mock-c1);
            color: #fff;
            font-size: 0.55rem;
            transition: background 0.25s;
        }

        .pbv-mock-shop__search-input {
            flex: 1;
            min-width: 0;
            padding: 5px 8px;
            font-size: 0.52rem;
            color: var(--gris-moyen, #737373);
            background: #fff;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pbv-mock-shop__lang {
            flex-shrink: 0;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 7px;
            border-radius: 8px;
            border: 1px solid rgba(13,13,13,0.1);
            background: #fafafa;
            font-size: 0.5rem;
            font-weight: 700;
            color: var(--titres, #0d0d0d);
        }

        .pbv-mock-shop__lang-flag {
            width: 14px;
            height: 10px;
            border-radius: 2px;
            background: url('https://flagcdn.com/w40/fr.png') center/cover no-repeat;
            box-shadow: 0 0 0 1px rgba(0,0,0,0.06);
        }

        .pbv-mock-shop__account {
            flex: 1 1 100%;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px 4px 4px;
            border-radius: 999px;
            background: var(--pbv-mock-c2);
            color: #fff;
            max-width: 100%;
            transition: background 0.25s;
        }

        .pbv-mock-shop__account-avatar {
            width: 26px;
            height: 26px;
            border-radius: 50%;
            background: #fff;
            color: var(--pbv-mock-c1);
            font-size: 0.65rem;
            font-weight: 800;
            display: grid;
            place-items: center;
            flex-shrink: 0;
            transition: color 0.25s;
        }

        .pbv-mock-shop__account-text {
            display: flex;
            flex-direction: column;
            min-width: 0;
            line-height: 1.15;
        }

        .pbv-mock-shop__account-text strong {
            font-size: 0.58rem;
            font-weight: 700;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .pbv-mock-shop__account-text small {
            font-size: 0.48rem;
            opacity: 0.92;
            font-weight: 500;
        }

        .pbv-mock-shop__section-title {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 10px 10px 6px;
            text-align: center;
        }

        .pbv-mock-shop__section-title h4 {
            margin: 0;
            font-size: 0.62rem;
            font-weight: 800;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: var(--titres, #0d0d0d);
            white-space: nowrap;
        }

        .pbv-mock-shop__section-line {
            width: 28px;
            height: 3px;
            border-radius: 2px;
            background: var(--pbv-mock-c1);
            flex-shrink: 0;
            transition: background 0.25s;
        }

        .pbv-mock-shop__products {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 138px));
            justify-content: center;
            gap: 7px;
            padding: 4px 10px 10px;
            background: #fff;
            max-width: 100%;
        }

        .pbv-mock-shop__card {
            background: #fff;
            border: 1px solid rgba(13,13,13,0.08);
            border-radius: 8px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 2px 8px rgba(0,0,0,0.05);
            max-width: 138px;
            width: 100%;
            margin: 0 auto;
        }

        .pbv-mock-shop__card-img {
            position: relative;
            aspect-ratio: 1 / 1;
            max-height: 88px;
            background: linear-gradient(145deg, #f3f4f6, #e5e7eb);
            overflow: hidden;
        }

        .pbv-mock-shop__card-photo {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .pbv-mock-shop__card-badge {
            position: absolute;
            top: 4px;
            left: 4px;
            z-index: 1;
            padding: 1px 5px;
            border-radius: 4px;
            font-size: 0.42rem;
            font-weight: 800;
            color: #fff;
            background: var(--pbv-mock-c2);
            transition: background 0.25s;
            line-height: 1.2;
        }

        .pbv-mock-shop__card-body {
            padding: 5px 6px 6px;
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .pbv-mock-shop__card-title {
            margin: 0;
            font-size: 0.44rem;
            font-weight: 500;
            line-height: 1.25;
            color: var(--noir-clair, #2d2d2d);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 2.2em;
        }

        .pbv-mock-shop__card-prices {
            display: flex;
            flex-wrap: wrap;
            align-items: baseline;
            gap: 2px 4px;
        }

        .pbv-mock-shop__card-price {
            margin: 0;
            font-size: 0.48rem;
            font-weight: 700;
            color: var(--pbv-mock-c2);
            transition: color 0.25s;
            line-height: 1.2;
        }

        .pbv-mock-shop__card-price-old {
            margin: 0;
            font-size: 0.4rem;
            color: var(--gris-clair, #a3a3a3);
            text-decoration: line-through;
            line-height: 1.2;
        }

        .pbv-mock-shop__card-btn {
            margin-top: auto;
            width: 100%;
            padding: 4px 5px;
            border-radius: 6px;
            border: 1px solid var(--pbv-mock-c1);
            background: #fff;
            color: var(--pbv-mock-c1);
            font-size: 0.42rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 3px;
            transition: border-color 0.25s, color 0.25s;
            cursor: default;
            font-family: inherit;
        }

        .pbv-mock-shop__card-btn i {
            font-size: 0.5em;
        }

        .pbv-card--colors { container-type: inline-size; container-name: pbv-colors-card; }

        @container pbv-colors-card (min-width: 480px) {
            .pbv-mock-shop__account {
                flex: 0 1 auto;
                max-width: 46%;
            }
        }

        @container pbv-colors-card (max-width: 520px) {
            .pbv-mock-shop__nav { gap: 5px; padding: 7px; }
            .pbv-mock-shop__products {
                grid-template-columns: repeat(2, minmax(0, 120px));
                gap: 6px;
                padding: 4px 7px 10px;
            }
            .pbv-mock-shop__card { max-width: 120px; }
            .pbv-mock-shop__card-img { max-height: 76px; }
        }

        /* ---- Select et Textarea ---- */
        .pbv-form-group { display: flex; flex-direction: column; gap: 6px; }

        .pbv-form-label {
            font-size: 0.73rem; font-weight: 700;
            text-transform: uppercase; letter-spacing: 0.06em;
            color: var(--gris-fonce, #4a4a4a);
        }

        .pbv-select,
        .pbv-textarea {
            width: 100%; padding: 11px 15px;
            border: 1.5px solid rgba(53,100,166,0.18);
            border-radius: 11px; background: #f9fbff;
            font-size: 0.87rem; color: var(--titres, #0d0d0d);
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: border-color 0.2s, box-shadow 0.2s;
            outline: none; box-sizing: border-box;
        }

        .pbv-select:focus,
        .pbv-textarea:focus {
            border-color: var(--couleur-dominante, #3564a6);
            box-shadow: 0 0 0 3px rgba(53,100,166,0.1);
            background: #fff;
        }

        .pbv-textarea { min-height: 100px; resize: vertical; line-height: 1.5; }

        /* ---- Bouton enregistrer par section ---- */
        .pbv-section-save {
            display: inline-flex; align-items: center; gap: 7px;
            padding: 10px 22px; border-radius: 11px;
            font-size: 0.82rem; font-weight: 700;
            border: none; cursor: pointer;
            font-family: var(--font-corps, 'Poppins', sans-serif);
            transition: all 0.2s; width: 100%;
            justify-content: center;
        }

        .pbv-section-save--orange {
            background: var(--orange, #FF6B35); color: #fff;
            box-shadow: 0 4px 14px rgba(255,107,53,0.25);
        }
        .pbv-section-save--orange:hover { background: var(--orange-fonce, #E85A2A); transform: translateY(-1px); box-shadow: 0 7px 18px rgba(255,107,53,0.32); }

        .pbv-section-save--blue {
            background: var(--couleur-dominante, #3564a6); color: #fff;
            box-shadow: 0 4px 14px rgba(53,100,166,0.25);
        }
        .pbv-section-save--blue:hover { background: var(--bleu-fonce, #2d5690); transform: translateY(-1px); box-shadow: 0 7px 18px rgba(53,100,166,0.32); }

        .pbv-section-save--green {
            background: #16a34a; color: #fff;
            box-shadow: 0 4px 14px rgba(22,163,74,0.22);
        }
        .pbv-section-save--green:hover { background: #15803d; transform: translateY(-1px); box-shadow: 0 7px 18px rgba(22,163,74,0.3); }

        .pbv-section-save--gold {
            background: #a16207; color: #fff;
            box-shadow: 0 4px 14px rgba(161,98,7,0.22);
        }
        .pbv-section-save--gold:hover { background: #854d0e; transform: translateY(-1px); box-shadow: 0 7px 18px rgba(161,98,7,0.3); }

        .pbv-section-save i { font-size: 0.82em; }

        /* ---- Responsive global (échelle proportionnelle) ---- */
        @media (max-width: 768px) {
            .pbv-page {
                gap: clamp(14px, 3.5vw, 18px);
                padding-bottom: calc(72px + env(safe-area-inset-bottom, 0px));
            }

            .pbv-page-header__title { font-size: clamp(1.05rem, 4.5vw, 1.3rem); }
            .pbv-page-header__eyebrow { font-size: 0.68rem; }

            .pbv-btn {
                padding: clamp(7px, 2vw, 9px) clamp(12px, 3vw, 16px);
                font-size: clamp(0.74rem, 2.2vw, 0.81rem);
            }

            .pbv-hero {
                border-radius: clamp(14px, 3vw, 18px);
                padding: clamp(14px, 3.5vw, 22px);
            }

            .pbv-hero__logo-wrap {
                width: clamp(48px, 12vw, 60px);
                height: clamp(48px, 12vw, 60px);
                border-radius: clamp(10px, 2.5vw, 14px);
            }

            .pbv-hero__link {
                width: 100%;
                margin-left: 0;
                justify-content: center;
                margin-top: 4px;
            }

            .pbv-grid { gap: clamp(10px, 2.5vw, 14px); }

            .pbv-card { border-radius: clamp(12px, 3vw, 16px); }

            .pbv-card__head {
                padding: clamp(11px, 2.8vw, 14px) clamp(12px, 3vw, 16px) clamp(9px, 2.2vw, 11px);
                gap: clamp(8px, 2vw, 10px);
            }

            .pbv-card__head-icon {
                width: clamp(32px, 8vw, 36px);
                height: clamp(32px, 8vw, 36px);
                font-size: clamp(0.8rem, 2.2vw, 0.9rem);
                border-radius: 10px;
            }

            .pbv-card__head-text h3 { font-size: clamp(0.82rem, 2.4vw, 0.9rem); }
            .pbv-card__head-text p  { font-size: clamp(0.66rem, 1.9vw, 0.72rem); }

            .pbv-card:not(.pbv-card--orange) .pbv-card__body {
                padding: clamp(12px, 3vw, 16px);
                gap: clamp(10px, 2.5vw, 12px);
            }

            .pbv-color-swatch-label { gap: clamp(6px, 1.5vw, 10px); }

            .pbv-logo-actions { padding: clamp(10px, 2.5vw, 14px); }
            .pbv-logo-preview { padding: clamp(10px, 2.5vw, 14px); }
            .pbv-mock-shop__products {
                grid-template-columns: repeat(2, minmax(0, 120px));
                gap: 6px;
                padding: 4px 7px 10px;
            }
            .pbv-mock-shop__card { max-width: 120px; }
            .pbv-mock-shop__card-img { max-height: 76px; }

            .pbv-section-save {
                padding: clamp(8px, 2.2vw, 10px) clamp(14px, 3.5vw, 18px);
                font-size: clamp(0.76rem, 2.2vw, 0.82rem);
            }

            .pbv-select,
            .pbv-textarea {
                padding: clamp(9px, 2.2vw, 11px) clamp(11px, 2.8vw, 14px);
                font-size: clamp(0.8rem, 2.3vw, 0.87rem);
            }
        }

        @media (min-width: 721px) and (max-width: 1024px) {
            .pbv-logo-frame { max-width: 100%; }
            .pbv-file-label,
            .pbv-section-save { font-size: 0.78rem; }
            .pbv-remove-logo { font-size: 0.74rem; }
        }

        @media (max-width: 380px) {
            .pbv-color-swatch-label {
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .pbv-color-slot__title,
            .pbv-color-hex {
                white-space: normal;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/nav.php'; ?>

<div class="pbv-page">

    <!-- ===== HEADER ===== -->
    <header class="vd-page-head">
        <div class="vd-page-head__text">
            <a href="parametres.php" class="vd-page-head__back"><i class="fas fa-arrow-left" aria-hidden="true"></i> Ma boutique</a>
            <h1 class="vd-page-head__title">Apparence de la vitrine</h1>
            <p class="vd-page-head__sub">Couverture, logo et couleurs vus par vos clients sur votre boutique.</p>
        </div>
    </header>

    <!-- ===== HERO BOUTIQUE ===== -->
    <div class="pbv-hero">
        <div class="pbv-hero__inner">
            <div class="pbv-hero__logo-wrap">
                <?php if ($logo_url !== ''): ?>
                    <img src="<?php echo $logo_url; ?>" alt="Logo de la boutique" class="pbv-hero__logo-img">
                <?php else: ?>
                    <span class="pbv-hero__logo-fallback"><?php echo htmlspecialchars($admin_initial); ?></span>
                <?php endif; ?>
            </div>
            <div class="pbv-hero__body">
                <div class="pbv-hero__name"><?php echo htmlspecialchars($boutique_nom); ?></div>
                <div class="pbv-hero__sub">
                    <i class="fas fa-palette" style="font-size:.7rem;"></i>
                    Personnalisez l&rsquo;apparence de votre vitrine
                </div>
                <div class="pbv-hero__colors">
                    <span class="pbv-hero__color-dot" id="hero-c1-dot" style="background:<?php echo $c1_val; ?>;"></span>
                    <span class="pbv-hero__color-dot" id="hero-c2-dot" style="background:<?php echo $c2_val; ?>;"></span>
                    <span class="pbv-hero__color-label">Couleurs actives</span>
                </div>
            </div>
            <a href="parametres.php" class="pbv-hero__link">
                <i class="fas fa-sliders-h"></i> Param&egrave;tres
            </a>
        </div>
    </div>

    <!-- ===== ALERTES via flash toast ===== -->

    <!-- ===== FORMULAIRE ===== -->
    <form method="post" enctype="multipart/form-data" action="" id="brv-form">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['admin_csrf']); ?>">
        <input type="hidden" name="boutique_branding_save" value="1">

        <div class="pbv-grid">

            <!-- LOGO -->
            <section class="pbv-card pbv-card--orange" aria-labelledby="pbv-logo-title">
                <div class="pbv-card__head">
                    <div class="pbv-card__head-icon pbv-card__head-icon--orange"><i class="fas fa-image"></i></div>
                    <div class="pbv-card__head-text">
                        <h3 id="pbv-logo-title">Logo de la boutique</h3>
                        <p>Aper&ccedil;u en direct avant enregistrement</p>
                    </div>
                </div>
                <div class="pbv-card__body">
                    <div class="pbv-logo-panel">
                        <div class="pbv-logo-preview">
                            <div class="pbv-logo-frame" id="brv-logo-frame" data-current-src="<?php echo $logo_url_attr; ?>">
                                <?php if ($logo_url !== ''): ?>
                                    <img src="<?php echo $logo_url; ?>" alt="Logo de la boutique" id="brv-preview-img">
                                <?php else: ?>
                                    <div class="pbv-logo-placeholder" id="brv-logo-placeholder">
                                        <i class="fas fa-store"></i>
                                        Aucun logo — logo marketplace par d&eacute;faut
                                    </div>
                                    <img src="" alt="" id="brv-preview-img" hidden>
                                <?php endif; ?>
                            </div>
                            <p class="pbv-logo-caption" id="brv-logo-caption">
                                <?php echo $logo_url !== '' ? 'Logo actuel' : 'Aper&ccedil;u'; ?>
                            </p>
                        </div>
                        <div class="pbv-logo-actions">
                            <input type="file" id="boutique_logo" name="boutique_logo"
                                class="visually-hidden"
                                accept="image/jpeg,image/png,image/gif,image/webp"
                                aria-describedby="pbv-logo-title">
                            <label for="boutique_logo" class="pbv-file-label">
                                <i class="fas fa-upload"></i> Choisir une image
                            </label>

                            <?php if ($logo_url !== ''): ?>
                                <label class="pbv-remove-logo">
                                    <input type="checkbox" name="retirer_logo" value="1" id="retirer_logo">
                                    <span>Retirer mon logo et utiliser le logo par d&eacute;faut du site</span>
                                </label>
                            <?php endif; ?>

                            <button type="submit" class="pbv-section-save pbv-section-save--orange">
                                <i class="fas fa-floppy-disk"></i> Enregistrer le logo
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            <!-- COUVERTURE -->
            <section class="pbv-card" aria-labelledby="pbv-cover-title">
                <div class="pbv-card__head">
                    <div class="pbv-card__head-icon"><i class="fas fa-image"></i></div>
                    <div class="pbv-card__head-text">
                        <h3 id="pbv-cover-title">Image de couverture</h3>
                        <p>Bannière en haut de votre vitrine. Sans image, la couverture colorée actuelle reste affichée.</p>
                    </div>
                </div>
                <div class="pbv-card__body">
                    <div class="pbv-cover-frame" id="brv-cover-frame">
                        <?php if ($cover_url !== ''): ?>
                            <img src="<?php echo $cover_url; ?>" alt="Couverture de la boutique" id="brv-cover-img">
                        <?php else: ?>
                            <div class="pbv-cover-placeholder" id="brv-cover-placeholder">Couverture par défaut</div>
                            <img src="" alt="" id="brv-cover-img" hidden>
                        <?php endif; ?>
                    </div>
                    <div class="pbv-logo-actions" style="margin-top:14px;">
                        <input type="file" id="boutique_couverture" name="boutique_couverture"
                            class="visually-hidden"
                            accept="image/jpeg,image/png,image/gif,image/webp">
                        <label for="boutique_couverture" class="pbv-file-label">
                            <i class="fas fa-upload"></i> Choisir une couverture
                        </label>
                        <?php if ($cover_url !== ''): ?>
                            <label class="pbv-remove-logo">
                                <input type="checkbox" name="retirer_couverture" value="1" id="retirer_couverture">
                                <span>Retirer ma couverture et revenir à la bannière par défaut</span>
                            </label>
                        <?php endif; ?>
                        <button type="submit" class="pbv-section-save">
                            <i class="fas fa-floppy-disk"></i> Enregistrer la couverture
                        </button>
                    </div>
                </div>
            </section>

            <!-- COULEURS -->
            <section class="pbv-card pbv-grid--wide pbv-card--colors" aria-labelledby="pbv-colors-title">
                <div class="pbv-card__head">
                    <div class="pbv-card__head-icon pbv-card__head-icon--violet"><i class="fas fa-palette"></i></div>
                    <div class="pbv-card__head-text">
                        <h3 id="pbv-colors-title">Couleurs de votre boutique</h3>
                        <p>Vitrine et espace vendeur.</p>
                    </div>
                </div>
                <div class="pbv-card__body">

                    <!-- Pickers + explications côte à côte -->
                    <div class="pbv-colors-layout">
                        <div class="pbv-colors-pickers-row">

                        <!-- Couleur principale -->
                        <div class="pbv-color-slot pbv-color-slot--main">
                            <div class="pbv-color-slot__picker-row">
                                <label for="couleur_principale" class="pbv-color-swatch-label">
                                    <span class="pbv-color-swatch" id="swatch-main" style="background:<?php echo $c1_val; ?>;">
                                        <input type="color" id="couleur_principale" name="couleur_principale" value="<?php echo $c1_val; ?>" title="Choisir la couleur principale">
                                    </span>
                                    <span class="pbv-color-slot__info">
                                        <span class="pbv-color-slot__title">Couleur principale</span>
                                        <span class="pbv-color-hex" id="hex-principale"><?php echo $c1_val; ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        <!-- Couleur accent -->
                        <div class="pbv-color-slot pbv-color-slot--accent">
                            <div class="pbv-color-slot__picker-row">
                                <label for="couleur_accent" class="pbv-color-swatch-label">
                                    <span class="pbv-color-swatch" id="swatch-accent" style="background:<?php echo $c2_val; ?>;">
                                        <input type="color" id="couleur_accent" name="couleur_accent" value="<?php echo $c2_val; ?>" title="Choisir la couleur d'accent">
                                    </span>
                                    <span class="pbv-color-slot__info">
                                        <span class="pbv-color-slot__title">Couleur d&rsquo;accent</span>
                                        <span class="pbv-color-hex" id="hex-accent"><?php echo $c2_val; ?></span>
                                    </span>
                                </label>
                            </div>
                        </div>

                        </div><!-- /.pbv-colors-pickers-row -->

                        <!-- Aperçu miniature boutique -->
                        <div class="pbv-color-preview-card">
                            <p class="pbv-color-preview-card__label"><i class="fas fa-eye"></i> Aper&ccedil;u en direct</p>
                            <?php
                            $__mock_logo_word = mb_strtoupper(mb_substr(preg_replace('/\s+/', '', $boutique_nom), 0, 5, 'UTF-8'), 'UTF-8');
                            if ($__mock_logo_word === '') {
                                $__mock_logo_word = 'SHOP';
                            }
                            ?>
                            <?php
                            $pbv_nb_produits = is_array($produits_vendeur) ? count($produits_vendeur) : 0;
                            $pbv_initial = mb_strtoupper(mb_substr($boutique_nom !== '' ? $boutique_nom : 'B', 0, 1, 'UTF-8'), 'UTF-8');
                            ?>
                            <div class="pbv-vitrine" id="preview-shop-root" aria-hidden="true"
                                style="--pbv-mock-c1:<?php echo $c1_val; ?>;--pbv-mock-c2:<?php echo $c2_val; ?>;">
                                <div class="pbv-vitrine__cover<?php echo $cover_url !== '' ? ' is-photo' : ''; ?>" id="preview-shop-cover">
                                    <img id="preview-shop-cover-img" alt="" <?php echo $cover_url !== '' ? 'src="' . $cover_url . '"' : 'hidden'; ?>>
                                </div>
                                <div class="pbv-vitrine__id">
                                    <div class="pbv-vitrine__logo" id="preview-shop-nav-logo">
                                        <img id="preview-shop-nav-logo-img" alt="" <?php echo $logo_url !== '' ? 'src="' . $logo_url . '"' : 'hidden'; ?>>
                                        <span id="preview-shop-logo-letter"<?php echo $logo_url !== '' ? ' hidden' : ''; ?>><?php echo htmlspecialchars($pbv_initial, ENT_QUOTES, 'UTF-8'); ?></span>
                                    </div>
                                    <div>
                                        <strong class="pbv-vitrine__name"><?php echo htmlspecialchars($boutique_nom, ENT_QUOTES, 'UTF-8'); ?></strong>
                                        <span class="pbv-vitrine__meta"><strong><?php echo (int) $pbv_nb_produits; ?></strong> produit<?php echo $pbv_nb_produits > 1 ? 's' : ''; ?></span>
                                    </div>
                                    <div class="pbv-vitrine__actions">
                                        <span class="pbv-vitrine__btn pbv-vitrine__btn--primary">Suivre</span>
                                        <span class="pbv-vitrine__btn">Contacter</span>
                                    </div>
                                </div>
                                <div class="pbv-vitrine__bar">
                                    <span class="is-active">Boutique</span>
                                    <span>Produits</span>
                                    <span>Nouveautés</span>
                                    <span>Promos</span>
                                    <span class="pbv-vitrine__search"><i class="fas fa-magnifying-glass" aria-hidden="true"></i> Rechercher</span>
                                </div>
                                <div class="pbv-vitrine__products">
                                    <?php foreach ($pbv_mock_cards as $idx => $pbv_card):
                                        $pbv_n = (int) $idx + 1;
                                    ?>
                                    <article class="pbv-vitrine__card">
                                        <div class="pbv-vitrine__card-img">
                                            <?php if (($pbv_card['img_url'] ?? '') !== ''): ?>
                                            <img src="<?php echo htmlspecialchars((string) $pbv_card['img_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="" loading="lazy">
                                            <?php endif; ?>
                                            <?php if (($pbv_card['badge'] ?? '') !== ''): ?>
                                            <span class="pbv-vitrine__card-badge" id="preview-shop-badge-<?php echo $pbv_n; ?>"><?php echo htmlspecialchars((string) $pbv_card['badge'], ENT_QUOTES, 'UTF-8'); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="pbv-vitrine__card-body">
                                            <p class="pbv-vitrine__card-title"><?php echo htmlspecialchars((string) $pbv_card['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <p class="pbv-vitrine__card-price" id="preview-shop-price-<?php echo $pbv_n; ?>"><?php echo htmlspecialchars((string) $pbv_card['price'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php if (!empty($pbv_card['has_promo']) && ($pbv_card['price_old'] ?? '') !== ''): ?>
                                            <p class="pbv-vitrine__card-price-old"><?php echo htmlspecialchars((string) $pbv_card['price_old'], ENT_QUOTES, 'UTF-8'); ?></p>
                                            <?php endif; ?>
                                            <span class="pbv-vitrine__add"><i class="fas fa-cart-shopping"></i> Ajouter</span>
                                        </div>
                                    </article>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div>

                    <button type="submit" class="pbv-section-save pbv-section-save--blue">
                        <i class="fas fa-floppy-disk"></i> Enregistrer les couleurs
                    </button>

                </div>
            </section>

        </div><!-- /.pbv-grid -->

    </form>

    <p class="pbv-localisation-hint" style="font-size:0.82rem;color:var(--gris-moyen,#737373);margin-top:8px;">
        <i class="fas fa-map-location-dot"></i>
        Pays, adresse et position GPS de la boutique :
        <a href="parametres.php" style="color:var(--couleur-dominante,#3564a6);font-weight:700;">Param&egrave;tres &rarr; Localisation</a>
    </p>

</div><!-- /.pbv-page -->

<script>
(function () {
    var fileInput   = document.getElementById('boutique_logo');
    var frame       = document.getElementById('brv-logo-frame');
    var img         = document.getElementById('brv-preview-img');
    var caption     = document.getElementById('brv-logo-caption');
    var placeholder = document.getElementById('brv-logo-placeholder');
    var removeCb    = document.getElementById('retirer_logo');
    var c1          = document.getElementById('couleur_principale');
    var c2          = document.getElementById('couleur_accent');
    var h1          = document.getElementById('hex-principale');
    var h2          = document.getElementById('hex-accent');
    var heroC1      = document.getElementById('hero-c1-dot');
    var heroC2      = document.getElementById('hero-c2-dot');
    var previewShopRoot = document.getElementById('preview-shop-root');
    var previewShopNavLogoImg = document.getElementById('preview-shop-nav-logo-img');
    var previewShopBadges = [
        document.getElementById('preview-shop-badge-1'),
        document.getElementById('preview-shop-badge-2')
    ];
    var previewShopPrices = [
        document.getElementById('preview-shop-price-1'),
        document.getElementById('preview-shop-price-2')
    ];
    var swatchMain  = document.getElementById('swatch-main');
    var swatchAccent= document.getElementById('swatch-accent');
    var currentSrc  = frame ? frame.getAttribute('data-current-src') || '' : '';
    var objectUrl   = null;

    function revokeObjectUrl() { if (objectUrl) { URL.revokeObjectURL(objectUrl); objectUrl = null; } }

    function showPlaceholder(show) { if (placeholder) placeholder.style.display = show ? 'block' : 'none'; }

    function applyRemovePending() {
        if (!frame || !removeCb) return;
        frame.classList.toggle('is-remove-pending', removeCb.checked);
    }

    function syncHex() {
        if (c1 && h1) h1.textContent = c1.value;
        if (c2 && h2) h2.textContent = c2.value;
        if (c1 && heroC1)      heroC1.style.background    = c1.value;
        if (c2 && heroC2)      heroC2.style.background    = c2.value;
        if (c1 && swatchMain)  swatchMain.style.background  = c1.value;
        if (c2 && swatchAccent)swatchAccent.style.background = c2.value;
        if (previewShopRoot) {
            if (c1) previewShopRoot.style.setProperty('--pbv-mock-c1', c1.value);
            if (c2) previewShopRoot.style.setProperty('--pbv-mock-c2', c2.value);
        }
        previewShopBadges.forEach(function (el) {
            if (el && c2) {
                el.style.background = c2.value;
            }
        });
        previewShopPrices.forEach(function (el) {
            if (el && c1) {
                el.style.color = c1.value;
            }
        });
    }

    var previewLogoLetter = document.getElementById('preview-shop-logo-letter');

    function syncPreviewNavLogo(src) {
        if (!previewShopNavLogoImg) return;
        if (!src) {
            previewShopNavLogoImg.setAttribute('hidden', 'hidden');
            if (previewLogoLetter) previewLogoLetter.removeAttribute('hidden');
            return;
        }
        previewShopNavLogoImg.removeAttribute('hidden');
        previewShopNavLogoImg.src = src;
        if (previewLogoLetter) previewLogoLetter.setAttribute('hidden', 'hidden');
    }

    if (fileInput && img && frame && caption) {
        fileInput.addEventListener('change', function () {
            revokeObjectUrl();
            if (removeCb) removeCb.checked = false;
            applyRemovePending();
            var f = fileInput.files && fileInput.files[0];
            if (!f) {
                if (currentSrc) {
                    img.removeAttribute('hidden'); img.src = currentSrc; img.alt = 'Logo de la boutique';
                    syncPreviewNavLogo(currentSrc);
                    caption.textContent = 'Logo actuel';
                    caption.classList.remove('pbv-logo-caption--preview');
                    frame.classList.remove('is-preview-new');
                } else {
                    img.removeAttribute('src'); img.setAttribute('hidden','hidden');
                    showPlaceholder(true);
                    caption.textContent = 'Aperçu';
                    caption.classList.remove('pbv-logo-caption--preview');
                    frame.classList.remove('is-preview-new');
                }
                return;
            }
            objectUrl = URL.createObjectURL(f);
            img.removeAttribute('hidden'); img.src = objectUrl; img.alt = 'Aperçu du nouveau logo';
            syncPreviewNavLogo(objectUrl);
            showPlaceholder(false);
            caption.textContent = 'Aperçu — enregistrez pour appliquer';
            caption.classList.add('pbv-logo-caption--preview');
            frame.classList.add('is-preview-new');
        });
    }

    if (removeCb && frame && img && caption) {
        removeCb.addEventListener('change', function () {
            applyRemovePending();
            if (removeCb.checked) {
                revokeObjectUrl();
                syncPreviewNavLogo('');
                if (fileInput) fileInput.value = '';
                if (currentSrc) {
                    img.removeAttribute('hidden'); img.src = currentSrc;
                    img.alt = 'Logo actuel (sera retiré après enregistrement)';
                    showPlaceholder(false);
                    caption.textContent = 'Suppression prévue au prochain enregistrement';
                    caption.classList.add('pbv-logo-caption--preview');
                    frame.classList.remove('is-preview-new');
                } else {
                    img.removeAttribute('src'); img.setAttribute('hidden','hidden');
                    showPlaceholder(true);
                    caption.textContent = 'Aucun logo après enregistrement';
                    caption.classList.add('pbv-logo-caption--preview');
                    frame.classList.remove('is-preview-new');
                }
            } else {
                if (fileInput && fileInput.files && fileInput.files[0]) {
                    fileInput.dispatchEvent(new Event('change'));
                } else if (currentSrc) {
                    img.removeAttribute('hidden'); img.src = currentSrc; img.alt = 'Logo de la boutique';
                    syncPreviewNavLogo(currentSrc);
                    showPlaceholder(false);
                    caption.textContent = 'Logo actuel';
                    caption.classList.remove('pbv-logo-caption--preview');
                    frame.classList.remove('is-preview-new');
                }
            }
        });
    }

    if (c1) c1.addEventListener('input', syncHex);
    if (c2) c2.addEventListener('input', syncHex);
    syncHex();

    var coverInput = document.getElementById('boutique_couverture');
    var coverFrame = document.getElementById('brv-cover-frame');
    var coverImg = document.getElementById('brv-cover-img');
    var coverPh = document.getElementById('brv-cover-placeholder');
    var coverRemove = document.getElementById('retirer_couverture');
    var coverUrl = null;
    if (coverInput && coverImg && coverFrame) {
        coverInput.addEventListener('change', function () {
            if (coverUrl) { URL.revokeObjectURL(coverUrl); coverUrl = null; }
            var f = coverInput.files && coverInput.files[0];
            if (!f) return;
            if (coverRemove) coverRemove.checked = false;
            coverFrame.classList.remove('is-remove-pending');
            coverUrl = URL.createObjectURL(f);
            coverImg.hidden = false;
            coverImg.removeAttribute('hidden');
            coverImg.src = coverUrl;
            if (coverPh) coverPh.style.display = 'none';
            var vitrineCover = document.getElementById('preview-shop-cover-img');
            if (vitrineCover) {
                vitrineCover.hidden = false;
                vitrineCover.removeAttribute('hidden');
                vitrineCover.src = coverUrl;
            }
        });
    }
    if (coverRemove && coverFrame) {
        coverRemove.addEventListener('change', function () {
            coverFrame.classList.toggle('is-remove-pending', coverRemove.checked);
            var vitrineCover = document.getElementById('preview-shop-cover-img');
            if (vitrineCover && coverRemove.checked) {
                vitrineCover.setAttribute('hidden', 'hidden');
            }
        });
    }
})();
</script>
<?php include __DIR__ . '/includes/footer.php'; ?>
</body>
</html>
