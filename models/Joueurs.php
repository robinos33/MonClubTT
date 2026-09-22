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
        /**
         * Joueurs ecartes faute de licence renouvelee pour la saison en cours.
         * @var array MonClubTT_Joueur
         */
        private $nonRenouveles = [];

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

            $debutSaison = monclubtt_saison_debut_timestamp();
            $candidats   = [];
            $dateConnue  = false;

            foreach ($joueursData as $joueurData) {
                if (!empty($joueurData)) {
                    $joueur = new MonClubTT_Joueur($joueurData);
                    // Exclure les joueurs sans points mensuels : ni comptabilisés
                    // à la synchronisation, ni affichés côté front/admin.
                    if ($joueur->getClassement()->getPointsMensuels() <= 0) {
                        continue;
                    }
                    if (null !== $joueur->getDateValidation()) {
                        $dateConnue = true;
                    }
                    $candidats[] = $joueur;
                }
            }

            // L'API rattache encore au club les joueurs qui n'ont pas resigné :
            // leur licence porte une date de validation antérieure au 1er juillet
            // de la saison en cours. On ne filtre que si l'API a effectivement
            // fourni des dates, sinon un changement de format côté FFTT viderait
            // la page au lieu de la laisser inchangée.
            if (!$dateConnue) {
                $this->joueurs = $candidats;
                return;
            }

            foreach ($candidats as $joueur) {
                if (false === $joueur->isLicenceRenouvelee($debutSaison)) {
                    $this->nonRenouveles[] = $joueur;
                    continue;
                }
                $this->joueurs[] = $joueur;
            }

            // Second garde-fou : un club entier sans un seul licencié n'existe
            // pas. Si le filtre écarte tout le monde, c'est que le champ de
            // l'API ne porte pas la date attendue — on rend la liste complète
            // plutôt qu'une page vide.
            if (empty($this->joueurs) && !empty($this->nonRenouveles)) {
                $this->joueurs       = $candidats;
                $this->nonRenouveles = [];
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

        /**
         * Joueurs écartés de la liste faute de licence renouvelée.
         * @return array MonClubTT_Joueur
         */
        public function getNonRenouveles() {
            return $this->nonRenouveles;
        }

    }

}
