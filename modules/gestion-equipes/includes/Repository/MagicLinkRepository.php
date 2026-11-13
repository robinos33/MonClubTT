<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

use MonClubTT\GestionEquipes\Domain\MagicLink;

class MagicLinkRepository
{
    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_magic_links';
    }

    /**
     * Crée un lien magique pour un joueur et retourne le token en clair
     * (seul son hash est stocké en base — un dump de la table ne suffit
     * pas à réutiliser les liens envoyés par mail).
     */
    public function create(int $playerId, string $season, int $ttlDays, bool $isReminder = false): string
    {
        global $wpdb;

        $token     = bin2hex(random_bytes(32));
        $expiresAt = gmdate('Y-m-d H:i:s', time() + $ttlDays * DAY_IN_SECONDS);

        $wpdb->insert($this->table, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            'player_id'   => $playerId,
            'token_hash'  => hash('sha256', $token),
            'season'      => $season,
            'expires_at'  => $expiresAt,
            'is_reminder' => $isReminder ? 1 : 0,
        ]);

        return $token;
    }

    public function findValidByToken(string $token): ?MagicLink
    {
        global $wpdb;

        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE token_hash = %s", hash('sha256', $token)), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );

        if (! $row) {
            return null;
        }

        $link = MagicLink::fromRow($row);
        return $link->isExpired() ? null : $link;
    }

    public function markUsed(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->table, ['used_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }

    /**
     * Liens de premier envoi restés sans réponse et assez anciens pour
     * justifier une relance : jamais utilisés, jamais relancés, pas encore
     * expirés, et créés il y a plus de $afterDays jours.
     *
     * Les liens de rappel (is_reminder = 1) sont exclus du scan : c'est ce
     * qui garantit UNE relance par envoi initial, sans effet boule de neige.
     *
     * @return list<array{id: int, player_id: int, expires_at: string}>
     */
    public function findAwaitingReminder(string $season, int $afterDays): array
    {
        global $wpdb;

        $now           = gmdate('Y-m-d H:i:s');
        $createdBefore = gmdate('Y-m-d H:i:s', time() - $afterDays * DAY_IN_SECONDS);

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare(
                "SELECT id, player_id, expires_at FROM {$this->table}" . // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name from $wpdb->prefix in constructor, not user input
                " WHERE season = %s AND is_reminder = 0" .
                " AND used_at IS NULL AND reminded_at IS NULL" .
                " AND expires_at > %s AND created_at < %s" .
                " ORDER BY created_at ASC",
                $season,
                $now,
                $createdBefore
            ),
            ARRAY_A
        );

        $result = [];
        $seen   = [];

        foreach ($rows ?: [] as $row) {
            $playerId = (int) $row['player_id'];
            // Plusieurs envois pour un même joueur : une seule relance.
            if (isset($seen[$playerId])) {
                continue;
            }
            $seen[$playerId] = true;

            $result[] = [
                'id'         => (int) $row['id'],
                'player_id'  => $playerId,
                'expires_at' => (string) $row['expires_at'],
            ];
        }

        return $result;
    }

    public function markReminded(int $id): void
    {
        global $wpdb;
        $wpdb->update($this->table, ['reminded_at' => gmdate('Y-m-d H:i:s')], ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
    }
}
