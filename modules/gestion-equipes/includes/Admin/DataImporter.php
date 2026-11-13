<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Admin;

/**
 * Import des données de la gestion des équipes.
 *
 * Sources acceptées, de même structure (voir DataExporter) :
 * - fichier JSON exporté par TT Team Planner (format « tt-team-planner-export ») ;
 * - fichier JSON exporté par ce module (format « monclubtt-gestion-equipes-export ») ;
 * - tables de TT Team Planner encore présentes sur ce site (plugin désactivé
 *   mais pas supprimé : sa désinstallation efface ses tables).
 *
 * L'import REMPLACE les données du module : les tables sont vidées puis
 * remplies avec les identifiants d'origine, ce qui garde intactes les
 * références `player_id`. Les liens magiques sont supprimés (ils visent les
 * anciens joueurs) : le staff en renverra depuis l'application.
 */
class DataImporter
{
    public const ACTION = 'monclubtt_ge_import_data';

    /** Format des exports de TT Team Planner. */
    public const LEGACY_FORMAT = 'tt-team-planner-export';

    /** Préfixes de l'ancien plugin (tables et options). */
    private const LEGACY_TABLE_PREFIX  = 'tttp_';
    private const LEGACY_OPTION_PREFIX = 'ttp_';

    /** Taille maximale du fichier importé. */
    private const MAX_BYTES = 20 * MB_IN_BYTES;

    /** Rapport du dernier import, affiché après la redirection. */
    private const REPORT_TRANSIENT = 'monclubtt_ge_import_report_';

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    // =========================================================================
    // Rendu (onglet Import / export)
    // =========================================================================

    public function render(): void
    {
        $this->renderReport();

        echo '<h2>' . esc_html__('Importer', 'mon-club-tt') . '</h2>';
        echo '<p class="description" style="max-width:960px">' . esc_html__('Rapatrie les données de TT Team Planner (ou d\'une sauvegarde de la gestion des équipes) : réglages, joueurs et leurs coordonnées, disponibilités, compositions, effectifs de phase, rencontres jouées, journées validées et forfaits.', 'mon-club-tt') . '</p>';
        echo '<div class="notice notice-warning inline" style="max-width:960px"><p>' . esc_html__('L\'import remplace toutes les données actuelles de la gestion des équipes. Les liens magiques déjà envoyés ne fonctionneront plus : renvoyez-en depuis l\'application.', 'mon-club-tt') . '</p></div>';

        $legacy = self::legacyCounts();
        if ($legacy !== null) {
            echo '<h3>' . esc_html__('Depuis TT Team Planner, sur ce site', 'mon-club-tt') . '</h3>';
            echo '<p>' . esc_html(sprintf(
                /* translators: 1: joueurs, 2: compositions, 3: disponibilités */
                __('Données de TT Team Planner trouvées : %1$d joueurs, %2$d places en composition, %3$d disponibilités.', 'mon-club-tt'),
                $legacy['players'],
                $legacy['team_compositions'],
                $legacy['availabilities']
            )) . '</p>';
            $this->renderForm('local');
        }

        echo '<h3>' . esc_html__('Depuis un fichier JSON', 'mon-club-tt') . '</h3>';
        echo '<p class="description">' . esc_html__('Fichier obtenu avec « Exporter en JSON » dans les réglages de TT Team Planner, ou avec l\'export ci-dessous.', 'mon-club-tt') . '</p>';
        $this->renderForm('file');

        if ($legacy !== null) {
            echo '<p class="description" style="max-width:960px">' . esc_html__('Une fois l\'import vérifié dans l\'application, vous pouvez supprimer l\'extension TT Team Planner : ses tables seront effacées, les données importées ici sont indépendantes.', 'mon-club-tt') . '</p>';
        }
    }

