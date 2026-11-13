<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

use MonClubTT\GestionEquipes\Domain\Player;

class PlayerRepository
{
    private const CACHE_GROUP = 'monclubtt_ge_players';

    private string $table;

    public function __construct()
    {
        global $wpdb;
        $this->table = $wpdb->prefix . 'monclubtt_ge_players';
    }

    /** @return Player[] */
    public function findAll(): array
    {
        $cached = wp_cache_get('all', self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results("SELECT * FROM {$this->table} ORDER BY ranking DESC", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $result = array_map([Player::class, 'fromRow'], $rows ?: []);

        wp_cache_set('all', $result, self::CACHE_GROUP);
        return $result;
    }

    public function findById(int $id): ?Player
    {
        $key    = "id_{$id}";
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached ?: null; // @phpstan-ignore-line
        }

        global $wpdb;
        $row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE id = %d", $id), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        $player = $row ? Player::fromRow($row) : null;

        wp_cache_set($key, $player ?? false, self::CACHE_GROUP);
        return $player;
    }

    /** @return Player[] */
    public function findByTeam(string $teamCode): array
    {
        $key    = 'team_' . sanitize_key($teamCode);
        $cached = wp_cache_get($key, self::CACHE_GROUP);
        if ($cached !== false) {
            return $cached; // @phpstan-ignore-line
        }

        global $wpdb;
        $rows   = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare("SELECT * FROM {$this->table} WHERE usual_team = %s ORDER BY ranking DESC", $teamCode), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        $result = array_map([Player::class, 'fromRow'], $rows ?: []);

        wp_cache_set($key, $result, self::CACHE_GROUP);
        return $result;
    }

    /**
     * Upsert depuis DataPing : met à jour uniquement les champs FFTT.
     * Les champs manuels (phone, usual_team, is_captain, is_mutation,
     * is_burned, notes) sont préservés lors des mises à jour.
     */
    public function upsertFromMonClubTT(array $data): int
    {
        global $wpdb;

        $externalId = sanitize_text_field($data['external_id'] ?? '');

        $existingRow = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare("SELECT id, best_official_points, best_official_season FROM {$this->table} WHERE external_id = %s", $externalId), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
            ARRAY_A
        );
        $existing = $existingRow['id'] ?? null;

        // Les points minimum se jugent sur l'un des deux classements officiels
        // de la saison : on conserve le meilleur relevé de la saison en cours,
        // remis à zéro au changement de saison.
        $season         = sanitize_text_field($data['season'] ?? '');
        $officialPoints = (int) ($data['official_points'] ?? 0);
        if ($existingRow && $existingRow['best_official_season'] === $season) {
            $officialPoints = max($officialPoints, (int) $existingRow['best_official_points']);
        }

        $ffttFields = [
            'external_id'    => $externalId,
            'license_number' => sanitize_text_field($data['license_number'] ?? $externalId),
            'first_name'     => sanitize_text_field($data['first_name']     ?? ''),
            'last_name'      => sanitize_text_field($data['last_name']      ?? ''),
            'ranking'        => (int) ($data['ranking']   ?? 0),
            'best_official_points' => $officialPoints,
            'best_official_season' => $season,
            'is_foreign'     => (int) (bool) ($data['is_foreign'] ?? false),
            'is_young'       => (int) (bool) ($data['is_young']   ?? false),
            'raw_payload'    => sanitize_textarea_field($data['raw_payload'] ?? ''),
            'synced_at'      => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($this->table, $ffttFields, ['id' => (int) $existing]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->invalidateCache();
            return (int) $existing;
        }

        $wpdb->insert($this->table, array_merge($ffttFields, [ // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            'phone'       => '',
            'email'       => '',
            'usual_team'  => '',
            'is_captain'  => 0,
            'is_mutation' => 0,
            'is_burned'   => 0,
            'is_active'   => 1,
            'notes'       => '',
        ]));

        $this->invalidateCache();
        return $wpdb->insert_id;
    }

    /**
     * Upsert complet (admin) — écrase tous les champs.
     */
    public function upsert(array $data): int
    {
        global $wpdb;

        $existing = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $wpdb->prepare("SELECT id FROM {$this->table} WHERE external_id = %s", $data['external_id'] ?? '') // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        );

        $row = [
            'external_id'    => sanitize_text_field($data['external_id']    ?? ''),
            'first_name'     => sanitize_text_field($data['first_name']     ?? ''),
            'last_name'      => sanitize_text_field($data['last_name']      ?? ''),
            'license_number' => sanitize_text_field($data['license_number'] ?? ''),
            'phone'          => sanitize_text_field($data['phone']           ?? ''),
            'email'          => sanitize_email($data['email']                ?? ''),
            'ranking'        => (int) ($data['ranking'] ?? 0),
            'usual_team'     => sanitize_text_field($data['usual_team']     ?? ''),
            'is_foreign'     => (int) (bool) ($data['is_foreign']           ?? false),
            'is_captain'     => (int) (bool) ($data['is_captain']           ?? false),
            'is_young'       => (int) (bool) ($data['is_young']             ?? false),
            'is_mutation'    => (int) (bool) ($data['is_mutation']          ?? false),
            'is_burned'      => (int) (bool) ($data['is_burned']            ?? false),
            'notes'          => sanitize_textarea_field($data['notes']      ?? ''),
            'raw_payload'    => wp_json_encode($data),
            'synced_at'      => current_time('mysql'),
        ];

        if ($existing) {
            $wpdb->update($this->table, $row, ['id' => (int) $existing]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->invalidateCache();
            return (int) $existing;
        }

        $wpdb->insert($this->table, $row); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
        $this->invalidateCache();
        return $wpdb->insert_id;
    }

    public function updateContactInfo(int $id, string $phone, string $notes, string $email = ''): bool
    {
        global $wpdb;
        $ok = (bool) $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            [
                'phone' => sanitize_text_field($phone),
                'email' => sanitize_email($email),
                'notes' => sanitize_textarea_field($notes),
            ],
            ['id' => $id]
        );

        if ($ok) {
            $this->invalidateCache($id);
        }
        return $ok;
    }

    /**
     * Suppression douce (désactivation) : le joueur reste en base — historique
     * de compos/appearances préservé — mais n'est plus sélectionnable dans
     * les équipes. `restore()` permet de le réintégrer à l'effectif.
     */
    public function setActive(int $id, bool $active): bool
    {
        global $wpdb;
        $ok = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
            $this->table,
            ['is_active' => $active ? 1 : 0],
            ['id' => $id]
        );

        if ($ok !== false) {
            $this->invalidateCache($id);
        }
        return $ok !== false;
    }

    /**
     * Suppression définitive et irréversible : le joueur est effacé de la base.
     * Contrairement à setActive(false), aucune restauration n'est possible.
     * L'appelant est responsable de purger au préalable les données liées
     * (disponibilités, compositions, présences, effectifs de phase).
     */
    public function delete(int $id): bool
    {
        global $wpdb;
        $ok = $wpdb->delete($this->table, ['id' => $id]); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching

        if ($ok) {
            $this->invalidateCache($id);
        }
        return (bool) $ok;
    }

    public function count(): int
    {
        $cached = wp_cache_get('count', self::CACHE_GROUP);
        if ($cached !== false) {
            return (int) $cached;
        }

        global $wpdb;
        $count = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$this->table}"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        wp_cache_set('count', $count, self::CACHE_GROUP);
        return $count;
    }

    private function invalidateCache(?int $id = null): void
    {
        wp_cache_flush_group(self::CACHE_GROUP);
    }
}
