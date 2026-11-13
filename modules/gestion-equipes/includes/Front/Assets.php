<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Front;

class Assets
{
    /** Max joueurs étrangers par composition — valeur fixe V1. */
    private const MAX_FOREIGN = 2;

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueFrontend']);
        add_action('wp_head',           [$this, 'injectManifestLink']);
        add_action('template_redirect',  [$this, 'maybeServeServiceWorker'], 0);
    }

    /**
     * Sert le service worker depuis la racine du site.
     *
     * Le scope d'un service worker est limité au dossier de son URL : servi
     * depuis /wp-content/plugins/…/assets/, il ne contrôlerait jamais la page
     * de l'application, et la consultation hors ligne ne marcherait pas.
     */
    public function maybeServeServiceWorker(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- asset public, pas de nonce applicable
        if (! isset($_GET['ttp_service_worker'])) {
            return;
        }

        $file = MONCLUBTT_GE_DIR . 'assets/service-worker.js';
        if (! is_readable($file)) {
            status_header(404);
            exit;
        }

        status_header(200);
        header('Content-Type: application/javascript; charset=utf-8');
        header('Service-Worker-Allowed: /');
        header('Cache-Control: no-cache');
        readfile($file); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- passthrough d'un asset du plugin, WP_Filesystem inutile ici
        exit;
    }

    /** URL du service worker, servie depuis la racine pour avoir un scope global. */
    public static function serviceWorkerUrl(): string
    {
        return add_query_arg([
            'ttp_service_worker' => '1',
            'base'               => (string) wp_parse_url(MONCLUBTT_GE_URL, PHP_URL_PATH),
            'v'                  => MONCLUBTT_GE_VERSION,
        ], home_url('/'));
    }

    public function enqueueFrontend(): void
    {
        global $post;
        if (! $post || ! has_shortcode($post->post_content, 'tt_team_planner')) {
            return;
        }

        wp_enqueue_style('ttp-app',  MONCLUBTT_GE_URL . 'assets/css/app.css', [], MONCLUBTT_GE_VERSION);
        wp_enqueue_script('ttp-app', MONCLUBTT_GE_URL . 'assets/js/app.js',  [], MONCLUBTT_GE_VERSION, true);
        wp_localize_script('ttp-app', 'TTPConfig', $this->buildConfig());
    }

    public function injectManifestLink(): void
    {
        global $post;
        if (! $post || ! has_shortcode($post->post_content, 'tt_team_planner')) {
            return;
        }
        if (! self::pwaEnabled()) {
            return;
        }
        echo '<link rel="manifest" href="' . esc_url(rest_url('monclubtt-equipes/v1/manifest')) . '">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url(self::pwaIconUrl()) . '">' . "\n";
    }

    public function buildConfig(): array
    {
        $season = self::computeSeason();
        $phase  = self::detectPhase();
        $teams  = get_option('monclubtt_ge_teams', []);

        return [
            'apiBase'           => rest_url('monclubtt-equipes/v1'),
            'nonce'             => wp_create_nonce('wp_rest'),
            'clubName'          => \monclubtt_nom_club(),
            'season'            => $season,
            'phase'             => $phase - 1, // 0-indexed pour le JS
            'teamsCount'        => count($teams),
            'teams'             => $teams,
            'journeeDates'      => [
                (array) get_option('monclubtt_ge_journee_dates_p1', array_fill(0, 7, '')),
                (array) get_option('monclubtt_ge_journee_dates_p2', array_fill(0, 7, '')),
            ],
            'version'           => MONCLUBTT_GE_VERSION,
            'maxForeignPlayers' => self::MAX_FOREIGN,
            'smsTemplates'      => [
                'availability' => get_option('monclubtt_ge_sms_template_availability', ''),
                'confirmation' => get_option('monclubtt_ge_sms_template_confirmation', ''),
            ],
            'pwaEnabled'        => self::pwaEnabled(),
            'swUrl'             => self::pwaEnabled() ? self::serviceWorkerUrl() : null,
        ];
    }

    // ─── Helpers statiques ────────────────────────────────────────────────────

    /**
     * Saison courante calculée depuis la date système.
     * Septembre = début de saison : 2025-09 → "2025-2026".
     */
    public static function computeSeason(): string
    {
        $year  = (int) current_time('Y');
        $month = (int) current_time('n');
        $start = $month >= 9 ? $year : $year - 1;
        return $start . '-' . ($start + 1);
    }

    /**
     * Phase active (1 ou 2) détectée depuis les dates saisies en BO.
     *
     * Règle :
     *  1. Si aujourd'hui est dans la plage [première date … dernière date] d'une phase → cette phase.
     *  2. Sinon, la phase dont la prochaine journée est la plus proche dans le futur.
     *  3. Fallback : phase 1.
     */
    public static function detectPhase(): int
    {
        $today = current_time('Y-m-d');

        // Passe 1 — phase en cours
        for ($p = 1; $p <= 2; $p++) {
            $dates = array_filter((array) get_option('monclubtt_ge_journee_dates_p' . $p, []));
            if (empty($dates)) {
                continue;
            }
            if ($today >= min($dates) && $today <= max($dates)) {
                return $p;
            }
        }

        // Passe 2 — prochaine phase à venir
        $bestTs    = null;
        $bestPhase = 1;
        $nowTs     = strtotime($today);

        for ($p = 1; $p <= 2; $p++) {
            $dates = array_filter((array) get_option('monclubtt_ge_journee_dates_p' . $p, []));
            foreach ($dates as $d) {
                $ts = strtotime((string) $d);
                if ($ts !== false && $ts >= $nowTs && ($bestTs === null || $ts < $bestTs)) {
                    $bestTs    = $ts;
                    $bestPhase = $p;
                }
            }
        }

        return $bestPhase;
    }

    /** Installation PWA activée en réglages — activée par défaut, le comportement historique. */
    public static function pwaEnabled(): bool
    {
        return get_option('monclubtt_ge_pwa_enabled', '1') === '1';
    }

    /** Icônes du manifest — le logo TT Team Planner livré avec le plugin. */
    public static function pwaIcons(): array
    {
        return [
            [
                'src'     => MONCLUBTT_GE_URL . 'assets/icons/icon-192.png',
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
            [
                'src'     => MONCLUBTT_GE_URL . 'assets/icons/icon-512.png',
                'sizes'   => '512x512',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ],
        ];
    }

    /** Icône pour apple-touch-icon : iOS ignore le manifest. */
    public static function pwaIconUrl(): string
    {
        return MONCLUBTT_GE_URL . 'assets/icons/icon-512.png';
    }
}
