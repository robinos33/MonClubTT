<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1 class="monclubtt-title">Réglages</h1>
    <p>Identité du club, reprise sur le site, le jeu de pong et les visuels réseaux sociaux.
        Les identifiants de l'API FFTT sont dans
        <a href="<?php echo esc_url(monclubtt_admin_url_donnees('connexion')); ?>">Données FFTT › Connexion</a>.</p>
    <?php $this->getForm('monclubtt_settings'); ?>

    <?php $monclubtt_pluginData = MonClubTT_Plugin::getPluginData(); ?>
    <p class="monclubtt-author">
        <?php echo esc_html($monclubtt_pluginData['Name']); ?> v<?php echo esc_html($monclubtt_pluginData['Version']); ?>,
        par <?php echo wp_kses($monclubtt_pluginData['Author'], array('a' => array('href' => array(), 'rel' => array()))); ?>
    </p>
</div>
