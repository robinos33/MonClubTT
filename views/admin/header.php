<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$pluginData = MonClubTT::getPluginData();
if (isset($pluginData)) { ?>
    <h1 class="monclubtt-title"><?php echo esc_html($pluginData['Name']); ?> <span class="monclubtt-version">v<?php echo esc_html($pluginData['Version']); ?></span></h1>
    <p class="monclubtt-author">Par <?php echo wp_kses($pluginData['Author'], array('a' => array('href' => array(), 'rel' => array()))); ?></p>
    <?php
}

$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if (!is_object(AccesFFTTApi::getInstance()) && $current_page !== 'monclubtt_parametres') {
    ?>
    <div class="wrap">
        <h1 class="monclubtt-title">Les équipes </h1>
        <?php echo 'Veuillez rentrer vos paramètres en cliquant sur "Mon Club TT" dans le menu'; ?>
    </div>
    <?php

}
$api = AccesFFTTApi::getInstance();



