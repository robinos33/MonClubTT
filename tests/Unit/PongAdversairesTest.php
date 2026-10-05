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
        $this->assertSame(array('nom' => MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT['F'][0], 'photo' => 0, 'pays' => 'CHN'), $adversaires['F'][0]);
        $this->assertSame('FRA', $adversaires['M'][1]['pays']);
    }

    public function test_should_clean_name_and_photo_id(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(array(
            'M' => array(array('nom' => '  <b>Felix</b>   LEBRUN ', 'photo' => '42', 'pays' => 'fra'), array('nom' => 'X', 'photo' => '-3')),
        ));

        $this->assertSame(array('nom' => 'Felix LEBRUN', 'photo' => 42, 'pays' => 'FRA'), $adversaires['M'][0]);
        $this->assertSame(0, $adversaires['M'][1]['photo']);
    }

    public function test_should_keep_rows_emptied_by_admin(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(array('M' => array(array('nom' => '', 'photo' => ''))));

        $this->assertSame('', $adversaires['M'][0]['nom']);
        $this->assertSame('', $adversaires['M'][1]['nom']);
        $this->assertSame(MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT['F'][0], $adversaires['F'][0]['nom']);
    }

    public function test_should_reject_unknown_country_and_keep_club_colors(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(array(
            'M' => array(array('nom' => 'WANG Chuqin', 'photo' => 0, 'pays' => 'XYZ'), array('nom' => 'Felix LEBRUN', 'photo' => 0, 'pays' => '')),
        ));

        $this->assertSame('', $adversaires['M'][0]['pays']);
        $this->assertSame('', $adversaires['M'][1]['pays']);
    }

    public function test_should_take_default_country_for_rows_saved_without_one(): void
    {
        // Option enregistrée avant l'arrivée du champ pays : le pays suit le nom.
        $adversaires = monclubtt_normaliser_adversaires(array(
            'M' => array(array('nom' => 'Truls MOREGARD', 'photo' => 0), array('nom' => 'Joueur Inconnu', 'photo' => 0)),
        ));

        $this->assertSame('SWE', $adversaires['M'][0]['pays']);
        $this->assertSame('', $adversaires['M'][1]['pays']);
    }
}
