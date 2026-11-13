<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Domain\Availability;
use MonClubTT\GestionEquipes\Front\Assets;
use MonClubTT\GestionEquipes\Front\MagicLinkTemplate;
use MonClubTT\GestionEquipes\Mail\MagicLinkMailer;
use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;
use MonClubTT\GestionEquipes\Repository\MagicLinkRepository;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;

/**
 * Endpoints publics accessibles via un lien magique (token à usage personnel,
 * envoyé par mail) : chaque token ne peut jamais lire ou écrire les données
 * d'un autre joueur que celui pour lequel il a été émis — le player_id est
 * TOUJOURS résolu depuis le token, jamais accepté depuis le corps de la requête.
 */
class MagicLinkController
{
    private const NS = 'monclubtt-equipes/v1';
    private const TOKEN_PATTERN = '[a-f0-9]{64}';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/magic-links/generate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'generate'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/magic-links/(?P<token>' . self::TOKEN_PATTERN . ')', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getContext'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/magic-links/(?P<token>' . self::TOKEN_PATTERN . ')/availability', [
            'methods'             => 'POST',
            'callback'            => [$this, 'setAvailability'],
            'permission_callback' => '__return_true',
        ]);
    }

    /**
     * Génère en masse des liens magiques pour les joueurs sélectionnés et
     * envoie un mail à chacun (ceux sans adresse mail sont ignorés, pas
     * en erreur — la sélection peut mélanger joueurs avec/sans email).
     */
    public function generate(WP_REST_Request $request): WP_REST_Response
    {
        $playerIds = array_map('intval', (array) $request->get_param('player_ids'));
        $ttlDays   = max(1, (int) ($request->get_param('ttl_days') ?: 90));
        $season    = sanitize_text_field($request->get_param('season') ?? Assets::computeSeason());

        $players = new PlayerRepository();
        $links   = new MagicLinkRepository();
        $mailer  = new MagicLinkMailer();
        $results = [];

        foreach ($playerIds as $playerId) {
            $player = $players->findById($playerId);
            if (! $player) {
                $results[] = ['player_id' => $playerId, 'status' => 'not_found'];
                continue;
            }
            if (empty($player->email) || ! is_email($player->email)) {
                $results[] = ['player_id' => $playerId, 'name' => $player->fullName(), 'status' => 'no_email'];
                continue;
            }

            $token = $links->create($playerId, $season, $ttlDays);
            $url   = MagicLinkTemplate::buildUrl($token);
            $sent  = $mailer->send($player->email, $player->firstName, $url, $ttlDays);

            $results[] = [
                'player_id' => $playerId,
                'name'      => $player->fullName(),
                'status'    => $sent ? 'sent' : 'mail_failed',
            ];
        }

        return new WP_REST_Response(['results' => $results], 200);
    }

    public function getContext(WP_REST_Request $request): WP_REST_Response
    {
        $link = (new MagicLinkRepository())->findValidByToken((string) $request['token']);
        if (! $link) {
            return new WP_REST_Response(['message' => __('Lien invalide ou expiré.', 'mon-club-tt')], 404);
        }

        $player = (new PlayerRepository())->findById($link->playerId);
        if (! $player) {
            return new WP_REST_Response(['message' => __('Joueur introuvable.', 'mon-club-tt')], 404);
        }

        $availabilities = (new AvailabilityRepository())->findByPlayer($link->playerId, $link->season);

        return new WP_REST_Response([
            'player'         => [
                'first_name' => $player->firstName,
                'last_name'  => $player->lastName,
            ],
            'season'         => $link->season,
            'club_name'      => \monclubtt_nom_club(),
            'journee_dates'  => [
                (array) get_option('monclubtt_ge_journee_dates_p1', array_fill(0, 7, '')),
                (array) get_option('monclubtt_ge_journee_dates_p2', array_fill(0, 7, '')),
            ],
            'availabilities' => array_map(fn($a) => $a->toArray(), $availabilities),
        ], 200);
    }

    public function setAvailability(WP_REST_Request $request): WP_REST_Response
    {
        $links = new MagicLinkRepository();
        $link  = $links->findValidByToken((string) $request['token']);
        if (! $link) {
            return new WP_REST_Response(['message' => __('Lien invalide ou expiré.', 'mon-club-tt')], 404);
        }

        $phase   = (int) $request->get_param('phase');
        $round   = (int) $request->get_param('round');
        $status  = sanitize_text_field($request->get_param('status') ?? 'unknown');
        $comment = sanitize_textarea_field($request->get_param('comment') ?? '');

        if (! in_array($status, Availability::VALID_STATUSES, true)) {
            return new WP_REST_Response(['message' => __('Statut invalide.', 'mon-club-tt')], 400);
        }

        // player_id et season proviennent EXCLUSIVEMENT du lien résolu ci-dessus,
        // jamais du corps de la requête : un token ne peut écrire que pour lui-même.
        (new AvailabilityRepository())->save($link->playerId, $link->season, $phase, $round, $status, $comment);
        $links->markUsed($link->id);

        return new WP_REST_Response(['success' => true], 200);
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_posts');
    }
}
