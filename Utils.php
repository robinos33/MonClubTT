<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class ConstantesDataPing {

    const DATAPING_ID_APPLICATION = 'DataPing_id_application';
    const DATAPING_MOT_DE_PASSE = 'DataPing_mot_de_passe';
    const DATAPING_NUM_CLUB = 'DataPing_num_club';

}

/**
 * Autoloading des models
 */
function autoload_dataPing_models() {
    $repertoireModels = __DIR__ . '/models/';
    $models = glob($repertoireModels . "*.php");
    foreach ($models as $model) {
        require_once $model;
    }
}

autoload_dataPing_models();
