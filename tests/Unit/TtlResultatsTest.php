<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Durée de cache des résultats de poule : 30 minutes du samedi midi au
 * dimanche soir, renouvellement à 8 h / 13 h le reste de la semaine.
 */
final class TtlResultatsTest extends TestCase
{
    private const NORMAL = 5 * 3600;

    /** Horodatage « local » WordPress : la date du club lue comme UTC. */
    private static function heure(string $date): int
    {
        return (int) gmmktime(...self::parties($date));
    }

    /** @return array{0: int, 1: int, 2: int, 3: int, 4: int, 5: int} */
    private static function parties(string $date): array
    {
        [$jour, $heure] = explode(' ', $date);
        [$a, $m, $j] = array_map('intval', explode('-', $jour));
        [$h, $min] = array_map('intval', explode(':', $heure));
        return array($h, $min, 0, $m, $j, $a);
    }

    public function test_should_keep_normal_ttl_on_weekdays(): void
    {
        // Mercredi 18 novembre 2026, 14 h.
        $this->assertSame(self::NORMAL, monclubtt_ttl_resultats(self::heure('2026-11-18 14:00'), self::NORMAL));
    }

    public function test_should_cap_saturday_morning_cache_at_noon(): void
    {
        // Samedi 21 novembre 2026, 10 h 30 : le cache ne passe pas midi.
        $this->assertSame(90 * 60, monclubtt_ttl_resultats(self::heure('2026-11-21 10:30'), self::NORMAL));
        // Moins long que midi : inchangé.
        $this->assertSame(600, monclubtt_ttl_resultats(self::heure('2026-11-21 10:30'), 600));
    }

    public function test_should_use_30_minutes_from_saturday_noon(): void
    {
        $this->assertSame(1800, monclubtt_ttl_resultats(self::heure('2026-11-21 12:00'), self::NORMAL));
        $this->assertSame(1800, monclubtt_ttl_resultats(self::heure('2026-11-21 21:45'), self::NORMAL));
    }

    public function test_should_use_30_minutes_all_sunday(): void
    {
        $this->assertSame(1800, monclubtt_ttl_resultats(self::heure('2026-11-22 00:10'), self::NORMAL));
        $this->assertSame(1800, monclubtt_ttl_resultats(self::heure('2026-11-22 23:50'), self::NORMAL));
        // Renouvellement de 8 h plus proche que 30 minutes : on le garde.
        $this->assertSame(600, monclubtt_ttl_resultats(self::heure('2026-11-22 07:50'), 600));
    }

    public function test_should_go_back_to_normal_on_monday(): void
    {
        $this->assertSame(self::NORMAL, monclubtt_ttl_resultats(self::heure('2026-11-23 00:05'), self::NORMAL));
    }

    public function test_should_never_return_less_than_a_minute(): void
    {
        $this->assertSame(60, monclubtt_ttl_resultats(self::heure('2026-11-21 11:59'), self::NORMAL));
        $this->assertSame(60, monclubtt_ttl_resultats(self::heure('2026-11-18 14:00'), 0));
    }
}
