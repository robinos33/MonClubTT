<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Repository\PhaseSquadRepository;
use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;

class PhaseSquadController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/phase-squads', [
            'methods'             => 'GET',
            'callback'            => [$this, 'getSquads'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NS, '/phase-squads', [
            'methods'             => 'POST',
            'callback'            => [$this, 'addPlayer'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/phase-squads', [
            'methods'             => 'DELETE',
            'callback'            => [$this, 'removePlayer'],
            'permission_callback' => [$this, 'canWrite'],
        ]);

        register_rest_route(self::NS, '/phase-squads/ventilate', [
            'methods'             => 'POST',
            'callback'            => [$this, 'ventilate'],
            'permission_callback' => [$this, 'canWrite'],
        ]);
    }

    public function getSquads(WP_REST_Request $request): WP_REST_Response
    {
        $season = sanitize_text_field($request->get_param('season') ?? '');
        $phase  = (int) ($request->get_param('phase') ?? 1);

        $repo  = new PhaseSquadRepository();
        $squads = $repo->findBySeasonPhase($season, $phase);

        return new WP_REST_Response(array_map(fn($s) => $s->toArray(), $squads), 200);
    }

    public function addPlayer(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? '');
        $phase    = (int) $request->get_param('phase');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');
        $playerId = (int) $request->get_param('player_id');

        if (! $season || ! $phase || ! $teamCode || ! $playerId) {
            return new WP_REST_Response(['message' => 'Paramètres manquants.'], 400);
        }

        $repo = new PhaseSquadRepository();
        $ok   = $repo->add($season, $phase, $teamCode, $playerId);

        return new WP_REST_Response(['success' => $ok], $ok ? 200 : 500);
    }

    public function removePlayer(WP_REST_Request $request): WP_REST_Response
    {
        $season   = sanitize_text_field($request->get_param('season') ?? '');
        $phase    = (int) $request->get_param('phase');
        $teamCode = sanitize_text_field($request->get_param('team_code') ?? '');
        $playerId = (int) $request->get_param('player_id');

        $repo = new PhaseSquadRepository();
        $ok   = $repo->remove($season, $phase, $teamCode, $playerId);

        return new WP_REST_Response(['success' => $ok], 200);
    }

    /**
     * Ventile automatiquement toutes les journées d'une phase
     * à partir des effectifs de phase définis.
     *
     * Algorithme :
     *  - Pour chaque journée (round 1→7) et chaque équipe :
     *    1. Prendre le squad de l'équipe
     *    2. Retirer les joueurs marqués INDISPONIBLES pour ce round
     *    3. Rotation : décaler la liste par (round - 1) pour varier les compos
     *    4. Prendre les 4 premiers et les affecter aux slots
     *  - Écrase uniquement les slots vides (ne touche pas aux compos déjà saisies)
     */
    public function ventilate(WP_REST_Request $request): WP_REST_Response
    {
        $season    = sanitize_text_field($request->get_param('season') ?? '');
        $phase     = (int) $request->get_param('phase');
        $overwrite = (bool) $request->get_param('overwrite'); // false = ne touche pas aux slots existants

        if (! $season || ! $phase) {
            return new WP_REST_Response(['message' => 'Paramètres manquants.'], 400);
        }

        $squadRepo  = new PhaseSquadRepository();
        $availRepo  = new AvailabilityRepository();
        $compoRepo  = new TeamCompositionRepository();

        // Récupère tous les squads de la phase groupés par équipe
        $squads = $squadRepo->findBySeasonPhase($season, $phase);
        $byTeam = [];
        foreach ($squads as $sq) {
            $byTeam[$sq->teamCode][] = $sq->playerId;
        }

        // Récupère toutes les dispos de la phase en une requête
        $allAvails = $availRepo->findByPhase($season, $phase);
        // Index: [playerId][round] => status
        $availIndex = [];
        foreach ($allAvails as $av) {
            $availIndex[$av->playerId][$av->round] = $av->status;
        }

        $placed = 0;

        foreach ($byTeam as $teamCode => $playerIds) {
            for ($round = 1; $round <= 7; $round++) {
                // Compo existante pour ce round/équipe
                $existing = $compoRepo->findByTeamAndRound($season, $phase, $round, $teamCode);
                $filledSlots = [];
                foreach ($existing as $c) {
                    if ($c->playerId !== null) {
                        $filledSlots[$c->slotNumber] = $c->playerId;
                    }
                }

                // Si overwrite=false et tous les slots sont remplis, on passe
                if (! $overwrite && count($filledSlots) >= 4) {
                    continue;
                }

                // Filtre les joueurs disponibles (retire les INDISPONIBLES)
                $available = array_values(array_filter(
                    $playerIds,
                    fn($pid) => ($availIndex[$pid][$round] ?? 'unknown') !== 'unavailable'
                ));

                // Rotation : décalage circulaire par (round - 1)
                if (count($available) > 4) {
                    $offset    = ($round - 1) % count($available);
                    $available = array_merge(
                        array_slice($available, $offset),
                        array_slice($available, 0, $offset)
                    );
                }

                // Affecte les 4 premiers aux slots libres (ou tous si overwrite)
                $assignIdx = 0;
                for ($slot = 1; $slot <= 4; $slot++) {
                    if (! $overwrite && isset($filledSlots[$slot])) {
                        continue; // Slot déjà rempli, on ne touche pas
                    }
                    if ($assignIdx >= count($available)) {
                        break;
                    }
                    $compoRepo->setSlot($season, $phase, $round, $teamCode, $slot, $available[$assignIdx]);
                    $assignIdx++;
                    $placed++;
                }
            }
        }

        return new WP_REST_Response([
            'success' => true,
            'placed'  => $placed,
            /* translators: %d: number of slots filled */
            'message' => sprintf(__('%d slot(s) rempli(s).', 'mon-club-tt'), $placed),
        ], 200);
    }

    public function canWrite(): bool
    {
        return current_user_can('edit_posts');
    }
}
