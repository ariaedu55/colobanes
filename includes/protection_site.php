<?php
/**
 * Protection du site, appliquée automatiquement à chaque requête (chargé par includes/session_user.php) :
 *   1. Mode maintenance : réponse 503 + Retry-After (Google patiente, aucune perte de référencement) ;
 *   2. Limitation de débit par adresse IP (trafic excessif, robots) : réponse 429 ;
 *   3. Piège à robots (honeypot) : champ invisible + contrôle du temps de saisie ;
 *   4. Cloudflare Turnstile sur les formulaires sensibles (inscription, connexion, contact…), vérifié en PHP.
 * Les compteurs (robots bloqués, IP limitées) s'affichent dans Super Admin → Sécurité.
 */

if (!defined('PROTECTION_DIR')) {
    define('PROTECTION_DIR', dirname(__DIR__) . '/storage/protection');
}

/** Formulaires sensibles : Turnstile exigé (si configuré) + contrôle du temps (sauf connexion). */
function protection_formulaires_sensibles(): array
{
    return [
        'choix-connexion.php' => ['temps' => false],
        'user/connexion.php' => ['temps' => false],
        'user/inscription.php' => ['temps' => true],
        'admin/inscription-vendeur.php' => ['temps' => true],
        'mot-de-passe-oublie.php' => ['temps' => true],
        'contact.php' => ['temps' => true],
        'dons/offrir.php' => ['temps' => true],
    ];
}

/** Script courant relatif à la racine du site (ex. « user/inscription.php »). */
function protection_script_courant(): string
{
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_FILENAME'] ?? ''));
    $racine = str_replace('\\', '/', dirname(__DIR__)) . '/';
    return str_starts_with($script, $racine) ? substr($script, strlen($racine)) : ltrim((string) ($_SERVER['SCRIPT_NAME'] ?? ''), '/');
}

/* ───────────── Adresse IP réelle (derrière Cloudflare) ───────────── */

function protection_cloudflare_reseaux(): array
{
    return [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];
}

function protection_ip_dans_reseau(string $ip, string $cidr): bool
{
    [$reseau, $bits] = explode('/', $cidr) + [1 => ''];
    $ipBin = @inet_pton($ip);
    $netBin = @inet_pton($reseau);
    if ($ipBin === false || $netBin === false || strlen($ipBin) !== strlen($netBin)) {
        return false;
    }
    $bits = (int) $bits;
    $octets = intdiv($bits, 8);
    if (strncmp($ipBin, $netBin, $octets) !== 0) {
        return false;
    }
    $reste = $bits % 8;
    if ($reste === 0) {
        return true;
    }
    $masque = chr((0xFF << (8 - $reste)) & 0xFF);
    return (($ipBin[$octets] & $masque) === ($netBin[$octets] & $masque));
}

/** IP du visiteur : l'en-tête de Cloudflare n'est cru que si la requête vient vraiment de Cloudflare. */
function protection_ip_client(): string
{
    static $ip = null;
    if ($ip !== null) {
        return $ip;
    }
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
    $cf = trim((string) ($_SERVER['HTTP_CF_CONNECTING_IP'] ?? ''));
    if ($cf !== '' && filter_var($cf, FILTER_VALIDATE_IP)) {
        foreach (protection_cloudflare_reseaux() as $r) {
            if (protection_ip_dans_reseau($ip, $r)) {
                $ip = $cf;
                break;
            }
        }
    }
    return $ip;
}

/* ───────────── Outils ───────────── */

function protection_cle(): string
{
    static $cle = null;
    if ($cle !== null) {
        return $cle;
    }
    $f = dirname(__DIR__) . '/storage/secrets/protection_cle';
    if (is_readable($f) && strlen((string) file_get_contents($f)) === 32) {
        return $cle = (string) file_get_contents($f);
    }
    if (!is_dir(dirname($f))) {
        @mkdir(dirname($f), 0700, true);
    }
    $cle = random_bytes(32);
    @file_put_contents($f, $cle, LOCK_EX);
    @chmod($f, 0600);
    return $cle;
}

