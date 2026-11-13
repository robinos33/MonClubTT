<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;

/**
 * POST /monclubtt-equipes/v1/players/sync
 *
 * Pulls player data from MonClubTT (via the 'monclubtt_get_joueurs' WP filter)
 * and upserts it into wp_monclubtt_ge_players.
 *
 * MonClubTT fields used:  licence, nom, prénom, classement (points mensuels),
 *                        nationalité (étranger), catégorie (jeune).
 * Manual fields preserved on update: phone, usual_team, is_captain,
 *                                    is_mutation, is_burned, notes.
 */
class SyncController
{
    private const NS = 'monclubtt-equipes/v1';

    /** FFTT youth categories that map to is_young = 1 */
    private const YOUTH_CATEGORIES = ['B', 'M', 'C', 'J']; // benjamin, minime, cadet, junior

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/players/sync', [
            'methods'             => 'POST',
            'callback'            => [$this, 'sync'],
            'permission_callback' => [$this, 'canSync'],
        ]);
    }

    public function sync(WP_REST_Request $request): WP_REST_Response
    {
        $result = self::syncPlayers();
        return new WP_REST_Response($result, $result['status']);
    }

    /**
     * Met à jour la base de la gestion des équipes depuis les joueurs de
     * Données FFTT. Appelée par le bouton de l'admin et après chaque
     * synchronisation des données FFTT (action monclubtt_apres_synchro).
     *
     * @return array{success: bool, status: int, message: string, synced?: int, errors?: int}
     */
    public static function syncPlayers(): array
    {
        /** @var \MonClubTT_Joueur[]|mixed $joueurs */
        $joueurs = apply_filters('monclubtt_get_joueurs', 'MF');

        if (! is_array($joueurs) || empty($joueurs)) {
            return [
                'success' => false,
                'status'  => 502,
                'message' => __('Aucun joueur dans les données FFTT. Vérifiez l\'onglet Connexion de Données FFTT.', 'mon-club-tt'),
            ];
        }

        $repo   = new PlayerRepository();
        $season = \MonClubTT\GestionEquipes\Front\Assets::computeSeason();
        $synced = 0;
        $errors = 0;

        foreach ($joueurs as $joueur) {
            // Guard: MonClubTT may return non-objects if API is misconfigured
            if (! is_object($joueur) || ! method_exists($joueur, 'getLicence')) {
                $errors++;
                continue;
            }

            $classement = $joueur->getClassement();
            $ranking    = $classement ? (int) round((float) $classement->getPointsMensuels()) : 0;
            $official   = $classement && method_exists($classement, 'getPointsOfficiels')
                ? (int) round((float) $classement->getPointsOfficiels())
                : 0;
            $categorie  = strtoupper((string) $joueur->getCategorie());

            $repo->upsertFromMonClubTT([
                'external_id'    => $joueur->getLicence(),
                'license_number' => $joueur->getLicence(),
                'first_name'     => $joueur->getPrenom(),
                'last_name'      => $joueur->getNom(),
                'ranking'        => $ranking,
                'official_points' => $official,
                'season'         => $season,
                'is_foreign'     => $joueur->isEtranger() ? 1 : 0,
                'is_young'       => in_array($categorie, self::YOUTH_CATEGORIES, true) ? 1 : 0,
                'raw_payload'    => wp_json_encode([
                    'licence'    => $joueur->getLicence(),
                    'classement' => $ranking,
                    'categorie'  => $categorie,
                    'sexe'       => $joueur->getSexe(),
                ]),
            ]);
            $synced++;
        }

        update_option('monclubtt_ge_last_sync', current_time('mysql'));

        $message = sprintf(
            /* translators: 1: count synced, 2: count errors */
            _n('%d joueur synchronisé.', '%d joueurs synchronisés.', $synced, 'mon-club-tt'),
            $synced
        );

        if ($errors > 0) {
            /* translators: %d: number of skipped entries */
            $message .= ' ' . sprintf( __('%d entrée(s) ignorée(s) (données invalides).', 'mon-club-tt'), $errors );
        }

        return [
            'success' => true,
            'status'  => 200,
            'synced'  => $synced,
            'errors'  => $errors,
            'message' => $message,
        ];
    }

    public function canSync(): bool
    {
        return current_user_can('manage_options');
    }
}
