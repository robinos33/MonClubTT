<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_FeuilleMatch' ) ) {

/**
 * Règles d'accès à la feuille de match publique (action AJAX
 * monclubtt_feuille_match, ouverte aux visiteurs).
 *
 * Seules les rencontres affichées par les pages d'équipe du club peuvent être
 * demandées : la poule doit être celle d'une équipe du club et la rencontre
 * doit y figurer, score saisi. Un identifiant quelconque n'entraîne ainsi ni
 * appel à l'API FFTT ni écriture en cache.
 */
class MonClubTT_FeuilleMatch {

    /**
     * Format des paramètres reçus : identifiants numériques, is_retour 0 ou 1.
     *
     * @param mixed $iddiv
     * @param mixed $idpoule
     * @param mixed $rencId
     * @param mixed $isRetour
     * @return bool
     */
    public static function parametresValides($iddiv, $idpoule, $rencId, $isRetour) {
        foreach (array($iddiv, $idpoule, $rencId) as $id) {
            if (!is_string($id) || $id === '' || strlen($id) > 12 || !ctype_digit($id)) {
                return false;
            }
        }
        return $isRetour === '0' || $isRetour === '1';
    }

    /**
     * La poule est-elle celle d'une équipe du club ?
     *
     * @param array  $equipes Équipes du club (getEquipesByClub), avec iddiv et idpoule.
     * @param string $iddiv
     * @param string $idpoule
     * @return bool
     */
    public static function pouleDuClub(array $equipes, $iddiv, $idpoule) {
        foreach ($equipes as $equipe) {
            if (is_array($equipe)
                && isset($equipe['iddiv'], $equipe['idpoule'])
                && (string) $equipe['iddiv'] === (string) $iddiv
                && (string) $equipe['idpoule'] === (string) $idpoule) {
                return true;
            }
        }
        return false;
    }

    /**
     * La rencontre figure-t-elle dans la poule, avec un score saisi (seules
     * ces lignes sont dépliables sur la page d'équipe) ?
     *
     * @param array  $rencontres Rencontres de la poule (getPouleRencontres).
     * @param string $rencId
     * @param int    $isRetour
     * @return bool
     */
    public static function rencontreDeLaPoule(array $rencontres, $rencId, $isRetour) {
        foreach ($rencontres as $rencontre) {
            if (!is_array($rencontre) || !isset($rencontre['lien']) || !is_string($rencontre['lien'])) {
                continue;
            }
            $lien = array();
            parse_str($rencontre['lien'], $lien);
            if ((string) ($lien['renc_id'] ?? '') !== (string) $rencId
                || (int) ($lien['is_retour'] ?? 0) !== (int) $isRetour) {
                continue;
            }
            $scoreA = $rencontre['scorea'] ?? '';
            $scoreB = $rencontre['scoreb'] ?? '';
            return is_scalar($scoreA) && is_scalar($scoreB)
                && trim((string) $scoreA) !== '' && trim((string) $scoreB) !== '';
        }
        return false;
    }
}

}
