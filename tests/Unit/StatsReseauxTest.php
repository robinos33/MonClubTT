<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_StatsReseaux;
use MonClubTT_TopPerfs;
use PHPUnit\Framework\TestCase;

/**
 * Tests des statistiques des visuels réseaux sociaux : bilan des équipes,
 * cartons pleins, victoires à la belle et paliers de points.
 */
final class StatsReseauxTest extends TestCase
{
    private const NUM_CLUB = '10330011';

    // ── Bilan des équipes ───────────────────────────────────────────────

    public function test_should_build_team_recap_from_club_side_when_home_or_away(): void
    {
        $journee = MonClubTT_TopPerfs::rencontresDerniereJournee(array(
            $this->rencontre('6000001', 'TALENCE US 10', self::NUM_CLUB, 'CESTAS SAG 1', '10330002', '8', '6'),
            $this->rencontre('6000002', 'PAU 2', '10640003', 'TALENCE US 2', self::NUM_CLUB, '9', '5'),
            $this->rencontre('6000003', 'TALENCE US 3', self::NUM_CLUB, 'PAU 1', '10640003', '7', '7'),
        ), self::NUM_CLUB);

        $recap = MonClubTT_StatsReseaux::recapEquipes($journee);

        $this->assertSame(array('TALENCE US 2', 'TALENCE US 3', 'TALENCE US 10'), array_column($recap, 'equipe'));
        $this->assertSame(array('D', 'N', 'V'), array_column($recap, 'resultat'));
        $this->assertSame('PAU 2', $recap[0]['adversaire']);
        $this->assertSame(5, $recap[0]['score']);
        $this->assertSame(9, $recap[0]['score_adversaire']);
        $this->assertFalse($recap[0]['domicile']);
        $this->assertTrue($recap[2]['domicile']);
    }

    public function test_should_list_both_teams_when_club_derby(): void
    {
        $journee = MonClubTT_TopPerfs::rencontresDerniereJournee(array(
            $this->rencontre('6000010', 'TALENCE US 4', self::NUM_CLUB, 'TALENCE US 5', self::NUM_CLUB, '6', '8'),
        ), self::NUM_CLUB);

        $recap = MonClubTT_StatsReseaux::recapEquipes($journee);

        $this->assertSame(array('D', 'V'), array_column($recap, 'resultat'));
        $this->assertSame('TALENCE US 4', $recap[1]['adversaire']);
    }

    public function test_should_find_rank_when_team_in_pool_standings(): void
    {
        $classement = array(
            array('clt' => '1', 'equipe' => 'TALENCE US 1'),
            array('clt' => '2', 'equipe' => 'CESTAS SAG 1'),
        );

        $this->assertSame(1, MonClubTT_StatsReseaux::rangDansPoule($classement, 'Talence US 1'));
        $this->assertSame(2, MonClubTT_StatsReseaux::rangDansPoule($classement, 'CESTAS SAG 1'));
        $this->assertNull(MonClubTT_StatsReseaux::rangDansPoule($classement, 'PAU 1'));
        $this->assertNull(MonClubTT_StatsReseaux::rangDansPoule(array(), 'PAU 1'));
    }

    // ── Détail des sets ─────────────────────────────────────────────────

    public function test_should_rebuild_set_scores_when_reading_detail(): void
    {
        $this->assertSame(
            array(array(11, 8), array(8, 11), array(14, 12), array(11, 0)),
            MonClubTT_StatsReseaux::setsDepuisDetail('08 -08 12 00')
        );
        $this->assertSame(
            array(array(8, 11), array(11, 8)),
            MonClubTT_StatsReseaux::setsDepuisDetail('08 -08', false)
        );
    }

    public function test_should_return_no_set_when_detail_unreadable(): void
    {
        $this->assertSame(array(), MonClubTT_StatsReseaux::setsDepuisDetail(''));
        $this->assertSame(array(), MonClubTT_StatsReseaux::setsDepuisDetail(array()));
        $this->assertSame(array(), MonClubTT_StatsReseaux::setsDepuisDetail('WO'));
    }

    // ── Parties du club ─────────────────────────────────────────────────

    public function test_should_list_club_singles_when_reading_sheet(): void
    {
        $parties = MonClubTT_StatsReseaux::partiesDuClub($this->feuille(), array('TALENCE US 1'));

        $this->assertSame(
            array('FOUCHET Romain', 'TREMULOT Damian', 'TREMULOT Damian', 'FOUCHET Romain'),
            array_column($parties, 'joueur')
        );
        $this->assertSame(array(true, false, true, true), array_column($parties, 'victoire'));
        $this->assertSame(array(11, 4), $parties[0]['sets'][0]);
        $this->assertSame('TALENCE US 1', $parties[0]['equipe']);
        $this->assertSame(1697, $parties[0]['points']);
        $this->assertSame(1722, $parties[0]['adversaire_points']);
    }

    // ── Cartons pleins ──────────────────────────────────────────────────

