<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_ArticleSocial as A;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/ArticleSocial.php';

/**
 * Article créé depuis un visuel « Réseaux sociaux » : titres proposés,
 * conversion légende → contenu, texte alternatif et doublons.
 */
final class ArticleSocialTest extends TestCase
{
    public function test_should_propose_title_for_each_visual(): void
    {
        $this->assertSame('Top Progression – novembre 2026', A::titre('prog-mens', 'novembre 2026'));
        $this->assertSame('Top Progression (dames) – novembre 2026', A::titre('prog-mens', 'novembre 2026', null, 'F'));
        $this->assertSame('Top Progression – saison 2026–2027', A::titre('prog-ann', 'Saison 2026–2027'));
        $this->assertSame('Nouveaux paliers de points – novembre 2026', A::titre('paliers', 'novembre 2026'));
        $this->assertSame('Journée 5 : les perfs du week-end', A::titre('perfs', 'Week-end du 14–15 novembre 2026', 5));
        $this->assertSame('Journée 5 : les résultats des équipes', A::titre('resultats', 'x', 5));
        $this->assertSame('Journée 5 : les cartons pleins du week-end', A::titre('cartons', 'x', 5));
        $this->assertSame('Journée 5 : victoires à la belle et remontadas', A::titre('belles', 'x', 5));
    }

    public function test_should_fall_back_to_dates_when_round_is_unknown(): void
    {
        $this->assertSame('Les perfs du week-end – week-end du 14–15 novembre 2026', A::titre('perfs', 'Week-end du 14–15 novembre 2026'));
        $this->assertSame('Les résultats des équipes', A::titre('resultats', ''));
    }

    public function test_should_convert_caption_to_paragraphs(): void
    {
        $legende = "🏓 Top Progression — novembre 2026\r\n\r\n🥇 Léa MARTIN : +45 pts\n🥈 Tom <b>DURAND</b> : +30 pts\n\n\n  Bravo à eux ! 👏  ";
        $contenu = A::contenu($legende);

        $this->assertSame(3, substr_count($contenu, '<!-- wp:paragraph -->'));
        $this->assertStringContainsString('<p>🏓 Top Progression — novembre 2026</p>', $contenu);
        $this->assertStringContainsString('<p>🥇 Léa MARTIN : +45 pts<br>🥈 Tom &lt;b&gt;DURAND&lt;/b&gt; : +30 pts</p>', $contenu);
        $this->assertStringContainsString('<p>Bravo à eux ! 👏</p>', $contenu);
    }

    public function test_should_add_data_as_list_then_end_link(): void
    {
        $contenu = A::contenu('Bravo !', ['Léa MARTIN : +45 pts', '  ', 'Tom DURAND : +30 pts'], 'Le détail', 'https://club.fr/essai/?a=1&b="2"', "Venez faire l'essai");

        $this->assertStringContainsString('<h2 class="wp-block-heading">Le détail</h2>', $contenu);
        $this->assertSame(2, substr_count($contenu, '<li>'));
        $this->assertStringContainsString('<li>Léa MARTIN : +45 pts</li>', $contenu);
        $this->assertStringContainsString('<a href="https://club.fr/essai/?a=1&amp;b=&quot;2&quot;">Venez faire l\'essai</a>', $contenu);
        // Ordre : légende, liste, lien.
        $this->assertLessThan(strpos($contenu, '<ul'), strpos($contenu, 'Bravo !'));
        $this->assertLessThan(strpos($contenu, '<a href'), strpos($contenu, '</ul>'));
    }