/** Compteurs affichés dans le super admin (robots bloqués, IP limitées…), par jour. */
function protection_stat(string $nom): void
{
    if (!is_dir(PROTECTION_DIR)) {
        @mkdir(PROTECTION_DIR, 0775, true);
    }
    $f = PROTECTION_DIR . '/stats.json';
    $fp = @fopen($f, 'c+');
    if (!$fp) {
        return;
    }
    if (flock($fp, LOCK_EX)) {
        $d = json_decode((string) stream_get_contents($fp), true) ?: [];
        $jour = date('Y-m-d');
        $d[$jour][$nom] = (int) ($d[$jour][$nom] ?? 0) + 1;
        $d = array_slice($d, -30, null, true);
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, json_encode($d));
        flock($fp, LOCK_UN);
    }
    fclose($fp);
}

function protection_stats(int $jours = 7): array
{
    $d = json_decode((string) @file_get_contents(PROTECTION_DIR . '/stats.json'), true) ?: [];
    $tot = [];
    foreach (array_slice($d, -$jours, null, true) as $vals) {
        foreach ((array) $vals as $k => $v) {
            $tot[$k] = ($tot[$k] ?? 0) + (int) $v;
        }
    }
    return $tot;
}

function protection_refuser(int $code, string $titre, string $texte, int $retry_after = 0): void
{
    if ($retry_after > 0 && !headers_sent()) {
        header('Retry-After: ' . $retry_after);
    }
    if (function_exists('erreurs_gracieuses_repondre')) {
        erreurs_gracieuses_repondre($code, $titre, $texte);
    } else {
        http_response_code($code);
        echo htmlspecialchars($texte, ENT_QUOTES, 'UTF-8');
    }
    exit;
}

/* ───────────── 1. Mode maintenance (503) ───────────── */

function protection_maintenance_etat(): array
{
    $d = json_decode((string) @file_get_contents(PROTECTION_DIR . '/maintenance.json'), true);
    return is_array($d) ? $d + ['actif' => false, 'message' => '', 'minutes' => 30] : ['actif' => false, 'message' => '', 'minutes' => 30];
}

function protection_maintenance_enregistrer(bool $actif, string $message = '', int $minutes = 30): void
{
    if (!is_dir(PROTECTION_DIR)) {
        @mkdir(PROTECTION_DIR, 0775, true);
    }
    @file_put_contents(PROTECTION_DIR . '/maintenance.json', json_encode([
        'actif' => $actif, 'message' => mb_substr(trim($message), 0, 300), 'minutes' => max(5, min(1440, $minutes)),
        'depuis' => date('c'),
    ], JSON_UNESCAPED_UNICODE), LOCK_EX);
}

/** Jeton qui laisse passer le super admin pendant la maintenance (cookie signé, 12 h). */
function protection_maintenance_jeton(): string
{
    $exp = time() + 43200;
    return $exp . '.' . hash_hmac('sha256', 'maintenance|' . $exp, protection_cle());
}

function protection_maintenance_autorise(): bool
{
    $c = (string) ($_COOKIE['colobanes_maint'] ?? '');
    if (!preg_match('/^(\d+)\.([a-f0-9]{64})$/', $c, $m) || (int) $m[1] < time()) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', 'maintenance|' . $m[1], protection_cle()), $m[2]);
}

function protection_maintenance_verifier(string $script): void
{
    $etat = protection_maintenance_etat();
    if (empty($etat['actif'])) {
        return;
    }
    /* Toujours accessibles : espace super admin et page de connexion (pour couper la maintenance) */
    if (str_starts_with($script, 'super_admin/') || $script === 'choix-connexion.php' || protection_maintenance_autorise()) {
        return;
    }
    $minutes = (int) ($etat['minutes'] ?? 30);
    $texte = trim((string) ($etat['message'] ?? '')) !== ''
        ? (string) $etat['message']
        : 'COLObanes fait peau neuve. Le site revient dans quelques minutes, merci de votre patience.';
    protection_refuser(503, 'Maintenance en cours', $texte, $minutes * 60);
}

