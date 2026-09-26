<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_TopPerfs' ) ) {

/**
 * Service de domaine (sans état) : « top perfs » d'une journée de championnat.
 *
 * Une perf est une victoire d'un joueur du club contre un adversaire mieux
 * classé (victoire « anormale » au sens FFTT). Elle est valorisée par les
 * points gagnés selon le barème officiel, coefficient 1 en championnat par
 * équipes.
 *
 * Travaille uniquement sur les tableaux bruts de l'API FFTT (rencontres de
 * poule et feuilles de match) : aucune dépendance à WordPress, pour rester
 * testable unitairement. L'orchestration (appels API, cache, photos) est
 * faite par le plugin.
 */
class MonClubTT_TopPerfs {

    /**
     * Barème FFTT des victoires anormales : [écart minimum, points gagnés],
     * du plus grand écart au plus petit.
     */
    const BAREME_PERF = array(
        array(500, 40.0),
        array(400, 28.0),
        array(300, 22.0),
        array(200, 17.0),
        array(150, 13.0),
        array(100, 10.0),
        array(50, 8.0),
        array(25, 7.0),
        array(0, 6.0),
    );

    /**
     * Nombre de jours avant la dernière date jouée encore rattachés à la même
     * journée : les équipes d'un club jouent du vendredi soir au dimanche.
     */
    const FENETRE_WEEKEND_JOURS = 2;

    /**
     * Lit les points d'un classement de feuille de match (« M 1697pts »,
     * « N°45 - M 2512pts »).
     *
     * @param mixed $classement Valeur brute (un élément XML vide devient un tableau).
     * @return int|null Points, ou null si illisibles.
     */
    public static function pointsDepuisClassement($classement) {
        if (!is_string($classement) || !preg_match('/(\d+)\s*pts/i', $classement, $m)) {
            return null;
        }
        return (int) $m[1];
    }

    /**
     * Lit le sexe (M / F) d'un classement de feuille de match.
     *
     * @param mixed $classement Valeur brute.
     * @return string 'M', 'F' ou '' si absent.
     */
    public static function sexeDepuisClassement($classement) {
        if (!is_string($classement) || !preg_match('/(?:^|\s)([MF])\s+\d+\s*pts/i', $classement, $m)) {
            return '';
        }
        return strtoupper($m[1]);
    }

    /**
     * Points gagnés pour une victoire contre un adversaire mieux classé.
     *
     * @param int $ecart Écart de points (adversaire − vainqueur), positif.
     * @return float
     */
    public static function gainPerf($ecart) {
        foreach (self::BAREME_PERF as $palier) {
            if ($ecart >= $palier[0]) {
                return $palier[1];
            }
        }
        return 0.0;
    }

    /**
     * Sélectionne les rencontres jouées par le club lors de la dernière
     * journée (le dernier week-end joué), toutes poules confondues.
     *
     * @param array  $rencontres Rencontres brutes (xml_result_equ.php) de plusieurs poules.
     * @param string $numClub    Numéro du club.
     * @return array Liste de ['renc_id', 'is_retour', 'date' (Y-m-d), 'tour' (int|null), 'equipes_club' => string[],
     *               'equipes' => [domicile, extérieur], 'scores' => [domicile, extérieur]],
     *               par date puis ordre d'origine.
     */
    public static function rencontresDerniereJournee(array $rencontres, $numClub) {
        $jouees = array();
        foreach ($rencontres as $rencontre) {
            $retenue = self::rencontreJoueeParLeClub($rencontre, (string) $numClub);
            if ($retenue !== null) {
                $jouees[] = $retenue;
            }
        }
        if (empty($jouees)) {
            return array();
        }

        $derniere = max(array_column($jouees, 'date'));
        $debut    = gmdate('Y-m-d', strtotime($derniere . ' UTC') - self::FENETRE_WEEKEND_JOURS * 86400);

        $selection = array_values(array_filter($jouees, function ($r) use ($debut) {
            return $r['date'] >= $debut;
        }));
        usort($selection, function ($a, $b) {
            return strcmp($b['date'], $a['date']);
        });

        return $selection;
    }

