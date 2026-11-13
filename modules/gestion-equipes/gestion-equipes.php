<?php
/**
 * Module « Gestion des équipes » (ex-plugin TT Team Planner) : disponibilités
 * des joueurs, compositions d'équipes, brûlage, application du staff (PWA)
 * et liens magiques.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! function_exists( 'monclubtt_ge_charger' ) ) {

    /**
     * Le module peut-il être chargé ? Renvoie '' si oui, sinon 'ttp' :
     * TT Team Planner est encore actif (mêmes adresses, tâches en double).
     */
    function monclubtt_ge_blocage() {
        if ( defined( 'TTP_VERSION' ) ) {
            return 'ttp';
        }
        return '';
    }

    /** Message expliquant pourquoi le module n'est pas chargé (traduit : à appeler après init). */
    function monclubtt_ge_indisponible() {
        switch ( monclubtt_ge_blocage() ) {
            case 'ttp':
                return __( 'L\'extension TT Team Planner est encore active. Désactivez-la (sans la supprimer : sa suppression efface ses données), puis revenez ici pour importer ses données.', 'mon-club-tt' );
        }
        return '';
    }

    /**
     * Charge le module sur plugins_loaded : TT Team Planner, chargé après
     * Mon Club TT, a alors défini TTP_VERSION s'il est actif.
     */
    function monclubtt_ge_charger() {
        if ( monclubtt_ge_blocage() !== '' ) {
            add_action( 'admin_menu', 'monclubtt_ge_menu_indisponible', 15 );
            return;
        }

        $plugin = get_file_data( MONCLUBTT_PLUGIN_FILE, array( 'Version' => 'Version' ) );
        define( 'MONCLUBTT_GE_VERSION', $plugin['Version'] );
        define( 'MONCLUBTT_GE_DIR', __DIR__ . '/' );
        define( 'MONCLUBTT_GE_URL', plugin_dir_url( __FILE__ ) );
        define( 'MONCLUBTT_GE_FILE', MONCLUBTT_PLUGIN_FILE );

        foreach ( array(
            'Domain/Player.php',
            'Domain/Availability.php',
            'Domain/TeamComposition.php',
            'Domain/MagicLink.php',
            'Domain/PhaseSquad.php',
            'Domain/MatchAppearance.php',
            'Domain/BurnageStatus.php',
            'Domain/BurnageChecker.php',
            'Repository/PlayerRepository.php',
            'Repository/AvailabilityRepository.php',
            'Repository/TeamCompositionRepository.php',
            'Repository/MagicLinkRepository.php',
            'Repository/PhaseSquadRepository.php',
            'Repository/MatchAppearanceRepository.php',
            'Repository/ValidatedRoundRepository.php',
            'Repository/TeamForfeitRepository.php',
            'Admin/DataExporter.php',
            'Admin/DataImporter.php',
            'Admin/SettingsPage.php',
            'Sync/AppearanceImporter.php',
            'Rest/PhaseSquadController.php',
            'Rest/PlayersController.php',
            'Rest/AvailabilityController.php',
            'Rest/TeamsController.php',
            'Rest/MatchAppearanceController.php',
            'Mail/MagicLinkMailer.php',
            'Mail/AvailabilityReminder.php',
            'Rest/MagicLinkController.php',
            'Rest/SyncController.php',
            'Rest/SeasonController.php',
            'Rest/ManifestController.php',
            'Front/Shortcode.php',
            'Front/Assets.php',
            'Front/StandaloneTemplate.php',
            'Front/MagicLinkTemplate.php',
            'Activator.php',
            'Plugin.php',
        ) as $monclubtt_fichier ) {
            require_once __DIR__ . '/includes/' . $monclubtt_fichier;
        }

        \MonClubTT\GestionEquipes\Plugin::getInstance();
    }

    /** Module non chargé : la page explique pourquoi, au lieu de disparaître du menu. */
    function monclubtt_ge_menu_indisponible() {
        add_submenu_page( MonClubTT_Constantes::MENU_PARENT, __( 'Gestion des équipes', 'mon-club-tt' ), __( 'Gestion des équipes', 'mon-club-tt' ), 'manage_options', 'monclubtt_gestion_equipes', function () {
            echo '<div class="wrap"><h1>' . esc_html__( 'Gestion des équipes', 'mon-club-tt' ) . '</h1>';
            echo '<div class="notice notice-warning inline"><p>' . esc_html( monclubtt_ge_indisponible() ) . '</p></div>';
            if ( monclubtt_ge_blocage() === 'ttp' ) {
                echo '<p><a class="button" href="' . esc_url( admin_url( 'plugins.php' ) ) . '">' . esc_html__( 'Aller aux extensions', 'mon-club-tt' ) . '</a></p>';
            }
            echo '</div>';
        } );
    }

    /** Désactivation de Mon Club TT : arrêt des tâches planifiées du module. */
    function monclubtt_ge_desactiver() {
        wp_clear_scheduled_hook( 'monclubtt_ge_send_availability_reminders' );
        wp_clear_scheduled_hook( 'monclubtt_ge_import_appearances' );
    }

    add_action( 'plugins_loaded', 'monclubtt_ge_charger', 5 );
}
