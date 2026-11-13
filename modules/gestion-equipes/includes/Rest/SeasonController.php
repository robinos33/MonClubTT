<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Front\Assets;

/**
 * POST /monclubtt-equipes/v1/season/reset
 *
 * Supprime TOUTES les compositions et disponibilités d'une phase/saison.
 * Remet aussi les dates de la phase à zéro.
 *
 * Body JSON : { "phase": 1|2, "season": "2025-2026" }
 *
 * Sécurité : manage_options + nonce wp_rest (X-WP-Nonce header).
 * Le front demande une saisie "RESET" côté JS avant d'appeler cet endpoint.
 */
class SeasonController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/season/reset', [
            'methods'             => 'POST',
            'callback'            => [$this, 'reset'],
            'permission_callback' => [$this, 'canReset'],
            'args' => [
                'phase' => [
                    'required'          => true,
                    'type'              => 'integer',
                    'enum'              => [1, 2],
                    'sanitize_callback' => 'absint',
                ],
            ],
        ]);
    }

    public function reset(WP_REST_Request $request): WP_REST_Response
    {
        global $wpdb;

        $phase  = (int) $request->get_param('phase');
        $season = Assets::computeSeason();

        // Suppression des compositions
        $deleted_compos = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'monclubtt_ge_team_compositions',
            ['season' => $season, 'phase' => $phase],
            ['%s', '%d']
        );

        // Suppression des disponibilités
        $deleted_avail = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prefix . 'monclubtt_ge_availabilities',
            ['season' => $season, 'phase' => $phase],
            ['%s', '%d']
        );

        if ($deleted_compos === false || $deleted_avail === false) {
            return new WP_REST_Response(
                ['message' => __('Erreur base de donnees lors de la reinitialisation.', 'mon-club-tt')],
                500
            );
        }

        // Remise à zéro des dates de la phase
        update_option('monclubtt_ge_journee_dates_p' . $phase, array_fill(0, 7, ''));

        return new WP_REST_Response([
            'success'          => true,
            'deleted_compos'   => (int) $deleted_compos,
            'deleted_avail'    => (int) $deleted_avail,
            'message'          => sprintf(
                /* translators: 1: numéro de phase, 2: saison */
                __('Phase %1$d (%2$s) reinitialisee : %3$d composition(s) et %4$d disponibilite(s) supprimees.', 'mon-club-tt'),
                $phase,
                $season,
                (int) $deleted_compos,
                (int) $deleted_avail
            ),
        ], 200);
    }

    public function canReset(): bool
    {
        return current_user_can('manage_options');
    }
}
