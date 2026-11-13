<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

class ValidatedRoundRepository
{
    private const CACHE_GROUP = 'monclubtt_ge_validated_rounds';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_validated_rounds';
    }

    public function isValidated(string $season, int $phase, int $round, string $teamCode): bool
    {
        $key    = "{$season}_p{$phase}_r{$round}_t" . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached === 'yes';
        }

        global $wpdb;
        $found = (bool) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$this->table} WHERE season = %s AND phase = %d AND round = %d AND team_code = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase,
                $round,
                $teamCode
            )
        );

        wp_cache_set($key, $found ? 'yes' : 'no', self::CACHE_GROUP);
        return $found;
    }

    public function markValidated(string $season, int $phase, int $round, string $teamCode, ?int $userId): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$this->table} (season, phase, round, team_code, validated_at, validated_by)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ' VALUES (%s, %d, %d, %s, NOW(), %d)' .
            ' ON DUPLICATE KEY UPDATE validated_at = NOW(), validated_by = VALUES(validated_by)',
            $season,
            $phase,
            $round,
            $teamCode,
            $userId
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function unmarkValidated(string $season, int $phase, int $round, string $teamCode): void
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
}
