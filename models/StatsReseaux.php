<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_StatsReseaux' ) ) {

/**
 * Service de domaine (sans état) : statistiques des visuels réseaux sociaux,
 * en plus des top perfs (MonClubTT_TopPerfs) — bilan des équipes du week-end,
 * cartons pleins, victoires à la belle, paliers de points franchis.
 *
 * Comme MonClubTT_TopPerfs, travaille sur les tableaux bruts de l'API FFTT
 * sans dépendance à WordPress, pour rester testable unitairement.
 */
class MonClubTT_StatsReseaux {

    /** Écart minimum entre deux paliers de points (un classement = 100 pts). */
    const PAS_PALIER = 100;

    /**
     * Premier palier retenu : 500 pts est le plancher FFTT (classement 5),
     * l'atteindre n'a rien d'un exploit.
     */
    const PALIER_MIN = 600;

    /**
     * Bilan des équipes du club sur la journée : une ligne par équipe (deux
     * pour un derby), triées par nom (ordre naturel : 2 avant 10).
     *
     * @param array $journee Rencontres issues de MonClubTT_TopPerfs::rencontresDerniereJournee().
     * @return array Liste de ['equipe', 'adversaire', 'score', 'score_adversaire', 'resultat' (V/N/D), 'domicile' (bool)].
     */
    public static function recapEquipes(array $journee) {
        $recap = array();
        foreach ($journee as $rencontre) {
            foreach (array(0, 1) as $i) {
                $equipe = $rencontre['equipes'][$i] ?? '';
                if ($equipe === '' || !in_array($equipe, $rencontre['equipes_club'], true)) {
                    continue;
                }
                $adverse = 1 - $i;
                $pour    = (int) $rencontre['scores'][$i];
                $contre  = (int) $rencontre['scores'][$adverse];
                $recap[] = array(
                    'equipe'           => $equipe,
                    'adversaire'       => (string) ($rencontre['equipes'][$adverse] ?? ''),
                    'score'            => $pour,
                    'score_adversaire' => $contre,
                    'resultat'         => $pour > $contre ? 'V' : ($pour < $contre ? 'D' : 'N'),
                    'domicile'         => $i === 0,
                );
            }
        }
        usort($recap, function ($a, $b) {
            return strnatcasecmp($a['equipe'], $b['equipe']);
        });
        return $recap;
    }

    /**
     * Place d'une équipe dans le classement de sa poule.
     *
     * @param array  $classement Lignes brutes (xml_result_equ.php, action=classement) : 'clt', 'equipe'.
     * @param string $equipe     Nom de l'équipe.
     * @return int|null
     */
    public static function rangDansPoule(array $classement, $equipe) {
        $cible = MonClubTT_TopPerfs::cle($equipe);
        foreach (MonClubTT_TopPerfs::liste($classement) as $ligne) {
            if (is_array($ligne) && MonClubTT_TopPerfs::cle(MonClubTT_TopPerfs::texte($ligne['equipe'] ?? '')) === $cible) {
                $clt = (int) MonClubTT_TopPerfs::texte($ligne['clt'] ?? '');
                return $clt > 0 ? $clt : null;
            }
        }
        return null;
    }

    /**
     * Scores des sets d'une partie, du point de vue d'un côté. Le détail FFTT
     * ne donne que les points du perdant de chaque set, signés du point de
     * vue de l'équipe A : « 08 » = set gagné par A 11-8, « -12 » = set perdu
     * par A 12-14.
     *
     * @param mixed $detail     Champ 'detail' de la partie (« 11 -08 12 09 »).
     * @param bool  $pointDeVueA true pour les scores du côté A, false pour B.
     * @return array Liste de [points marqués, points encaissés] ; vide si illisible.
     */
    public static function setsDepuisDetail($detail, $pointDeVueA = true) {
        $sets = array();
        foreach (preg_split('/\s+/', MonClubTT_TopPerfs::texte($detail), -1, PREG_SPLIT_NO_EMPTY) as $jeton) {
            if (!preg_match('/^(-?)(\d+)$/', $jeton, $m)) {
                return array();
            }
            $perdant   = (int) $m[2];
            $gagnant   = max(11, $perdant + 2);
            $gagneParA = $m[1] === '';
            $scoreA    = $gagneParA ? array($gagnant, $perdant) : array($perdant, $gagnant);
            $sets[]    = $pointDeVueA ? $scoreA : array($scoreA[1], $scoreA[0]);
        }
        return $sets;
    }

