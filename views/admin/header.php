<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Retour d'options.php après l'enregistrement d'un formulaire de réglages.
if (isset($_GET['settings-updated']) && filter_input(INPUT_GET, 'settings-updated', FILTER_SANITIZE_NUMBER_INT)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    echo '<div class="notice notice-success is-dismissible"><p><strong>Paramètres enregistrés.</strong></p></div>';
}

$monclubtt_current_page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ($monclubtt_current_page === 'monclubtt_reseaux_sociaux' && !is_object(MonClubTT_AccesFFTTApi::getInstance())) {
    echo '<div class="notice notice-warning"><p>Renseignez d\'abord les identifiants de l\'API FFTT dans <a href="' . esc_url(monclubtt_admin_url_donnees('connexion')) . '">Données FFTT › Connexion</a>.</p></div>';
}
