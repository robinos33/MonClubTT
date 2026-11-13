<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

use MonClubTT\GestionEquipes\Domain\TeamComposition;

class TeamCompositionRepository
{
    private const CACHE_GROUP = 'monclubtt_ge_compositions';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_team_compositions';
    }

    /** @return TeamComposition[] */
    public function findByRound(string $season, int $phase, int $round): array
    {
        $key    = "{$season}_p{$phase}_r{$round}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d ORDER BY team_code, slot_number", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase, $round
            ),
            ARRAY_A
        );
        $result = array_map([TeamComposition::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return TeamComposition[] */
    public function findByPhase(string $season, int $phase): array
    {
        $key    = "{$season}_p{$phase}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY round, team_code, slot_number", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase
            ),
            ARRAY_A
        );
        $result = array_map([TeamComposition::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return TeamComposition[] */
    public function findByTeamAndRound(string $season, int $phase, int $round, string $teamCode): array
    {
        $key    = "{$season}_p{$phase}_r{$round}_t" . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d AND team_code = %s ORDER BY slot_number", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season, $phase, $round, $teamCode
            ),
            ARRAY_A
        );
        $result = array_map([TeamComposition::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    public function setSlot(string $season, int $phase, int $round, string $teamCode, int $slot, int $playerId): void
    {
        global $wpdb;

        $this->clearPlayerFromRound($season, $phase, $round, $playerId);

        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$this->table} (season, phase, round, team_code, slot_number, player_id)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            " VALUES (%s, %d, %d, %s, %d, %d)" .
            " ON DUPLICATE KEY UPDATE player_id = VALUES(player_id), updated_at = NOW()",
            $season, $phase, $round, $teamCode, $slot, $playerId
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function clearSlot(string $season, int $phase, int $round, string $teamCode, int $slot): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$this->table} (season, phase, round, team_code, slot_number, player_id)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            " VALUES (%s, %d, %d, %s, %d, NULL)" .
            " ON DUPLICATE KEY UPDATE player_id = NULL, updated_at = NOW()",
            $season, $phase, $round, $teamCode, $slot
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function clearTeam(string $season, int $phase, int $round, string $teamCode): void
    {
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            ['player_id' => null],
            ['season' => $season, 'phase' => $phase, 'round' => $round, 'team_code' => $teamCode]
        );

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Retire un joueur de toutes les compositions NON validées (toutes saisons/
     * phases/journées confondues) — utilisé lors de la suppression d'un joueur
     * de l'effectif. Les journées déjà validées ne sont jamais réécrites : elles
     * reflètent un match réellement joué, dont la trace définitive vit dans
     * monclubtt_ge_match_appearances.
     */
    public function clearPlayerFromUnvalidatedRounds(int $playerId): void
    {
        global $wpdb;
        $validatedTable = $wpdb->prefix . 'monclubtt_ge_validated_rounds';

        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- noms de tables issus de $wpdb->prefix
            "UPDATE {$this->table} tc" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table names from $wpdb->prefix, not user input
            " LEFT JOIN {$validatedTable} vr" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix
            "   ON vr.season = tc.season AND vr.phase = tc.phase AND vr.round = tc.round AND vr.team_code = tc.team_code" .
            ' SET tc.player_id = NULL' .
            ' WHERE tc.player_id = %d AND vr.id IS NULL',
            $playerId
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Retire un joueur de toutes les compositions, y compris les journées déjà
     * validées — utilisé lors de la suppression définitive d'un joueur de
     * l'effectif (le joueur n'existe plus, sa trace ne peut plus être conservée).
     */
    public function clearPlayerEverywhere(int $playerId): void
    {
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            ['player_id' => null],
            ['player_id' => $playerId]
        );

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    private function clearPlayerFromRound(string $season, int $phase, int $round, int $playerId): void
    {
        global $wpdb;
        $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            ['player_id' => null],
            ['season' => $season, 'phase' => $phase, 'round' => $round, 'player_id' => $playerId]
        );
    }
}
