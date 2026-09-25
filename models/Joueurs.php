<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('MonClubTT_Joueurs')) {

    /**
     * Description of joueurs
     *
     * @author robin
     */
    class MonClubTT_Joueurs {

        private $_api;
        /**
         * Tableau contenant les joueurs du club
         * @var array MonClubTT_Joueur
         */
        private $joueurs = [];

        public function __construct() {
            $this->_api = MonClubTT_AccesFFTTApi::getInstance();
            $this->loadJoueurs();
        }

        private function loadJoueurs() {
            $club = MonClubTT_ParametresPlugin::getNumClub();
            $cacheKey = $this->_api->buildCacheKeyPublic('joueurs_club', array('numclu' => $club));
            $lifeTime = $this->_api->computeHalfDayTtlPublic();

            $joueursData = $this->_api->getCachedDataPublic($cacheKey, $lifeTime, function() use ($club) {
                return $this->_api->getLicencesByClubComplet($club);
            });

            if (!is_array($joueursData)) {
                return;
            }

            $licencesExclues = MonClubTT_ParametresPlugin::getLicencesExclues();

            foreach ($joueursData as $joueurData) {
                if (!empty($joueurData)) {
                    $joueur = new MonClubTT_Joueur($joueurData);
                    // Exclure les joueurs sans points mensuels : ni comptabilisés
                    // à la synchronisation, ni affichés côté front/admin.
                    if ($joueur->getClassement()->getPointsMensuels() <= 0) {
                        continue;
                    }
                    // Exclusion manuelle (réglages du plugin) : la FFTT ne fournit
                    // aucun champ fiable pour détecter qu'un licencié a quitté le
                    // club, son API continue de le rattacher au club.
                    if (in_array((string) $joueur->getLicence(), $licencesExclues, true)) {
                        continue;
                    }
                    // Photo éventuellement associée manuellement dans l'admin ;
                    // chaîne vide si aucune, le rendu retombe alors sur l'avatar dessiné.
                    $joueur->setPhotoUrl(monclubtt_get_joueur_photo_url($joueur->getLicence(), 'medium'));
                    $this->joueurs[] = $joueur;
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
