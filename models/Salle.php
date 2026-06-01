<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class Salle {

    private $nom;
    private $adresse1;
    private $adresse2;
    private $adresse3;
    private $codePostal;
    private $ville;
    private $long;
    private $lat;

    public function __construct($club) {
        $this->setNom($club['nomsalle']);
        $this->setAdresse1($club['adressesalle1']);
        $this->setAdresse2($club['adressesalle2']);
        $this->setAdresse3($club['adressesalle3']);
        $this->setCodePostal($club['codepsalle']);
        $this->setVille($club['villesalle']);
        $this->setLat($club['latitude']);
        $this->setLong($club['longitude']);
    }

    public function getNom() {
        return $this->nom;
    }

    public function getAdresse1() {
        return $this->adresse1;
    }

    public function getAdresse2() {
        return $this->adresse2;
    }

    public function getAdresse3() {
        return $this->adresse3;
    }

    public function getCodePostal() {
        return $this->codePostal;
    }

    public function getVille() {
        return $this->ville;
    }

    public function getLong() {
        return $this->long;
    }

    public function getLat() {
        return $this->lat;
    }

    public function setNom($nom) {
        $this->nom = $nom;
    }

    public function setAdresse1($adresse1) {
        $this->adresse1 = $adresse1;
    }

    public function setAdresse2($adresse2) {
        $this->adresse2 = $adresse2;
    }

    public function setAdresse3($adresse3) {
        $this->adresse3 = $adresse3;
    }

    public function setCodePostal($codePostal) {
        $this->codePostal = $codePostal;
    }

    public function setVille($ville) {
        $this->ville = $ville;
    }

    public function setLong($long) {
        $this->long = $long;
    }

    public function setLat($lat) {
        $this->lat = $lat;
    }

}
