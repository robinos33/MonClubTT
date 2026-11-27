<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_PongConcours;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PongConcours.php';

/**
 * Tests du concours du mois du jeu de pong : période, barème
 * FFTT, cotes des joueurs et des adversaires, abandons et limite par jour.
 */
final class PongConcoursTest extends TestCase
{
    private const JOUR = '2026-11-27';

    private function joueur(string $cle = 'L1', float $points = 1000.0): array
    {
        return array('cle' => $cle, 'affiche' => 'Joueur ' . $cle, 'points' => $points);
    }

    private function adv(string $cle = 'club:A|normal', float $depart = 1000.0): array
    {
        return array('cle' => $cle, 'affiche' => 'Adv', 'depart' => $depart);
    }

    /** Lance puis termine un match, renvoie l'état. */
    private function jouer(array $etat, string $jeton, array $joueur, array $adv, bool $victoire, int $t = 1000, string $jour = self::JOUR): array
    {
        $lance = MonClubTT_PongConcours::lancer($etat, $jeton, $joueur, $adv, 'ip', $jour, $t);
        return MonClubTT_PongConcours::terminer($lance['etat'], $jeton, $joueur['cle'], $adv['cle'], $victoire, $t + 60)['etat'];
    }

    public function test_should_compute_monthly_period(): void
    {
        $this->assertSame('2026-11', MonClubTT_PongConcours::periode(2026, 11));
        $this->assertSame('2027-01', MonClubTT_PongConcours::periode(2027, 1));
    }

    public function test_should_apply_fftt_scale(): void
    {
        // Écart 0-24 : 6 / -5 dans tous les cas.
        $this->assertSame(6.0, MonClubTT_PongConcours::variation(1000, 1010, true));
        $this->assertSame(-5.0, MonClubTT_PongConcours::variation(1010, 1000, false));
        // Écart 100-149 : victoire normale 4, perf 10, défaite normale -3, contre -8.
        $this->assertSame(4.0, MonClubTT_PongConcours::variation(1120, 1000, true));
        $this->assertSame(10.0, MonClubTT_PongConcours::variation(1000, 1120, true));
        $this->assertSame(-3.0, MonClubTT_PongConcours::variation(1000, 1120, false));
        $this->assertSame(-8.0, MonClubTT_PongConcours::variation(1120, 1000, false));
        // Écart 500 et plus.
        $this->assertSame(40.0, MonClubTT_PongConcours::variation(500, 2000, true));
        $this->assertSame(0.0, MonClubTT_PongConcours::variation(500, 2000, false));
    }

    public function test_should_start_opponent_rating_from_points_level_and_world_rank(): void
    {
        $this->assertSame(1200.0, MonClubTT_PongConcours::coteDepartAdversaire('club', 1200, 'normal'));
        $this->assertSame(1500.0, MonClubTT_PongConcours::coteDepartAdversaire('club', 1200, 'mondial'));
        $this->assertSame(500.0, MonClubTT_PongConcours::coteDepartAdversaire('club', null, 'normal'));
        $this->assertSame(3000.0, MonClubTT_PongConcours::coteDepartAdversaire('monde', null, 'normal'));
    }

    public function test_should_move_both_ratings_after_a_match(): void
    {
        $etat = $this->jouer(MonClubTT_PongConcours::vide('2026-11'), 't1', $this->joueur(), $this->adv(), true);

        $this->assertSame(1006.0, $etat['joueurs']['L1']['cote']);
        $this->assertSame(995.0, $etat['adversaires']['club:A|normal']['cote']);
        $this->assertSame(1, $etat['joueurs']['L1']['victoires']);
        $this->assertSame(array(), $etat['en_cours']);
    }

    public function test_should_make_a_beaten_strong_opponent_lose_rating(): void
    {
        // Le joueur faible bat le plus fort : perf pour lui, contre pour l'adversaire.
        $etat = $this->jouer(MonClubTT_PongConcours::vide('s'), 't1', $this->joueur('L1', 600), $this->adv('club:B|normal', 2000), true);

        $this->assertSame(640.0, $etat['joueurs']['L1']['cote']);
        $this->assertSame(1971.0, $etat['adversaires']['club:B|normal']['cote']);
    }

