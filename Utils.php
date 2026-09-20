<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_Constantes' ) ) {

    class MonClubTT_Constantes {

        const MONCLUBTT_ID_APPLICATION = 'monclubtt_id_application';
        const MONCLUBTT_MOT_DE_PASSE = 'monclubtt_mot_de_passe';
        const MONCLUBTT_NUM_CLUB = 'monclubtt_num_club';

    }

}

if ( ! function_exists( 'monclubtt_date_locale' ) ) {

    /**
     * Formate un horodatage UTC dans le fuseau horaire configuré dans WordPress.
     *
     * Les horodatages du plugin sont stockés avec time() (donc en UTC), alors que
     * date_i18n() attend un timestamp déjà décalé : l'appeler directement affichait
     * l'heure UTC et non l'heure du site (ex. -2h pour Paris en été).
     *
     * @param int         $timestamp Horodatage UTC (issu de time()).
     * @param string|null $format    Format de date ; par défaut les formats du site.
     * @return string
     */
    function monclubtt_date_locale($timestamp, $format = null) {
        $timestamp = (int) $timestamp;

        if (null === $format) {
            $format = get_option('date_format') . ' à ' . get_option('time_format');
        }

        // wp_date() est la seule fonction WP qui convertit un timestamp UTC
        // vers le fuseau horaire du site (option « Fuseau horaire », DST incluse).
        if (function_exists('wp_date')) {
            $formatted = wp_date($format, $timestamp);
            if (false !== $formatted) {
                return $formatted;
            }
        }

        // Repli pour WordPress < 5.3 : on applique manuellement le décalage du site.
        return date_i18n($format, $timestamp + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }

}

/**
 * Autoloading des models
 */
if ( ! function_exists( 'monclubtt_autoload_models' ) ) {

    function monclubtt_autoload_models() {
        $repertoireModels = __DIR__ . '/models/';
        $models = glob($repertoireModels . "*.php");
        if ( ! is_array( $models ) ) {
            return;
        }
        foreach ($models as $model) {
            require_once $model;
        }
    }

}

monclubtt_autoload_models();
