<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_PongModeration;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PongModeration.php';

/**
 * Tests de la modération du pong : termes interdits dans le prénom des
 * visiteurs, retrait des entrées existantes, quota de parties par IP.
 */
final class PongModerationTest extends TestCase
{
    public function test_should_normalise_case_accents_punctuation_and_repeated_letters(): void
    {
        $this->assertSame('elodie', MonClubTT_PongModeration::normaliser('Élo-DIE !'));
        $this->assertSame('conard', MonClubTT_PongModeration::normaliser('C o n n n a r d'));
        $this->assertSame('oeuf', MonClubTT_PongModeration::normaliser('Œuf'));
    }

    public function test_should_read_digits_and_symbols_as_the_letters_they_imitate(): void
    {
        $this->assertSame('salope', MonClubTT_PongModeration::normaliser('$4l0p3'));
        $this->assertSame('pute', MonClubTT_PongModeration::normaliser('pu7e'));
    }

    public function test_should_block_a_name_containing_a_term_in_disguise(): void
    {
        $termes = array('connard');

        $this->assertTrue(MonClubTT_PongModeration::interdit('C0nnnard', $termes));
        $this->assertTrue(MonClubTT_PongModeration::interdit('Le Con-nard du 33', $termes));
        $this->assertFalse(MonClubTT_PongModeration::interdit('Conrad', $termes));
    }

    public function test_should_block_anywhere_in_the_name_so_short_terms_block_more(): void
    {
        $this->assertTrue(MonClubTT_PongModeration::interdit('Conrad', array('con')));
    }

    public function test_should_accept_any_name_without_terms(): void
    {
        $this->assertFalse(MonClubTT_PongModeration::interdit('Kévin', array()));
        $this->assertFalse(MonClubTT_PongModeration::interdit('Kévin', array('', ' !! ')));
        $this->assertFalse(MonClubTT_PongModeration::interdit('', array('con')));
    }

    public function test_should_read_terms_one_per_line_or_comma_separated_without_duplicates(): void
    {
        $termes = MonClubTT_PongModeration::termes("connard\r\n  Salope ,  CONNARD\n\n c0nnard\n?!");

        $this->assertSame(array('connard', 'Salope'), $termes);
    }

    public function test_should_cut_terms_too_long(): void
    {
        $termes = MonClubTT_PongModeration::termes(str_repeat('a', 60) . 'b');

        $this->assertSame(array(str_repeat('a', MonClubTT_PongModeration::TERME_LONGUEUR)), $termes);
    }

    public function test_should_remove_visitor_entries_with_a_forbidden_name_from_a_list(): void
    {
        $entrees = array(
            array('joueur' => 'C0nnard', 'visiteur' => true),
            array('joueur' => 'Léa', 'visiteur' => true),
            array('joueur' => 'Jean CONNARD', 'visiteur' => false),
        );

        $resultat = MonClubTT_PongModeration::retirer($entrees, array('connard'));

        $this->assertSame(array(0), $resultat['retirees']);
        $this->assertSame(array($entrees[1], $entrees[2]), $resultat['entrees']);
    }

    public function test_should_keep_match_ids_and_check_entries_without_visitor_flag(): void
    {
        $matchs = array(
            'abc' => array('joueur' => 'Connard'),
            'def' => array('joueur' => 'Léa', 'visiteur' => true),
        );

        $resultat = MonClubTT_PongModeration::retirer($matchs, array('connard'));

        $this->assertSame(array('abc'), $resultat['retirees']);
        $this->assertSame(array('def' => $matchs['def']), $resultat['entrees']);
    }

    public function test_should_accept_matches_up_to_the_quota_then_refuse(): void
    {
        $etat = null;
        for ($i = 0; $i < 3; $i++) {
            $r = MonClubTT_PongModeration::quota($etat, 1000 + $i, 3);
            $this->assertTrue($r['autorise']);
            $etat = $r['etat'];
        }

        $r = MonClubTT_PongModeration::quota($etat, 1500, 3);

        $this->assertFalse($r['autorise']);
        $this->assertSame(1000 + MonClubTT_PongModeration::FENETRE, $r['reprise']);
        $this->assertSame(3, $r['etat']['n']);
    }

    public function test_should_open_a_new_window_once_the_previous_one_is_over(): void
    {
        $etat = array('debut' => 1000, 'n' => 3);

        $r = MonClubTT_PongModeration::quota($etat, 1000 + MonClubTT_PongModeration::FENETRE, 3);

        $this->assertTrue($r['autorise']);
        $this->assertSame(array('debut' => 1000 + MonClubTT_PongModeration::FENETRE, 'n' => 1), $r['etat']);
    }

    public function test_should_never_refuse_without_quota(): void
    {
        $r = MonClubTT_PongModeration::quota(array('debut' => 1000, 'n' => 500), 1001, 0);

        $this->assertTrue($r['autorise']);
    }
}
