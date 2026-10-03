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
                    // Sécurité : une licence sans aucun point (ni mensuel ni
                    // officiel) n'a rien à afficher dans les tableaux.
                    if ($joueur->getClassement()->getPointsMensuels() <= 0) {
                        continue;
                    }
                    // Licence non validée pour la saison en cours (joueur qui n'a
                    // pas repris) : l'appli FFTT ne l'affiche pas non plus.
                    if (!$joueur->isLicenceValidee()) {
                        continue;
                    }
                    // Exclusion manuelle (réglages du plugin) pour les cas
                    // particuliers que l'API ne permet pas de distinguer.
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

        /**
         * Joueurs proposés dans le jeu de pong, par ordre alphabétique.
         *
         * @return array Liste de ['nom', 'prenom', 'sex', 'pts', 'photo'].
         */
        public function getDonneesPong() {
            $donnees = array();
            foreach ($this->joueurs as $joueur) {
                $donnees[] = array(
                    'nom'    => $joueur->getNom(),
                    'prenom' => $joueur->getPrenom(),
                    'sex'    => $joueur->getSexe(),
                    'pts'    => (float) $joueur->getClassement()->getPointsOfficiels(),
                    'photo'  => $joueur->getPhotoUrl(),
                );
            }
            usort($donnees, function ($a, $b) {
                return strcasecmp(remove_accents($a['nom'] . ' ' . $a['prenom']), remove_accents($b['nom'] . ' ' . $b['prenom']));
            });
            return $donnees;
        }

        /**
         * Données du podium « Top Progression » (widget front et visuels
         * réseaux sociaux) : joueurs classés uniquement.
         *
         * @param string $sexe 'MF', 'M' ou 'F'
         * @return array Liste de ['nom', 'prenom', 'sex', 'cl', 'pts', 'mens', 'dm', 'da', 'photo'].
         */
        public function getDonneesTopProgression($sexe) {
            $donnees = array();
            foreach ($this->getJoueurs($sexe) as $joueur) {
                $classement = $joueur->getClassement();
                if (is_null($classement->getClassementOfficiel())) {
                    continue;
                }
                $donnees[] = array(
                    'nom'    => $joueur->getNom(),
                    'prenom' => $joueur->getPrenom(),
                    'sex'    => $joueur->getSexe(),
                    'cl'     => $classement->getClassementOfficiel(),
                    'pts'    => (float) $classement->getPointsOfficiels(),
                    'mens'   => (float) $classement->getPointsMensuels(),
                    'dm'     => (float) $classement->getProgressionMensuelle(),
                    'da'     => (float) $classement->getProgressionAnnuelle(),
                    'photo'  => $joueur->getPhotoUrl(),
                );
            }
            return $donnees;
        }

    }

}