    /**
     * Extrait les perfs des joueurs du club d'une feuille de match.
     *
     * @param array    $feuille      Feuille brute (xml_chp_renc.php) : resultat, joueur, partie.
     * @param string[] $equipesClub  Noms des équipes du club dans cette rencontre.
     * @return array Liste de ['joueur', 'sexe', 'points', 'adversaire_points', 'ecart', 'gain', 'equipe'].
     */
    public static function extrairePerfs(array $feuille, array $equipesClub) {
        $cotesClub = self::cotesDuClub($feuille, $equipesClub);
        if (empty($cotesClub)) {
            return array();
        }

        // Composition : nom → classement, par côté. Les doubles (noms combinés)
        // n'y figurent pas et sont donc ignorés naturellement.
        $classements = array('a' => array(), 'b' => array());
        foreach (self::liste($feuille['joueur'] ?? array()) as $ligne) {
            foreach (array('a', 'b') as $cote) {
                $nom = self::texte($ligne['xj' . $cote] ?? '');
                if ($nom !== '') {
                    $classements[$cote][$nom] = $ligne['xc' . $cote] ?? '';
                }
            }
        }

        $perfs = array();
        foreach (self::liste($feuille['partie'] ?? array()) as $partie) {
            $vainqueur = self::coteVainqueur($partie);
            if ($vainqueur === null || !isset($cotesClub[$vainqueur])) {
                continue;
            }
            $perdant   = $vainqueur === 'a' ? 'b' : 'a';
            $joueur    = self::texte($partie['j' . $vainqueur] ?? '');
            $adversaire = self::texte($partie['j' . $perdant] ?? '');
            if (!isset($classements[$vainqueur][$joueur], $classements[$perdant][$adversaire])) {
                continue;
            }

            $points           = self::pointsDepuisClassement($classements[$vainqueur][$joueur]);
            $adversairePoints = self::pointsDepuisClassement($classements[$perdant][$adversaire]);
            if ($points === null || $adversairePoints === null || $adversairePoints <= $points) {
                continue;
            }

            $ecart   = $adversairePoints - $points;
            $perfs[] = array(
                'joueur'            => $joueur,
                'sexe'              => self::sexeDepuisClassement($classements[$vainqueur][$joueur]),
                'points'            => $points,
                'adversaire_points' => $adversairePoints,
                'ecart'             => $ecart,
                'gain'              => self::gainPerf($ecart),
                'equipe'            => $cotesClub[$vainqueur],
            );
        }

        return $perfs;
    }

    /**
     * Regroupe les perfs par joueur : points gagnés cumulés, meilleure perf
     * (plus gros écart) mise en avant.
     *
     * @param array $perfs Perfs issues de extrairePerfs().
     * @return array Une entrée par joueur (champs d'une perf + 'nb_perfs'),
     *               dans l'ordre de première apparition.
     */
    public static function bilanParJoueur(array $perfs) {
        $bilan = array();
        foreach ($perfs as $perf) {
            $cle = self::cle($perf['joueur']);
            if (!isset($bilan[$cle])) {
                $bilan[$cle] = $perf + array('nb_perfs' => 0);
                $bilan[$cle]['gain'] = 0.0;
            } elseif ($perf['ecart'] > $bilan[$cle]['ecart']) {
                $bilan[$cle]['ecart']             = $perf['ecart'];
                $bilan[$cle]['adversaire_points'] = $perf['adversaire_points'];
            }
            $bilan[$cle]['gain'] += $perf['gain'];
            $bilan[$cle]['nb_perfs']++;
        }
        return array_values($bilan);
    }

    /**
     * Classe les perfs : plus gros gain d'abord, puis plus gros écart.
     *
     * @param array $perfs  Perfs issues de extrairePerfs().
     * @param int   $limite Nombre maximum de perfs retournées.
     * @return array
     */
    public static function classer(array $perfs, $limite = 5) {
        usort($perfs, function ($a, $b) {
            return array($b['gain'], $b['ecart']) <=> array($a['gain'], $a['ecart']);
        });
        return array_slice($perfs, 0, max(0, (int) $limite));
    }

