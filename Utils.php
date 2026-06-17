<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class MonClubTT_Constantes {

    const MONCLUBTT_ID_APPLICATION = 'monclubtt_id_application';
    const MONCLUBTT_MOT_DE_PASSE = 'monclubtt_mot_de_passe';
    const MONCLUBTT_NUM_CLUB = 'monclubtt_num_club';

}

/**
 * Autoloading des models
 */
function monclubtt_autoload_models() {
    $repertoireModels = __DIR__ . '/models/';
    $models = glob($repertoireModels . "*.php");
    foreach ($models as $model) {
        require_once $model;
    }
}

monclubtt_autoload_models();
