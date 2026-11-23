<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_PongDefis;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PongScores.php';
require_once __DIR__ . '/../../models/PongDefis.php';

/**
 * Tests du défi du jour du jeu de pong : tirage de l'adversaire, durée
 * plausible, une tentative par joueur, classement et purge des vieux jours.
 */
final class PongDefisTest extends TestCase
{
    /** @param array<string, mixed> $champs */
    private function entree(string $cle, int $pj, int $pa, float $duree, int $date = 1, array $champs = array()): array
    {
        return array_merge(array('cle' => $cle, 'joueur' => strtoupper($cle), 'pj' => $pj, 'pa' => $pa, 'duree' => $duree, 'date' => $date), $champs);
    }

    public function test_should_draw_the_same_opponent_for_a_given_date(): void
    {
        $candidats = array('club|A|a', 'club|B|b', 'monde|C|', 'club|D|d');

        $tirage = MonClubTT_PongDefis::tirage('2026-11-23', $candidats);

        $this->assertContains($tirage, $candidats);
        $this->assertSame($tirage, MonClubTT_PongDefis::tirage('2026-11-23', array_reverse($candidats)));
    }

    public function test_should_change_opponent_over_the_days(): void
    {
        $candidats = array('a', 'b', 'c', 'd', 'e', 'f', 'g', 'h');
        $tirages   = array();
        foreach (range(1, 14) as $jour) {
            $tirages[] = MonClubTT_PongDefis::tirage(sprintf('2026-12-%02d', $jour), $candidats);
        }

        $this->assertGreaterThan(3, count(array_unique($tirages)));
    }

    public function test_should_not_draw_yesterdays_opponent_when_there_is_a_choice(): void
    {
        foreach (range(1, 20) as $jour) {
            $date = sprintf('2026-12-%02d', $jour);
            $this->assertSame('b', MonClubTT_PongDefis::tirage($date, array('a', 'b'), 'a'));
        }
        $this->assertSame('a', MonClubTT_PongDefis::tirage('2026-12-01', array('a'), 'a'));
        $this->assertNull(MonClubTT_PongDefis::tirage('2026-12-01', array()));
    }

    public function test_should_reject_implausible_durations(): void
    {
        $this->assertTrue(MonClubTT_PongDefis::dureeValide(11, 4, 60));
        $this->assertFalse(MonClubTT_PongDefis::dureeValide(11, 4, 10), 'Moins de 1,2 s par point');
        $this->assertFalse(MonClubTT_PongDefis::dureeValide(11, 4, 4000));
        $this->assertFalse(MonClubTT_PongDefis::dureeValide(11, 4, 'vite'));
    }

    public function test_should_rank_by_margin_then_duration_then_arrival(): void
    {
        $entrees = MonClubTT_PongDefis::classer(array(
            $this->entree('lent', 11, 3, 90.0, 1),
            $this->entree('defaite', 9, 11, 80.0, 2),
            $this->entree('rapide', 11, 3, 60.0, 3),
            $this->entree('large', 11, 1, 120.0, 4),
            $this->entree('tardif', 11, 3, 60.0, 5),
        ));

        $this->assertSame(array('large', 'rapide', 'tardif', 'lent', 'defaite'), array_column($entrees, 'cle'));
    }

    public function test_should_count_only_the_first_attempt_of_a_player(): void
    {
        $premier = MonClubTT_PongDefis::ajouter(array(), $this->entree('louis', 11, 9, 70.0));
        $second  = MonClubTT_PongDefis::ajouter($premier['entrees'], $this->entree('louis', 11, 0, 50.0, 2));

        $this->assertSame(1, $premier['rang']);
        $this->assertFalse($premier['deja']);
        $this->assertNull($second['rang']);
        $this->assertTrue($second['deja']);
        $this->assertCount(1, $second['entrees']);
        $this->assertSame(9, $second['entrees'][0]['pa']);
    }

    public function test_should_give_the_rank_of_the_new_attempt(): void
    {
        $entrees  = array($this->entree('a', 11, 2, 60.0), $this->entree('b', 11, 8, 60.0));
        $resultat = MonClubTT_PongDefis::ajouter($entrees, $this->entree('c', 11, 5, 60.0, 9));

        $this->assertSame(2, $resultat['rang']);
    }

    public function test_should_drop_invalid_entries(): void
    {
        $entrees = MonClubTT_PongDefis::classer(array(
            $this->entree('score', 30, 0, 60.0),
            $this->entree('chrono', 11, 0, 2.0),
            array('cle' => 'incomplet'),
            'pas un tableau',
        ));

        $this->assertSame(array(), $entrees);
    }

    public function test_should_keep_only_recent_days(): void
    {
        $jours = MonClubTT_PongDefis::purger(array(
            '2026-11-16' => array(), '2026-11-17' => array(), '2026-11-23' => array(), '2026-11-24' => array(), 'n/a' => array(),
        ), '2026-11-23');

        $this->assertSame(array('2026-11-17', '2026-11-23'), array_keys($jours));
    }

    public function test_should_hide_the_identification_key(): void
    {
        $publique = MonClubTT_PongDefis::publique($this->entree('v:zoe|abc', 11, 5, 64.37, 1, array('joueur' => 'Zoé', 'visiteur' => true)));

        $this->assertSame(array('joueur' => 'Zoé', 'visiteur' => true, 'pj' => 11, 'pa' => 5, 'duree' => 64.4), $publique);
    }
}
