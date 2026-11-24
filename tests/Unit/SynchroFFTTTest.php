<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use MonClubTT_SynchroFFTT;
use PHPUnit\Framework\TestCase;

/**
 * Synchro FFTT automatique : prochain passage d'un créneau hebdomadaire.
 */
final class SynchroFFTTTest extends TestCase
{
    private function paris(string $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date, new DateTimeZone('Europe/Paris'));
    }

    private function prochain(string $creneau, string $maintenant): string
    {
        [$jour, $heure, $minute] = MonClubTT_SynchroFFTT::CRENEAUX[$creneau];
        return MonClubTT_SynchroFFTT::prochainPassage($jour, $heure, $minute, $this->paris($maintenant))
            ->format('Y-m-d H:i T');
    }

    public function test_should_plan_sunday_evening_later_the_same_week(): void
    {
        // mardi 24 novembre 2026
        $this->assertSame('2026-11-29 20:00 CET', $this->prochain('dimanche-soir', '2026-11-24 10:00'));
    }

    public function test_should_plan_today_when_the_time_is_still_ahead(): void
    {
        $this->assertSame('2026-11-29 20:00 CET', $this->prochain('dimanche-soir', '2026-11-29 19:59'));
    }

    public function test_should_plan_next_week_once_the_time_is_reached(): void
    {
        $this->assertSame('2026-12-06 20:00 CET', $this->prochain('dimanche-soir', '2026-11-29 20:00'));
    }

    public function test_should_plan_monday_morning_right_after_sunday_evening(): void
    {
        $this->assertSame('2026-11-30 06:00 CET', $this->prochain('lundi-matin', '2026-11-29 20:05'));
    }

    public function test_should_keep_local_time_across_a_daylight_saving_change(): void
    {
        // passage à l'heure d'été le dimanche 28 mars 2027 à 2 h
        $this->assertSame('2027-03-28 20:00 CEST', $this->prochain('dimanche-soir', '2027-03-27 12:00'));
        $this->assertSame('2027-03-29 06:00 CEST', $this->prochain('lundi-matin', '2027-03-27 12:00'));
    }
}
