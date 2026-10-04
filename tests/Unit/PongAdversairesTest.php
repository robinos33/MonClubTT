<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Constantes;
use PHPUnit\Framework\TestCase;

/**
 * Tests des adversaires du jeu de pong (top 10 mondial des réglages) :
 * normalisation de l'option et découpage des noms au format ITTF.
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
            'M' => array(array('nom' => '  <b>LEBRUN</b>   Felix ', 'photo' => '42'), array('nom' => 'X', 'photo' => '-3')),
        ));

        $this->assertSame(array('nom' => 'LEBRUN Felix', 'photo' => 42), $adversaires['M'][0]);
        $this->assertSame(0, $adversaires['M'][1]['photo']);
    }

    public function test_should_keep_rows_emptied_by_admin(): void
    {
        $adversaires = monclubtt_normaliser_adversaires(array('M' => array(array('nom' => '', 'photo' => ''))));

        $this->assertSame('', $adversaires['M'][0]['nom']);
        $this->assertSame('', $adversaires['M'][1]['nom']);
        $this->assertSame(MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT['F'][0], $adversaires['F'][0]['nom']);
    }

    public function test_should_split_ittf_name_into_last_and_first_name(): void
    {
        $this->assertSame(array('nom' => 'LIN', 'prenom' => 'Yun-Ju'), monclubtt_decouper_nom_joueur('LIN Yun-Ju'));
        $this->assertSame(array('nom' => 'ÖSTER', 'prenom' => 'Jon Erik'), monclubtt_decouper_nom_joueur('ÖSTER Jon Erik'));
    }

    public function test_should_keep_whole_name_when_not_ittf_format(): void
    {
        $this->assertSame(array('nom' => 'Felix Lebrun', 'prenom' => ''), monclubtt_decouper_nom_joueur('Felix Lebrun'));
    }
}