/* ───────────── 2. Limitation de débit par IP ───────────── */

function protection_limite_debit(): void
{
    $post = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    $max = $post ? 40 : 240;           /* requêtes PHP par minute et par IP (les images / CSS / JS ne comptent pas) */
    $ip = protection_ip_client();
    $dir = PROTECTION_DIR . '/debit';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true) && !is_dir($dir)) {
        return;
    }
    $minute = (int) floor(time() / 60);
    $f = $dir . '/' . substr(hash('sha256', $ip . '|' . ($post ? 'p' : 'g')), 0, 24);
    $fp = @fopen($f, 'c+');
    if (!$fp) {
        return;
    }
    $n = 0;
    if (flock($fp, LOCK_EX)) {
        [$m, $c] = array_map('intval', explode(':', (string) stream_get_contents($fp)) + [0, 0]);
        $n = ($m === $minute) ? $c + 1 : 1;
        ftruncate($fp, 0);
        rewind($fp);
        fwrite($fp, $minute . ':' . $n);
        flock($fp, LOCK_UN);
    }
    fclose($fp);
    /* Ménage des compteurs anciens, de temps en temps */
    if (mt_rand(1, 500) === 1) {
        foreach (glob($dir . '/*') ?: [] as $vieux) {
            if (@filemtime($vieux) < time() - 600) {
                @unlink($vieux);
            }
        }
    }
    if ($n > $max) {
        if ($n === $max + 1) {
            protection_stat('ip_limitees');
            error_log('[protection] débit dépassé pour ' . $ip . ' (' . ($post ? 'POST' : 'GET') . ')');
        }
        protection_refuser(429, 'Trop de requêtes', 'Vous allez un peu vite ! Patientez une minute puis réessayez.', 60);
    }
}

/* ───────────── 3. Piège à robots (honeypot + temps de saisie) ───────────── */

/** Champs cachés à mettre dans un formulaire (ajoutés automatiquement à site_csrf_field()). */
function protection_honeypot_champs(): string
{
    $t = time();
    $sig = substr(hash_hmac('sha256', 'af|' . $t, protection_cle()), 0, 20);
    return '<div class="af-hp" aria-hidden="true" style="position:absolute!important;left:-10000px!important;width:1px;height:1px;overflow:hidden">'
        . '<label>Ne pas remplir<input type="text" name="site_web" value="" tabindex="-1" autocomplete="off"></label></div>'
        . '<input type="hidden" name="af_t" value="' . $t . '.' . $sig . '">';
}

function protection_honeypot_verifier(string $script): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    /* Un humain ne voit pas ce champ : s'il est rempli, c'est un robot */
    if (isset($_POST['site_web']) && trim((string) $_POST['site_web']) !== '') {
        protection_stat('robots_honeypot');
        protection_refuser(400, 'Envoi refusé', 'Votre envoi n’a pas pu être traité. Rechargez la page et réessayez.');
    }
    /* Formulaire sensible envoyé en moins de 2 secondes après affichage : robot */
    $conf = protection_formulaires_sensibles()[$script] ?? null;
    if ($conf && !empty($conf['temps']) && isset($_POST['af_t'])
        && preg_match('/^(\d+)\.([a-f0-9]{20})$/', (string) $_POST['af_t'], $m)
        && hash_equals(substr(hash_hmac('sha256', 'af|' . $m[1], protection_cle()), 0, 20), $m[2])
        && time() - (int) $m[1] < 2) {
        protection_stat('robots_trop_rapides');
        protection_refuser(400, 'Envoi trop rapide', 'Prenez une seconde pour vérifier le formulaire, puis renvoyez-le.');
    }
}

