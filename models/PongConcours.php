<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_PongConcours' ) ) {

/**
 * Service de domaine (sans état) : concours du mois du jeu de pong.
 *
 * Chaque licencié a une cote pong, qui démarre à ses points FFTT officiels
 * à son premier match classé du mois. Chaque adversaire, à chaque niveau,
 * a aussi la sienne : elle démarre à ses points (bonus en Expert) et évolue
 * avec les résultats, comme celle d'un joueur. Un adversaire que tout le
 * monde bat voit donc sa cote baisser : choisir le plus fort du club ne
 * rapporte que ce que l'IA vaut réellement. Les cotes des adversaires sont
 * gardées d'un mois à l'autre (il leur faut beaucoup de matchs pour être
 * justes) ; celles des joueurs repartent de leurs points chaque mois.
 *
 * Après chaque match, les deux cotes bougent selon le barème FFTT des points
 * classement (écart de cotes, victoire normale ou anormale). Le classement
 * suit la progression depuis le premier match du mois.
 *
 * Un match est déclaré au serveur dès son lancement : s'il n'est jamais
 * terminé (onglet fermé quand on est mené), il compte comme une défaite.
 * Aucune dépendance à WordPress, pour rester testable unitairement.
 */
class MonClubTT_PongConcours {

    /**
     * Barème FFTT : écart maximal (inclus), victoire normale, défaite normale,
     * victoire anormale, défaite anormale. Normal = le mieux coté l'emporte.
     */
    const BAREME = array(
        array(24, 6, -5, 6, -5),
        array(49, 5.5, -4.5, 7, -6),
        array(99, 5, -4, 8, -7),
        array(149, 4, -3, 10, -8),
        array(199, 3, -2, 13, -10),
        array(299, 2, -1, 17, -12.5),
        array(399, 1, -0.5, 22, -16),
        array(499, 0.5, 0, 28, -20),
        array(PHP_INT_MAX, 0, 0, 40, -29),
    );

    /** Cote de départ d'un adversaire du top 10 mondial (niveau Normal). */
    const COTE_MONDE = 3000;

    /** Bonus de cote de départ d'un adversaire au niveau Expert. */
    const BONUS_EXPERT = 300;

    /** Cote de départ d'un licencié sans points. */
    const COTE_MIN = 500;

    /** Matchs classés comptés par joueur et par jour. */
    const MATCHS_PAR_JOUR = 5;

    /** Matchs joués pour apparaître au classement. */
    const MATCHS_MIN = 5;

    /** Délai (s) au-delà duquel un match lancé et jamais terminé est perdu. */
    const DELAI_ABANDON = 1800;

    /** Durée minimale plausible d'un point (s), comme au défi du jour. */
    const SECONDES_PAR_POINT = 1.2;

    /** Lignes du classement envoyées au navigateur. */
    const AFFICHES = 10;

    /**
     * Période du concours d'une date : « 2026-11 ».
     *
     * @param int $annee
     * @param int $mois 1-12
     * @return string
     */
    public static function periode($annee, $mois) {
        return sprintf('%04d-%02d', (int) $annee, (int) $mois);
    }

    /**
     * Variation de cote d'un joueur après un match, selon le barème FFTT.
     *
     * @param float $cote     Cote du joueur.
     * @param float $coteAdv  Cote de son adversaire.
     * @param bool  $victoire
     * @return float
     */
    public static function variation($cote, $coteAdv, $victoire) {
        $ecart = abs((float) $cote - (float) $coteAdv);
        $favori = (float) $cote >= (float) $coteAdv;
        foreach (self::BAREME as $ligne) {
            if ($ecart <= $ligne[0]) {
                if ($victoire) {
                    return (float) ($favori ? $ligne[1] : $ligne[3]);
                }
                return (float) ($favori ? $ligne[4] : $ligne[2]);
            }
        }
        return 0.0;
    }

    /**
     * Cote de départ d'un adversaire.
     *
     * @param string     $type   'club' ou 'monde'.
     * @param float|null $points Points FFTT (club).
     * @param string     $niveau 'normal' ou 'mondial' (Expert).
     * @return float
     */
    public static function coteDepartAdversaire($type, $points, $niveau) {
        $base = $type === 'monde' ? self::COTE_MONDE : max(self::COTE_MIN, (float) $points);
        return (float) ($base + ($niveau === 'mondial' ? self::BONUS_EXPERT : 0));
    }

    /**
     * État vide d'un mois. Les cotes des adversaires passées sont gardées.
     *
     * @param string $periode     AAAA-MM.
     * @param array  $adversaires Cotes des adversaires à reprendre.
     * @return array
     */
    public static function vide($periode, array $adversaires = array()) {
        return array('periode' => (string) $periode, 'joueurs' => array(), 'adversaires' => $adversaires, 'en_cours' => array());
    }

