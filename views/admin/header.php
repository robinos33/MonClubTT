<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$monclubtt_pluginData = MonClubTT_Plugin::getPluginData();
if (isset($monclubtt_pluginData)) { ?>
    <h1 class="monclubtt-title"><?php echo esc_html($monclubtt_pluginData['Name']); ?> <span class="monclubtt-version">v<?php echo esc_html($monclubtt_pluginData['Version']); ?></span></h1>
    <p class="monclubtt-author">Par <?php echo wp_kses($monclubtt_pluginData['Author'], array('a' => array('href' => array(), 'rel' => array()))); ?></p>
    <?php
}

$monclubtt_current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if (!is_object(MonClubTT_AccesFFTTApi::getInstance()) && $monclubtt_current_page !== 'monclubtt_parametres') {
    ?>
    <div class="wrap">
        <h1 class="monclubtt-title">Les équipes </h1>
        <?php echo 'Veuillez rentrer vos paramètres en cliquant sur "Mon Club TT" dans le menu'; ?>
    </div>
    <?php

}
$monclubtt_api = MonClubTT_AccesFFTTApi::getInstance();