    /**
     * Normalise une rencontre jouée par le club, ou null si elle ne le
     * concerne pas, n'est pas jouée ou n'a pas de feuille de match.
     *
     * @param mixed  $rencontre
     * @param string $numClub
     * @return array|null
     */
    private static function rencontreJoueeParLeClub($rencontre, $numClub) {
        if (!is_array($rencontre)
            || self::texte($rencontre['scorea'] ?? '') === ''
            || self::texte($rencontre['scoreb'] ?? '') === '') {
            return null;
        }

        $lien = array();
        parse_str(self::texte($rencontre['lien'] ?? ''), $lien);
        $rencId = isset($lien['renc_id']) ? (string) $lien['renc_id'] : '';

        $equipesClub = array();
        foreach (array(1, 2) as $i) {
            if (isset($lien['clubnum_' . $i]) && (string) $lien['clubnum_' . $i] === $numClub) {
                $equipesClub[] = (string) ($lien['equip_' . $i] ?? '');
            }
        }

        $date = self::dateIso(self::texte($rencontre['datereelle'] ?? '') ?: self::texte($rencontre['dateprevue'] ?? ''));

        if ($rencId === '' || empty($equipesClub) || $date === null) {
            return null;
        }

        // Numéro de journée : « Poule 2 - tour n°3 du 10/10/2026 ».
        $tour = preg_match('/tour\s+n\D{0,2}(\d+)/iu', self::texte($rencontre['libelle'] ?? ''), $m) ? (int) $m[1] : null;

        return array(
            'renc_id'      => $rencId,
            'is_retour'    => (int) ($lien['is_retour'] ?? 0),
            'date'         => $date,
            'tour'         => $tour,
            'equipes_club' => $equipesClub,
            // Côté domicile (1) puis extérieur (2), comme dans la poule.
            'equipes'      => array((string) ($lien['equip_1'] ?? ''), (string) ($lien['equip_2'] ?? '')),
            'scores'       => array((int) self::texte($rencontre['scorea']), (int) self::texte($rencontre['scoreb'])),
        );
    }

    /**
     * Côtés (a / b) de la feuille occupés par le club, avec le nom d'équipe.
     * La feuille ne reprend pas forcément l'ordre domicile/extérieur de la
     * poule : on se fie au nom des équipes.
     *
     * @return array<string, string>
     */
    public static function cotesDuClub(array $feuille, array $equipesClub) {
        $cibles = array_map(array(__CLASS__, 'cle'), $equipesClub);
        $cotes  = array();
        foreach (array('a', 'b') as $cote) {
            $nom = self::texte($feuille['resultat']['equ' . $cote] ?? '');
            if ($nom !== '' && in_array(self::cle($nom), $cibles, true)) {
                $cotes[$cote] = $nom;
            }
        }
        return $cotes;
    }

    /**
     * @return string|null 'a', 'b' ou null (partie non jouée / illisible).
     */
    public static function coteVainqueur($partie) {
        if (!is_array($partie)) {
            return null;
        }
        if (self::texte($partie['scorea'] ?? '') === '1') {
            return 'a';
        }
        if (self::texte($partie['scoreb'] ?? '') === '1') {
            return 'b';
        }
        return null;
    }

    /**
     * Un élément XML unique est converti en tableau associatif et non en
     * liste : on le réemballe.
     */
    public static function liste($valeur) {
        if (!is_array($valeur) || empty($valeur)) {
            return array();
        }
        return isset($valeur[0]) ? $valeur : array($valeur);
    }

    /**
     * Champ scalaire de l'API (un élément XML vide devient un tableau vide).
     */
    public static function texte($valeur) {
        return is_scalar($valeur) ? trim((string) $valeur) : '';
    }

    public static function cle($nom) {
        return strtoupper(preg_replace('/\s+/', ' ', trim((string) $nom)));
    }

    /**
     * @param string $date Date « jj/mm/aaaa ».
     * @return string|null Date « aaaa-mm-jj ».
     */
    private static function dateIso($date) {
        if (!preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m) || !checkdate((int) $m[2], (int) $m[1], (int) $m[3])) {
            return null;
        }
        return $m[3] . '-' . $m[2] . '-' . $m[1];
    }

}

}
