<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes;

use MonClubTT\GestionEquipes\Admin\SettingsPage;
use MonClubTT\GestionEquipes\Front\Assets;
use MonClubTT\GestionEquipes\Front\MagicLinkTemplate;
use MonClubTT\GestionEquipes\Front\Shortcode;
use MonClubTT\GestionEquipes\Front\StandaloneTemplate;
use MonClubTT\GestionEquipes\Mail\AvailabilityReminder;
use MonClubTT\GestionEquipes\Sync\AppearanceImporter;
use MonClubTT\GestionEquipes\Rest\MatchAppearanceController;
use MonClubTT\GestionEquipes\Rest\PhaseSquadController;
use MonClubTT\GestionEquipes\Rest\PlayersController;
use MonClubTT\GestionEquipes\Rest\AvailabilityController;
use MonClubTT\GestionEquipes\Rest\MagicLinkController;
use MonClubTT\GestionEquipes\Rest\TeamsController;
use MonClubTT\GestionEquipes\Rest\SyncController;
use MonClubTT\GestionEquipes\Rest\SeasonController;
use MonClubTT\GestionEquipes\Rest\ManifestController;

final class Plugin
{
    private static ?Plugin $instance = null;

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        add_action('plugins_loaded', [Activator::class, 'maybeUpgrade']);
        add_action('init',           [$this, 'init']);
        add_action('rest_api_init',  [$this, 'registerRestRoutes']);

        // Relances de disponibilité : le handler doit être accroché à chaque
        // chargement (WP-Cron s'exécute dans une requête où rien d'autre du
        // plugin n'est sollicité), la planification se répare toute seule.
        add_action(AvailabilityReminder::CRON_HOOK, [$this, 'sendAvailabilityReminders']);
        add_action('init', [AvailabilityReminder::class, 'ensureScheduled']);

        // Import des rencontres jouées depuis les feuilles FFTT (MonClubTT),
        // même principe d'accroche et d'auto-réparation que les relances.
        add_action(AppearanceImporter::CRON_HOOK, [$this, 'importAppearances']);
        add_action('init', [AppearanceImporter::class, 'ensureScheduled']);

        // Les joueurs suivent automatiquement chaque synchronisation de Données FFTT.
        add_action('monclubtt_apres_synchro', [SyncController::class, 'syncPlayers']);

        if (is_admin()) {
            add_action('admin_menu', [$this, 'registerAdminMenu'], 15);
            add_action('admin_init', [$this, 'registerSettings']);
        }
    }

    public function init(): void
    {
        (new MagicLinkTemplate())->register();
        (new StandaloneTemplate())->register();

        // Le shortcode et les assets classiques restent pour la compatibilité
        // (éditeur de blocs, preview admin, etc.) mais la vraie page
        // est servie par StandaloneTemplate avant que le thème ne s'affiche.
        (new Shortcode())->register();
        (new Assets())->register();
    }

    public function sendAvailabilityReminders(): void
    {
        (new AvailabilityReminder())->run();
    }

    public function importAppearances(): void
    {
        (new AppearanceImporter())->run();
    }

    public function registerRestRoutes(): void
    {
        (new PhaseSquadController())->registerRoutes();
        (new PlayersController())->registerRoutes();
        (new AvailabilityController())->registerRoutes();
        (new TeamsController())->registerRoutes();
        (new MatchAppearanceController())->registerRoutes();
        (new SyncController())->registerRoutes();
        (new SeasonController())->registerRoutes();
        (new MagicLinkController())->registerRoutes();
        (new ManifestController())->registerRoutes();
    }

    /** Entrée « Gestion des équipes » du menu Mon Club TT, après Données FFTT. */
    public function registerAdminMenu(): void
    {
        add_submenu_page(
            \MonClubTT_Constantes::MENU_PARENT,
            __('Gestion des équipes', 'mon-club-tt'),
            __('Gestion des équipes', 'mon-club-tt'),
            'manage_options',
            SettingsPage::SLUG,
            [new SettingsPage(), 'render']
        );
    }

    public function registerSettings(): void
    {
        (new SettingsPage())->registerSettings();
    }
}
