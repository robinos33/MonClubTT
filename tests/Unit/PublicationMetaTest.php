<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_PublicationMeta as M;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/PublicationMeta.php';

/**
 * Publication d'un article sur Facebook et Instagram : construction des
 * requêtes Graph, texte depuis l'article, messages d'erreur, historique.
 */
final class PublicationMetaTest extends TestCase
{
    private const G = 'https://graph.facebook.com/' . M::VERSION . '/';

    public function test_should_build_unpublished_facebook_photo_request(): void
    {
        $r = M::requetePhotoFacebook('123', 'TOK', 'https://club.fr/a.jpg');
        $this->assertSame('POST', $r['methode']);
        $this->assertSame(self::G . '123/photos', $r['url']);
        $this->assertSame(['url' => 'https://club.fr/a.jpg', 'published' => 'false', 'access_token' => 'TOK'], $r['params']);
    }

    public function test_should_attach_photos_and_append_link_to_facebook_message(): void
    {
        $r = M::requeteFilFacebook('123', 'TOK', 'Bravo à tous', 'https://club.fr/article/', ['p1', 'p2']);
        $this->assertSame(self::G . '123/feed', $r['url']);
        $this->assertSame("Bravo à tous\n\nhttps://club.fr/article/", $r['params']['message']);
        $this->assertSame('{"media_fbid":"p1"}', $r['params']['attached_media[0]']);
        $this->assertSame('{"media_fbid":"p2"}', $r['params']['attached_media[1]']);
        $this->assertArrayNotHasKey('link', $r['params']);
    }

    public function test_should_use_link_preview_without_photos(): void
    {
        $r = M::requeteFilFacebook('123', 'TOK', 'Bravo', 'https://club.fr/article/', []);
        $this->assertSame('https://club.fr/article/', $r['params']['link']);
        $this->assertSame('Bravo', $r['params']['message']);
    }

    public function test_should_not_duplicate_link_already_in_message(): void
    {
        $r = M::requeteFilFacebook('1', 'T', 'Voir https://club.fr/a/', 'https://club.fr/a/', ['p']);
        $this->assertSame('Voir https://club.fr/a/', $r['params']['message']);
    }

    public function test_should_build_instagram_single_and_carousel_requests(): void
    {
        $seule = M::requeteConteneurInstagram('ig', 'TOK', 'https://club.fr/a.jpg', 'Légende', false);
        $this->assertSame(self::G . 'ig/media', $seule['url']);
        $this->assertSame('Légende', $seule['params']['caption']);
        $this->assertArrayNotHasKey('is_carousel_item', $seule['params']);

        $item = M::requeteConteneurInstagram('ig', 'TOK', 'https://club.fr/a.jpg', 'Légende', true);
        $this->assertSame('true', $item['params']['is_carousel_item']);
        $this->assertArrayNotHasKey('caption', $item['params']);

        $carrousel = M::requeteCarrouselInstagram('ig', 'TOK', ['c1', 'c2'], 'Légende');
        $this->assertSame(['media_type' => 'CAROUSEL', 'children' => 'c1,c2', 'caption' => 'Légende', 'access_token' => 'TOK'], $carrousel['params']);

        $statut = M::requeteStatutInstagram('c9', 'TOK');
        $this->assertSame('GET', $statut['methode']);
        $this->assertSame(self::G . 'c9', $statut['url']);

        $pub = M::requetePublicationInstagram('ig', 'TOK', 'c9');
        $this->assertSame(self::G . 'ig/media_publish', $pub['url']);
        $this->assertSame('c9', $pub['params']['creation_id']);
    }

    public function test_should_extract_plain_text_from_article(): void
    {
        $contenu = "<!-- wp:paragraph -->\n<p>Bravo à <strong>tous</strong> !<br>Belle journée.</p>\n<!-- /wp:paragraph -->\n\n"
            . "<!-- wp:list -->\n<ul class=\"wp-block-list\"><!-- wp:list-item -->\n<li>Léa : +45&nbsp;pts</li>\n<!-- /wp:list-item -->"
            . "<!-- wp:list-item -->\n<li>Tom : +30 pts</li>\n<!-- /wp:list-item --></ul>\n<!-- /wp:list -->"
            . "<!-- wp:paragraph -->\n<p><a href=\"https://club.fr\">Venez l&#8217;essayer</a></p>\n<!-- /wp:paragraph -->";

        $this->assertSame(
            "Journée 5 : les perfs\n\nBravo à tous !\nBelle journée.\n\n• Léa : +45\u{a0}pts\n• Tom : +30 pts\n\nVenez l’essayer",
            M::texteArticle('Journée 5 : les perfs', $contenu)
        );
    }

