<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_PongModeration' ) ) {

/**
 * Service de domaine (sans état) : modération du jeu de pong.
 *
 * - Termes interdits dans le prénom libre des visiteurs hors club, comparés
 *   sans tenir compte de la casse, des accents, des espaces et de la
 *   ponctuation, des lettres répétées ni des chiffres mis pour des lettres :
 *   « C0n  nnard » est bloqué par « connard ».
 * - Quota de parties enregistrées par adresse IP sur une fenêtre de temps.
 *
 * Aucune dépendance à WordPress, pour rester testable unitairement.
 */
class MonClubTT_PongModeration {

    /** Termes conservés au plus. */
    const TERMES_MAX = 500;

    /** Longueur maximale d'un terme saisi. */
    const TERME_LONGUEUR = 50;

    /** Parties enregistrées par adresse IP et par fenêtre, par défaut. */
    const QUOTA_DEFAUT = 30;

    /** Fenêtre du quota (s). */
    const FENETRE = 3600;

    /** Lettres accentuées et ligatures, en minuscules. */
    const ACCENTS = array(
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    );

    /** Chiffres et symboles écrits à la place d'une lettre. */
    const SUBSTITUTIONS = array(
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a', '5' => 's', '7' => 't', '@' => 'a', '$' => 's', '€' => 'e',
    );

    /**
     * Forme comparable d'un texte : minuscules sans accents, chiffres et
     * symboles ramenés à la lettre qu'ils imitent, rien d'autre que a-z, et
     * chaque lettre répétée réduite à une seule.
     *
     * @param string $texte
     * @return string
     */
    public static function normaliser($texte) {
        $texte = mb_strtolower((string) $texte, 'UTF-8');
        $texte = strtr($texte, self::ACCENTS);
        $texte = strtr($texte, self::SUBSTITUTIONS);
        $texte = (string) preg_replace('/[^a-z]/', '', $texte);
        return (string) preg_replace('/(.)\1+/', '$1', $texte);
    }

    /**
     * Liste de termes saisie dans l'admin : un par ligne (ou séparés par des
     * virgules), espaces superflus retirés, doublons écartés (à forme
     * comparable égale, le premier est gardé), termes vides une fois
     * normalisés écartés.
     *
     * @param string|array<mixed> $saisie
     * @return list<string>
     */
    public static function termes($saisie) {
        $lignes = is_array($saisie) ? $saisie : preg_split('/[\r\n,]+/', (string) $saisie);
        $termes = array();
        foreach ((array) $lignes as $ligne) {
            if (!is_scalar($ligne)) {
                continue;
            }
            $terme = trim((string) preg_replace('/\s+/u', ' ', (string) $ligne));
            $terme = mb_substr($terme, 0, self::TERME_LONGUEUR, 'UTF-8');
            $cle   = self::normaliser($terme);
            if ($cle !== '' && !isset($termes[$cle])) {
                $termes[$cle] = $terme;
            }
        }
        $termes = array_values($termes);
        sort($termes, SORT_STRING | SORT_FLAG_CASE);
        return array_slice($termes, 0, self::TERMES_MAX);
    }

    /**
     * Le nom contient-il un des termes, n'importe où ?
     *
     * @param string        $nom
     * @param array<string> $termes
     * @return bool
     */
    public static function interdit($nom, array $termes) {
        $nom = self::normaliser($nom);
        if ($nom === '') {
            return false;
        }
        foreach ($termes as $terme) {
            $terme = self::normaliser($terme);
            if ($terme !== '' && strpos($nom, $terme) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Retire les entrées (scores, défi, matchs partagés) dont le joueur est un
     * visiteur au nom interdit. Une entrée sans indication « visiteur » (match
     * enregistré avant qu'elle n'existe) est vérifiée aussi : au pire, un
     * vieux lien de partage d'un licencié disparaît.
     *
     * @param array<array-key, array<string, mixed>> $entrees
     * @param array<string>                          $termes
     * @return array{entrees: array<array-key, array<string, mixed>>, retirees: list<array-key>}
     */
    public static function retirer(array $entrees, array $termes) {
        $liste    = array_values($entrees) === $entrees;
        $retirees = array();
        foreach ($entrees as $cle => $entree) {
            if (!is_array($entree)) {
                continue;
            }
            $visiteur = !array_key_exists('visiteur', $entree) || !empty($entree['visiteur']);
            if ($visiteur && self::interdit((string) ($entree['joueur'] ?? ''), $termes)) {
                $retirees[] = $cle;
                unset($entrees[$cle]);
            }
        }
        return array('entrees' => $liste ? array_values($entrees) : $entrees, 'retirees' => $retirees);
    }

    /**
     * Quota de parties sur une fenêtre fixe, ouverte par la première partie.
     * Une partie acceptée est comptée.
     *
     * @param array{debut?: int, n?: int}|mixed $etat        État précédent (null au premier passage).
     * @param int                               $maintenant  Horodatage courant.
     * @param int                               $quota       Parties autorisées par fenêtre ; 0 = sans limite.
     * @param int                               $fenetre     Durée de la fenêtre (s).
     * @return array{autorise: bool, etat: array{debut: int, n: int}, reprise: int}
     *         reprise = horodatage de fin de la fenêtre en cours.
     */
    public static function quota($etat, $maintenant, $quota, $fenetre = self::FENETRE) {
        $debut = is_array($etat) ? (int) ($etat['debut'] ?? 0) : 0;
        $n     = is_array($etat) ? (int) ($etat['n'] ?? 0) : 0;
        if ($debut <= 0 || $maintenant - $debut >= $fenetre) {
            $debut = $maintenant;
            $n     = 0;
        }
        $autorise = $quota <= 0 || $n < $quota;
        if ($autorise) {
            $n++;
        }
        return array('autorise' => $autorise, 'etat' => array('debut' => $debut, 'n' => $n), 'reprise' => $debut + $fenetre);
    }
}

}