    /**
     * État du mois en cours, normalisé. Changement de mois : les joueurs et
     * les matchs en cours repartent de zéro, les cotes des adversaires sont
     * gardées, et le podium du mois clos est renvoyé pour le palmarès.
     *
     * @param mixed  $etat    État enregistré.
     * @param string $periode Mois en cours (AAAA-MM).
     * @return array{etat: array, close: array|null} close = ['periode', 'podium'].
     */
    public static function ouvrir($etat, $periode) {
        if (!is_array($etat) || !isset($etat['periode'])) {
            return array('etat' => self::vide($periode), 'close' => null);
        }
        foreach (array('joueurs', 'adversaires', 'en_cours') as $cle) {
            $etat[$cle] = isset($etat[$cle]) && is_array($etat[$cle]) ? $etat[$cle] : array();
        }
        if ($etat['periode'] === $periode) {
            return array('etat' => $etat, 'close' => null);
        }
        $podium = array_map(array(__CLASS__, 'publique'), array_slice(self::classer($etat), 0, 3));
        return array(
            'etat'  => self::vide($periode, $etat['adversaires']),
            'close' => $podium ? array('periode' => (string) $etat['periode'], 'podium' => $podium) : null,
        );
    }

    /**
     * Lancement d'un match classé. Les matchs du même joueur laissés en
     * cours depuis le même appareil comptent comme perdus ; au-delà de
     * MATCHS_PAR_JOUR matchs dans la journée, le match n'est pas classé.
     *
     * @param array  $etat
     * @param string $jeton     Identifiant du match.
     * @param array  $joueur    ['cle', 'affiche', 'points'].
     * @param array  $adv       ['cle', 'affiche', 'depart'] (cote de départ).
     * @param string $appareil  Empreinte de l'appareil (adresse IP).
     * @param string $jour      AAAA-MM-JJ.
     * @param int    $maintenant
     * @return array{etat: array, classe: bool, restants: int}
     */
    public static function lancer(array $etat, $jeton, array $joueur, array $adv, $appareil, $jour, $maintenant) {
        $etat = self::expirer($etat, $maintenant);
        foreach ($etat['en_cours'] as $id => $m) {
            if ($m['joueur'] === $joueur['cle'] && $m['appareil'] === $appareil) {
                $etat = self::conclure($etat, $id, false);
            }
        }

        $cle = (string) $joueur['cle'];
        if (!isset($etat['joueurs'][$cle])) {
            $depart = max(self::COTE_MIN, round((float) $joueur['points'], 1));
            $etat['joueurs'][$cle] = array(
                'affiche' => '', 'depart' => $depart, 'cote' => $depart,
                'matchs' => 0, 'victoires' => 0, 'jour' => '', 'jour_n' => 0,
            );
        }
        $fiche = &$etat['joueurs'][$cle];
        $fiche['affiche'] = (string) $joueur['affiche'];
        if ($fiche['jour'] !== $jour) {
            $fiche['jour']   = $jour;
            $fiche['jour_n'] = 0;
        }
        if ($fiche['jour_n'] >= self::MATCHS_PAR_JOUR) {
            unset($fiche);
            return array('etat' => $etat, 'classe' => false, 'restants' => 0);
        }
        $fiche['jour_n']++;
        $restants = self::MATCHS_PAR_JOUR - $fiche['jour_n'];
        unset($fiche);

        $advCle = (string) $adv['cle'];
        if (!isset($etat['adversaires'][$advCle])) {
            $etat['adversaires'][$advCle] = array('cote' => (float) $adv['depart'], 'matchs' => 0);
        }
        $etat['en_cours'][(string) $jeton] = array(
            'joueur'   => $cle,
            'adv'      => $advCle,
            'appareil' => (string) $appareil,
            'debut'    => (int) $maintenant,
        );
        return array('etat' => $etat, 'classe' => true, 'restants' => $restants);
    }

