<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_TopPerfs;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests du calcul des « top perfs » d'une journée de championnat :
 * victoires contre un adversaire mieux classé, valorisées selon le barème
 * FFTT des victoires anormales.
 */
final class TopPerfsTest extends TestCase
{
    private const NUM_CLUB = '10330011';

    // ── Lecture du classement d'une feuille de match ────────────────────

    public function test_should_read_points_when_classement_is_standard(): void
    {
        $this->assertSame(1697, MonClubTT_TopPerfs::pointsDepuisClassement('M 1697pts'));
    }

    public function test_should_read_points_when_player_is_numbered(): void
    {
        $this->assertSame(2512, MonClubTT_TopPerfs::pointsDepuisClassement('N°45 - M 2512pts'));
    }

    public function test_should_return_null_points_when_classement_unreadable(): void
    {
        $this->assertNull(MonClubTT_TopPerfs::pointsDepuisClassement(''));
        $this->assertNull(MonClubTT_TopPerfs::pointsDepuisClassement(array()));
    }

    public function test_should_read_sexe_when_classement_has_letter(): void
    {
        $this->assertSame('F', MonClubTT_TopPerfs::sexeDepuisClassement('F 845pts'));
        $this->assertSame('M', MonClubTT_TopPerfs::sexeDepuisClassement('N°45 - M 2512pts'));
        $this->assertSame('', MonClubTT_TopPerfs::sexeDepuisClassement('1234pts'));
    }

    // ── Barème des victoires anormales ──────────────────────────────────

    /**
     * @return array<string, array{int, float}>
     */
    public static function baremeProvider(): array
    {
        return array(
            'écart nul'      => array(0, 6.0),
            'écart 24'       => array(24, 6.0),
            'écart 25'       => array(25, 7.0),
            'écart 50'       => array(50, 8.0),
            'écart 100'      => array(100, 10.0),
            'écart 150'      => array(150, 13.0),
            'écart 200'      => array(200, 17.0),
            'écart 300'      => array(300, 22.0),
            'écart 400'      => array(400, 28.0),
            'écart 499'      => array(499, 28.0),
            'écart 500 et +' => array(812, 40.0),
        );
    }

    #[DataProvider('baremeProvider')]
    public function test_should_apply_fftt_scale_when_computing_perf_gain(int $ecart, float $gain): void
    {
        $this->assertSame($gain, MonClubTT_TopPerfs::gainPerf($ecart));
    }

    // ── Sélection de la dernière journée ────────────────────────────────

    public function test_should_keep_only_last_weekend_club_matches_when_several_played(): void
    {
        $rencontres = array(
            $this->rencontre('6000001', '12/09/2026', 'TALENCE US 1', self::NUM_CLUB, 'CESTAS SAG 1', '10330002'),
            $this->rencontre('6000002', '19/09/2026', 'TALENCE US 1', self::NUM_CLUB, 'CESTAS SAG 1', '10330002'),
            $this->rencontre('6000003', '18/09/2026', 'PAU 2', '10640003', 'TALENCE US 3', self::NUM_CLUB),
            // Journée de la même poule sans le club : ignorée.
            $this->rencontre('6000004', '19/09/2026', 'PAU 1', '10640003', 'ANGOULEME 3', '10160029'),
            // Pas encore jouée (pas de score) : ignorée.
            $this->rencontre('6000005', '26/09/2026', 'TALENCE US 2', self::NUM_CLUB, 'CESTAS SAG 2', '10330002', '', ''),
        );

        $selection = MonClubTT_TopPerfs::rencontresDerniereJournee($rencontres, self::NUM_CLUB);

        $this->assertSame(array('6000002', '6000003'), array_column($selection, 'renc_id'));
        $this->assertSame(array('TALENCE US 3'), $selection[1]['equipes_club']);
        $this->assertSame('2026-09-19', $selection[0]['date']);
        $this->assertSame(1, $selection[0]['tour']);
    }

