<?php
/**
 * Navigation latérale super administrateur
 */

$current_dir = dirname($_SERVER['PHP_SELF'] ?? '');
$current_page = basename($_SERVER['PHP_SELF'] ?? '');
$is_boutiques = strpos($current_dir, '/boutiques') !== false;
$is_utilisateurs = strpos($current_dir, '/utilisateurs') !== false;
$is_logs = strpos($current_dir, '/logs') !== false;
$is_assistant_ia = strpos($current_dir, '/assistant') !== false;
$is_securite = strpos($current_dir, '/securite') !== false;
$is_parametres = strpos($current_dir, '/parametres') !== false;
$is_certifications = strpos($current_dir, '/certifications') !== false;
$is_comptes_sa = strpos($current_dir, '/comptes') !== false;
$is_annonces_sa = strpos($current_dir, '/annonces') !== false;
$is_chiffre_affaires = strpos($current_dir, '/chiffre-affaires') !== false;

$is_moderation_img = strpos($current_dir, '/moderation-images') !== false;
$is_dons_sa = strpos($current_dir, '/dons') !== false;
$is_contact_sa = strpos($current_dir, '/messages-contact') !== false;
/* Liens relatifs : '' à la racine de super_admin/, '../' dans n'importe quel sous-dossier */
$base_path = (basename($current_dir) === 'super_admin') ? '' : '../';

$sa_cert_pending_count = 0;
// Le badge de navigation ne doit pas déclencher une migration complète à
// chaque page. La page Certifications reste responsable de son propre schéma.
if (isset($db) && $db instanceof PDO) {
    try {
        $sa_cert_pending_count = (int) $db
            ->query("SELECT COUNT(*) FROM vendeur_certification_demandes WHERE statut = 'en_attente'")
            ->fetchColumn();
    } catch (PDOException $e) {
        $sa_cert_pending_count = 0;
    }
}

