<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Constantes;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la normalisation des couleurs du club (primaire, secondaire,
 * fond), utilisées par le podium du site et les visuels réseaux sociaux.
 */
final class CouleursTest extends TestCase
{
    public function test_should_keep_colors_when_valid_hex(): void
    {
        $couleurs = monclubtt_normaliser_couleurs(array(
            'primaire'   => '#0F6E4C',
            'secondaire' => '#c8103e',
            'fond'       => '#f4f1ea',
        ));

        $this->assertSame(array('primaire' => '#0f6e4c', 'secondaire' => '#c8103e', 'fond' => '#f4f1ea'), $couleurs);
    }

    public function test_should_fallback_to_default_when_color_invalid(): void
    {
        $defaut   = MonClubTT_Constantes::COULEURS_DEFAUT;
        $couleurs = monclubtt_normaliser_couleurs(array(
            'primaire'   => 'red',
            'secondaire' => '#c8103e;background:url(x)',
            'fond'       => array('#fff'),
        ));

        $this->assertSame($defaut, $couleurs);
    }

    public function test_should_return_defaults_when_value_not_array(): void
    {
        $this->assertSame(MonClubTT_Constantes::COULEURS_DEFAUT, monclubtt_normaliser_couleurs(false));
    }

    public function test_should_ignore_unknown_keys_when_normalizing(): void
    {
        $couleurs = monclubtt_normaliser_couleurs(array('primaire' => '#112233', 'autre' => '#445566'));

        $this->assertSame(array('primaire', 'secondaire', 'fond'), array_keys($couleurs));
        $this->assertSame('#112233', $couleurs['primaire']);
    }
}