    public function test_should_add_bio_mention_and_count_characters_like_instagram(): void
    {
        $this->assertSame("Bravo\n\n" . M::MENTION_BIO, M::texteInstagram('  Bravo '));
        $this->assertSame(3, M::longueur('é🏓a'));
    }

    public function test_should_explain_graph_errors(): void
    {
        $this->assertStringContainsString('jeton', M::messageErreur(['code' => 190, 'error_subcode' => 463, 'message' => 'Session has expired']));
        $this->assertTrue(M::jetonInvalide(['code' => 190]));
        $this->assertFalse(M::jetonInvalide(['code' => 100]));
        $this->assertStringContainsString('ratio', M::messageErreur(['code' => 36003, 'error_subcode' => 2207009]));
        $this->assertStringContainsString('JPEG', M::messageErreur(['code' => 9004, 'error_subcode' => 2207026]));
        $this->assertStringContainsString('relié', M::messageErreur(['code' => 10, 'message' => 'Permission denied']));
        $this->assertStringContainsString('introuvable', M::messageErreur(['code' => 100, 'message' => 'Object with ID 1 does not exist']));
        $this->assertSame('Erreur de l\'API Meta (code 1) : Oups', M::messageErreur(['code' => 1, 'message' => 'Oups']));
    }

    public function test_should_validate_selection_per_network(): void
    {
        $this->assertSame('', M::valider('facebook', 'Texte', []));
        $this->assertNotSame('', M::valider('facebook', '  ', []));
        $this->assertNotSame('', M::valider('instagram', 'Texte', []));
        $this->assertNotSame('', M::valider('instagram', str_repeat('a', 2201), ['1']));
        $this->assertSame('', M::valider('instagram', str_repeat('🏓', 2200), ['1']));
        $this->assertNotSame('', M::valider('facebook', 'x', range(1, 11)));
        $this->assertNotSame('', M::valider('x', 'x', []));
    }

    public function test_should_keep_history_and_find_last_success(): void
    {
        $h = M::ajouterHistorique([], ['reseau' => 'facebook', 'lien' => 'https://fb/1', 'date' => '2026-11-15 10:00:00', 'auteur' => 1]);
        $h = M::ajouterHistorique($h, ['reseau' => 'facebook', 'lien' => '', 'date' => '2026-11-15 12:00:00', 'auteur' => 1, 'erreur' => 'Jeton expiré']);
        $h = M::ajouterHistorique($h, ['reseau' => 'instagram', 'lien' => 'https://ig/1', 'date' => '2026-11-15 11:00:00', 'auteur' => 2]);

        $this->assertCount(3, $h);
        $this->assertSame('https://fb/1', M::dernierePublication($h, 'facebook')['lien']);
        $this->assertSame('https://ig/1', M::dernierePublication($h, 'instagram')['lien']);
        $this->assertNull(M::dernierePublication([], 'facebook'));

        for ($i = 0; $i < 60; $i++) {
            $h = M::ajouterHistorique($h, ['reseau' => 'facebook', 'date' => '2026-11-16']);
        }
        $this->assertCount(50, $h);
    }

    public function test_should_detect_public_urls(): void
    {
        $this->assertTrue(M::urlPublique('https://www.club-tt.fr/wp-content/uploads/a.jpg'));
        $this->assertFalse(M::urlPublique('http://localhost:8000/a.jpg'));
        $this->assertFalse(M::urlPublique('http://club.local/a.jpg'));
        $this->assertFalse(M::urlPublique('http://192.168.1.10/a.jpg'));
        $this->assertTrue(M::urlPublique('http://93.184.216.34/a.jpg'));
        $this->assertFalse(M::urlPublique('ftp://club.fr/a.jpg'));
    }
}
