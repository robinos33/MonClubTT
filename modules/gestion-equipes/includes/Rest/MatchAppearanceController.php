<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Domain\BurnageChecker;
use MonClubTT\GestionEquipes\Repository\MatchAppearanceRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;
use MonClubTT\GestionEquipes\Repository\TeamForfeitRepository;
use MonClubTT\GestionEquipes\Repository\ValidatedRoundRepository;
use MonClubTT\GestionEquipes\Sync\AppearanceImporter;

class MatchAppearanceController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/appearances/validate', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getValidationStatus'],
            'permission_callback' => [$this, 'canRead'],
        ]);

        register_rest_route(self::NS, '/appearances/validate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'validateRound'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/appearances/validate', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'unvalidateRound'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NS, '/appearances/import', [
            'methods'             => 'POST',
            'callback'            => [$this, 'importAppearances'],
            'permission_callback' => [$this, 'canManage'],
        ]);

        register_rest_route(self::NS, '/forfeits', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getForfeits'],
            'permission_callback' => [$this, 'canRead'],
        ]);

        register_rest_route(self::NS, '/forfeits', [
            'methods'             => 'POST',
            'callback'            => [$this, 'declareForfeit'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/forfeits', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'withdrawForfeit'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/burnage', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getBurnageStatus'],
            'permission_callback' => [$this, 'canRead'],
        ]);
    }

    public function getValidationStatus(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) ($request->get_param('phase') ?? get_option('monclubtt_ge_active_phase', 1));
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        if (! $season || ! $round || ! $teamCode) {
            return new WP_REST_Response(['validated' => false], 200);
        }

        $validated = (new ValidatedRoundRepository())->isValidated($season, $phase, $round, $teamCode);

        return new WP_REST_Response(['validated' => $validated], 200);
    }

    public function validateRound(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        if (! $season || ! $phase || ! $round || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'mon-club-tt')], 400);
        }

        $compositionRepo = new TeamCompositionRepository();
        $slots           = $compositionRepo->findByTeamAndRound($season, $phase, $round, $teamCode);
        $filled          = array_filter($slots, fn($slot) => $slot->playerId !== null);

        if (count($filled) === 0) {
            return new WP_REST_Response(['message' => __('Aucun joueur affecté pour cette équipe et cette journée.', 'mon-club-tt')], 400);
        }

        $teamRank = BurnageChecker::extractTeamRank($teamCode) ?? 0;
        $userId   = get_current_user_id() ?: null;

        (new MatchAppearanceRepository())->recordForRound($season, $phase, $round, $teamCode, $teamRank, $filled, $userId);
        (new ValidatedRoundRepository())->markValidated($season, $phase, $round, $teamCode, $userId);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function unvalidateRound(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) $request->get_param('phase');
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        if (! $season || ! $phase || ! $round || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'mon-club-tt')], 400);
        }

        (new MatchAppearanceRepository())->deleteForRound($season, $phase, $round, $teamCode);
        (new ValidatedRoundRepository())->unmarkValidated($season, $phase, $round, $teamCode);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function importAppearances(WP_REST_Request $request): WP_REST_Response
    {
        if (! AppearanceImporter::isAvailable()) {
            return new WP_REST_Response(
                ['message' => __('Import indisponible : MonClubTT 1.9.0 ou plus récent doit être activé.', 'mon-club-tt')],
                400
            );
        }

        $report = (new AppearanceImporter())->run();

        return new WP_REST_Response($report + [
            'message' => sprintf(
                /* translators: 1: nombre de participations, 2: nombre de journées */
                __('%1$d participation(s) importée(s) sur %2$d journée(s) d\'équipe.', 'mon-club-tt'),
                $report['imported'],
                count($report['rounds'])
            ),
        ], 200);
    }

    public function getForfeits(WP_REST_Request $request): WP_REST_Response
    {
        $season = sanitize_text_field($request->get_param('season') ?? '');
        $phase  = (int) $request->get_param('phase');

        if (! $season || ! $phase) {
            return new WP_REST_Response([], 200);
        }

        return new WP_REST_Response((new TeamForfeitRepository())->findByPhase($season, $phase), 200);
    }

    public function declareForfeit(WP_REST_Request $request): WP_REST_Response
    {
        [$season, $phase, $round, $teamCode] = $this->roundParams($request);
        if (! $season || ! $phase || ! $round || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'mon-club-tt')], 400);
        }

        (new TeamForfeitRepository())->declare($season, $phase, $round, $teamCode, get_current_user_id() ?: null);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function withdrawForfeit(WP_REST_Request $request): WP_REST_Response
    {
        [$season, $phase, $round, $teamCode] = $this->roundParams($request);
        if (! $season || ! $phase || ! $round || ! $teamCode) {
            return new WP_REST_Response(['message' => __('Paramètres invalides.', 'mon-club-tt')], 400);
        }

        (new TeamForfeitRepository())->withdraw($season, $phase, $round, $teamCode);

        return new WP_REST_Response(['success' => true], 200);
    }

    /** @return array{0: string, 1: int, 2: int, 3: string} */
    private function roundParams(WP_REST_Request $request): array
    {
        return [
            sanitize_text_field($request->get_param('season') ?? ''),
            (int) $request->get_param('phase'),
            (int) $request->get_param('round'),
            sanitize_text_field($request->get_param('team_code') ?? ''),
        ];
    }

    public function getBurnageStatus(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? get_option('monclubtt_ge_active_season', ''));
        $phase    = (int) ($request->get_param('phase') ?? get_option('monclubtt_ge_active_phase', 1));
        $round    = (int) $request->get_param('round');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');

        $playerIdsParam = (string) ($request->get_param('player_ids') ?? '');
        $playerIds      = array_filter(array_map('intval', explode(',', $playerIdsParam)));

        if (! $season || ! $round || ! $teamCode || ! $playerIds) {
            return new WP_REST_Response([], 200);
        }

        $checker = new BurnageChecker();
        $result  = [];
        foreach ($playerIds as $playerId) {
            $result[$playerId] = $checker->statusFor($season, $phase, $round, $teamCode, $playerId)->toArray();
        }

        return new WP_REST_Response($result, 200);
    }

    public function canRead(): bool
    {
        return current_user_can('read');
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_posts');
    }

    public function canManage(): bool
    {
        return current_user_can('manage_options');
    }
}
