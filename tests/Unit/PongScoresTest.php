<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_PongScores;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PongScores.php';

/**
 * Tests du tableau des meilleurs scores du jeu de pong : validité d'un score
 * de manche et classement des victoires.
 */
final class PongScoresTest extends TestCase
{
    public function test_should_accept_only_legal_set_scores(): void
    {
        $this->assertTrue(MonClubTT_PongScores::scoreValide(11, 0));
        $this->assertTrue(MonClubTT_PongScores::scoreValide(11, 9));
        $this->assertTrue(MonClubTT_PongScores::scoreValide(12, 10));
        $this->assertTrue(MonClubTT_PongScores::scoreValide(15, 13));
        $this->assertFalse(MonClubTT_PongScores::scoreValide(11, 10));
        $this->assertFalse(MonClubTT_PongScores::scoreValide(13, 10));
        $this->assertFalse(MonClubTT_PongScores::scoreValide(12, 9));
        $this->assertFalse(MonClubTT_PongScores::scoreValide(10, 8));
        $this->assertFalse(MonClubTT_PongScores::scoreValide(11, -1));
    }

    public function test_should_rank_by_level_then_margin_then_age(): void
    {
        $tableau = array();
        foreach (array(
            array('A', 'normal', 11, 2, 1),
            array('B', 'mondial', 11, 9, 2),
            array('C', 'normal', 11, 2, 3),
        ) as list($joueur, $niveau, $pj, $pa, $date)) {
            $resultat = MonClubTT_PongScores::ajouter($tableau, compact('joueur', 'niveau', 'pj', 'pa', 'date') + array('adversaire' => 'X'));
            $tableau  = $resultat['tableau'];
        }

        $this->assertSame(array('B', 'A', 'C'), array_column($tableau, 'joueur'));
        $this->assertSame(3, $resultat['rang']);
    }

    public function test_should_keep_ten_entries_and_report_null_rank_when_out(): void
    {
        $tableau = array();
        for ($i = 0; $i < 12; $i++) {
            $tableau = MonClubTT_PongScores::ajouter($tableau, array('joueur' => "J$i", 'adversaire' => 'X', 'niveau' => 'mondial', 'pj' => 11, 'pa' => 0, 'date' => $i))['tableau'];
        }
        $resultat = MonClubTT_PongScores::ajouter($tableau, array('joueur' => 'L', 'adversaire' => 'X', 'niveau' => 'normal', 'pj' => 11, 'pa' => 9, 'date' => 99));

        $this->assertCount(MonClubTT_PongScores::TAILLE, $resultat['tableau']);
        $this->assertNull($resultat['rang']);
    }

    public function test_should_drop_invalid_entries_when_ranking(): void
    {
        $tableau = MonClubTT_PongScores::classer(array(
            array('joueur' => 'A', 'adversaire' => 'X', 'niveau' => 'triche', 'pj' => 11, 'pa' => 0, 'date' => 1),
            array('joueur' => 'B', 'adversaire' => 'X', 'niveau' => 'normal', 'pj' => 30, 'pa' => 0, 'date' => 2),
            'pas un tableau',
        ));

        $this->assertSame(array(), $tableau);
    }
}
