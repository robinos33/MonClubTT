<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Joueur;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la détection des licences validées pour la saison en cours,
 * à partir du champ 'validation' de xml_licence_b.php.
 */
final class JoueurTest extends TestCase
{
    public function test_should_be_validated_when_validation_date_present(): void
    {
        $joueur = new MonClubTT_Joueur(array('nom' => 'FOUCHET', 'pointm' => '1697', 'validation' => '23/07/2026'));

        $this->assertTrue($joueur->isLicenceValidee());
    }

    public function test_should_not_be_validated_when_validation_empty_xml_element(): void
    {
        // Un élément XML vide (<validation/>) est converti en tableau vide.
        $joueur = new MonClubTT_Joueur(array('nom' => 'RAILLARD', 'pointm' => '1957', 'validation' => array()));

        $this->assertFalse($joueur->isLicenceValidee());
    }

    public function test_should_not_be_validated_when_validation_missing_or_blank(): void
    {
        $this->assertFalse((new MonClubTT_Joueur(array('nom' => 'X')))->isLicenceValidee());
        $this->assertFalse((new MonClubTT_Joueur(array('nom' => 'X', 'validation' => '  ')))->isLicenceValidee());
    }
}
