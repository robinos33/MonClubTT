<?php ob_start();
if ( ! defined( 'ABSPATH' ) ) exit;
$pluginData = DataPing::getPluginData();
if (isset($pluginData)) { ?>
    <h1 class="DataPing_title">DataPing <span class="DataPing_version">v<?php echo $pluginData['Version']; ?></span></h1>
    <p class="DataPing_author">Par <?php echo $pluginData['Author']; ?></p>
    <?php
}

if (!is_object(AccesFFTTApi::getInstance()) && $_GET['page'] !== 'parametres_DataPing') {
    ?>
    <div class="wrap">
        <h1 class="DataPing_title">Les équipes </h1>
        <?php echo 'Veuillez rentrer vos paramètres en cliquant sur "DataPing" dans le menu'; ?>
    </div>
    <?php

}
$api = AccesFFTTApi::getInstance();



