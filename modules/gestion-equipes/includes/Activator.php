<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes;


class Activator
{
    /**
     * Applique les migrations de schéma si la version DB est dépassée.
     * À appeler à chaque chargement admin (pas seulement à l'activation).
     */
    public static function maybeUpgrade(): void
    {
        if (get_option('monclubtt_ge_db_version') === MONCLUBTT_GE_VERSION) {
            return;
        }
        self::createTables(); // dbDelta() ajoute les colonnes manquantes sans rien casser
        self::seedDefaultOptions();
    }

    private static function createTables(): void
    {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();

        $sql = [];

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_players (
            id             bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            external_id    varchar(100)        NOT NULL DEFAULT '',
            first_name     varchar(100)        NOT NULL DEFAULT '',
            last_name      varchar(100)        NOT NULL DEFAULT '',
            license_number varchar(50)         NOT NULL DEFAULT '',
            phone          varchar(30)         NOT NULL DEFAULT '',
            email          varchar(255)        NOT NULL DEFAULT '',
            ranking        int(11)             NOT NULL DEFAULT 0,
            best_official_points int(11)       NOT NULL DEFAULT 0,
            best_official_season varchar(20)   NOT NULL DEFAULT '',
            usual_team     varchar(20)         NOT NULL DEFAULT '',
            is_foreign     tinyint(1)          NOT NULL DEFAULT 0,
            is_captain     tinyint(1)          NOT NULL DEFAULT 0,
            is_young       tinyint(1)          NOT NULL DEFAULT 0,
            is_mutation    tinyint(1)          NOT NULL DEFAULT 0,
            is_burned      tinyint(1)          NOT NULL DEFAULT 0,
            is_active      tinyint(1)          NOT NULL DEFAULT 1,
            notes          text                NOT NULL,
            raw_payload    longtext            NOT NULL,
            synced_at      datetime                     DEFAULT NULL,
            created_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at     datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            UNIQUE KEY external_id (external_id),
            KEY usual_team (usual_team)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_availabilities (
            id        bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id bigint(20) UNSIGNED NOT NULL,
            season    varchar(20)         NOT NULL DEFAULT '',
            phase     tinyint(1)          NOT NULL DEFAULT 1,
            round     tinyint(2)          NOT NULL DEFAULT 1,
            status    enum('available','unavailable','uncertain','unknown') NOT NULL DEFAULT 'unknown',
            comment   text                NOT NULL,
            created_at datetime           NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY player_season_phase_round (player_id, season, phase, round),
            KEY season_phase_round (season, phase, round)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_team_compositions (
            id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season      varchar(20)         NOT NULL DEFAULT '',
            phase       tinyint(1)          NOT NULL DEFAULT 1,
            round       tinyint(2)          NOT NULL DEFAULT 1,
            team_code   varchar(20)         NOT NULL DEFAULT '',
            slot_number tinyint(1)          NOT NULL DEFAULT 1,
            player_id   bigint(20) UNSIGNED          DEFAULT NULL,
            created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_round_team_slot (season, phase, round, team_code, slot_number),
            KEY player_season (player_id, season, phase, round)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_match_appearances (
            id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season       varchar(20)         NOT NULL DEFAULT '',
            phase        tinyint(1)          NOT NULL DEFAULT 1,
            round        tinyint(2)          NOT NULL DEFAULT 1,
            team_code    varchar(20)         NOT NULL DEFAULT '',
            team_rank    smallint(5)         NOT NULL DEFAULT 0,
            player_id    bigint(20) UNSIGNED NOT NULL,
            slot_number  tinyint(1)          NOT NULL DEFAULT 1,
            validated_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            validated_by bigint(20) UNSIGNED          DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_round_team_player (season, phase, round, team_code, player_id),
            KEY player_phase (player_id, season, phase),
            KEY season_phase_round (season, phase, round)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_validated_rounds (
            id           bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season       varchar(20)         NOT NULL DEFAULT '',
            phase        tinyint(1)          NOT NULL DEFAULT 1,
            round        tinyint(2)          NOT NULL DEFAULT 1,
            team_code    varchar(20)         NOT NULL DEFAULT '',
            validated_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            validated_by bigint(20) UNSIGNED          DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_round_team (season, phase, round, team_code)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_magic_links (
            id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            player_id   bigint(20) UNSIGNED NOT NULL,
            token_hash  varchar(64)         NOT NULL DEFAULT '',
            season      varchar(20)         NOT NULL DEFAULT '',
            expires_at  datetime            NOT NULL,
            used_at     datetime                     DEFAULT NULL,
            reminded_at datetime                     DEFAULT NULL,
            is_reminder tinyint(1)          NOT NULL DEFAULT 0,
            created_at  datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY token_hash (token_hash),
            KEY player_id (player_id),
            KEY reminder_scan (season, is_reminder, used_at, reminded_at)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_phase_squads (
            id         bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season     varchar(20)         NOT NULL DEFAULT '',
            phase      tinyint(1)          NOT NULL DEFAULT 1,
            team_code  varchar(20)         NOT NULL DEFAULT '',
            player_id  bigint(20) UNSIGNED NOT NULL,
            position   smallint(5)         NOT NULL DEFAULT 0,
            created_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_team_player (season, phase, team_code, player_id),
            KEY season_phase (season, phase)
        ) $charset;";

        $sql[] = "CREATE TABLE {$wpdb->prefix}monclubtt_ge_team_forfeits (
            id          bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            season      varchar(20)         NOT NULL DEFAULT '',
            phase       tinyint(1)          NOT NULL DEFAULT 1,
            round       tinyint(2)          NOT NULL DEFAULT 1,
            team_code   varchar(20)         NOT NULL DEFAULT '',
            declared_at datetime            NOT NULL DEFAULT CURRENT_TIMESTAMP,
            declared_by bigint(20) UNSIGNED          DEFAULT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY season_phase_round_team (season, phase, round, team_code)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        foreach ($sql as $query) {
            dbDelta($query);
        }

        update_option('monclubtt_ge_db_version', MONCLUBTT_GE_VERSION);
    }

    private static function seedDefaultOptions(): void
    {
        $defaults = [
            'monclubtt_ge_teams'                      => [],
            'monclubtt_ge_sms_template_availability'  => 'Bonjour {prenom}, es-tu disponible pour la J{journee} ({date}) ? Merci de répondre. — ' . get_bloginfo('name'),
            'monclubtt_ge_sms_template_confirmation'  => 'Bonjour {prenom}, tu joues en {equipe} pour la J{journee} le {date}. À bientôt ! — ' . get_bloginfo('name'),
            'monclubtt_ge_last_sync'                  => null,
        ];

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
}