    public function test_should_reject_end_with_unknown_token_or_other_player(): void
    {
        $lance = MonClubTT_PongConcours::lancer(MonClubTT_PongConcours::vide('s'), 't1', $this->joueur(), $this->adv(), 'ip', self::JOUR, 1000);

        $this->assertNull(MonClubTT_PongConcours::terminer($lance['etat'], 'inconnu', 'L1', 'club:A|normal', true, 1060)['resultat']);
        $this->assertNull(MonClubTT_PongConcours::terminer($lance['etat'], 't1', 'L2', 'club:A|normal', true, 1060)['resultat']);
        $this->assertNull(MonClubTT_PongConcours::terminer($lance['etat'], 't1', 'L1', 'club:Z|normal', true, 1060)['resultat']);

        // 11-5 déclaré 5 secondes après le lancement : impossible, le match reste en cours.
        $tropTot = MonClubTT_PongConcours::terminer($lance['etat'], 't1', 'L1', 'club:A|normal', true, 1005, 16);
        $this->assertNull($tropTot['resultat']);
        $this->assertArrayHasKey('t1', $tropTot['etat']['en_cours']);

        $fin = MonClubTT_PongConcours::terminer($lance['etat'], 't1', 'L1', 'club:A|normal', true, 1060, 16);
        $this->assertSame(6.0, $fin['resultat']['variation']);
        $this->assertSame(6.0, $fin['resultat']['progression']);
        $this->assertSame(1006.0, $fin['resultat']['cote']);
    }

    public function test_should_count_abandoned_match_as_defeat_after_delay(): void
    {
        $lance = MonClubTT_PongConcours::lancer(MonClubTT_PongConcours::vide('s'), 't1', $this->joueur(), $this->adv(), 'ip', self::JOUR, 1000);

        $etat = MonClubTT_PongConcours::expirer($lance['etat'], 1000 + MonClubTT_PongConcours::DELAI_ABANDON);
        $this->assertArrayHasKey('t1', $etat['en_cours']);

        $etat = MonClubTT_PongConcours::expirer($etat, 1001 + MonClubTT_PongConcours::DELAI_ABANDON);
        $this->assertSame(array(), $etat['en_cours']);
        $this->assertSame(995.0, $etat['joueurs']['L1']['cote']);
        $this->assertSame(1, $etat['joueurs']['L1']['matchs']);
        // Trop tard pour déclarer la victoire.
        $this->assertNull(MonClubTT_PongConcours::terminer($etat, 't1', 'L1', 'club:A|normal', true, 5000)['resultat']);
    }

    public function test_should_count_previous_unfinished_match_from_same_device_as_lost(): void
    {
        $etat = MonClubTT_PongConcours::lancer(MonClubTT_PongConcours::vide('s'), 't1', $this->joueur(), $this->adv(), 'ip', self::JOUR, 1000)['etat'];
        // Même joueur depuis un autre appareil : le premier match reste en cours.
        $etat = MonClubTT_PongConcours::lancer($etat, 't2', $this->joueur(), $this->adv(), 'autre-ip', self::JOUR, 1010)['etat'];
        $this->assertCount(2, $etat['en_cours']);
        // Même appareil : le match laissé en plan est perdu.
        $etat = MonClubTT_PongConcours::lancer($etat, 't3', $this->joueur(), $this->adv(), 'ip', self::JOUR, 1020)['etat'];

        $this->assertSame(array('t2', 't3'), array_keys($etat['en_cours']));
        $this->assertSame(1, $etat['joueurs']['L1']['matchs']);
        $this->assertSame(995.0, $etat['joueurs']['L1']['cote']);
    }