/* ───────────── 4. Cloudflare Turnstile ───────────── */

function protection_turnstile_cles(): array
{
    if (!function_exists('assistant_keys_load')) {
        $f = __DIR__ . '/assistant/assistant_keys.php';
        if (is_file($f)) {
            require_once $f;
        }
    }
    $k = function_exists('assistant_keys_load') ? assistant_keys_load() : [];
    return [(string) ($k['turnstile_site_key'] ?? ''), (string) ($k['turnstile_secret_key'] ?? '')];
}

function protection_turnstile_actif(): bool
{
    [$site, $secret] = protection_turnstile_cles();
    return $site !== '' && $secret !== '';
}

/** Widget à placer dans un formulaire sensible (n'apparaît que si une vérification est nécessaire). */
function protection_turnstile_widget(): string
{
    if (!protection_turnstile_actif()) {
        return '';
    }
    static $script = false;
    [$site] = protection_turnstile_cles();
    $html = '<div class="cf-turnstile" data-sitekey="' . htmlspecialchars($site, ENT_QUOTES, 'UTF-8')
        . '" data-language="fr" data-theme="light" data-appearance="interaction-only" style="margin:6px 0"></div>';
    if (!$script) {
        $script = true;
        $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
    }
    return $html;
}

/** Champs anti-robots complets d'un formulaire sensible : piège + Turnstile. */
function protection_champs_formulaire(): string
{
    return protection_honeypot_champs() . protection_turnstile_widget();
}

/** Vérifie le jeton Turnstile auprès de Cloudflare. */
function protection_turnstile_valide(string $jeton): bool
{
    [, $secret] = protection_turnstile_cles();
    if ($jeton === '' || strlen($jeton) > 2048) {
        return false;
    }
    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 6,
        CURLOPT_CONNECTTIMEOUT => 4,
        CURLOPT_POSTFIELDS => http_build_query(['secret' => $secret, 'response' => $jeton, 'remoteip' => protection_ip_client()]),
    ]);
    $ca = dirname(__DIR__) . '/config/cacert.pem';
    if (is_readable($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    $body = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($body === false || $code !== 200) {
        /* Cloudflare injoignable : on laisse passer (les autres protections restent actives) plutôt que bloquer les clients */
        error_log('[protection] Turnstile injoignable (HTTP ' . $code . ')');
        return true;
    }
    $d = json_decode((string) $body, true);
    return !empty($d['success']);
}

function protection_turnstile_verifier(string $script): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || !isset(protection_formulaires_sensibles()[$script])) {
        return;
    }
    /* Les clés sont en base : connexion ouverte ici si la page ne l'a pas encore fait */
    global $db;
    if (!$db instanceof PDO && is_file(dirname(__DIR__) . '/conn/conn.php')) {
        require_once dirname(__DIR__) . '/conn/conn.php';
    }
    if (!protection_turnstile_actif()) {
        return;
    }
    /* Seuls les envois du formulaire sont contrôlés (pas les appels AJAX de la même page sans jeton CSRF) */
    if (!isset($_POST['csrf_token']) && !isset($_POST['af_t']) && !isset($_POST['cf-turnstile-response'])) {
        return;
    }
    if (!protection_turnstile_valide((string) ($_POST['cf-turnstile-response'] ?? ''))) {
        protection_stat('robots_turnstile');
        protection_refuser(403, 'Vérification anti-robot', 'La vérification de sécurité n’a pas abouti. Revenez en arrière, patientez un instant puis renvoyez le formulaire.');
    }
}

/* ───────────── Point d'entrée ───────────── */

function protection_appliquer(): void
{
    static $fait = false;
    if ($fait || PHP_SAPI === 'cli') {
        return;
    }
    $fait = true;
    $script = protection_script_courant();
    protection_maintenance_verifier($script);
    protection_limite_debit();
    protection_honeypot_verifier($script);
    protection_turnstile_verifier($script);
}
