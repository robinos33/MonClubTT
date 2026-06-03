<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('joueurs')) {

    /**
     * Description of joueurs
     *
     * @author robin
     */
    class Joueurs {

        private $_api;
        /**
         * Tableau contenant les joueurs du club
         * @var array Joueur
         */
        private $joueurs = [];

        public function __construct() {
            $this->_api = AccesFFTTApi::getInstance();
            $this->loadJoueurs();
        }

        private function loadJoueurs() {
            $club = ParametresPlugin::getNumClub();
            $cacheKey = $this->_api->buildCacheKeyPublic('joueurs_club', array('numclu' => $club));
            $lifeTime = $this->_api->computeHalfDayTtlPublic();

            $joueursData = $this->_api->getCachedDataPublic($cacheKey, $lifeTime, function() use ($club) {
                return $this->_api->getLicencesByClubComplet($club);
            });

            if (!is_array($joueursData)) {
                return;
            }

            foreach ($joueursData as $joueurData) {
                if (!empty($joueurData)) {
                    $this->joueurs[] = new Joueur($joueurData);
                }
            }
        }

        public function getJoueurs($sexe) {
            $joueurs = array();
            switch ($sexe) {
                default:
                case 'MF':
                    $joueurs = $this->joueurs;
                    break;
                case 'F':
                case 'M':
                    foreach ($this->joueurs as $joueur) {
                        if ($joueur->getSexe() === $sexe) {
                            $joueurs[] = $joueur;
                        }
                    }
                    break;
            }
            return $joueurs;
        }

    }

}