    public function test_should_rate_only_daily_quota_of_matches_per_player(): void
    {
        $etat = MonClubTT_PongConcours::vide('s');
        for ($i = 0; $i < MonClubTT_PongConcours::MATCHS_PAR_JOUR; $i++) {
            $lance = MonClubTT_PongConcours::lancer($etat, 't' . $i, $this->joueur(), $this->adv(), 'ip' . $i, self::JOUR, 1000);
            $this->assertTrue($lance['classe']);
            $this->assertSame(MonClubTT_PongConcours::MATCHS_PAR_JOUR - $i - 1, $lance['restants']);
            $etat = $lance['etat'];
        }

        $refus = MonClubTT_PongConcours::lancer($etat, 'tx', $this->joueur(), $this->adv(), 'ip9', self::JOUR, 1000);
        $this->assertFalse($refus['classe']);
        $this->assertArrayNotHasKey('tx', $refus['etat']['en_cours']);

        // Le lendemain, le compteur repart.
        $this->assertTrue(MonClubTT_PongConcours::lancer($etat, 'ty', $this->joueur(), $this->adv(), 'ip9', '2026-11-28', 90000)['classe']);
    }

    public function test_should_rank_by_progression_after_minimum_matches(): void
    {
        $etat = MonClubTT_PongConcours::vide('s');
        $t = 0;
        // L1 (1500 pts) et L2 (800 pts, perfs) : MATCHS_MIN victoires. L3 : un match de moins.
        $min = MonClubTT_PongConcours::MATCHS_MIN;
        foreach (array(array('L1', 1500.0, $min), array('L2', 800.0, $min), array('L3', 900.0, $min - 1)) as list($cle, $pts, $n)) {
            for ($i = 0; $i < $n; $i++) {
                $t += 100000; // un jour sur deux : pas de limite quotidienne
                $etat = $this->jouer($etat, $cle . $i, $this->joueur($cle, $pts), $this->adv('club:' . $cle . $i . '|normal', 1000), true, $t, 'j' . $t);
            }
        }

        $classement = MonClubTT_PongConcours::classer($etat);
        $this->assertSame(array('L2', 'L1'), array_column($classement, 'cle'));
        $this->assertSame(2, MonClubTT_PongConcours::rang($etat, 'L1'));
        $this->assertNull(MonClubTT_PongConcours::rang($etat, 'L3'));
        $this->assertArrayNotHasKey('cle', MonClubTT_PongConcours::publique($classement[0]));
    }

    public function test_should_reset_players_on_new_month_keep_opponents_and_return_podium(): void
    {
        $etat = MonClubTT_PongConcours::vide('2026-10');
        for ($i = 0; $i < MonClubTT_PongConcours::MATCHS_MIN; $i++) {
            $etat = $this->jouer($etat, 't' . $i, $this->joueur(), $this->adv(), true, 1000 + $i * 100, 'j' . $i);
        }
        $etat = MonClubTT_PongConcours::lancer($etat, 'encours', $this->joueur(), $this->adv(), 'ip', 'jx', 5000)['etat'];

        $memeMois = MonClubTT_PongConcours::ouvrir($etat, '2026-10');
        $this->assertNull($memeMois['close']);
        $this->assertSame($etat['joueurs'], $memeMois['etat']['joueurs']);

        $nouveau = MonClubTT_PongConcours::ouvrir($etat, '2026-11');
        $this->assertSame('2026-11', $nouveau['etat']['periode']);
        $this->assertSame(array(), $nouveau['etat']['joueurs']);
        $this->assertSame(array(), $nouveau['etat']['en_cours']);
        // L'adversaire garde la cote acquise en octobre.
        $this->assertSame($etat['adversaires'], $nouveau['etat']['adversaires']);
        $this->assertLessThan(1000.0, $nouveau['etat']['adversaires']['club:A|normal']['cote']);
        $this->assertSame('2026-10', $nouveau['close']['periode']);
        $this->assertSame('Joueur L1', $nouveau['close']['podium'][0]['joueur']);

        $this->assertSame('2026-11', MonClubTT_PongConcours::ouvrir(null, '2026-11')['etat']['periode']);
    }

    public function test_should_remove_player_and_pending_matches(): void
    {
        $etat = MonClubTT_PongConcours::lancer(MonClubTT_PongConcours::vide('s'), 't1', $this->joueur(), $this->adv(), 'ip', self::JOUR, 1000)['etat'];
        $etat = MonClubTT_PongConcours::retirer($etat, 'L1');

        $this->assertSame(array(), $etat['joueurs']);
        $this->assertSame(array(), $etat['en_cours']);
    }
}
