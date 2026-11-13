<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Domain\Player;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;
use MonClubTT\GestionEquipes\Repository\PhaseSquadRepository;
use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;
use MonClubTT\GestionEquipes\Repository\MatchAppearanceRepository;

class PlayersController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/players', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPlayers'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getPlayer'],
            'permission_callback' => '__return_true',
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)', [
            'methods'             => 'PATCH',
            'callback'            => [$this, 'updatePlayer'],
            'permission_callback' => [$this, 'canWrite'],
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deletePlayer'],
            'permission_callback' => [$this, 'canWrite'],
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)/restore', [
            'methods'             => 'POST',
            'callback'            => [$this, 'restorePlayer'],
            'permission_callback' => [$this, 'canWrite'],
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);

        register_rest_route(self::NS, '/players/(?P<id>\d+)/permanent', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'deletePlayerPermanently'],
            'permission_callback' => [$this, 'canWrite'],
            'args' => ['id' => ['validate_callback' => fn($v) => is_numeric($v)]],
        ]);
    }

    public function getPlayers(WP_REST_Request $request): WP_REST_Response
    {
        $repo    = new PlayerRepository();
        $players = $repo->findAll();
        return new WP_REST_Response(array_map(fn($p) => $this->serialize($p), $players), 200);
    }

    public function getPlayer(WP_REST_Request $request): WP_REST_Response
    {
        $repo   = new PlayerRepository();
        $player = $repo->findById((int) $request['id']);

        if (! $player) {
            return new WP_REST_Response(['message' => __('Joueur introuvable.', 'mon-club-tt')], 404);
        }

        return new WP_REST_Response($this->serialize($player), 200);
    }

    public function updatePlayer(WP_REST_Request $request): WP_REST_Response
    {
        $repo  = new PlayerRepository();
        $notes = sanitize_textarea_field($request->get_param('notes') ?? '');
        $phone = sanitize_text_field($request->get_param('phone') ?? '');
        $email = sanitize_email($request->get_param('email') ?? '');
        $ok    = $repo->updateContactInfo((int) $request['id'], $phone, $notes, $email);

        if (! $ok) {
            return new WP_REST_Response(['success' => false], 500);
        }

        $player = $repo->findById((int) $request['id']);
        return new WP_REST_Response($this->serialize($player), 200);
    }

    /**
     * Suppression douce de l'effectif : le joueur n'est plus sélectionnable
     * dans les équipes mais son historique (compos, appearances) est conservé.
     */
    public function deletePlayer(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new PlayerRepository();
        $id   = (int) $request['id'];

        if (! $repo->findById($id)) {
            return new WP_REST_Response(['message' => __('Joueur introuvable.', 'mon-club-tt')], 404);
        }

        $ok = $repo->setActive($id, false);
        if (! $ok) {
            return new WP_REST_Response(['success' => false], 500);
        }

        // Retire le joueur de toute sélection encore modifiable : compositions
        // non validées et effectifs de phase. Les journées déjà validées ne
        // sont pas réécrites (voir clearPlayerFromUnvalidatedRounds) et
        // l'historique réel (match_appearances) n'est jamais touché.
        (new TeamCompositionRepository())->clearPlayerFromUnvalidatedRounds($id);
        (new PhaseSquadRepository())->removePlayerEverywhere($id);

        return new WP_REST_Response($this->serialize($repo->findById($id)), 200);
    }

    /**
     * Suppression définitive et irréversible : le joueur et tout son historique
     * (disponibilités, compositions y compris journées validées, présences en
     * match, effectifs de phase) sont effacés de la base. Contrairement à
     * deletePlayer(), aucune restauration n'est possible ensuite.
     */
    public function deletePlayerPermanently(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new PlayerRepository();
        $id   = (int) $request['id'];

        if (! $repo->findById($id)) {
            return new WP_REST_Response(['message' => __('Joueur introuvable.', 'mon-club-tt')], 404);
        }

        (new AvailabilityRepository())->deleteByPlayer($id);
        (new TeamCompositionRepository())->clearPlayerEverywhere($id);
        (new MatchAppearanceRepository())->deleteByPlayer($id);
        (new PhaseSquadRepository())->removePlayerEverywhere($id);

        $ok = $repo->delete($id);
        if (! $ok) {
            return new WP_REST_Response(['success' => false], 500);
        }

        return new WP_REST_Response(['success' => true, 'id' => $id], 200);
    }

    public function restorePlayer(WP_REST_Request $request): WP_REST_Response
    {
        $repo = new PlayerRepository();
        $id   = (int) $request['id'];

        if (! $repo->findById($id)) {
            return new WP_REST_Response(['message' => __('Joueur introuvable.', 'mon-club-tt')], 404);
        }

        $ok = $repo->setActive($id, true);
        if (! $ok) {
            return new WP_REST_Response(['success' => false], 500);
        }

        return new WP_REST_Response($this->serialize($repo->findById($id)), 200);
    }

    /**
     * Ajoute l'URL de la photo gérée dans MonClubTT (>= 1.8.0), indexée par
     * numéro de licence. Chaîne vide si MonClubTT est absent ou sans photo.
     */
    private function serialize(?Player $player): ?array
    {
        if (! $player) {
            return null;
        }

        $data              = $player->toArray();
        $data['photo_url'] = function_exists('monclubtt_get_joueur_photo_url')
            ? (string) monclubtt_get_joueur_photo_url($player->licenseNumber, 'thumbnail')
            : '';

        return $data;
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
