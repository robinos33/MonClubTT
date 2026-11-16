<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Integration;

use MonClubTT_ClientMeta;
use MonClubTT_ErreurMeta;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/wp-http.php';
require_once __DIR__ . '/../../models/PublicationMeta.php';
require_once __DIR__ . '/../../models/ClientMeta.php';

/**
 * Client de l'API Graph avec des réponses HTTP simulées, comme le ferait un
 * filtre pre_http_request : succès Facebook et Instagram (photo seule et
 * carrousel), jeton expiré, conteneur Instagram en erreur.
 */
final class ClientMetaTest extends TestCase
{
    /** @var list<array{methode: string, url: string, body: array}> */
    private array $appels = [];

    protected function tearDown(): void
    {
        monclubtt_test_pre_http_request(null);
    }

    /**
     * Réponses selon « MÉTHODE chemin » (chemin Graph sans version), dans
     * l'ordre pour un même chemin si une liste est donnée.
     */
    private function simuler(array $reponses): void
    {
        $this->appels = [];
        monclubtt_test_pre_http_request(function (string $url, array $args) use (&$reponses) {
            $chemin = preg_replace('#^https://graph\.facebook\.com/v[\d.]+/#', '', strtok($url, '?'));
            $cle = $args['method'] . ' ' . $chemin;
            $this->appels[] = ['methode' => $args['method'], 'url' => $url, 'body' => $args['body'] ?? []];
            if (!isset($reponses[$cle])) {
                return ['response' => ['code' => 404], 'body' => '{"error":{"code":803,"message":"inattendu ' . $cle . '"}}'];
            }
            $r = is_array($reponses[$cle]) ? array_shift($reponses[$cle]) : $reponses[$cle];
            return ['response' => ['code' => str_contains($r, '"error"') ? 400 : 200], 'body' => $r];
        });
    }

    private function client(): MonClubTT_ClientMeta
    {
        return new MonClubTT_ClientMeta('111', '222', 'TOK', function () {});
    }

    public function test_should_publish_facebook_post_with_photos(): void
    {
        $this->simuler([
            'POST 111/photos' => ['{"id":"p1"}', '{"id":"p2"}'],
            'POST 111/feed'   => '{"id":"111_999"}',
        ]);

        $r = $this->client()->publierFacebook('Bravo', 'https://club.fr/a/', ['https://club.fr/1.jpg', 'https://club.fr/2.jpg']);

        $this->assertSame('111_999', $r['id']);
        $this->assertSame('https://www.facebook.com/111_999', $r['lien']);
        $this->assertCount(3, $this->appels);
        $this->assertSame('false', $this->appels[0]['body']['published']);
        $this->assertSame('{"media_fbid":"p2"}', $this->appels[2]['body']['attached_media[1]']);
    }

    public function test_should_publish_instagram_carousel_after_containers_finish(): void
    {
        $this->simuler([
            'POST 222/media'         => ['{"id":"c1"}', '{"id":"c2"}', '{"id":"car"}'],
            'GET c1'                 => ['{"status_code":"IN_PROGRESS"}', '{"status_code":"FINISHED"}'],
            'GET c2'                 => '{"status_code":"FINISHED"}',
            'GET car'                => '{"status_code":"FINISHED"}',
            'POST 222/media_publish' => '{"id":"m1"}',
            'GET m1'                 => '{"permalink":"https://www.instagram.com/p/abc/"}',
        ]);

        $r = $this->client()->publierInstagram('Légende', ['https://club.fr/1.jpg', 'https://club.fr/2.jpg']);

        $this->assertSame('m1', $r['id']);
        $this->assertSame('https://www.instagram.com/p/abc/', $r['lien']);
        $carrousel = $this->appels[5]['body'];
        $this->assertSame('CAROUSEL', $carrousel['media_type']);
        $this->assertSame('c1,c2', $carrousel['children']);
        $this->assertSame('car', $this->appels[7]['body']['creation_id']);
    }

    public function test_should_publish_single_instagram_photo_with_caption(): void
    {
        $this->simuler([
            'POST 222/media'         => '{"id":"c1"}',
            'GET c1'                 => '{"status_code":"FINISHED"}',
            'POST 222/media_publish' => '{"id":"m1"}',
            'GET m1'                 => '{"permalink":"https://www.instagram.com/p/x/"}',
        ]);

        $r = $this->client()->publierInstagram('Légende', ['https://club.fr/1.jpg']);

        $this->assertSame('https://www.instagram.com/p/x/', $r['lien']);
        $this->assertSame('Légende', $this->appels[0]['body']['caption']);
    }

    public function test_should_report_expired_token(): void
    {
        $this->simuler([
            'POST 111/feed' => '{"error":{"message":"Error validating access token: Session has expired","type":"OAuthException","code":190,"error_subcode":463}}',
        ]);

        try {
            $this->client()->publierFacebook('Bravo', 'https://club.fr/a/', []);
            $this->fail('Exception attendue');
        } catch (MonClubTT_ErreurMeta $e) {
            $this->assertTrue($e->jetonInvalide);
            $this->assertStringContainsString('jeton', $e->getMessage());
        }
    }

    public function test_should_report_instagram_container_error(): void
    {
        $this->simuler([
            'POST 222/media' => '{"id":"c1"}',
            'GET c1'         => '{"status_code":"ERROR","status":"Error: 2207009 aspect ratio"}',
        ]);

        $this->expectException(MonClubTT_ErreurMeta::class);
        $this->expectExceptionMessage('ratio');
        $this->client()->publierInstagram('Légende', ['https://club.fr/1.jpg']);
    }

    public function test_should_report_network_failure_and_missing_configuration(): void
    {
        monclubtt_test_pre_http_request(fn () => new \WP_Error('http_request_failed', 'cURL error 6'));
        try {
            $this->client()->publierFacebook('Bravo', '', []);
            $this->fail('Exception attendue');
        } catch (MonClubTT_ErreurMeta $e) {
            $this->assertStringContainsString('cURL error 6', $e->getMessage());
            $this->assertFalse($e->jetonInvalide);
        }

        $this->expectException(MonClubTT_ErreurMeta::class);
        $this->expectExceptionMessage('non configurée');
        (new MonClubTT_ClientMeta('111', '222', ''))->publierFacebook('x', '', []);
    }

    public function test_should_test_connection(): void
    {
        $this->simuler([
            'GET 111' => '{"id":"111","name":"US Talence TT"}',
            'GET 222' => '{"id":"222","username":"ustalence_tt"}',
        ]);

        $this->assertSame(['page' => 'US Talence TT', 'instagram' => 'ustalence_tt', 'erreur_instagram' => ''], $this->client()->tester());
        $this->assertStringContainsString('access_token=TOK', $this->appels[0]['url']);
    }

    public function test_should_validate_page_even_if_instagram_account_is_not_usable(): void
    {
        $this->simuler([
            'GET 111' => '{"id":"111","name":"US Talence TT"}',
            'GET 222' => '{"error":{"code":100,"message":"Object with ID 222 does not exist"}}',
        ]);

        $r = $this->client()->tester();
        $this->assertSame('US Talence TT', $r['page']);
        $this->assertSame('', $r['instagram']);
        $this->assertStringContainsString('introuvable', $r['erreur_instagram']);
    }
}
