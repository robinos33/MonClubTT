<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_FeuilleMatch;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests de la liste blanche de la feuille de match publique : seules les
 * rencontres affichées sur les pages d'équipe du club sont consultables.
 */
final class FeuilleMatchTest extends TestCase
{
    private const EQUIPES = array(
        array('libequipe' => 'Club 1', 'iddiv' => '123456', 'idpoule' => '789012'),
        array('libequipe' => 'Club 2', 'iddiv' => '123457', 'idpoule' => '789013'),
        array('libequipe' => 'Coupe', 'iddiv' => null, 'idpoule' => null),
    );

    private static function rencontres(): array
    {
        return array(
            array('libelle' => 'Tour 1', 'scorea' => '18', 'scoreb' => '24', 'lien' => 'renc_id=6595431&is_retour=0&clubnum_1=10330011'),
            array('libelle' => 'Tour 2', 'scorea' => '22', 'scoreb' => '20', 'lien' => 'renc_id=6595432&is_retour=1&clubnum_1=10330011'),
            // Rencontre à venir : la FFTT renvoie des scores vides (tableaux après conversion XML).
            array('libelle' => 'Tour 3', 'scorea' => array(), 'scoreb' => array(), 'lien' => 'renc_id=6595433&is_retour=0'),
            array('libelle' => 'Tour 4', 'scorea' => '', 'scoreb' => '', 'lien' => 'renc_id=6595434&is_retour=0'),
        );
    }

    // ── Format des paramètres ───────────────────────────────────────────

    public function test_should_accept_numeric_parameters(): void
    {
        $this->assertTrue(MonClubTT_FeuilleMatch::parametresValides('123456', '789012', '6595431', '0'));
        $this->assertTrue(MonClubTT_FeuilleMatch::parametresValides('123456', '789012', '6595431', '1'));
    }

    public static function parametresInvalides(): array
    {
        return array(
            'renc_id vide'            => array('123456', '789012', '', '0'),
            'renc_id non numérique'   => array('123456', '789012', '65a5431', '0'),
            'renc_id négatif'         => array('123456', '789012', '-6595431', '0'),
            'renc_id trop long'       => array('123456', '789012', '1234567890123', '0'),
            'iddiv manquant'          => array('', '789012', '6595431', '0'),
            'idpoule avec injection'  => array('123456', '789012"]', '6595431', '0'),
            'is_retour hors 0/1'      => array('123456', '789012', '6595431', '2'),
            'is_retour vide'          => array('123456', '789012', '6595431', ''),
            'renc_id non chaîne'      => array('123456', '789012', 6595431, '0'),
        );
    }

    #[DataProvider('parametresInvalides')]
    public function test_should_reject_invalid_parameters($iddiv, $idpoule, $rencId, $isRetour): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::parametresValides($iddiv, $idpoule, $rencId, $isRetour));
    }

    // ── Poule du club ───────────────────────────────────────────────────

    public function test_should_accept_pool_of_a_club_team(): void
    {
        $this->assertTrue(MonClubTT_FeuilleMatch::pouleDuClub(self::EQUIPES, '123457', '789013'));
    }

    public function test_should_reject_pool_of_another_club(): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::pouleDuClub(self::EQUIPES, '999999', '789012'));
        // Division et poule de deux équipes différentes.
        $this->assertFalse(MonClubTT_FeuilleMatch::pouleDuClub(self::EQUIPES, '123456', '789013'));
    }

    public function test_should_reject_pool_when_club_has_no_team(): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::pouleDuClub(array(), '123456', '789012'));
    }

    // ── Rencontre de la poule ───────────────────────────────────────────

    public function test_should_accept_played_match_of_the_pool(): void
    {
        $this->assertTrue(MonClubTT_FeuilleMatch::rencontreDeLaPoule(self::rencontres(), '6595431', 0));
        $this->assertTrue(MonClubTT_FeuilleMatch::rencontreDeLaPoule(self::rencontres(), '6595432', 1));
    }

    public function test_should_reject_match_absent_from_the_pool(): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::rencontreDeLaPoule(self::rencontres(), '1234567', 0));
    }

    public function test_should_reject_match_when_is_retour_differs(): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::rencontreDeLaPoule(self::rencontres(), '6595431', 1));
    }

    public function test_should_reject_match_not_played_yet(): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::rencontreDeLaPoule(self::rencontres(), '6595433', 0));
        $this->assertFalse(MonClubTT_FeuilleMatch::rencontreDeLaPoule(self::rencontres(), '6595434', 0));
    }

    public function test_should_reject_match_when_pool_is_empty(): void
    {
        $this->assertFalse(MonClubTT_FeuilleMatch::rencontreDeLaPoule(array(), '6595431', 0));
    }
}
