<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_PongImage;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PongImage.php';

/**
 * Tests de l'image de partage d'un match de pong (og:image), rendue avec GD.
 */
final class PongImageTest extends TestCase
{
    public function test_should_render_1200x630_png_without_photos(): void
    {
        if (!MonClubTT_PongImage::disponible()) {
            $this->markTestSkipped('GD avec FreeType indisponible.');
        }

        $png = MonClubTT_PongImage::rendre(array(
            'joueur'           => 'Louis CARRÈRE',
            'adversaire'       => 'Felix LEBRUN',
            'pj'               => 11,
            'pa'               => 9,
            'victoire'         => true,
            'niveau_libelle'   => 'Expert',
            'photo_joueur'     => '',
            'photo_adversaire' => '/chemin/inexistant.png',
            'club'             => 'Club de test',
            'site'             => 'exemple.fr',
            'couleurs'         => array('primaire' => '#2b7cb5', 'secondaire' => '#d34328', 'fond' => '#f4f1ea', 'maillot' => '#2b7cb5'),
            'police'           => __DIR__ . '/../../assets/fonts/LiberationSans-Bold.ttf',
        ));

        $this->assertIsString($png);
        $taille = getimagesizefromstring($png);
        $this->assertSame(array(1200, 630), array($taille[0], $taille[1]));
        $this->assertSame('image/png', $taille['mime']);
    }

    public function test_should_return_null_when_font_missing(): void
    {
        $this->assertNull(MonClubTT_PongImage::rendre(array('police' => '/chemin/inexistant.ttf')));
    }
}
