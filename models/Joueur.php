<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('MonClubTT_Joueur')) {

    class MonClubTT_Joueur {

        private $nom;
        private $prenom;
        private $sexe;
        private $licence;
        private $club;
        private $classement;
        private $categorie;
        private $etranger;
        private $dateValidation;

        /**
         * Initialisation du joueur depuis les données xml_licence_b.php
         * @param array $donnees Données brutes retournées par l'API
         */
        public function __construct($donnees) {
            $this->setClassement($donnees);
            $this->setClub($donnees['numclub'] ?? '');
            $this->setNom($donnees['nom'] ?? '');
            $this->setPrenom($donnees['prenom'] ?? '');
            $this->setSexe($donnees['sexe'] ?? '');
            $this->setLicence($donnees['licence'] ?? '');
            $this->setCategorie($donnees['cat'] ?? '');
            $this->setEtranger($donnees['natio'] ?? 'F');
            $this->setDateValidation($donnees['validation'] ?? ($donnees['datevalidation'] ?? ''));
        }

        public function getNom() {
            return $this->nom;
        }

        public function getPrenom() {
            return $this->prenom;
        }

        public function getSexe() {
            return $this->sexe;
        }

        public function getLicence() {
            return $this->licence;
        }

        public function getClub() {
            return $this->club;
        }

        /**
         * @return MonClubTT_Classement
         */
        public function getClassement() {
            return $this->classement;
        }

        public function setNom($nom) {
            $this->nom = $nom;
        }

        public function setPrenom($prenom) {
            $this->prenom = $prenom;
        }

        public function setSexe($sexe) {
            $this->sexe = $sexe;
        }

        public function setLicence($licence) {
            $this->licence = $licence;
        }

        public function setClub($numClub) {
            $this->club = $numClub;
        }

        public function setClassement($donnees) {
            $this->classement = new MonClubTT_Classement($donnees);
        }

        public function getCategorie() {
            return $this->categorie;
        }

        public function setCategorie($categorie) {
            $this->categorie = $categorie;
        }

        public function getEtranger() {
            return $this->etranger;
        }

        public function setEtranger($natio) {
            $this->etranger = ($natio === 'E');
        }

        public function isEtranger() {
            return $this->etranger;
        }

        /**
         * Horodatage de validation de la licence, ou null si l'API ne fournit
         * pas la date (champ absent ou format inattendu).
         *
         * @return int|null
         */
        public function getDateValidation() {
            return $this->dateValidation;
        }

        public function setDateValidation($valeur) {
            // Une installation portant deux copies du plugin peut charger le
            // Utils.php d'une version anterieure, ou le helper n'existe pas.
            $this->dateValidation = function_exists('monclubtt_parse_date_fftt')
                ? monclubtt_parse_date_fftt($valeur)
                : null;
        }

        /**
         * La licence a-t-elle ete renouvelee pour la saison en cours ?
         *
         * L'API rattache encore au club les joueurs qui n'ont pas resigne : leur
         * fiche porte alors la date de validation de la saison precedente. Une
         * date manquante renvoie null plutot que false, pour que l'appelant
         * distingue « pas renouvelee » de « information indisponible » et evite
         * d'exclure tout le club si le champ disparaissait de l'API.
         *
         * @param int $debutSaison Horodatage du 1er juillet ouvrant la saison.
         * @return bool|null
         */
        public function isLicenceRenouvelee($debutSaison) {
            if (null === $this->dateValidation) {
                return null;
            }

            return $this->dateValidation >= (int) $debutSaison;
        }

    }

}
