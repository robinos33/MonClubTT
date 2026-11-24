<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

/**
 * Journal des actions. Il ne garde que les RETENTION_DAYS derniers jours :
 * chaque écriture purge les entrées plus anciennes, sans tâche planifiée, et
 * la lecture ignore celles qu'aucune écriture n'a encore purgées.
 */
class AuditLogRepository
{
    public const RETENTION_DAYS = 7;

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_audit_log';
    }

    /**
     * @param array{user_id: int, actor: string, source: string, action: string, summary: string,
     *              season: string, phase: int, round: int, team_code: string} $entry
     */
    public function add(array $entry): void
    {
        global $wpdb;
        $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
            $this->table,
            [
                'created_at' => gmdate('Y-m-d H:i:s'),
                'user_id'    => $entry['user_id'],
                'actor'      => mb_substr($entry['actor'], 0, 100),
                'source'     => $entry['source'],
                'action'     => $entry['action'],
                'summary'    => $entry['summary'],
                'season'     => $entry['season'],
                'phase'      => $entry['phase'],
                'round'      => $entry['round'],
                'team_code'  => $entry['team_code'],
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "DELETE FROM {$this->table} WHERE created_at < %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                self::cutoff()
            )
        );
    }

    /** @return list<array<string, mixed>> du plus récent au plus ancien */
    public function recent(int $limit = 500): array
    {
        global $wpdb;
        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT * FROM {$this->table} WHERE created_at >= %s ORDER BY created_at DESC, id DESC LIMIT %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                self::cutoff(),
                $limit
            ),
            ARRAY_A
        );

        return array_map(static fn (array $row): array => [
            'id'         => (int) $row['id'],
            'created_at' => str_replace(' ', 'T', (string) $row['created_at']) . 'Z',
            'user_id'    => (int) $row['user_id'],
            'actor'      => (string) $row['actor'],
            'source'     => (string) $row['source'],
            'action'     => (string) $row['action'],
            'summary'    => (string) $row['summary'],
            'season'     => (string) $row['season'],
            'phase'      => (int) $row['phase'],
            'round'      => (int) $row['round'],
            'team_code'  => (string) $row['team_code'],
        ], $rows ?: []);
    }

    private static function cutoff(): string
    {
        return gmdate('Y-m-d H:i:s', time() - self::RETENTION_DAYS * DAY_IN_SECONDS);
    }
}