    /**
     * Fin d'un match classé : le jeton doit être celui d'un match en cours
     * du même joueur contre le même adversaire, lancé depuis assez longtemps
     * pour les points joués (sinon le match reste en cours, et finit perdu).
     *
     * @param int $points Points joués (au moins ceux de la dernière manche).
     * @return array{etat: array, resultat: array|null} resultat = ['variation', 'cote', 'progression', 'matchs'].
     */
    public static function terminer(array $etat, $jeton, $joueurCle, $advCle, $victoire, $maintenant, $points = 0) {
        $etat  = self::expirer($etat, $maintenant);
        $jeton = (string) $jeton;
        $m     = $etat['en_cours'][$jeton] ?? null;
        if (!$m || $m['joueur'] !== (string) $joueurCle || $m['adv'] !== (string) $advCle
            || (int) $maintenant - (int) $m['debut'] < (int) $points * self::SECONDES_PAR_POINT) {
            return array('etat' => $etat, 'resultat' => null);
        }
        $avant = $etat['joueurs'][$m['joueur']]['cote'];
        $etat  = self::conclure($etat, $jeton, (bool) $victoire);
        $fiche = $etat['joueurs'][$m['joueur']];
        return array('etat' => $etat, 'resultat' => array(
            'variation'   => round($fiche['cote'] - $avant, 1),
            'cote'        => $fiche['cote'],
            'progression' => round($fiche['cote'] - $fiche['depart'], 1),
            'matchs'      => $fiche['matchs'],
        ));
    }

    /**
     * Matchs en cours depuis plus de DELAI_ABANDON : perdus.
     */
    public static function expirer(array $etat, $maintenant) {
        foreach ($etat['en_cours'] as $id => $m) {
            if ((int) $maintenant - (int) $m['debut'] > self::DELAI_ABANDON) {
                $etat = self::conclure($etat, $id, false);
            }
        }
        return $etat;
    }

    /**
     * Applique le résultat d'un match en cours aux deux cotes et le retire.
     */
    private static function conclure(array $etat, $jeton, $victoire) {
        $m = $etat['en_cours'][$jeton];
        unset($etat['en_cours'][$jeton]);
        if (!isset($etat['joueurs'][$m['joueur']], $etat['adversaires'][$m['adv']])) {
            return $etat;
        }
        $j = &$etat['joueurs'][$m['joueur']];
        $a = &$etat['adversaires'][$m['adv']];
        $dj = self::variation($j['cote'], $a['cote'], $victoire);
        $da = self::variation($a['cote'], $j['cote'], !$victoire);
        $j['cote'] = round($j['cote'] + $dj, 1);
        $a['cote'] = max(self::COTE_MIN, round($a['cote'] + $da, 1));
        $j['matchs']++;
        $a['matchs']++;
        if ($victoire) {
            $j['victoires']++;
        }
        unset($j, $a);
        return $etat;
    }

    /**
     * Classement : joueurs d'au moins MATCHS_MIN matchs, par progression,
     * puis cote, puis nombre de matchs.
     *
     * @param array $etat
     * @return array<array{cle: string, joueur: string, cote: float, progression: float, matchs: int, victoires: int}>
     */
    public static function classer(array $etat) {
        $lignes = array();
        foreach ((array) ($etat['joueurs'] ?? array()) as $cle => $fiche) {
            if ((int) $fiche['matchs'] < self::MATCHS_MIN) {
                continue;
            }
            $lignes[] = array(
                'cle'         => (string) $cle,
                'joueur'      => (string) $fiche['affiche'],
                'cote'        => (float) $fiche['cote'],
                'progression' => round($fiche['cote'] - $fiche['depart'], 1),
                'matchs'      => (int) $fiche['matchs'],
                'victoires'   => (int) $fiche['victoires'],
            );
        }
        usort($lignes, function ($a, $b) {
            return array($b['progression'], $b['cote'], $b['matchs']) <=> array($a['progression'], $a['cote'], $a['matchs']);
        });
        return $lignes;
    }

    /**
     * Rang (1..n) d'un joueur au classement, null s'il n'y figure pas encore.
     */
    public static function rang(array $etat, $joueurCle) {
        foreach (self::classer($etat) as $i => $ligne) {
            if ($ligne['cle'] === (string) $joueurCle) {
                return $i + 1;
            }
        }
        return null;
    }

    /**
     * Ligne publique du classement (sans la clé interne, numéro de licence).
     */
    public static function publique(array $ligne) {
        return array(
            'joueur'      => (string) $ligne['joueur'],
            'cote'        => (int) round($ligne['cote']),
            'progression' => (float) $ligne['progression'],
            'matchs'      => (int) $ligne['matchs'],
        );
    }

    /**
     * Retire un joueur (cote usurpée, par exemple) et ses matchs en cours.
     */
    public static function retirer(array $etat, $joueurCle) {
        unset($etat['joueurs'][(string) $joueurCle]);
        foreach ($etat['en_cours'] as $id => $m) {
            if ($m['joueur'] === (string) $joueurCle) {
                unset($etat['en_cours'][$id]);
            }
        }
        return $etat;
    }
}

}
