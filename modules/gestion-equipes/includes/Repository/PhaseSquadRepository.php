<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

use MonClubTT\GestionEquipes\Domain\PhaseSquad;

class PhaseSquadRepository
{
    private const CACHE_GROUP = 'monclubtt_ge_squads';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_phase_squads';
    }

    /** @return PhaseSquad[] */
    public function findBySeasonPhase(string $season, int $phase): array
    {
        $key    = "{$season}_p{$phase}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY team_code, position ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase
            ),
            ARRAY_A
        );
        $result = array_map([PhaseSquad::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return PhaseSquad[] */
    public function findByTeam(string $season, int $phase, string $teamCode): array
    {
        $key    = "{$season}_p{$phase}_t" . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND team_code = %s ORDER BY position ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase,
                $teamCode
            ),
            ARRAY_A
        );
        $result = array_map([PhaseSquad::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    public function add(string $season, int $phase, string $teamCode, int $playerId): bool
    {
        global $wpdb;

        $maxPos = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COALESCE(MAX(position), 0) FROM {$this->table} WHERE season = %s AND phase = %d AND team_code = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase,
                $teamCode
            )
        );

        $result = $wpdb->insert($this->table, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            'season'    => $season,
            'phase'     => $phase,
            'team_code' => $teamCode,
            'player_id' => $playerId,
            'position'  => $maxPos + 1,
        ]);

        if ($result !== false) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }

        return $result !== false;
    }

    public function remove(string $season, int $phase, string $teamCode, int $playerId): bool
    {
        global $wpdb;
        $ok = (bool) $wpdb->delete($this->table, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            'season'    => $season,
            'phase'     => $phase,
            'team_code' => $teamCode,
            'player_id' => $playerId,
        ]);

        if ($ok) {
            wp_cache_flush_group(self::CACHE_GROUP);
        }

        return $ok;
    }

    /**
     * Retire un joueur de tous les effectifs de phase (toutes saisons/phases/
     * équipes confondues) — utilisé lors de la suppression d'un joueur de
     * l'effectif.
     */
    public function removePlayerEverywhere(int $playerId): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['player_id' => $playerId]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function existsInAnyTeam(string $season, int $phase, int $playerId): bool
    {
        $key    = "exists_{$season}_p{$phase}_{$playerId}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return (bool) $cached;
        }

        global $wpdb;
        $result = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE season = %s AND phase = %d AND player_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase,
                $playerId
            )
        );

        wp_cache_set($key, (int) $result, self::CACHE_GROUP);
        return $result;
    }
}
