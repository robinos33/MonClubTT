<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Admin;

use MonClubTT\GestionEquipes\Repository\SmsTemplateRepository;

/**
 * Export complet des données de la gestion des équipes au format JSON
 * (sauvegarde, transfert vers un autre site), relu par DataImporter. Même
 * structure que l'export de TT Team Planner ; les identifiants SQL sont
 * conservés tels quels (les tables se référencent par `player_id`).
 *
 * Les liens magiques ne sont pas exportés : ce sont des jetons d'accès
 * temporaires, sans valeur hors de ce site.
 */
class DataExporter
{
    public const FORMAT         = 'monclubtt-gestion-equipes-export';
    public const FORMAT_VERSION = 1;
    public const ACTION         = 'monclubtt_ge_export_data';

    /** Options exportées (réglages + métadonnées de synchronisation). */
    public const OPTIONS = [
        'monclubtt_ge_pwa_enabled',
        'monclubtt_ge_teams',
        'monclubtt_ge_sms_template_availability',
        'monclubtt_ge_sms_template_confirmation',
        'monclubtt_ge_journee_dates_p1',
        'monclubtt_ge_journee_dates_p2',
        'monclubtt_ge_last_sync',
        'monclubtt_ge_last_appearance_import',
    ];

    /**
     * Tables exportées (clé JSON → suffixe de table) et colonnes entières,
     * converties en int pour que le JSON soit typé.
     */
    public const TABLES = [
        'players' => [
            'table' => 'monclubtt_ge_players',
            'ints'  => ['id', 'ranking', 'best_official_points', 'is_foreign', 'is_young', 'is_mutation', 'is_active'],
        ],
        'availabilities' => [
            'table' => 'monclubtt_ge_availabilities',
            'ints'  => ['id', 'player_id', 'phase', 'round'],
        ],
        'team_compositions' => [
            'table' => 'monclubtt_ge_team_compositions',
            'ints'  => ['id', 'phase', 'round', 'slot_number', 'player_id', 'is_captain'],
        ],
        'phase_squads' => [
            'table' => 'monclubtt_ge_phase_squads',
            'ints'  => ['id', 'phase', 'player_id', 'position', 'is_captain'],
        ],
        'match_appearances' => [
            'table' => 'monclubtt_ge_match_appearances',
            'ints'  => ['id', 'phase', 'round', 'team_rank', 'player_id', 'slot_number', 'validated_by'],
        ],
        'validated_rounds' => [
            'table' => 'monclubtt_ge_validated_rounds',
            'ints'  => ['id', 'phase', 'round', 'validated_by'],
        ],
        'team_forfeits' => [
            'table' => 'monclubtt_ge_team_forfeits',
            'ints'  => ['id', 'phase', 'round', 'declared_by'],
        ],
        'audit_log' => [
            'table' => 'monclubtt_ge_audit_log',
            'ints'  => ['id', 'user_id', 'phase', 'round'],
        ],
    ];

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handleDownload']);
    }

    public function handleDownload(): void
    {
        check_admin_referer(self::ACTION);

        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission insuffisante.', 'mon-club-tt'));
        }

        $json = wp_json_encode(
            $this->build(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        if ($json === false) {
            wp_die(esc_html__("Impossible de générer l'export JSON.", 'mon-club-tt'));
        }

        $filename = sprintf('monclubtt-gestion-equipes-%s.json', gmdate('Y-m-d-His'));

        nocache_headers();
        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($json));

        echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- téléchargement JSON brut, pas de rendu HTML
        exit;
    }

    /** @return array<string, mixed> */
    public function build(): array
    {
        $options = [];
        foreach (self::OPTIONS as $name) {
            $options[$name] = get_option($name, null);
        }

        $tables = [];
        foreach (self::TABLES as $key => $def) {
            $tables[$key] = $this->dumpTable($def['table'], $def['ints']);
        }

        return [
            'format'         => self::FORMAT,
            'format_version' => self::FORMAT_VERSION,
            'plugin_version' => MONCLUBTT_GE_VERSION,
            'club_name'      => \monclubtt_nom_club(),
            'exported_at'    => gmdate('c'),
            'site_url'       => home_url('/'),
            'counts'         => array_map('count', $tables),
            'options'        => $options,
            'tables'         => $tables,
            'user_sms_templates' => self::userSmsTemplates([SmsTemplateRepository::class, 'key']),
        ];
    }

    /**
     * SMS personnalisés par les membres, avec leur login et e-mail pour
     * retrouver le compte à l'import (les ID utilisateurs peuvent différer).
     *
     * @param callable(string): string $metaKey clé de user meta d'un type de SMS
     * @return list<array<string, mixed>>
     */
    public static function userSmsTemplates(callable $metaKey): array
    {
        $query = ['relation' => 'OR'];
        foreach (SmsTemplateRepository::KINDS as $kind) {
            $query[] = ['key' => $metaKey($kind), 'compare' => 'EXISTS'];
        }
        $users = get_users([
            'fields'     => ['ID', 'user_login', 'user_email'],
            'meta_query' => $query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- export ponctuel
        ]);

        $result = [];
        foreach ($users as $user) {
            $row = [
                'user_id'    => (int) $user->ID,
                'user_login' => (string) $user->user_login,
                'user_email' => (string) $user->user_email,
            ];
            foreach (SmsTemplateRepository::KINDS as $kind) {
                $value      = get_user_meta((int) $user->ID, $metaKey($kind), true);
                $row[$kind] = is_string($value) && $value !== '' ? $value : null;
            }
            $result[] = $row;
        }
        return $result;
    }

    /**
     * @param list<string> $intColumns
     * @return list<array<string, mixed>>
     */
    private function dumpTable(string $suffix, array $intColumns): array
    {
        global $wpdb;
        $table = $wpdb->prefix . $suffix;

        // Table absente (migration pas encore passée) : liste vide plutôt
        // qu'une erreur SQL imprimée au milieu du fichier téléchargé.
        if (! self::tableExists($table)) {
            return [];
        }

        $rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- export ponctuel ; table issue de $wpdb->prefix et d'une constante
            "SELECT * FROM {$table} ORDER BY id", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- nom de table issu de $wpdb->prefix et d'une constante
            ARRAY_A
        );

        $intColumns = array_flip($intColumns);
        $result     = [];
        foreach ($rows ?: [] as $row) {
            foreach ($row as $col => $value) {
                if ($value !== null && isset($intColumns[$col])) {
                    $row[$col] = (int) $value;
                }
            }
            if (isset($row['raw_payload']) && is_string($row['raw_payload'])) {
                // Le payload FFTT est stocké en JSON : on le restitue en objet
                // plutôt qu'en chaîne échappée, s'il est décodable.
                $decoded = json_decode($row['raw_payload'], true);
                $row['raw_payload'] = is_array($decoded) ? $decoded : $row['raw_payload'];
            }
            $result[] = $row;
        }
        return $result;
    }

    public static function tableExists(string $table): bool
    {
        global $wpdb;
        return $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($table))) === $table; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- vérification ponctuelle
    }
}
