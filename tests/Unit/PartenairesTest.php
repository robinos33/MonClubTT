<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Constantes;
use PHPUnit\Framework\TestCase;

/**
 * Tests des partenaires du club (Réglages) : normalisation de l'option.
 */
final class PartenairesTest extends TestCase
{
    public function test_should_return_empty_list_when_option_missing(): void
    {
        $this->assertSame(array(), monclubtt_normaliser_partenaires(null));
        $this->assertSame(array(), monclubtt_normaliser_partenaires('n\'importe quoi'));
    }

    public function test_should_keep_order_and_clean_fields(): void
    {
        $partenaires = monclubtt_normaliser_partenaires(array(
            array('logo' => '12', 'nom' => '  <b>Boulangerie</b>   Martin ', 'url' => 'https://boulangerie.example/'),
            array('logo' => 7, 'nom' => 'Garage', 'url' => ''),
        ));

        $this->assertSame(array(
            array('logo' => 12, 'nom' => 'Boulangerie Martin', 'url' => 'https://boulangerie.example/'),
            array('logo' => 7, 'nom' => 'Garage', 'url' => ''),
        ), $partenaires);
    }

    public function test_should_drop_rows_without_logo(): void
    {
        $partenaires = monclubtt_normaliser_partenaires(array(
            array('logo' => '', 'nom' => 'Sans logo'),
            array('logo' => '-4', 'nom' => 'Logo invalide'),
            'pas une ligne',
            array('logo' => '3', 'nom' => 'Gardé'),
        ));

        $this->assertCount(1, $partenaires);
        $this->assertSame('Gardé', $partenaires[0]['nom']);
    }

    public function test_should_keep_only_http_links(): void
    {
        $partenaires = monclubtt_normaliser_partenaires(array(
            array('logo' => 1, 'url' => 'javascript:alert(1)'),
            array('logo' => 2, 'url' => 'ftp://serveur.example/'),
            array('logo' => 3, 'url' => 'pas une adresse'),
            array('logo' => 4, 'url' => 'http://club.example/partenaires'),
        ));

        $this->assertSame(array('', '', '', 'http://club.example/partenaires'), array_column($partenaires, 'url'));
    }

    public function test_should_cap_the_number_of_partners(): void
    {
        $lignes = array();
        for ($i = 1; $i <= MonClubTT_Constantes::PARTENAIRES_MAX + 5; $i++) {
            $lignes[] = array('logo' => $i);
        }

        $this->assertCount(MonClubTT_Constantes::PARTENAIRES_MAX, monclubtt_normaliser_partenaires($lignes));
    }
}
