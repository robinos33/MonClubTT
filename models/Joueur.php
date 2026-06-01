<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists('joueur')) {

    class Joueur {

        private $nom;
        private $prenom;
        private $sexe;
        private $licence;
        private $club;
        private $classement;
        private $categorie;
        private $etranger;

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
         * @return Classement
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
            $this->classement = new Classement($donnees);
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

    }

}
