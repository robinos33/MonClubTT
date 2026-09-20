<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_Correspondant' ) ) {

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

/**
 * Description of Correspondant
 *
 * @author robin
 */
class MonClubTT_Correspondant {

    private $nom;
    private $prenom;
    private $telephone;
    private $mail;

}

}
