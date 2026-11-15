<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Appli;
use PHPUnit\Framework\TestCase;

/**
 * Appli d'équipe (écran d'accueil) : lecture des shortcodes, numéro et clé
 * d'équipe, noms affichés sous l'icône.
 */
final class AppliTest extends TestCase
{
    public function test_should_read_shortcode_attributes_whatever_the_quotes(): void
    {
        $contenu = 'Intro [monclubtt_equipe iddiv="123" idpoule=\'456\' nom_court=SM1] puis '
            . '[monclubtt_equipe iddiv=“789” idpoule=“12” appli="non"]';

        $this->assertSame(
            array(
                array('iddiv' => '123', 'idpoule' => '456', 'nom_court' => 'SM1'),
                array('iddiv' => '789', 'idpoule' => '12', 'appli' => 'non'),
            ),
            MonClubTT_Appli::shortcodesEquipe($contenu)
        );
    }

    public function test_should_ignore_other_shortcodes(): void
    {
        $this->assertSame(array(), MonClubTT_Appli::shortcodesEquipe('[monclubtt_equipes iddiv="1"] [monclubtt_joueurs]'));
        $this->assertSame(array(array()), MonClubTT_Appli::shortcodesEquipe('[monclubtt_equipe]'));
    }

    public function test_should_extract_team_number_without_phase(): void
    {
        $this->assertSame(15, MonClubTT_Appli::numeroEquipe('US TALENCE 15 - Phase 1'));
        $this->assertSame(3, MonClubTT_Appli::numeroEquipe('PESSAC TT 3'));
        $this->assertNull(MonClubTT_Appli::numeroEquipe('PESSAC TT - Phase 2'));
        $this->assertSame('US TALENCE 15', MonClubTT_Appli::libelleSansPhase('US TALENCE 15 - Phase 1'));
    }

    public function test_should_build_and_read_stable_team_key(): void
    {
        $this->assertSame('M3', MonClubTT_Appli::cleEquipe('M', 3));
        $this->assertSame('F1', MonClubTT_Appli::cleEquipe('F', 1));
        $this->assertSame(array('type' => 'F', 'numero' => 12), MonClubTT_Appli::lireCle('F12'));
        $this->assertNull(MonClubTT_Appli::lireCle('M0'));
        $this->assertNull(MonClubTT_Appli::lireCle('X3'));
        $this->assertNull(MonClubTT_Appli::lireCle('M3<script>'));
    }

    public function test_should_derive_acronym_from_club_name(): void
    {
        $this->assertSame('ASPTT', MonClubTT_Appli::sigle('ASPTT Bordeaux'));
        $this->assertSame('SBTT', MonClubTT_Appli::sigle('Stade Bordelais Tennis de Table'));
        $this->assertSame('', MonClubTT_Appli::sigle('Pessac'));
    }

    public function test_should_keep_short_name_within_android_limit(): void
    {
        $this->assertSame('US Talence 3', MonClubTT_Appli::nomCourt('US Talence', '3'));
        $this->assertSame('ASPTT 15', MonClubTT_Appli::nomCourt('ASPTT Bordeaux', '15'));
        $this->assertSame('SBTT D1', MonClubTT_Appli::nomCourt('Stade Bordelais Tennis de Table', 'D1'));
        $this->assertSame('Talence D1', MonClubTT_Appli::nomCourt('US Talence', 'D1'));
        $this->assertSame('Villenave 2', MonClubTT_Appli::nomCourt('Villenave', '2'));
        $this->assertSame('Villenaved 2', MonClubTT_Appli::nomCourt('Villenavedornon', '2'));
        $this->assertLessThanOrEqual(12, mb_strlen(MonClubTT_Appli::nomCourt('Saint-Médard-en-Jalles Pongistes Associés', '12')));
    }

    public function test_should_name_team_app_by_number_and_keep_club_in_full_name(): void
    {
        $identite = MonClubTT_Appli::identite(
            'US Talence',
            array('libequipe' => 'US TALENCE 3 - Phase 1', 'libdivision' => 'Pré-Régionale Messieurs Poule 2'),
            'M'
        );

        $this->assertSame('Équipe 3 · US Talence', $identite['nom']);
        $this->assertSame('Équipe 3', $identite['nom_court']);
        $this->assertSame('l’équipe 3', $identite['accroche']);
        $this->assertSame('M3', $identite['cle']);
        $this->assertSame('Résultats et classement de l’équipe 3 de US Talence en Pré-Régionale Messieurs Poule 2.', $identite['description']);
    }

    public function test_should_name_women_team_app(): void
    {
        $identite = MonClubTT_Appli::identite('ASPTT Bordeaux', array('libequipe' => 'ASPTT BORDEAUX 1', 'libdivision' => ''), 'F');

        $this->assertSame('Équipe Dames 1 · ASPTT Bordeaux', $identite['nom']);
        $this->assertSame('Dames 1', $identite['nom_court']);
        $this->assertSame('l’équipe Dames 1', $identite['accroche']);
        $this->assertSame('F1', $identite['cle']);
    }

    public function test_should_fall_back_to_page_app_without_team(): void
    {
        $identite = MonClubTT_Appli::identite('US Talence', null, 'M');

        $this->assertSame('US Talence · Résultats', $identite['nom']);
        $this->assertSame('US Talence', $identite['nom_court']);
        $this->assertSame('', $identite['cle']);
    }

    public function test_should_honour_names_forced_in_shortcode(): void
    {
        $equipe = array('libequipe' => 'US TALENCE 3', 'libdivision' => 'PR');

        $impose = MonClubTT_Appli::identite('US Talence', $equipe, 'M', array('nom_appli' => 'Les Requins', 'nom_court' => 'Requins'));
        $this->assertSame('Les Requins', $impose['nom']);
        $this->assertSame('Requins', $impose['nom_court']);
        $this->assertSame('Les Requins', $impose['accroche']);
        $this->assertSame('M3', $impose['cle']);

        $long = MonClubTT_Appli::identite('US Talence', $equipe, 'M', array('nom_appli' => 'Les Requins de Talence'));
        $this->assertLessThanOrEqual(12, mb_strlen($long['nom_court']));
    }

    public function test_should_detect_app_disabled_by_attribute(): void
    {
        $this->assertTrue(MonClubTT_Appli::desactiveeParAttribut(array('appli' => 'NON')));
        $this->assertFalse(MonClubTT_Appli::desactiveeParAttribut(array('appli' => 'oui')));
        $this->assertFalse(MonClubTT_Appli::desactiveeParAttribut(array()));
    }
}
