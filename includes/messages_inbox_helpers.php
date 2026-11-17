<?php
/**
 * Messagerie (client + vendeur) — aides d'affichage de la liste des conversations
 */

if (!function_exists('messages_inbox_time_label')) {
    /** Heure du dernier message façon messagerie : 14:32 · Hier · lun. · 12/09/26 */
    function messages_inbox_time_label($date)
    {
        $ts = $date ? strtotime((string) $date) : false;
        if (!$ts) {
            return '';
        }
        $today = strtotime('today');
        if ($ts >= $today) {
            return date('H:i', $ts);
        }
        if ($ts >= $today - 86400) {
            return 'Hier';
        }
        if ($ts >= $today - 6 * 86400) {
            $jours = ['dim.', 'lun.', 'mar.', 'mer.', 'jeu.', 'ven.', 'sam.'];
            return $jours[(int) date('w', $ts)];
        }
        return date('d/m/y', $ts);
    }
}

if (!function_exists('messages_inbox_preview')) {
    /** Aperçu une ligne du dernier message (balises retirées, espaces normalisés) */
    function messages_inbox_preview($contenu)
    {
        $txt = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $contenu)));
        return mb_strlen($txt) > 70 ? mb_substr($txt, 0, 68) . '…' : $txt;
    }
}
