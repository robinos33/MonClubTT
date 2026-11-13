<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

use MonClubTT\GestionEquipes\Domain\MatchAppearance;
use MonClubTT\GestionEquipes\Domain\TeamComposition;

class MatchAppearanceRepository
{
    private const CACHE_GROUP = 'monclubtt_ge_appearances';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_match_appearances';
    }

    /** @return MatchAppearance[] */
    public function findByPlayerAndPhase(string $season, int $phase, int $playerId): array
    {
        $key    = "{$season}_p{$phase}_pl{$playerId}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE season = %s AND phase = %d AND player_id = %d ORDER BY round ASC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase,
                $playerId
            ),
            ARRAY_A
        );
        $result = array_map([MatchAppearance::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /** @return MatchAppearance[] */
    public function findByPhaseAndRound(string $season, int $phase, int $round): array
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
                $season,
                $phase,
                $round
            ),
            ARRAY_A
        );
        $result = array_map([MatchAppearance::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /**
     * Enregistre la composition validée d'une équipe pour une journée donnée.
     *
     * @param TeamComposition[] $compositions Slots remplis de l'équipe pour cette journée.
     */
    public function recordForRound(
        string $season,
        int $phase,
        int $round,
        string $teamCode,
        int $teamRank,
        array $compositions,
        ?int $userId
    ): void {
        global $wpdb;

        foreach ($compositions as $composition) {
            if ($composition->playerId === null) {
                continue;
            }

            $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
                "INSERT INTO {$this->table} (season, phase, round, team_code, team_rank, player_id, slot_number, validated_at, validated_by)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
                ' VALUES (%s, %d, %d, %s, %d, %d, %d, NOW(), %d)' .
                ' ON DUPLICATE KEY UPDATE team_rank = VALUES(team_rank), slot_number = VALUES(slot_number), validated_at = NOW(), validated_by = VALUES(validated_by)',
                $season,
                $phase,
                $round,
                $teamCode,
                $teamRank,
                $composition->playerId,
                $composition->slotNumber,
                $userId
            ));
        }

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function deleteForRound(string $season, int $phase, int $round, string $teamCode): void
    {
        global $wpdb;
        $wpdb->delete($this->table, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            'season'    => $season,
            'phase'     => $phase,
            'round'     => $round,
            'team_code' => $teamCode,
        ]);

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Efface toutes les présences en match d'un joueur — utilisé lors de la
     * suppression définitive d'un joueur de l'effectif. Contrairement à la
     * suppression douce, cette opération efface aussi l'historique des
     * journées déjà validées : elle est irréversible.
     */
    public function deleteByPlayer(int $playerId): void
    {
        global $wpdb;
        $wpdb->delete($this->table, ['player_id' => $playerId]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    /**
     * Compte les journées distinctes où le joueur a joué dans une équipe de rang <= $teamRank.
     */
    public function countDistinctRoundsAtRankOrAbove(string $season, int $phase, int $playerId, int $teamRank): int
    {
        $rounds = [];
        foreach ($this->findByPlayerAndPhase($season, $phase, $playerId) as $appearance) {
            if ($appearance->teamRank <= $teamRank) {
                $rounds[$appearance->round] = true;
            }
        }

        return count($rounds);
    }
}
