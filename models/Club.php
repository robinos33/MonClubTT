<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Club
 *
 * @author robin
 */
class Club {

    private $joueurs;
    private $equipes;
    private $salle;
    private $id;
    private $numero;
    private $site;

    public function __construct($numClub) {
        $api = AccesFFTTApi::getInstance();
        $club = $api->getClub($numClub);
        //$this->initSalle($club);
        $this->setSite($club['web']);
        $this->setNumero($club['numero']);
    }

    public function initSalle($club) {
        $this->setSalle(new Salle($club));
    }

    public function getJoueurs() {
        return $this->joueurs;
    }

    public function getEquipes() {
        return $this->equipes;
    }

    public function getSalle() {
        return $this->salle;
    }

    public function getId() {
        return $this->id;
    }

    public function getNumero() {
        return $this->numero;
    }

    public function getSite() {
        return $this->site;
    }

    public function setJoueurs($joueurs) {
        $this->joueurs = $joueurs;
    }

    public function setEquipes($equipes) {
        $this->equipes = $equipes;
    }

    public function setSalle($salle) {
        $this->salle = $salle;
    }

    public function setId($id) {
        $this->id = $id;
    }

    public function setNumero($numero) {
        $this->numero = $numero;
    }

    public function setSite($site) {
        $this->site = $site;
    }

}
