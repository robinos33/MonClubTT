<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$monclubtt_onglets = array(
    'joueurs'   => 'Joueurs',
    'equipes'   => 'Équipes',
    'connexion' => 'Connexion',
);
$monclubtt_api_ok = is_object(MonClubTT_AccesFFTTApi::getInstance());
$monclubtt_onglet = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if (!isset($monclubtt_onglets[$monclubtt_onglet])) {
    // Sans identifiants FFTT, on arrive directement sur l'onglet qui permet de les saisir.
    $monclubtt_onglet = $monclubtt_api_ok ? 'joueurs' : 'connexion';
}
?>
<div class="wrap">
    <h1 class="monclubtt-title">Données FFTT</h1>

    <nav class="nav-tab-wrapper wp-clearfix" aria-label="Onglets des données FFTT">
        <?php foreach ($monclubtt_onglets as $monclubtt_cle => $monclubtt_libelle): ?>
            <a href="<?php echo esc_url(monclubtt_admin_url_donnees($monclubtt_cle)); ?>"
               class="nav-tab<?php echo $monclubtt_cle === $monclubtt_onglet ? ' nav-tab-active' : ''; ?>"
               <?php echo $monclubtt_cle === $monclubtt_onglet ? 'aria-current="page"' : ''; ?>><?php echo esc_html($monclubtt_libelle); ?></a>
        <?php endforeach; ?>
    </nav>

    <?php if ($monclubtt_onglet !== 'connexion' && !$monclubtt_api_ok): ?>
        <div class="notice notice-warning inline">
            <p>Renseignez d'abord les identifiants de l'API FFTT dans l'onglet
                <a href="<?php echo esc_url(monclubtt_admin_url_donnees('connexion')); ?>">Connexion</a>.</p>
        </div>
    <?php else:
        include __DIR__ . '/' . ($monclubtt_onglet === 'connexion' ? 'donnees-connexion' : $monclubtt_onglet) . '.php';
    endif; ?>
</div>
