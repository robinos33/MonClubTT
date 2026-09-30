<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_Classement' ) ) {

/**
 * Description of Classement
 * Données issues de xml_licence_b.php (un seul appel API par club)
 *
 * @author robin
 */
class MonClubTT_Classement {

    private $pointsMensuels;
    private $pointsOfficiels;
    private $progressionAnnuelle;
    private $progressionMensuelle;
    private $classementOfficiel;

    public function __construct($datas) {
        // xml_licence_b.php : 'pointm' = points mensuels, 'point' = points classement (officiel)
        // Un élément XML vide arrive en tableau : lu comme absent (null).
        $pointsOff = $this->lirePoints($datas, 'point');
        $apointm   = $this->lirePoints($datas, 'apointm');
        $initm     = $this->lirePoints($datas, 'initm');
        // Pas encore de points mensuels (arrivée au club, nouveau licencié) :
        // on retombe sur les points officiels.
        $pointm    = $this->lirePoints($datas, 'pointm') ?? $pointsOff ?? 0.0;

        $this->setPointsMensuels($pointm);
        $this->setPointsOfficiels($pointsOff ?? 0.0);
        // Sans base de comparaison, pas de progression (sinon « +718 » fictif).
        $this->setProgressionMensuelle(is_null($apointm) ? 0 : round($pointm - $apointm, 2));
        $this->setProgressionAnnuelle(is_null($initm) ? 0 : round($pointm - $initm, 2));
        $this->setClassementOfficiel($this->calculerClassementFromPoints($pointsOff));
    }

    public function getPointsMensuels() {
        return $this->pointsMensuels;
    }

    public function setPointsMensuels($pointsMensuels) {
        $this->pointsMensuels = round($pointsMensuels, 2);
    }

    public function getPointsOfficiels() {
        return $this->pointsOfficiels;
    }

    public function setPointsOfficiels($pointsOfficiels) {
        $this->pointsOfficiels = round($pointsOfficiels, 2);
    }

    public function getProgressionAnnuelle() {
        return $this->progressionAnnuelle;
    }

    public function setProgressionAnnuelle($progressionAnnuelle) {
        $this->progressionAnnuelle = round($progressionAnnuelle, 2);
    }

    public function getProgressionMensuelle() {
        return $this->progressionMensuelle;
    }

    public function setProgressionMensuelle($progressionMensuelle) {
        $this->progressionMensuelle = round($progressionMensuelle, 2);
    }

    public function getClassementOfficiel() {
        return $this->classementOfficiel;
    }

    public function setClassementOfficiel($classementOfficiel) {
        $this->classementOfficiel = $classementOfficiel;
    }

    private function lirePoints($datas, $cle) {
        $valeur = $datas[$cle] ?? null;
        if (!is_scalar($valeur) || trim((string) $valeur) === '') {
            return null;
        }
        return (float) $valeur;
    }

    /**
     * Calcule le classement à partir des points officiels
     * - 4 chiffres : prendre les 2 premiers (ex: 1232 => 12)
     * - 3 chiffres : prendre le 1er chiffre (ex: 879 => 8)
     */
    private function calculerClassementFromPoints($points) {
        if (empty($points)) {
            return '';
        }

        $pointsStr = (string) intval($points);
        $nbChiffres = strlen($pointsStr);

        if ($nbChiffres >= 4) {
            return intval(substr($pointsStr, 0, 2));
        } elseif ($nbChiffres === 3) {
            return intval(substr($pointsStr, 0, 1));
        } else {
            return intval($points);
        }
    }

}

}
