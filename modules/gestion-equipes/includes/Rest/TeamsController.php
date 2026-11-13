<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;
use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;

class TeamsController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/compositions', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getCompositions'],
            'permission_callback' => [$this, 'canRead'],
        ]);

        register_rest_route(self::NS, '/compositions', [
            'methods'             => 'POST',
            'callback'            => [$this, 'setSlot'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/compositions', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'clearSlot'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

    }

    public function getCompositions(WP_REST_Request $request): WP_REST_Response
    {
        $season     = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase      = (int) ($request->get_param('phase') ?? get_option('monclubtt_ge_active_phase', 1));
        $roundParam = $request->get_param('round');

        $repo = new TeamCompositionRepository();

        if ($roundParam !== null) {
            // Journée spécifique
            $rows = $repo->findByRound($season, $phase, (int) $roundParam);
        } else {
            // Toute la phase (pour le dashboard)
            $rows = $repo->findByPhase($season, $phase);
        }

        return new WP_REST_Response(array_map(fn($c) => $c->toArray(), $rows), 200);
    }

    public function setSlot(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');
        $slot     = (int) $request->get_param('slot_number');
        $playerId = (int) $request->get_param('player_id');

        if ($slot < 1 || $slot > 4 || ! $playerId || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'mon-club-tt')], 400);
        }

        (new TeamCompositionRepository())->setSlot($season, $phase, $round, $teamCode, $slot, $playerId);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function clearSlot(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');
        $slot     = (int) $request->get_param('slot_number');

        (new TeamCompositionRepository())->clearSlot($season, $phase, $round, $teamCode, $slot);
        return new WP_REST_Response(['success' => true], 200);
    }

    public function canRead(): bool
    {
        return current_user_can('read');
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_posts');
    }
}
