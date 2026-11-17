<?php
/**
 * Médias certification (pièces d'identité, documents) — accès contrôlé uniquement.
 * Programmation procédurale.
 */

if (!function_exists('certification_media_normalize_path')) {
    /**
     * Normalise un chemin relatif stocké en BDD vers certifications/{id}/{fichier}.
     *
     * @return string chemin normalisé ou '' si invalide
     */
    function certification_media_normalize_path($raw)
    {
        $path = trim(str_replace('\\', '/', (string) $raw));
        $path = ltrim($path, '/');
        if (strpos($path, 'upload/') === 0) {
            $path = substr($path, 7);
        }
        if ($path === '' || strpos($path, '..') !== false) {
            return '';
        }
        if (!preg_match('#^certifications/([0-9]+)/([A-Za-z0-9._-]+)$#', $path)) {
            return '';
        }
        return $path;
    }
}

if (!function_exists('certification_media_url')) {
    /**
     * URL sécurisée (nécessite session admin ou super admin).
     */
    function certification_media_url($raw_path)
    {
        if (!function_exists('app_url')) {
            require_once __DIR__ . '/app_base.php';
        }
        $path = certification_media_normalize_path($raw_path);
        if ($path === '') {
            return '';
        }
        return app_url('/media/certification.php?p=' . rawurlencode($path));
    }
}

if (!function_exists('certification_media_absolute_file')) {
    /**
     * Résout le chemin fichier réel sous upload/certifications, ou false.
     *
     * @return string|false
     */
    function certification_media_absolute_file($raw_path)
    {
        $path = certification_media_normalize_path($raw_path);
        if ($path === '') {
            return false;
        }
        $root = realpath(__DIR__ . '/../upload/certifications');
        if ($root === false || !is_dir($root)) {
            return false;
        }
        $full = realpath(__DIR__ . '/../upload/' . $path);
        if ($full === false || !is_file($full)) {
            return false;
        }
        $root_prefix = $root . DIRECTORY_SEPARATOR;
        if (strncmp($full, $root_prefix, strlen($root_prefix)) !== 0) {
            return false;
        }
        return $full;
    }
}

if (!function_exists('certification_media_owner_admin_id')) {
    /**
     * Extrait l'admin_id propriétaire depuis le chemin.
     */
    function certification_media_owner_admin_id($raw_path)
    {
        $path = certification_media_normalize_path($raw_path);
        if ($path === '' || !preg_match('#^certifications/([0-9]+)/#', $path, $m)) {
            return 0;
        }
        return (int) $m[1];
    }
}

if (!function_exists('certification_media_kind_from_path')) {
    /**
     * Type d'affichage pour un fichier certification (image ou PDF).
     */
    function certification_media_kind_from_path($raw_path)
    {
        $path = certification_media_normalize_path($raw_path);
        if ($path === '') {
            return 'image';
        }
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if ($ext === 'pdf') {
            return 'pdf';
        }
        return 'image';
    }
}

if (!function_exists('certification_demande_pieces_list')) {
    /**
     * Liste des pièces jointes d'une demande (max 10).
     *
     * @return array<int, array{url: string, label: string, kind: string}>
     */
    function certification_demande_pieces_list(array $demande, $niveau = 'standard')
    {
        $nv = (string) $niveau;
        $labels = [
            'photo_local_1' => 'Façade',
            'photo_local_2' => 'Intérieur',
            'photo_local_3' => 'Local — vue 3',
            'photo_local_4' => 'Local — vue 4',
            'photo_document' => $nv === 'vip' ? 'RCCM' : 'Document',
            'photo_piece_identite' => 'Pièce d\'identité (recto)',
            'photo_piece_identite_verso' => 'Pièce d\'identité (verso)',
        ];
        $pieces = [];
        foreach ($labels as $col => $label) {
            if (empty($demande[$col])) {
                continue;
            }
            $url = certification_media_url((string) $demande[$col]);
            if ($url === '') {
                continue;
            }
            $pieces[] = [
                'url' => $url,
                'label' => $label,
                'kind' => certification_media_kind_from_path((string) $demande[$col]),
            ];
        }
        return array_slice($pieces, 0, 10);
    }
}

if (!function_exists('certification_media_can_access')) {
    /**
     * Super admin : tout. Vendeur/admin connecté : uniquement ses dossiers.
     */
    function certification_media_can_access($raw_path)
    {
        $owner_id = certification_media_owner_admin_id($raw_path);
        if ($owner_id <= 0) {
            return false;
        }
        if (!empty($_SESSION['super_admin_id'])) {
            /* Pièces d'identité : seulement pour un compte super admin encore actif */
            if (!function_exists('get_super_admin_by_id')) {
                require_once dirname(__DIR__) . '/models/model_super_admin.php';
            }
            $sa = get_super_admin_by_id((int) $_SESSION['super_admin_id']);
            if ($sa && ($sa['statut'] ?? '') === 'actif') {
                return true;
            }
        }
        $admin_id = (int) ($_SESSION['admin_id'] ?? 0);
        if ($admin_id > 0 && $admin_id === $owner_id) {
            return true;
        }
        return false;
    }
}