    public function test_should_read_round_number_when_libelle_has_tour(): void
    {
        $rencontre = $this->rencontre('6000020', '10/10/2026', 'TALENCE US 1', self::NUM_CLUB, 'CESTAS SAG 1', '10330002');
        $rencontre['libelle'] = 'Poule 2 - tour n°3 du 10/10/2026';
        $sansTour = $this->rencontre('6000021', '10/10/2026', 'TALENCE US 2', self::NUM_CLUB, 'PAU 1', '10640003');
        $sansTour['libelle'] = array();

        $selection = MonClubTT_TopPerfs::rencontresDerniereJournee(array($rencontre, $sansTour), self::NUM_CLUB);

        $this->assertSame(3, $selection[0]['tour']);
        $this->assertNull($selection[1]['tour']);
    }

    public function test_should_keep_both_teams_when_club_derby(): void
    {
        $rencontres = array(
            $this->rencontre('6000010', '19/09/2026', 'TALENCE US 4', self::NUM_CLUB, 'TALENCE US 5', self::NUM_CLUB),
        );

        $selection = MonClubTT_TopPerfs::rencontresDerniereJournee($rencontres, self::NUM_CLUB);

        $this->assertSame(array('TALENCE US 4', 'TALENCE US 5'), $selection[0]['equipes_club']);
    }

    public function test_should_return_nothing_when_club_never_played(): void
    {
        $rencontres = array(
            $this->rencontre('6000004', '19/09/2026', 'PAU 1', '10640003', 'ANGOULEME 3', '10160029'),
        );

        $this->assertSame(array(), MonClubTT_TopPerfs::rencontresDerniereJournee($rencontres, self::NUM_CLUB));
    }

    // ── Extraction des perfs d'une feuille de match ─────────────────────

    public function test_should_extract_perf_when_club_player_beats_higher_ranked(): void
    {
        $perfs = MonClubTT_TopPerfs::extrairePerfs($this->feuille(), array('TALENCE US 1'));

        $this->assertCount(1, $perfs);
        $this->assertSame('FOUCHET Romain', $perfs[0]['joueur']);
        $this->assertSame('M', $perfs[0]['sexe']);
        $this->assertSame(1697, $perfs[0]['points']);
        $this->assertSame(1722, $perfs[0]['adversaire_points']);
        $this->assertSame(25, $perfs[0]['ecart']);
        $this->assertSame(7.0, $perfs[0]['gain']);
        $this->assertSame('TALENCE US 1', $perfs[0]['equipe']);
    }

    public function test_should_ignore_opponent_perfs_when_extracting(): void
    {
        // Même feuille, vue depuis l'autre club : TOURNAUX (1478) bat TREMULOT (1602).
        $perfs = MonClubTT_TopPerfs::extrairePerfs($this->feuille(), array('CESTAS SAG 1'));

        $this->assertSame(array('TOURNAUX Maxime'), array_column($perfs, 'joueur'));
    }

    public function test_should_ignore_doubles_when_players_not_in_composition(): void
    {
        $feuille = $this->feuille();
        $feuille['partie'][] = array('ja' => 'TANG B. / TOURNAUX M.', 'scorea' => '-', 'jb' => 'FOUCHET R. / TREMULOT D.', 'scoreb' => '1', 'detail' => '');

        $this->assertCount(1, MonClubTT_TopPerfs::extrairePerfs($feuille, array('TALENCE US 1')));
    }

    public function test_should_return_no_perf_when_club_not_on_sheet(): void
    {
        $this->assertSame(array(), MonClubTT_TopPerfs::extrairePerfs($this->feuille(), array('PAU 1')));
        $this->assertSame(array(), MonClubTT_TopPerfs::extrairePerfs(array(), array('TALENCE US 1')));
    }

    // ── Classement des perfs ────────────────────────────────────────────

    public function test_should_rank_perfs_by_gain_then_gap_when_classing(): void
    {
        $perfs = array(
            array('joueur' => 'A', 'gain' => 7.0, 'ecart' => 30),
            array('joueur' => 'B', 'gain' => 13.0, 'ecart' => 160),
            array('joueur' => 'C', 'gain' => 7.0, 'ecart' => 45),
            array('joueur' => 'D', 'gain' => 6.0, 'ecart' => 3),
        );

        $classees = MonClubTT_TopPerfs::classer($perfs, 3);

        $this->assertSame(array('B', 'C', 'A'), array_column($classees, 'joueur'));
    }

    // ── Bilan par joueur ────────────────────────────────────────────────

