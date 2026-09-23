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
            $params['licencesExclues'] = get_option(MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, '');
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

        /**
         * Numéros de licence à exclure manuellement de la liste des joueurs
         * (ex : joueurs qui ne sont plus au club mais que la FFTT continue de
         * rattacher au club dans son API, faute de mieux détectable).
         *
         * @return string[] Numéros de licence (chaînes).
         */
        public static function getLicencesExclues()
        {
            $params = self::getParametresFromDatabase();
            $valeur = $params['licencesExclues'];

            if (empty($valeur)) {
                return array();
            }

            $licences = preg_split('/[\s,]+/', (string) $valeur);
            return array_values(array_filter(array_map('trim', $licences)));
        }

    }

}
