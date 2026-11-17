<?php
/**
 * Boutons connexion sociale (Google + Apple).
 * Variables optionnelles :
 * - $google_auth_type / $social_auth_type : auto|client|vendor
 * - $google_auth_redirect / $social_auth_redirect : URL relative après connexion
 * - $google_auth_position / $social_auth_position : top|bottom
 */
$social_auth_type = isset($social_auth_type) ? trim((string) $social_auth_type) : (isset($google_auth_type) ? trim((string) $google_auth_type) : 'auto');
if (!in_array($social_auth_type, ['auto', 'client', 'vendor'], true)) {
    $social_auth_type = 'auto';
}
$social_auth_redirect = isset($social_auth_redirect)
    ? (string) $social_auth_redirect
    : (isset($google_auth_redirect) ? (string) $google_auth_redirect : '');
$social_auth_position = (isset($social_auth_position) && $social_auth_position === 'bottom')
    ? 'bottom'
    : ((isset($google_auth_position) && $google_auth_position === 'bottom') ? 'bottom' : 'top');
$position_class = $social_auth_position === 'top' ? ' social-auth--top' : ' social-auth--bottom';
$social_auth_disabled = !empty($google_auth_disabled) || !empty($social_auth_disabled);
$social_auth_variant = isset($social_auth_variant) ? trim((string) $social_auth_variant) : '';
$is_tiles_variant = ($social_auth_variant === 'tiles');
$is_hub_variant = ($social_auth_variant === 'hub') || $is_tiles_variant;
$hub_class = $is_tiles_variant ? ' social-auth--tiles' : ($is_hub_variant ? ' social-auth--hub' : '');
?>
<div class="social-auth<?php echo $position_class . $hub_class; ?><?php echo $social_auth_disabled ? ' social-auth--disabled' : ''; ?>">
    <?php if ($social_auth_position === 'bottom' && !$is_hub_variant): ?>
        <div class="social-auth__divider"><span>ou</span></div>
    <?php endif; ?>

    <div class="social-auth__buttons">
        <button type="button"
            class="google-auth-btn"
            data-social-auth-type="<?php echo htmlspecialchars($social_auth_type, ENT_QUOTES, 'UTF-8'); ?>"
            data-social-auth-redirect="<?php echo htmlspecialchars($social_auth_redirect, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $social_auth_disabled ? 'disabled' : ''; ?>>
            <?php if ($is_tiles_variant): ?>
            <span class="google-auth-btn__icon google-auth-btn__icon--color" aria-hidden="true">
                <svg viewBox="0 0 48 48" width="26" height="26"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/><path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/></svg>
            </span>
            <?php else: ?>
            <span class="google-auth-btn__icon" aria-hidden="true">G</span>
            <?php endif; ?>
            <span class="social-auth-btn__label">
                <span class="social-auth-btn__label-full">Continuer avec Google</span>
                <span class="social-auth-btn__label-short" aria-hidden="true">Google</span>
            </span>
        </button>

        <button type="button"
            class="apple-auth-btn"
            data-social-auth-type="<?php echo htmlspecialchars($social_auth_type, ENT_QUOTES, 'UTF-8'); ?>"
            data-social-auth-redirect="<?php echo htmlspecialchars($social_auth_redirect, ENT_QUOTES, 'UTF-8'); ?>"
            <?php echo $social_auth_disabled ? 'disabled' : ''; ?>>
            <span class="apple-auth-btn__icon" aria-hidden="true"><i class="fab fa-apple"></i></span>
            <span class="social-auth-btn__label">
                <span class="social-auth-btn__label-full">Continuer avec Apple</span>
                <span class="social-auth-btn__label-short" aria-hidden="true">Apple</span>
            </span>
        </button>
    </div>

    <?php if ($social_auth_position === 'top' && !$is_hub_variant): ?>
        <div class="social-auth__divider"><span>ou</span></div>
    <?php endif; ?>
</div>
