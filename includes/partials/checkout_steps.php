<?php
/**
 * Frise du parcours d'achat : Panier → Livraison → Confirmation.
 * Variable attendue : $checkout_step (1, 2 ou 3).
 */
$checkout_step = (int) ($checkout_step ?? 1);
$checkout_steps = [1 => ['Panier', 'fa-cart-shopping'], 2 => ['Livraison', 'fa-truck-fast'], 3 => ['Confirmation', 'fa-circle-check']];
?>
<ol class="co-steps" aria-label="Étapes de la commande">
    <?php foreach ($checkout_steps as $n => [$label, $icon]):
        $state = $n < $checkout_step ? 'done' : ($n === $checkout_step ? 'current' : 'todo'); ?>
        <li class="co-step co-step--<?php echo $state; ?>"<?php echo $state === 'current' ? ' aria-current="step"' : ''; ?>>
            <span class="co-step__dot" aria-hidden="true">
                <i class="fas <?php echo $state === 'done' ? 'fa-check' : $icon; ?>"></i>
            </span>
            <span class="co-step__label"><small>Étape <?php echo $n; ?></small><?php echo htmlspecialchars($label, ENT_QUOTES, 'UTF-8'); ?></span>
        </li>
    <?php endforeach; ?>
</ol>
