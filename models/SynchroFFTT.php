<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_SynchroFFTT' ) ) {

/**
 * Créneaux de la synchronisation automatique des données FFTT : le dimanche
 * soir, puis le lundi matin pour les résultats saisis tard.
 */
class MonClubTT_SynchroFFTT {

    /** Créneaux : clé => [jour ISO (1 = lundi, 7 = dimanche), heure, minute], heure du site. */
    const CRENEAUX = array(
        'dimanche-soir' => array(7, 20, 0),
        'lundi-matin'   => array(1, 6, 0),
    );

    /**
     * Prochain passage d'un créneau strictement après $maintenant, dans le
     * fuseau de $maintenant. Les jours s'ajoutent en heure locale : un
     * changement d'heure ne décale pas le créneau.
     */
    public static function prochainPassage($jourIso, $heure, $minute, DateTimeImmutable $maintenant) {
        $ecart   = ((int) $jourIso - (int) $maintenant->format('N') + 7) % 7;
        $passage = $maintenant->setTime((int) $heure, (int) $minute)->modify('+' . $ecart . ' days');
        if ($passage <= $maintenant) {
            $passage = $passage->modify('+7 days');
        }
        return $passage;
    }
}

}
