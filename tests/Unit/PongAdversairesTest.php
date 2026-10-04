<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Constantes;
use PHPUnit\Framework\TestCase;

/**
 * Tests des adversaires du jeu de pong (top 10 mondial des réglages) :
 * normalisation de l'option.
 */
final class PongAdversairesTest extends TestCase
{
    public function test_should_return_default_top10_when_option_missing(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(null);

        $this->assertCount(10, $adversaires['M']);
        $this->assertCount(10, $adversaires['F']);
        $this->assertSame(array('nom' => MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT['F'][0], 'photo' => 0), $adversaires['F'][0]);
    }

    public function test_should_clean_name_and_photo_id(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(array(
            'M' => array(array('nom' => '  <b>Felix</b>   LEBRUN ', 'photo' => '42'), array('nom' => 'X', 'photo' => '-3')),
        ));

        $this->assertSame(array('nom' => 'Felix LEBRUN', 'photo' => 42), $adversaires['M'][0]);
        $this->assertSame(0, $adversaires['M'][1]['photo']);
    }

    public function test_should_keep_rows_emptied_by_admin(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(array('M' => array(array('nom' => '', 'photo' => ''))));

        $this->assertSame('', $adversaires['M'][0]['nom']);
        $this->assertSame('', $adversaires['M'][1]['nom']);
        $this->assertSame(MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT['F'][0], $adversaires['F'][0]['nom']);
    }
}
