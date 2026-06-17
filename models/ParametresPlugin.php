<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('MonClubTT_ParametresPlugin')) {


    class MonClubTT_ParametresPlugin
    {

        /**
         * @return array $params
         */
        private static function getParametresFromDatabase()
        {
            global $wpdb;
            $params['idApplication'] = get_option(MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION);
            $params['motDePasse'] = get_option(MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE);
            $params['numClub'] = get_option(MonClubTT_Constantes::MONCLUBTT_NUM_CLUB);
            return $params;
        }

        public static function getIdApplication()
        {
            $params = self::getParametresFromDatabase();
            return $params['idApplication'];
        }

        public static function getMotDePasse()
        {
            $params = self::getParametresFromDatabase();
            return $params['motDePasse'];
        }

        public static function getNumClub()
        {
            $params = self::getParametresFromDatabase();
            return $params['numClub'];
        }

    }

}