    /**
     * Parties en simple jouées par les joueurs du club sur une feuille de
     * match, victoires et défaites.
     *
     * @param array    $feuille     Feuille brute (xml_chp_renc.php).
     * @param string[] $equipesClub Noms des équipes du club dans cette rencontre.
     * @return array Liste de ['joueur', 'sexe', 'points', 'adversaire_points', 'equipe', 'victoire' (bool), 'sets'].
     */
    public static function partiesDuClub(array $feuille, array $equipesClub) {
        $cotesClub = MonClubTT_TopPerfs::cotesDuClub($feuille, $equipesClub);
        if (empty($cotesClub)) {
            return array();
        }

        // Composition : nom → classement. Les doubles (noms combinés) n'y
        // figurent pas et sont donc ignorés.
        $classements = array('a' => array(), 'b' => array());
        foreach (MonClubTT_TopPerfs::liste($feuille['joueur'] ?? array()) as $ligne) {
            foreach (array('a', 'b') as $cote) {
                $nom = MonClubTT_TopPerfs::texte($ligne['xj' . $cote] ?? '');
                if ($nom !== '') {
                    $classements[$cote][$nom] = $ligne['xc' . $cote] ?? '';
                }
            }
        }

        $parties = array();
        foreach (MonClubTT_TopPerfs::liste($feuille['partie'] ?? array()) as $partie) {
            $vainqueur = MonClubTT_TopPerfs::coteVainqueur($partie);
            if ($vainqueur === null) {
                continue;
            }
            foreach ($cotesClub as $cote => $equipe) {
                $adverse    = $cote === 'a' ? 'b' : 'a';
                $joueur     = MonClubTT_TopPerfs::texte($partie['j' . $cote] ?? '');
                $adversaire = MonClubTT_TopPerfs::texte($partie['j' . $adverse] ?? '');
                if (!isset($classements[$cote][$joueur], $classements[$adverse][$adversaire])) {
                    continue;
                }
                $parties[] = array(
                    'joueur'            => $joueur,
                    'sexe'              => MonClubTT_TopPerfs::sexeDepuisClassement($classements[$cote][$joueur]),
                    'points'            => MonClubTT_TopPerfs::pointsDepuisClassement($classements[$cote][$joueur]),
                    'adversaire_points' => MonClubTT_TopPerfs::pointsDepuisClassement($classements[$adverse][$adversaire]),
                    'equipe'            => $equipe,
                    'victoire'          => $vainqueur === $cote,
                    'sets'              => self::setsDepuisDetail($partie['detail'] ?? '', $cote === 'a'),
                );
            }
        }

        return $parties;
    }

    /**
     * Joueurs invaincus sur le week-end (toutes leurs parties en simple
     * gagnées), du plus grand nombre de victoires au plus petit.
     *
     * @param array $parties Parties issues de partiesDuClub().
     * @param int   $minimum Nombre minimum de parties jouées.
     * @return array Liste de ['joueur', 'sexe', 'points', 'equipe', 'victoires'].
     */
    public static function cartonsPleins(array $parties, $minimum = 2) {
        $bilan = array();
        foreach ($parties as $partie) {
            $cle = MonClubTT_TopPerfs::cle($partie['joueur']);
            if (!isset($bilan[$cle])) {
                $bilan[$cle] = array(
                    'joueur'    => $partie['joueur'],
                    'sexe'      => $partie['sexe'],
                    'points'    => $partie['points'],
                    'equipe'    => $partie['equipe'],
                    'victoires' => 0,
                    'jouees'    => 0,
                );
            }
            $bilan[$cle]['jouees']++;
            if ($partie['victoire']) {
                $bilan[$cle]['victoires']++;
            }
        }

        $cartons = array_values(array_filter($bilan, function ($b) use ($minimum) {
            return $b['jouees'] >= $minimum && $b['victoires'] === $b['jouees'];
        }));
        usort($cartons, function ($a, $b) {
            return $b['victoires'] <=> $a['victoires'];
        });

        return array_map(function ($b) {
            unset($b['jouees']);
            return $b;
        }, $cartons);
    }

    /**
     * Victoires arrachées à la belle (5 sets), les plus spectaculaires
     * d'abord : remontada (menés 0-2), puis belle gagnée aux avantages, puis
     * plus gros écart de points avec l'adversaire.
     *
     * @param array $parties Parties issues de partiesDuClub().
     * @return array Parties gagnées en 5 sets, avec 'remontada' et 'finish' (belle ≥ 12-10).
     */
    public static function victoiresALaBelle(array $parties) {
        $belles = array();
        foreach ($parties as $partie) {
            $sets = $partie['sets'];
            if (!$partie['victoire'] || count($sets) !== 5) {
                continue;
            }
            $partie['remontada'] = $sets[0][0] < $sets[0][1] && $sets[1][0] < $sets[1][1];
            $partie['finish']    = $sets[4][1] >= 10;
            $belles[] = $partie;
        }

        usort($belles, function ($a, $b) {
            return array($b['remontada'], $b['finish'], (int) $b['adversaire_points'] - (int) $b['points'])
                <=> array($a['remontada'], $a['finish'], (int) $a['adversaire_points'] - (int) $a['points']);
        });

        return $belles;
    }

    /**
     * Palier de points franchi ce mois-ci (1000, 1500, …), ou null.
     *
     * @param float $points      Points mensuels actuels.
     * @param float $progression Progression depuis le mois précédent.
     * @return int|null Le plus haut palier franchi (PALIER_MIN au moins).
     */
    public static function palierFranchi($points, $progression) {
        if ($progression <= 0) {
            return null;
        }
        $palier = (int) (floor($points / self::PAS_PALIER) * self::PAS_PALIER);
        return $palier >= self::PALIER_MIN && $points - $progression < $palier ? $palier : null;
    }

}

}
