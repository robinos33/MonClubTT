<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Front;

use MonClubTT\GestionEquipes\Repository\MagicLinkRepository;

/**
 * Sert une page minimale et autonome pour un lien magique de dispo joueur.
 *
 * Volontairement séparée de StandaloneTemplate (l'app "coach" complète) :
 * - pas de <link rel="manifest"> ni d'enregistrement de service worker,
 *   donc aucune installation PWA proposée sur cette page à usage unique ;
 * - pas de chargement de app.js (qui charge TOUTE la liste des joueurs) :
 *   un token n'a accès qu'aux données de son propre joueur.
 */
class MagicLinkTemplate
{
    private const QUERY_PARAM = 'monclubtt_dispo';

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeServeMagicLink']);
    }

    public static function buildUrl(string $token): string
    {
        return add_query_arg(self::QUERY_PARAM, $token, home_url('/'));
    }

    public function maybeServeMagicLink(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture d'un token à usage unique, pas une action à protéger par nonce
        $token = isset($_GET[self::QUERY_PARAM]) ? sanitize_text_field(wp_unslash($_GET[self::QUERY_PARAM])) : '';
        if ($token === '' || ! preg_match('/^[a-f0-9]{64}$/', $token)) {
            return;
        }

        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        $link = (new MagicLinkRepository())->findValidByToken($token);

        echo '<!DOCTYPE html><html lang="fr"><head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">';
        echo '<meta name="theme-color" content="#2563eb">';
        echo '<meta name="robots" content="noindex,nofollow">';
        echo '<title>' . esc_html__('Mes disponibilités', 'mon-club-tt') . '</title>';
        // Pas de <link rel="manifest"> ici : condition nécessaire pour que Chrome/Edge
        // ne proposent pas d'installer cette page comme application.
        echo '<style>*{box-sizing:border-box}body{margin:0;font-family:system-ui,-apple-system,sans-serif}</style>';
        echo '</head>';
        echo '<body style="margin:0;padding:0;background:#f5f7fb;min-height:100vh">';

        if (! $link) {
            echo '<div style="max-width:420px;margin:15vh auto 0;padding:24px;text-align:center;font-family:system-ui,sans-serif;color:#475569">' .
                '<div style="font-size:40px;margin-bottom:12px">⌛</div>' .
                '<h1 style="font-size:16px;margin:0 0 8px">' . esc_html__('Lien invalide ou expiré', 'mon-club-tt') . '</h1>' .
                '<p style="font-size:13px;line-height:1.5">' . esc_html__('Demande un nouveau lien à ton club.', 'mon-club-tt') . '</p>' .
                '</div>';
        } else {
            $jsUrl      = esc_url(MONCLUBTT_GE_URL . 'assets/js/magic-link.js?v=' . MONCLUBTT_GE_VERSION);
            $configJson = wp_json_encode([
                'apiBase' => rest_url('monclubtt-equipes/v1'),
                'token'   => $token,
            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);

            echo '<div id="ttp-magic-app" role="main"></div>';
            echo '<script>window.TTPMagicConfig = ' . $configJson . ';</script>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_json_encode() + flags anti-XSS
            echo '<script src="' . esc_url($jsUrl) . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- page standalone, hors thème
        }

        echo '</body></html>';
        exit;
    }
}
