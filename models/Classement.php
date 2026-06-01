<?php
if ( ! defined( 'ABSPATH' ) ) exit;

/**
 * Description of Classement
 * Données issues de xml_licence_b.php (un seul appel API par club)
 *
 * @author robin
 */
class Classement {

    private $pointsMensuels;
    private $pointsOfficiels;
    private $progressionAnnuelle;
    private $progressionMensuelle;
    private $classementOfficiel;

    public function __construct($datas) {
        // xml_licence_b.php : 'pointm' = points mensuels, 'point' = points classement (officiel)
        $pointm  = (float) ($datas['pointm']  ?? 0);
        $apointm = (float) ($datas['apointm'] ?? 0);
        $initm   = (float) ($datas['initm']   ?? 0);
        $pointsOff = (float) ($datas['point'] ?? 0);

        $this->setPointsMensuels($pointm);
        $this->setPointsOfficiels($pointsOff);
        $this->setProgressionMensuelle(round($pointm - $apointm, 2));
        $this->setProgressionAnnuelle(round($pointm - $initm, 2));
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