$sa_dons_pending_count = 0;
if (isset($db) && $db instanceof PDO) {
    try {
        /* Dons : annonces à valider + signalements à traiter */
        $sa_dons_pending_count = (int) $db->query("SELECT
            (SELECT COUNT(*) FROM dons WHERE statut = 'en_moderation') +
            (SELECT COUNT(*) FROM dons_signalements WHERE statut = 'nouveau')")->fetchColumn();
    } catch (PDOException $e) {
        $sa_dons_pending_count = 0;
    }
}

$sa_contact_count = 0;
if (isset($db) && $db instanceof PDO) {
    try {
        $sa_contact_count = (int) $db->query("SELECT COUNT(*) FROM contact_messages WHERE statut = 'nouveau'")->fetchColumn();
    } catch (PDOException $e) {
        $sa_contact_count = 0;
    }
}

$sa_img_pending_count = 0;
if (isset($db) && $db instanceof PDO) {
    try {
        $sa_img_pending_count = (int) $db
            ->query("SELECT COUNT(*) FROM produit_image_moderation WHERE statut = 'en_attente'")
            ->fetchColumn();
    } catch (PDOException $e) {
        $sa_img_pending_count = 0;
    }
}

?>
<!-- Bouton menu mobile -->
<button class="mobile-menu-toggle" id="menuToggle" type="button" aria-label="Ouvrir le menu">
    <i class="fas fa-bars"></i>
</button>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<script>
    (function () {
        function toggleAdminSidebar() {
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (sidebar && overlay) {
                sidebar.classList.toggle('show');
                overlay.classList.toggle('show');
                document.body.style.overflow = sidebar.classList.contains('show') ? 'hidden' : '';
            }
        }
        window.toggleSidebar = toggleAdminSidebar;
        document.addEventListener('DOMContentLoaded', function () {
            var btn = document.getElementById('menuToggle');
            var overlay = document.getElementById('sidebarOverlay');
            if (btn) btn.addEventListener('click', toggleAdminSidebar);
            if (overlay) overlay.addEventListener('click', toggleAdminSidebar);
        });
    })();
</script>

<div class="admin-container">
    <aside class="admin-sidebar" id="adminSidebar">
        <div class="sidebar-header">
            <a href="<?php echo htmlspecialchars($base_path . 'dashboard.php', ENT_QUOTES, 'UTF-8'); ?>"
                class="sidebar-logo" aria-label="COLObanes — accueil">
                <img src="/image/logo_market.webp" alt="COLObanes" width="182" height="55" decoding="async">
            </a>
            <h2>Super Admin</h2>
        </div>
        <nav class="sidebar-menu" aria-label="Navigation super administrateur">
            <a href="<?php echo htmlspecialchars($base_path . 'dashboard.php'); ?>"
                class="menu-item <?php echo $current_page === 'dashboard.php' && basename($current_dir) === 'super_admin' ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-chart-line"></i></span>
                <span class="menu-item__text">Tableau de bord</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'chiffre-affaires/index.php'); ?>"
                class="menu-item <?php echo $is_chiffre_affaires ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-coins"></i></span>
                <span class="menu-item__text">Chiffre d'affaires</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'boutiques/index.php'); ?>"
                class="menu-item <?php echo $is_boutiques ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-store"></i></span>
                <span class="menu-item__text">Boutiques</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'utilisateurs/index.php'); ?>"
                class="menu-item <?php echo $is_utilisateurs ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-users"></i></span>
                <span class="menu-item__text">Clients plateforme</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'certifications/index.php'); ?>"
                class="menu-item <?php echo $is_certifications ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-certificate"></i></span>
                <span class="menu-item__text">Certifications</span>
                <?php if ($sa_cert_pending_count > 0): ?>
                    <span class="menu-item__badge-cert" title="Demandes en attente"><?php echo (int) $sa_cert_pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'moderation-images/index.php'); ?>"
                class="menu-item <?php echo $is_moderation_img ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-images"></i></span>
                <span class="menu-item__text">Modération images</span>
                <?php if ($sa_img_pending_count > 0): ?>
                    <span class="menu-item__badge-cert" title="Images en attente"><?php echo (int) $sa_img_pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'dons/index.php'); ?>"
                class="menu-item <?php echo $is_dons_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-hand-holding-heart"></i></span>
                <span class="menu-item__text">Dons</span>
                <?php if ($sa_dons_pending_count > 0): ?>
                    <span class="menu-item__badge-cert" title="Annonces à valider et signalements"><?php echo (int) $sa_dons_pending_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'messages-contact/index.php'); ?>"
                class="menu-item <?php echo $is_contact_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-envelope-open-text"></i></span>
                <span class="menu-item__text">Messages de contact</span>
                <?php if ($sa_contact_count > 0): ?>
                    <span class="menu-item__badge-cert" title="Messages à traiter"><?php echo (int) $sa_contact_count; ?></span>
                <?php endif; ?>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'logs/index.php'); ?>"
                class="menu-item <?php echo $is_logs ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-clipboard-list"></i></span>
                <span class="menu-item__text">Journal d'audit</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'assistant/index.php'); ?>"
                class="menu-item <?php echo $is_assistant_ia ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-robot"></i></span>
                <span class="menu-item__text">Assistant IA</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'securite/index.php'); ?>"
                class="menu-item <?php echo $is_securite ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-shield-halved"></i></span>
                <span class="menu-item__text">Sécurité</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'parametres/index.php'); ?>"
                class="menu-item <?php echo $is_parametres ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-cog"></i></span>
                <span class="menu-item__text">Paramètres</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'annonces/index.php'); ?>"
                class="menu-item <?php echo $is_annonces_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-bullhorn"></i></span>
                <span class="menu-item__text">Annonces</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'comptes/index.php'); ?>"
                class="menu-item <?php echo $is_comptes_sa ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-user-shield"></i></span>
                <span class="menu-item__text">Comptes super admin</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'compte.php'); ?>"
                class="menu-item <?php echo $current_page === 'compte.php' ? 'active' : ''; ?>">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-id-card"></i></span>
                <span class="menu-item__text">Mon compte</span>
            </a>
            <a href="<?php echo htmlspecialchars($base_path . 'logout.php'); ?>" class="menu-item menu-item--logout">
                <span class="menu-item__icon" aria-hidden="true"><i class="fas fa-sign-out-alt"></i></span>
                <span class="menu-item__text">Déconnexion</span>
            </a>
        </nav>
    </aside>

    <main class="admin-content" id="adminContent">
