<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('ParametresPlugin')) {


    class ParametresPlugin
    {

        /**
         * @return array $params
         */
        private static function getParametresFromDatabase()
        {
            global $wpdb;
            $params['idApplication'] = get_option(ConstantesMonClubTT::MONCLUBTT_ID_APPLICATION);
            $params['motDePasse'] = get_option(ConstantesMonClubTT::MONCLUBTT_MOT_DE_PASSE);
            $params['numClub'] = get_option(ConstantesMonClubTT::MONCLUBTT_NUM_CLUB);
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