    private function renderForm(string $source): void
    {
        $id = 'monclubtt-ge-import-' . $source;
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '"' . ($source === 'file' ? ' enctype="multipart/form-data"' : '') . '>';
        echo '<input type="hidden" name="action" value="' . esc_attr(self::ACTION) . '">';
        echo '<input type="hidden" name="source" value="' . esc_attr($source) . '">';
        wp_nonce_field(self::ACTION);
        if ($source === 'file') {
            echo '<p><input type="file" name="fichier" accept=".json,application/json" required></p>';
        }
        echo '<p><label for="' . esc_attr($id) . '"><input type="checkbox" id="' . esc_attr($id) . '" name="confirmer" value="1" required> ';
        echo esc_html__('Remplacer les données actuelles de la gestion des équipes', 'mon-club-tt') . '</label></p>';
        submit_button(
            $source === 'local' ? __('Importer depuis TT Team Planner', 'mon-club-tt') : __('Importer le fichier', 'mon-club-tt'),
            'primary',
            'submit',
            false
        );
        echo '</form>';
    }

    private function renderReport(): void
    {
        $key    = self::REPORT_TRANSIENT . get_current_user_id();
        $report = get_transient($key);
        if (! is_array($report)) {
            return;
        }
        delete_transient($key);

        if (! empty($report['error'])) {
            echo '<div class="notice notice-error"><p><strong>' . esc_html__('Import impossible :', 'mon-club-tt') . '</strong> ' . esc_html((string) $report['error']) . '</p></div>';
            return;
        }

        $labels = [
            'players'           => __('joueurs', 'mon-club-tt'),
            'availabilities'    => __('disponibilités', 'mon-club-tt'),
            'team_compositions' => __('places en composition', 'mon-club-tt'),
            'phase_squads'      => __('places en effectif de phase', 'mon-club-tt'),
            'match_appearances' => __('participations', 'mon-club-tt'),
            'validated_rounds'  => __('journées validées', 'mon-club-tt'),
            'team_forfeits'     => __('forfaits', 'mon-club-tt'),
        ];
        echo '<div class="notice notice-success"><p><strong>' . esc_html__('Import terminé.', 'mon-club-tt') . '</strong></p><ul style="list-style:disc;margin-left:20px">';
        foreach ($labels as $key => $label) {
            echo '<li>' . esc_html(number_format_i18n((int) ($report['counts'][$key] ?? 0)) . ' ' . $label) . '</li>';
        }
        echo '</ul>';
        if (! empty($report['skipped'])) {
            echo '<p>' . esc_html(sprintf(
                /* translators: %d: nombre de lignes */
                _n('%d ligne ignorée (données invalides).', '%d lignes ignorées (données invalides).', (int) $report['skipped'], 'mon-club-tt'),
                (int) $report['skipped']
            )) . '</p>';
        }
        echo '</div>';
    }

    // =========================================================================
    // Traitement
    // =========================================================================

    public function handle(): void
    {
        check_admin_referer(self::ACTION);
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission insuffisante.', 'mon-club-tt'), 403);
        }

        $report = [];
        try {
            if (empty($_POST['confirmer'])) {
                throw new \RuntimeException(esc_html__('Cochez la case de confirmation.', 'mon-club-tt'));
            }
            $source = isset($_POST['source']) ? sanitize_key(wp_unslash($_POST['source'])) : '';
            $data   = $source === 'local' ? self::buildFromLegacyTables() : self::readUploadedFile();
            $report = $this->import($data);
        } catch (\RuntimeException $e) {
            $report = ['error' => $e->getMessage()];
        }

