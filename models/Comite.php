<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_Comite' ) ) {

/**
 * Règles du comité directeur du club, sans WordPress : nom affiché, initiales,
 * libellé des commissions et répartition des membres entre le bureau et le
 * reste du comité.
 *
 * Un membre : [ 'id', 'prenom', 'nom', 'ordre', 'photoId', 'photo' (URL),
 * 'posteBureau' (vide = pas au bureau), 'commissions' => [ commission, … ] ].
 * Une commission : [ 'id', 'nom', 'slug', 'ordre' ].
 */
class MonClubTT_Comite {

    /** « Prénom Nom », espaces superflus retirés. */
    public static function nomComplet($prenom, $nom)
    {
        return trim(preg_replace('/\s+/u', ' ', trim((string) $prenom) . ' ' . trim((string) $nom)));
    }

    /** Initiales (2 lettres au plus) pour l'avatar d'un membre sans photo. */
    public static function initiales($prenom, $nom)
    {
        $initiales = '';
        foreach (array($prenom, $nom) as $partie) {
            $partie = trim((string) $partie);
            if ($partie !== '') {
                $initiales .= mb_strtoupper(mb_substr($partie, 0, 1));
            }
        }
        return $initiales;
    }

    /** Poste au bureau saisi : texte simple, espaces superflus retirés. */
    public static function poste($saisie)
    {
        return trim(preg_replace('/\s+/u', ' ', strip_tags((string) $saisie)));
    }

    /**
     * Pastille des commissions d'un membre : « Commission Communication / Équipes ».
     * Un nom déjà préfixé (« Commission sportive ») n'est pas répété.
     *
     * @param string[] $noms
     */
    public static function libelleCommissions(array $noms)
    {
        $noms = array_values(array_filter(array_map(function ($nom) {
            return trim(preg_replace('/^commissions?\s+/iu', '', trim((string) $nom)));
        }, $noms), 'strlen'));
        return $noms ? 'Commission ' . implode(' / ', $noms) : '';
    }

    /** Tri des membres : ordre choisi, puis nom, puis prénom. */
    public static function trierMembres(array $membres)
    {
        usort($membres, function ($a, $b) {
            return array((int) $a['ordre'], self::cleTri($a['nom']), self::cleTri($a['prenom']))
                <=> array((int) $b['ordre'], self::cleTri($b['nom']), self::cleTri($b['prenom']));
        });
        return $membres;
    }

    /** Tri des commissions : ordre choisi, puis nom. */
    public static function trierCommissions(array $commissions)
    {
        usort($commissions, function ($a, $b) {
            return array((int) $a['ordre'], self::cleTri($a['nom'])) <=> array((int) $b['ordre'], self::cleTri($b['nom']));
        });
        return $commissions;
    }

    /**
     * Membres répartis entre le bureau (ceux qui ont un poste au bureau) et le
     * reste du comité, chacun trié. Les commissions de chaque membre sont
     * triées et résumées dans 'libelleCommissions'.
     *
     * @param array    $membres
     * @param string[] $filtre slugs de commissions : ne garder que leurs membres (vide = tous).
     * @return array [ 'bureau' => membre[], 'comite' => membre[] ]
     */
    public static function repartir(array $membres, array $filtre = array())
    {
        $filtre = array_values(array_filter(array_map(function ($s) {
            return strtolower(trim((string) $s));
        }, $filtre), 'strlen'));

        $parties = array('bureau' => array(), 'comite' => array());
        foreach (self::trierMembres($membres) as $membre) {
            $commissions = self::trierCommissions((array) ($membre['commissions'] ?? array()));
            if ($filtre && !array_intersect($filtre, array_map('strtolower', array_column($commissions, 'slug')))) {
                continue;
            }
            $membre['commissions']        = $commissions;
            $membre['libelleCommissions'] = self::libelleCommissions(array_column($commissions, 'nom'));
            $membre['posteBureau']        = self::poste($membre['posteBureau'] ?? '');
            $parties[$membre['posteBureau'] !== '' ? 'bureau' : 'comite'][] = $membre;
        }
        return $parties;
    }

    /** Clé de tri insensible à la casse et aux accents courants. */
    private static function cleTri($texte)
    {
        $texte = mb_strtolower(trim((string) $texte));
        return strtr($texte, array(
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'á' => 'a', 'ç' => 'c',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'í' => 'i', 'ô' => 'o', 'ö' => 'o', 'ó' => 'o',
            'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ú' => 'u', 'ÿ' => 'y', 'œ' => 'oe', 'æ' => 'ae',
        ));
    }
}

}
