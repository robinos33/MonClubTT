<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_PongScores' ) ) {

/**
 * Service de domaine (sans état) : tableau des meilleurs scores du jeu de pong.
 *
 * Une entrée est une victoire d'un licencié du club : contre qui, à quel
 * niveau et sur quel score. Classement : niveau Expert d'abord, puis le plus
 * grand écart, puis la victoire la plus ancienne (premier arrivé, premier
 * servi). Aucune dépendance à WordPress, pour rester testable unitairement.
 */
class MonClubTT_PongScores {

    /** Niveaux du jeu, du plus facile au plus dur (rang = poids au classement). */
    const NIVEAUX = array('normal', 'mondial');

    /** Nombre d'entrées affichées et conservées. */
    const TAILLE = 10;

    /**
     * Score final d'une manche valide au ping : le gagnant atteint 11 avec
     * deux points d'écart ; au-delà de 10-10, l'écart est exactement de deux.
     *
     * @param int $gagnant Points du vainqueur.
     * @param int $perdant Points du vaincu.
     * @return bool
     */
    public static function scoreValide($gagnant, $perdant) {
        $gagnant = (int) $gagnant;
        $perdant = (int) $perdant;
        if ($perdant < 0 || $gagnant < 11 || $gagnant - $perdant < 2) {
            return false;
        }
        return $perdant >= 10 ? $gagnant - $perdant === 2 : $gagnant === 11;
    }

    /**
     * Ajoute une victoire au tableau et le reclasse.
     *
     * @param array $tableau Entrées existantes.
     * @param array $entree  ['joueur', 'adversaire', 'niveau', 'pj', 'pa', 'date'].
     * @return array{tableau: array, rang: int|null} Tableau classé (TAILLE entrées
     *                                               au plus), rang 1..TAILLE de la
     *                                               nouvelle entrée ou null si hors tableau.
     */
    public static function ajouter(array $tableau, array $entree) {
        $entree['id'] = isset($entree['id']) ? $entree['id'] : uniqid('', true);
        $tableau[]    = $entree;
        $tableau      = self::classer($tableau);
        $rang = null;
        foreach ($tableau as $i => $ligne) {
            if ($ligne['id'] === $entree['id']) {
                $rang = $i + 1;
                break;
            }
        }
        return array('tableau' => $tableau, 'rang' => $rang);
    }

    /**
     * Classe les entrées valides et garde les TAILLE premières.
     *
     * @param array $tableau
     * @return array
     */
    public static function classer(array $tableau) {
        $tableau = array_values(array_filter($tableau, function ($e) {
            return is_array($e) && isset($e['joueur'], $e['adversaire'], $e['niveau'], $e['pj'], $e['pa'], $e['date'])
                && in_array($e['niveau'], self::NIVEAUX, true)
                && self::scoreValide($e['pj'], $e['pa']);
        }));
        usort($tableau, function ($a, $b) {
            return array(array_search($b['niveau'], self::NIVEAUX, true), $b['pj'] - $b['pa'], $a['date'])
                <=> array(array_search($a['niveau'], self::NIVEAUX, true), $a['pj'] - $a['pa'], $b['date']);
        });
        return array_slice($tableau, 0, self::TAILLE);
    }

    /**
     * Version publique d'une entrée (sans identifiant interne).
     *
     * @param array $entree
     * @return array{joueur: string, adversaire: string, niveau: string, pj: int, pa: int, visiteur: bool}
     */
    public static function publique(array $entree) {
        return array(
            'joueur'     => (string) $entree['joueur'],
            'visiteur'   => !empty($entree['visiteur']),
            'adversaire' => (string) $entree['adversaire'],
            'niveau'     => (string) $entree['niveau'],
            'pj'         => (int) $entree['pj'],
            'pa'         => (int) $entree['pa'],
        );
    }
}

}
