<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Domain\Availability;
use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;

class AvailabilityController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/availability', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getAvailability'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/availability', [
            'methods'             => 'POST',
            'callback'            => [$this, 'setAvailability'],
            'permission_callback' => [$this, 'canWrite'],
        ]);
    }

    public function getAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $season = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $repo   = new AvailabilityRepository();

        if ($request->get_param('phase') && $request->get_param('round')) {
            $rows = $repo->findByRound($season, (int) $request['phase'], (int) $request['round']);
        } else {
            $rows = $repo->findBySeason($season);
        }

        return new WP_REST_Response(array_map(fn($a) => $a->toArray(), $rows), 200);
    }

    public function setAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $playerId = (int) $request->get_param('player_id');
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $status   = sanitize_text_field($request->get_param('status') ?? 'unknown');
        $comment  = sanitize_textarea_field($request->get_param('comment') ?? '');

        if (! in_array($status, Availability::VALID_STATUSES, true)) {
            return new WP_REST_Response(['message' => __('Statut invalide.', 'mon-club-tt')], 400);
        }

        $repo = new AvailabilityRepository();
        $repo->save($playerId, $season, $phase, $round, $status, $comment);

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
