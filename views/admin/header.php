<?php ob_start();
if ( ! defined( 'ABSPATH' ) ) exit;
$pluginData = DataPing::getPluginData();
if (isset($pluginData)) { ?>
    <h1 class="DataPing_title">DataPing <span class="DataPing_version">v<?php echo esc_html($pluginData['Version']); ?></span></h1>
    <p class="DataPing_author">Par <?php echo wp_kses($pluginData['Author'], array('a' => array('href' => array(), 'rel' => array()))); ?></p>
    <?php
}

$current_page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if (!is_object(AccesFFTTApi::getInstance()) && $current_page !== 'parametres_DataPing') {
    ?>
    <div class="wrap">
        <h1 class="DataPing_title">Les équipes </h1>
        <?php echo 'Veuillez rentrer vos paramètres en cliquant sur "DataPing" dans le menu'; ?>
    </div>
    <?php

}
$api = AccesFFTTApi::getInstance();