    public function test_should_keep_unbeaten_players_when_computing_cartons_pleins(): void
    {
        $parties = MonClubTT_StatsReseaux::partiesDuClub($this->feuille(), array('TALENCE US 1'));

        $cartons = MonClubTT_StatsReseaux::cartonsPleins($parties);

        $this->assertSame(array('FOUCHET Romain'), array_column($cartons, 'joueur'));
        $this->assertSame(2, $cartons[0]['victoires']);
        $this->assertArrayNotHasKey('jouees', $cartons[0]);
    }

    public function test_should_require_minimum_matches_when_computing_cartons_pleins(): void
    {
        $parties = array($this->partie('SEUL Match', true));

        $this->assertSame(array(), MonClubTT_StatsReseaux::cartonsPleins($parties));
        $this->assertCount(1, MonClubTT_StatsReseaux::cartonsPleins($parties, 1));
    }

    // ── Victoires à la belle ────────────────────────────────────────────

    public function test_should_rank_comebacks_first_when_listing_five_set_wins(): void
    {
        $parties = array(
            $this->partie('BELLE Simple', true, array(array(11, 5), array(8, 11), array(11, 9), array(5, 11), array(11, 3))),
            $this->partie('REMONTADA Joueur', true, array(array(5, 11), array(9, 11), array(11, 9), array(11, 7), array(11, 6))),
            $this->partie('FINISH Joueur', true, array(array(11, 5), array(8, 11), array(11, 9), array(5, 11), array(13, 11))),
            $this->partie('TROIS Sets', true, array(array(11, 5), array(11, 8), array(11, 9))),
            $this->partie('BELLE Perdue', false, array(array(11, 5), array(8, 11), array(11, 9), array(5, 11), array(9, 11))),
        );

        $belles = MonClubTT_StatsReseaux::victoiresALaBelle($parties);

        $this->assertSame(array('REMONTADA Joueur', 'FINISH Joueur', 'BELLE Simple'), array_column($belles, 'joueur'));
        $this->assertTrue($belles[0]['remontada']);
        $this->assertTrue($belles[1]['finish']);
        $this->assertFalse($belles[2]['finish']);
    }

    // ── Paliers ─────────────────────────────────────────────────────────

    public function test_should_detect_crossed_threshold_when_player_progresses(): void
    {
        $this->assertSame(1500, MonClubTT_StatsReseaux::palierFranchi(1512.0, 24.0));
        $this->assertSame(1000, MonClubTT_StatsReseaux::palierFranchi(1000.0, 3.5));
        $this->assertNull(MonClubTT_StatsReseaux::palierFranchi(1580.0, 24.0));
        $this->assertNull(MonClubTT_StatsReseaux::palierFranchi(1480.0, -30.0));
    }

    public function test_should_ignore_fftt_floor_when_detecting_threshold(): void
    {
        $this->assertNull(MonClubTT_StatsReseaux::palierFranchi(500.0, 0.5));
        $this->assertSame(600, MonClubTT_StatsReseaux::palierFranchi(605.0, 8.0));
    }

    // ── Jeux de données ─────────────────────────────────────────────────

    /**
     * @return array<string, mixed>
     */
    private function rencontre(string $rencId, string $equipeA, string $clubA, string $equipeB, string $clubB, string $scoreA, string $scoreB): array
    {
        return array(
            'libelle'    => 'Poule 2 - tour n°1 du 19/09/2026',
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
            'dateprevue' => '19/09/2026',
            'datereelle' => '19/09/2026',
        );
    }

    /**
     * @param array<int, array{int, int}> $sets
     * @return array<string, mixed>
     */
    private function partie(string $joueur, bool $victoire, array $sets = array()): array
    {
        return array(
            'joueur'            => $joueur,
            'sexe'              => 'M',
            'points'            => 1200,
            'adversaire_points' => 1300,
            'equipe'            => 'TALENCE US 1',
            'victoire'          => $victoire,
            'sets'              => $sets,
        );
    }

    /**
     * Feuille de match : CESTAS en A, TALENCE en B.
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
            ),
            'partie'   => array(
                array('ja' => 'TANG BOULANGER Clement', 'scorea' => '-', 'jb' => 'FOUCHET Romain', 'scoreb' => '1', 'detail' => '-04 -09 -05'),
                array('ja' => 'TOURNAUX Maxime', 'scorea' => '1', 'jb' => 'TREMULOT Damian', 'scoreb' => '-', 'detail' => '10 08 -06 06'),
                array('ja' => 'TANG BOULANGER Clement', 'scorea' => '-', 'jb' => 'TREMULOT Damian', 'scoreb' => '1', 'detail' => '-08 10 -02 -12'),
                array('ja' => 'TOURNAUX Maxime', 'scorea' => '-', 'jb' => 'FOUCHET Romain', 'scoreb' => '1', 'detail' => '-07 -10 -08'),
                // Double : ignoré.
                array('ja' => 'TANG B. / TOURNAUX M.', 'scorea' => '-', 'jb' => 'FOUCHET R. / TREMULOT D.', 'scoreb' => '1', 'detail' => ''),
            ),
        );
    }
}
