<?php
/**
 * Super Admin — consommation de l'assistant multi-IA
 */
require_once __DIR__ . '/../includes/require_login.php';
require_once dirname(__DIR__, 2) . '/includes/assistant/assistant_ai.php';

/* Clés API saisies ici (chiffrées en base) : rien à modifier sur le serveur */
$cles_champs = [
    'gemini_api_key' => ['Gemini', 'gemini'],
    'mistral_api_key' => ['Mistral', 'mistral'],
    'openrouter_api_key' => ['OpenRouter', 'openrouter'],
    'cloudflare_api_token' => ['Cloudflare — jeton', 'cloudflare'],
    'cloudflare_account_id' => ['Cloudflare — Account ID', 'cloudflare'],
];
$flash = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['cles_ia'])) {
    $n = 0;
    foreach ($cles_champs as $nom => [$lib, $prov]) {
        $val = trim((string) ($_POST[$nom] ?? ''));
        if (!empty($_POST['retirer'][$nom])) {
            assistant_keys_save($nom, '');
            $n++;
        } elseif ($val !== '' && mb_strlen($val) <= 300 && !preg_match('/\s/', $val)) {
            if (assistant_keys_save($nom, $val)) {
                assistant_ai_unpause($prov);
                $n++;
            }
        }
    }
    $_SESSION['sa_ai_flash'] = $n > 0 ? 'Clés enregistrées. L’assistant les utilise dès maintenant.' : 'Aucune modification.';
    header('Location: index.php');
    exit;
}
if (!empty($_SESSION['sa_ai_flash'])) {
    $flash = (string) $_SESSION['sa_ai_flash'];
    unset($_SESSION['sa_ai_flash']);
}
$cles = assistant_keys_load(true);

$days = isset($_GET['jours']) ? (int) $_GET['jours'] : 30;
if (!in_array($days, [1, 7, 30], true)) {
    $days = 30;
}

$summary = assistant_ai_usage_summary($days);
$recent = assistant_ai_usage_recent(50);
$keys = assistant_keys_public_status();
$labels = assistant_ai_labels();
$order = assistant_ai_provider_order();

$fmt = static function ($n) {
    return number_format((int) $n, 0, ',', ' ');
};
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php include dirname(__DIR__, 2) . '/includes/favicon.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assistant IA — Super Admin</title>
    <?php require_once dirname(__DIR__, 2) . '/includes/asset_version.php'; ?>
    <?php require_once dirname(__DIR__, 2) . '/includes/ui_font_awesome.php'; ?>
    <link rel="stylesheet" href="/css/admin-dashboard.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-clients.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-ca.css<?php echo asset_version_query(); ?>">
    <link rel="stylesheet" href="/css/super-admin-assistant.css<?php echo asset_version_query(); ?>">
