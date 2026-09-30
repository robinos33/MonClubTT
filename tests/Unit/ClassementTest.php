<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Classement;
use PHPUnit\Framework\TestCase;

/**
 * Tests du calcul des points et progressions depuis xml_licence_b.php,
 * où un élément XML vide (<pointm/>) arrive sous forme de tableau vide.
 */
final class ClassementTest extends TestCase
{
    public function test_should_compute_progressions_when_all_points_present(): void
    {
        $classement = new MonClubTT_Classement(array('point' => '1217', 'pointm' => '1230', 'apointm' => '1210', 'initm' => '1200'));

        $this->assertSame(1230.0, $classement->getPointsMensuels());
        $this->assertSame(1217.0, $classement->getPointsOfficiels());
        $this->assertSame(20.0, $classement->getProgressionMensuelle());
        $this->assertSame(30.0, $classement->getProgressionAnnuelle());
        $this->assertSame(12, $classement->getClassementOfficiel());
    }

    public function test_should_fallback_to_official_points_when_monthly_points_empty(): void
    {
        // Joueur arrivé au club : points officiels mais pas encore de points mensuels.
        $classement = new MonClubTT_Classement(array('point' => '718', 'pointm' => array(), 'apointm' => array(), 'initm' => array()));

        $this->assertSame(718.0, $classement->getPointsMensuels());
        $this->assertSame(7, $classement->getClassementOfficiel());
    }

    public function test_should_have_no_progression_when_comparison_base_missing(): void
    {
        $classement = new MonClubTT_Classement(array('point' => '718', 'pointm' => array(), 'apointm' => array(), 'initm' => array()));

        $this->assertSame(0.0, $classement->getProgressionMensuelle());
        $this->assertSame(0.0, $classement->getProgressionAnnuelle());
    }

    public function test_should_have_zero_points_when_no_points_at_all(): void
    {
        $classement = new MonClubTT_Classement(array('point' => array(), 'pointm' => array()));

        $this->assertSame(0.0, $classement->getPointsMensuels());
        $this->assertSame('', $classement->getClassementOfficiel());
    }
}