        set_transient(self::REPORT_TRANSIENT . get_current_user_id(), $report, 10 * MINUTE_IN_SECONDS);
        wp_safe_redirect(SettingsPage::url('donnees'));
        exit;
    }

    /** @return array<string, mixed> */
    private static function readUploadedFile(): array
    {
        $file = $_FILES['fichier'] ?? null; // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- nonce vérifié dans handle() ; seul le fichier temporaire est lu, après is_uploaded_file()
        if (! is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || ! is_uploaded_file((string) $file['tmp_name'])) {
            throw new \RuntimeException(esc_html__('Aucun fichier reçu.', 'mon-club-tt'));
        }
        if ((int) $file['size'] > self::MAX_BYTES) {
            throw new \RuntimeException(esc_html__('Fichier trop volumineux (20 Mo maximum).', 'mon-club-tt'));
        }

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lecture du fichier téléversé temporaire
        $json = file_get_contents((string) $file['tmp_name']);
        $data = is_string($json) ? json_decode($json, true) : null;
        if (! is_array($data)) {
            throw new \RuntimeException(esc_html__('Le fichier n\'est pas un JSON valide.', 'mon-club-tt'));
        }
        return $data;
    }

    /**
     * Nombre de lignes des tables de TT Team Planner présentes sur ce site,
     * ou null si elles n'existent pas.
     *
     * @return array<string, int>|null
     */
    public static function legacyCounts(): ?array
    {
        global $wpdb;
        $players = $wpdb->prefix . self::LEGACY_TABLE_PREFIX . 'players';
        if ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($players))) !== $players) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- détection ponctuelle de l'ancien plugin
            return null;
        }

        $counts = [];
        foreach (DataExporter::TABLES as $key => $def) {
            $table = $wpdb->prefix . self::legacyTable($def['table']);
            $counts[$key] = (int) $wpdb->get_var("SELECT COUNT(*) FROM `{$table}`"); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- nom de table issu de $wpdb->prefix et d'une constante
        }
        return $counts;
    }

    /**
     * Construit, depuis les tables et options de TT Team Planner, la même
     * structure qu'un fichier d'export.
     *
     * @return array<string, mixed>
     */
    public static function buildFromLegacyTables(): array
    {
        global $wpdb;
        if (self::legacyCounts() === null) {
            throw new \RuntimeException(esc_html__('Aucune donnée de TT Team Planner sur ce site.', 'mon-club-tt'));
        }

        $options = [];
        foreach (DataExporter::OPTIONS as $name) {
            $legacy           = self::legacyOption($name);
            $options[$legacy] = get_option($legacy, null);
        }
        $options[self::LEGACY_OPTION_PREFIX . 'club_name'] = get_option(self::LEGACY_OPTION_PREFIX . 'club_name', null);

        $tables = [];
        foreach (DataExporter::TABLES as $key => $def) {
            $table = $wpdb->prefix . self::legacyTable($def['table']);
            $tables[$key] = (array) $wpdb->get_results("SELECT * FROM `{$table}` ORDER BY id", ARRAY_A); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- import ponctuel, nom de table issu de $wpdb->prefix et d'une constante
        }

        return [
            'format'         => self::LEGACY_FORMAT,
            'format_version' => 1,
            'options'        => $options,
            'tables'         => $tables,
        ];
    }

    /**
     * Remplace les données du module par celles de $data.
     *
     * @param array<string, mixed> $data Structure d'export (DataExporter::build()).
     * @return array{counts: array<string, int>, skipped: int}
     */
    public function import(array $data): array
    {
        global $wpdb;

        $format = (string) ($data['format'] ?? '');
        if (! in_array($format, [self::LEGACY_FORMAT, DataExporter::FORMAT], true)) {
            throw new \RuntimeException(esc_html__('Ce fichier n\'est pas un export de TT Team Planner ni de la gestion des équipes.', 'mon-club-tt'));
        }
        if ((int) ($data['format_version'] ?? 0) !== 1) {
            throw new \RuntimeException(esc_html__('Version de format non prise en charge.', 'mon-club-tt'));
        }
        if (! isset($data['tables']) || ! is_array($data['tables'])) {
            throw new \RuntimeException(esc_html__('Le fichier ne contient aucune table.', 'mon-club-tt'));
        }

        // Tables vidées d'abord : les lignes gardent leurs identifiants d'origine.
        $targets = [];
        foreach (DataExporter::TABLES as $key => $def) {
            $targets[$key] = $wpdb->prefix . $def['table'];
        }
        $targets['magic_links'] = $wpdb->prefix . 'monclubtt_ge_magic_links';

        // phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- import ponctuel, noms de tables issus de $wpdb->prefix et de constantes
        $wpdb->query('START TRANSACTION');
        foreach ($targets as $table) {
            $wpdb->query("DELETE FROM `{$table}`");
        }

        $counts  = [];
        $skipped = 0;
        foreach (DataExporter::TABLES as $key => $def) {
            $table   = $targets[$key];
            $columns = array_flip((array) $wpdb->get_col("DESCRIBE `{$table}`", 0));
            $counts[$key] = 0;

            foreach ((array) ($data['tables'][$key] ?? []) as $row) {
                if (! is_array($row) || ! isset($row['id'])) {
                    $skipped++;
                    continue;
                }
                // Colonnes inconnues ignorées : seules celles de la table sont écrites.
                $row = array_intersect_key($row, $columns);
                if (isset($row['raw_payload']) && ! is_string($row['raw_payload'])) {
                    $row['raw_payload'] = (string) wp_json_encode($row['raw_payload']);
                }
                foreach ($row as $col => $value) {
                    if (is_array($value) || is_object($value)) {
                        $row[$col] = (string) wp_json_encode($value);
                    } elseif (is_bool($value)) {
                        $row[$col] = (int) $value;
                    }
                }
                if ($wpdb->insert($table, $row) === false) {
                    $skipped++;
                    continue;
                }
                $counts[$key]++;
            }
        }
        $wpdb->query('COMMIT');
        // phpcs:enable

        $this->importOptions(is_array($data['options'] ?? null) ? $data['options'] : [], $format, (string) ($data['club_name'] ?? ''));
        self::flushCaches();

        return ['counts' => $counts, 'skipped' => $skipped];
    }

    /** @param array<string, mixed> $options */
    private function importOptions(array $options, string $format, string $clubName): void
    {
        foreach (DataExporter::OPTIONS as $name) {
            $source = $format === self::LEGACY_FORMAT ? self::legacyOption($name) : $name;
            if (array_key_exists($source, $options) && $options[$source] !== null) {
                update_option($name, $options[$source]);
            }
        }

        // Nom du club : repris seulement s'il n'est pas déjà saisi dans Réglages.
        if ($format === self::LEGACY_FORMAT) {
            $clubName = (string) ($options[self::LEGACY_OPTION_PREFIX . 'club_name'] ?? '');
        }
        $clubName = sanitize_text_field($clubName);
        if ($clubName !== '' && trim((string) get_option(\MonClubTT_Constantes::MONCLUBTT_NOM_CLUB, '')) === '') {
            update_option(\MonClubTT_Constantes::MONCLUBTT_NOM_CLUB, $clubName);
        }
    }

    /** Vide les caches objet des dépôts, qui garderaient les anciennes lignes. */
    private static function flushCaches(): void
    {
        $groups = [
            'monclubtt_ge_players', 'monclubtt_ge_availability', 'monclubtt_ge_compositions', 'monclubtt_ge_squads',
            'monclubtt_ge_appearances', 'monclubtt_ge_validated_rounds', 'monclubtt_ge_team_forfeits',
        ];
        if (function_exists('wp_cache_supports') && wp_cache_supports('flush_group')) {
            foreach ($groups as $group) {
                wp_cache_flush_group($group);
            }
            return;
        }
        wp_cache_flush();
    }

    /** monclubtt_ge_players → tttp_players */
    private static function legacyTable(string $table): string
    {
        return self::LEGACY_TABLE_PREFIX . substr($table, strlen('monclubtt_ge_'));
    }

    /** monclubtt_ge_teams → ttp_teams */
    private static function legacyOption(string $option): string
    {
        return self::LEGACY_OPTION_PREFIX . substr($option, strlen('monclubtt_ge_'));
    }
}
