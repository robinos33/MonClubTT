<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_PongDefis' ) ) {

/**
 * Service de domaine (sans état) : défi du jour du jeu de pong.
 *
 * Chaque jour, un même adversaire pour tout le monde, tiré au sort à partir
 * de la date. Une seule tentative comptée par joueur et par jour, défaites
 * comprises. Classement : plus grand écart de points, puis match le plus
 * court, puis premier arrivé. Aucune dépendance à WordPress, pour rester
 * testable unitairement.
 */
class MonClubTT_PongDefis {

    /** Niveau imposé : le même pour tous, sinon les scores ne se comparent pas. */
    const NIVEAU = 'normal';

    /** Jours conservés (classement d'hier compris). */
    const JOURS = 7;

    /** Entrées conservées par jour. */
    const TAILLE = 200;

    /** Lignes du classement envoyées au navigateur. */
    const AFFICHES = 20;

    /** Durée minimale plausible d'un point (s) : lancer, aller, retour. */
    const SECONDES_PAR_POINT = 1.2;

    /** Durée maximale acceptée d'un match (s). */
    const DUREE_MAX = 3600;

    /**
     * Adversaire du jour parmi des candidats, stable pour une date donnée.
     * Celui d'hier est écarté quand il y a le choix.
     *
     * @param string        $date       Date du jour (AAAA-MM-JJ).
     * @param array<string> $candidats  Clés des adversaires possibles.
     * @param string|null   $hier       Clé de l'adversaire d'hier.
     * @return string|null Clé tirée, null sans candidat.
     */
    public static function tirage($date, array $candidats, $hier = null) {
        $candidats = array_values(array_unique(array_map('strval', $candidats)));
        sort($candidats, SORT_STRING);
        if (count($candidats) > 1 && $hier !== null) {
            $candidats = array_values(array_diff($candidats, array((string) $hier)));
        }
        if (!$candidats) {
            return null;
        }
        return $candidats[crc32('monclubtt-defi-' . $date) % count($candidats)];
    }

    /**
     * Durée de match plausible pour ce score.
     *
     * @param int   $pj    Points du joueur.
     * @param int   $pa    Points de l'adversaire.
     * @param mixed $duree Durée déclarée (s).
     * @return bool
     */
    public static function dureeValide($pj, $pa, $duree) {
        if (!is_numeric($duree)) {
            return false;
        }
        $duree = (float) $duree;
        return $duree >= ((int) $pj + (int) $pa) * self::SECONDES_PAR_POINT && $duree <= self::DUREE_MAX;
    }

    /**
     * Ajoute la tentative d'un joueur au classement du jour, si c'est sa
     * première (même clé = même joueur).
     *
     * @param array $entrees Entrées du jour.
     * @param array $entree  ['cle', 'joueur', 'visiteur', 'pj', 'pa', 'duree', 'date'].
     * @return array{entrees: array, rang: int|null, deja: bool} Rang 1..n de la
     *         nouvelle entrée, null si elle n'entre pas (déjà jouée ou hors des TAILLE).
     */
    public static function ajouter(array $entrees, array $entree) {
        foreach ($entrees as $e) {
            if (is_array($e) && isset($e['cle']) && $e['cle'] === $entree['cle']) {
                return array('entrees' => self::classer($entrees), 'rang' => null, 'deja' => true);
            }
        }
        $entrees[] = $entree;
        $entrees   = self::classer($entrees);
        $rang      = null;
        foreach ($entrees as $i => $e) {
            if ($e['cle'] === $entree['cle']) {
                $rang = $i + 1;
                break;
            }
        }
        return array('entrees' => $entrees, 'rang' => $rang, 'deja' => false);
    }

    /**
     * Classe les entrées valides et garde les TAILLE premières.
     *
     * @param array $entrees
     * @return array
     */
    public static function classer(array $entrees) {
        $entrees = array_values(array_filter($entrees, function ($e) {
            if (!is_array($e) || !isset($e['cle'], $e['joueur'], $e['pj'], $e['pa'], $e['duree'], $e['date'])) {
                return false;
            }
            $score = $e['pj'] > $e['pa']
                ? MonClubTT_PongScores::scoreValide($e['pj'], $e['pa'])
                : MonClubTT_PongScores::scoreValide($e['pa'], $e['pj']);
            return $score && self::dureeValide($e['pj'], $e['pa'], $e['duree']);
        }));
        usort($entrees, function ($a, $b) {
            return array($b['pj'] - $b['pa'], $a['duree'], $a['date'])
                <=> array($a['pj'] - $a['pa'], $b['duree'], $b['date']);
        });
        return array_slice($entrees, 0, self::TAILLE);
    }

    /**
     * Garde les jours récents (JOURS derniers, aujourd'hui compris).
     *
     * @param array  $jours       Jours indexés par date AAAA-MM-JJ.
     * @param string $aujourdhui  Date du jour.
     * @return array
     */
    public static function purger(array $jours, $aujourdhui) {
        $limite = (new DateTimeImmutable($aujourdhui))->modify('-' . (self::JOURS - 1) . ' days')->format('Y-m-d');
        return array_filter($jours, function ($date) use ($limite, $aujourdhui) {
            return is_string($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) && $date >= $limite && $date <= $aujourdhui;
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Version publique d'une entrée (sans clé d'identification).
     *
     * @param array $entree
     * @return array{joueur: string, visiteur: bool, pj: int, pa: int, duree: float}
     */
    public static function publique(array $entree) {
        return array(
            'joueur'   => (string) $entree['joueur'],
            'visiteur' => !empty($entree['visiteur']),
            'pj'       => (int) $entree['pj'],
            'pa'       => (int) $entree['pa'],
            'duree'    => round((float) $entree['duree'], 1),
        );
    }
}

}
