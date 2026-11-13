<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Front;

/**
 * Remplace le thème WordPress par une page HTML autonome
 * pour toute page contenant le shortcode [tt_team_planner].
 *
 * - Non connecté  → redirection vers wp-login.php (avec retour vers l'app)
 * - Connecté      → HTML minimal, zero chrome WordPress
 */
class StandaloneTemplate
{
    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeServeApp']);
    }

    public function maybeServeApp(): void
    {
        global $post;

        if (! $post || ! has_shortcode($post->post_content, 'tt_team_planner')) {
            return;
        }

        // Garde d'authentification — redirige vers login avec retour
        if (! is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(get_permalink((int) $post->ID)));
            exit;
        }

        $config      = (new Assets())->buildConfig();
        $configJson  = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP);
        $clubName    = esc_html(\monclubtt_nom_club());
        $cssUrl      = esc_url(MONCLUBTT_GE_URL . 'assets/css/app.css?v=' . MONCLUBTT_GE_VERSION);
        $jsUrl       = esc_url(MONCLUBTT_GE_URL . 'assets/js/app.js?v='  . MONCLUBTT_GE_VERSION);
        $pwaEnabled  = Assets::pwaEnabled();

        // On prend la main sur la réponse
        status_header(200);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- template standalone, toutes les variables sont esc_url()/esc_attr()/wp_json_encode()
        echo '<!DOCTYPE html><html lang="fr"><head>';
        echo '<meta charset="UTF-8">';
        echo '<meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">';
        echo '<meta name="theme-color" content="#2563eb">';
        echo '<meta name="robots" content="noindex,nofollow">';
        echo '<title>' . $clubName . ' — Gestion des équipes</title>';
        if ($pwaEnabled) {
            // iOS ignore le manifest : l'ajout à l'écran d'accueil ne marche
            // que via apple-touch-icon et les meta apple-mobile-web-app-*.
            echo '<link rel="manifest" href="' . esc_url(rest_url('monclubtt-equipes/v1/manifest')) . '">';
            echo '<link rel="apple-touch-icon" href="' . esc_url(Assets::pwaIconUrl()) . '">';
            echo '<meta name="apple-mobile-web-app-capable" content="yes">';
            echo '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">';
            echo '<meta name="apple-mobile-web-app-title" content="' . $clubName . '">';
        }
        echo '<link rel="stylesheet" href="' . $cssUrl . '">'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- app shell autonome, wp_enqueue_style() non applicable
        echo '</head>';
        echo '<body style="margin:0;padding:0;overflow:hidden;background:#f5f7fb;height:100dvh">';
        echo '<div id="ttp-app"></div>';
        echo '<script>window.TTPConfig = ' . $configJson . ';</script>';
        echo '<script src="' . $jsUrl . '"></script>'; // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- app shell autonome, wp_enqueue_script() non applicable
        echo '</body></html>';
        // phpcs:enable

        exit;
    }
}