    public function test_should_sum_gains_and_keep_best_perf_when_player_has_several(): void
    {
        $perfs = array(
            array('joueur' => 'FRANCOIS Octave', 'sexe' => 'M', 'points' => 500, 'adversaire_points' => 592, 'ecart' => 92, 'gain' => 8.0, 'equipe' => 'US TALENCE 8'),
            array('joueur' => 'METAYER Sylvain', 'sexe' => 'M', 'points' => 551, 'adversaire_points' => 798, 'ecart' => 247, 'gain' => 17.0, 'equipe' => 'US TALENCE 6'),
            array('joueur' => 'FRANCOIS Octave', 'sexe' => 'M', 'points' => 500, 'adversaire_points' => 653, 'ecart' => 153, 'gain' => 13.0, 'equipe' => 'US TALENCE 8'),
        );

        $bilan = MonClubTT_TopPerfs::bilanParJoueur($perfs);

        $this->assertCount(2, $bilan);
        $this->assertSame('FRANCOIS Octave', $bilan[0]['joueur']);
        $this->assertSame(2, $bilan[0]['nb_perfs']);
        $this->assertSame(21.0, $bilan[0]['gain']);
        $this->assertSame(653, $bilan[0]['adversaire_points']);
        $this->assertSame(153, $bilan[0]['ecart']);
        $this->assertSame(1, $bilan[1]['nb_perfs']);
    }

    // ── Jeux de données ─────────────────────────────────────────────────

    /**
     * Rencontre telle que renvoyée par xml_result_equ.php (converti en tableau).
     *
     * @return array<string, mixed>
     */
    private function rencontre(
        string $rencId,
        string $date,
        string $equipeA,
        string $clubA,
        string $equipeB,
        string $clubB,
        string $scoreA = '8',
        string $scoreB = '6'
    ): array {
        return array(
            'libelle'    => 'Poule 2 - tour n°1 du ' . $date,
            'equa'       => $equipeA,
            'equb'       => $equipeB,
            'scorea'     => $scoreA,
            'scoreb'     => $scoreB,
            'lien'       => http_build_query(array(
                'renc_id'   => $rencId,
                'is_retour' => 0,
                'equip_1'   => $equipeA,
                'equip_2'   => $equipeB,
                'clubnum_1' => $clubA,
                'clubnum_2' => $clubB,
            )),
            'dateprevue' => $date,
            'datereelle' => $date,
        );
    }

    /**
     * Feuille de match réelle (xml_chp_renc.php) : CESTAS en A, TALENCE en B.
     *
     * @return array<string, mixed>
     */
    private function feuille(): array
    {
        return array(
            'resultat' => array('equa' => 'CESTAS SAG 1', 'equb' => 'TALENCE US 1', 'resa' => '1', 'resb' => '3'),
            'joueur'   => array(
                array('xja' => 'TANG BOULANGER Clement', 'xca' => 'M 1722pts', 'xjb' => 'FOUCHET Romain', 'xcb' => 'M 1697pts'),
                array('xja' => 'TOURNAUX Maxime', 'xca' => 'M 1478pts', 'xjb' => 'TREMULOT Damian', 'xcb' => 'M 1602pts'),
                array('xja' => 'GUILLEMET Loris', 'xca' => 'M 1684pts', 'xjb' => 'FUMEAU Jeremie', 'xcb' => 'M 1601pts'),
            ),
            'partie'   => array(
                // Perf TALENCE : FOUCHET (1697) bat TANG BOULANGER (1722).
                array('ja' => 'TANG BOULANGER Clement', 'scorea' => '-', 'jb' => 'FOUCHET Romain', 'scoreb' => '1', 'detail' => '-04 -09 -05'),
                // Perf CESTAS : TOURNAUX (1478) bat TREMULOT (1602).
                array('ja' => 'TOURNAUX Maxime', 'scorea' => '1', 'jb' => 'TREMULOT Damian', 'scoreb' => '-', 'detail' => '10 08 -06 06'),
                // Victoire normale TALENCE : pas une perf.
                array('ja' => 'TOURNAUX Maxime', 'scorea' => '-', 'jb' => 'TREMULOT Damian', 'scoreb' => '1', 'detail' => '-08 10 -02 -12'),
                // Victoire normale CESTAS : pas une perf.
                array('ja' => 'GUILLEMET Loris', 'scorea' => '1', 'jb' => 'TREMULOT Damian', 'scoreb' => '-', 'detail' => '07 -10 08 05'),
            ),
        );
    }
}