    public function test_should_put_full_visual_first_as_image_block(): void
    {
        $contenu = A::contenu('Bravo', [], '', '', '', ['id' => 42, 'url' => 'https://club.fr/visuel.jpg', 'alt' => 'Podium "novembre"']);

        $this->assertStringStartsWith('<!-- wp:image {"id":42,"sizeSlug":"full","linkDestination":"none"} -->', $contenu);
        $this->assertStringContainsString('<img src="https://club.fr/visuel.jpg" alt="Podium &quot;novembre&quot;" class="wp-image-42"/>', $contenu);
        $this->assertLessThan(strpos($contenu, 'Bravo'), strpos($contenu, 'wp-image-42'));
        $this->assertStringNotContainsString('wp:image', A::contenu('Bravo', [], '', '', '', ['id' => 42, 'url' => 'javascript:x']));
    }

    public function test_should_skip_empty_list_and_unsafe_link(): void
    {
        $contenu = A::contenu('Texte', [], 'Le détail', 'javascript:alert(1)', 'Clic');
        $this->assertStringNotContainsString('<ul', $contenu);
        $this->assertStringNotContainsString('Le détail', $contenu);
        $this->assertStringNotContainsString('<a ', $contenu);
        $this->assertSame('', A::contenu(''));
    }

    public function test_should_build_alt_text_from_visual_and_data(): void
    {
        $this->assertSame(
            'Podium Top Progression de novembre 2026 : 1. Léa MARTIN : +45 pts, 2. Tom DURAND : +30 pts.',
            A::texteAlternatif('prog-mens', 'novembre 2026', ['Léa MARTIN : +45 pts', 'Tom DURAND : +30 pts'])
        );
        $this->assertSame(
            'Top perfs, journée 5 (week-end du 14–15 novembre 2026) : A, B, C, D, E et 2 autres.',
            A::texteAlternatif('perfs', 'Week-end du 14–15 novembre 2026', ['A', 'B', 'C', 'D', 'E', 'F', 'G'], 5)
        );
        $this->assertSame('Résultats des équipes, journée 2 (x)', A::texteAlternatif('resultats', 'x', [], 2));
    }

    public function test_should_validate_period_per_visual(): void
    {
        $this->assertTrue(A::periodeValide('prog-mens', '2026-11'));
        $this->assertFalse(A::periodeValide('prog-mens', '2026-13'));
        $this->assertTrue(A::periodeValide('prog-ann', '2026-2027'));
        $this->assertFalse(A::periodeValide('prog-ann', '2026-2028'));
        $this->assertTrue(A::periodeValide('perfs', '2026-11-15'));
        $this->assertFalse(A::periodeValide('perfs', '2026-02-30'));
        $this->assertFalse(A::periodeValide('inconnu', '2026-11'));
    }

    public function test_should_key_articles_by_visual_period_and_audience(): void
    {
        $this->assertSame('prog-mens|2026-11', A::cle('prog-mens', '2026-11', 'MF'));
        $this->assertSame('prog-mens|2026-11|F', A::cle('prog-mens', '2026-11', 'F'));
        $this->assertSame('perfs|2026-11-15', A::cle('perfs', '2026-11-15', 'F'));
    }

    public function test_should_detect_existing_article_outside_trash(): void
    {
        $articles = [
            ['id' => 1, 'cle' => 'perfs|2026-11-15', 'statut' => 'trash', 'date' => '2026-11-16 10:00:00'],
            ['id' => 2, 'cle' => 'perfs|2026-11-15', 'statut' => 'draft', 'date' => '2026-11-15 20:00:00'],
            ['id' => 3, 'cle' => 'perfs|2026-11-15', 'statut' => 'publish', 'date' => '2026-11-15 21:00:00'],
            ['id' => 4, 'cle' => 'cartons|2026-11-15', 'statut' => 'draft', 'date' => '2026-11-17 08:00:00'],
        ];

        $this->assertSame(3, A::doublon($articles, 'perfs|2026-11-15')['id']);
        $this->assertSame(4, A::doublon($articles, 'cartons|2026-11-15')['id']);
        $this->assertNull(A::doublon($articles, 'belles|2026-11-15'));
        $this->assertNull(A::doublon([$articles[0]], 'perfs|2026-11-15'));
    }
}
