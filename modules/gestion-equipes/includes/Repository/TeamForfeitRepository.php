<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

/**
 * Forfaits déclarés par les équipes du club, journée par journée. Ils
 * alimentent les règles de qualification de l'article II.112.2.
 */
class TeamForfeitRepository
{
    private const CACHE_GROUP = 'monclubtt_ge_team_forfeits';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_team_forfeits';
    }

    /** @return list<array{round: int, team_code: string}> */
    public function findByPhase(string $season, int $phase): array
    {
        $key    = "{$season}_p{$phase}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT round, team_code FROM {$this->table} WHERE season = %s AND phase = %d ORDER BY round, team_code", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                $season,
                $phase
            ),
            ARRAY_A
        );

        $result = array_map(
            static fn (array $row): array => ['round' => (int) $row['round'], 'team_code' => (string) $row['team_code']],
            $rows ?: []
        );

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    public function isForfeit(string $season, int $phase, int $round, string $teamCode): bool
    {
        foreach ($this->findByPhase($season, $phase) as $forfeit) {
            if ($forfeit['round'] === $round && $forfeit['team_code'] === $teamCode) {
                return true;
            }
        }
        return false;
    }

    /** @return list<string> Codes des équipes forfait à cette journée. */
    public function teamsForRound(string $season, int $phase, int $round): array
    {
        $teams = [];
        foreach ($this->findByPhase($season, $phase) as $forfeit) {
            if ($forfeit['round'] === $round) {
                $teams[] = $forfeit['team_code'];
            }
        }
        return $teams;
    }

    public function declare(string $season, int $phase, int $round, string $teamCode, ?int $userId): void
    {
        global $wpdb;
        $wpdb->query($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            "INSERT INTO {$this->table} (season, phase, round, team_code, declared_at, declared_by)" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ' VALUES (%s, %d, %d, %s, NOW(), %d)' .
            ' ON DUPLICATE KEY UPDATE declared_at = NOW(), declared_by = VALUES(declared_by)',
            $season,
            $phase,
            $round,
            $teamCode,
            $userId
        ));

        wp_cache_flush_group(self::CACHE_GROUP);
    }

    public function withdraw(string $season, int $phase, int $round, string $teamCode): void
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