</head>
<body class="page-users admin-clients-page sa-users-page sa-ca-page sa-ai-page">
    <?php include __DIR__ . '/../includes/nav.php'; ?>

    <div class="sa-users-shell sa-ca-shell">
        <header class="sa-ca-hero" aria-labelledby="sa-ai-title">
            <div class="sa-ca-hero__top">
                <div>
                    <h1 class="sa-ca-hero__title" id="sa-ai-title">Assistant IA</h1>
                    <p class="sa-ca-hero__period">Bascule automatique entre les IA</p>
                </div>
                <nav class="sa-ca-hero__nav" aria-label="Période">
                    <a class="sa-ca-hero__nav-btn" href="index.php?jours=1" title="24 heures">1j</a>
                    <a class="sa-ca-hero__nav-btn" href="index.php?jours=7" title="7 jours">7j</a>
                    <a class="sa-ca-hero__nav-btn" href="index.php?jours=30" title="30 jours">30j</a>
                </nav>
            </div>
        </header>

        <div class="sa-ai-providers">
            <?php foreach ($order as $p):
                $row = $summary[$p] ?? ['ok' => 0, 'error' => 0, 'failovers' => 0, 'tokens_in' => 0, 'tokens_out' => 0];
                $ready = !empty($keys[$p]['configured']);
                ?>
                <article class="sa-ai-card">
                    <div class="sa-ai-card__head">
                        <h2 class="sa-ai-card__name"><?php echo htmlspecialchars($labels[$p] ?? $p, ENT_QUOTES, 'UTF-8'); ?></h2>
                        <?php $pause = $ready && assistant_ai_paused($p); ?>
                        <span class="sa-ai-pill<?php echo !$ready ? ' sa-ai-pill--off' : ($pause ? ' sa-ai-pill--pause' : ''); ?>"
                            <?php if ($pause): ?>title="Erreur récente (quota ou lenteur) : l’IA suivante répond à sa place, nouvel essai automatique dans quelques minutes"<?php endif; ?>>
                            <?php echo !$ready ? 'Clé absente' : ($pause ? 'En pause' : 'Active'); ?>
                        </span>
                    </div>
                    <div class="sa-ai-card__row"><span>Succès</span><strong><?php echo $fmt($row['ok']); ?></strong></div>
                    <div class="sa-ai-card__row"><span>Erreurs</span><strong><?php echo $fmt($row['error']); ?></strong></div>
                    <div class="sa-ai-card__row"><span>Basculements</span><strong><?php echo $fmt($row['failovers']); ?></strong></div>
                    <div class="sa-ai-card__row"><span>Jetons in / out</span><strong><?php echo $fmt($row['tokens_in']); ?> / <?php echo $fmt($row['tokens_out']); ?></strong></div>
                </article>
            <?php endforeach; ?>
        </div>

        <section class="sa-ai-keys" aria-labelledby="sa-ai-keys-title">
            <h2 id="sa-ai-keys-title">Clés API</h2>
            <?php if ($flash !== ''): ?><p class="sa-ai-flash" role="status"><?php echo htmlspecialchars($flash, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
            <form method="post" class="sa-ai-keys__form" autocomplete="off">
                <?php echo super_admin_csrf_field(); ?>
                <input type="hidden" name="cles_ia" value="1">
                <?php foreach ($cles_champs as $nom => [$lib, $prov]):
                    $en_place = (string) ($cles[$nom] ?? '') !== '';
                    $ici = !empty($cles['admin'][$nom]);
                    ?>
                    <div class="sa-ai-keys__row">
                        <label for="k_<?php echo $nom; ?>"><?php echo htmlspecialchars($lib, ENT_QUOTES, 'UTF-8'); ?></label>
                        <input type="password" id="k_<?php echo $nom; ?>" name="<?php echo $nom; ?>" spellcheck="false"
                            placeholder="<?php echo $en_place ? 'Enregistrée — collez une nouvelle clé pour la remplacer' : 'Collez la clé ici'; ?>">
                        <?php if ($ici): ?>
                            <label class="sa-ai-keys__del"><input type="checkbox" name="retirer[<?php echo $nom; ?>]" value="1"> Retirer</label>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="sa-ai-keys__save">Enregistrer les clés</button>
            </form>
        </section>

        <section class="sa-ai-table-wrap">
            <h2>Derniers appels</h2>
            <table class="sa-ai-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>IA</th>
                        <th>Statut</th>
                        <th>HTTP</th>
                        <th>Jetons</th>
                        <th>ms</th>
                        <th>Détail</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$recent): ?>
                        <tr><td colspan="7">Aucun appel pour le moment.</td></tr>
                    <?php else: ?>
                        <?php foreach ($recent as $r): ?>
                            <tr>
                                <td><?php echo htmlspecialchars((string) $r['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($labels[$r['provider']] ?? (string) $r['provider'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="<?php echo ($r['status'] ?? '') === 'ok' ? 'sa-ai-ok' : 'sa-ai-err'; ?>">
                                    <?php echo htmlspecialchars((string) $r['status'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                                <td><?php echo (int) ($r['http_code'] ?? 0) ?: '—'; ?></td>
                                <td><?php echo (int) ($r['tokens_in'] ?? 0); ?> / <?php echo (int) ($r['tokens_out'] ?? 0); ?></td>
                                <td><?php echo (int) ($r['latency_ms'] ?? 0); ?></td>
                                <td><?php
                                    $det = trim((string) ($r['error_class'] ?? ''));
                                    if (!empty($r['failover_from'])) {
                                        $det = trim($det . ' ← ' . ($labels[$r['failover_from']] ?? $r['failover_from']));
                                    }
                                    echo $det !== '' ? htmlspecialchars($det, ENT_QUOTES, 'UTF-8') : '—';
                                ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>
    </div>

    <?php include __DIR__ . '/../includes/footer.php'; ?>
