<?php
/**
 * Suppression du plugin (Extensions › Supprimer, `wp plugin uninstall`).
 *
 * N'efface rien, sauf si la case « Effacer toutes les données du plugin quand
 * on le supprime » est cochée dans Mon Club TT › Réglages : par défaut,
 * réinstaller le plugin retrouve tout. Sur un multisite, chaque site décide
 * pour lui-même.
 *
 * Restent dans tous les cas : les pages et articles (pages d'équipe générées
 * comprises) et les images de la médiathèque, qui appartiennent au site.
 */

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

require_once __DIR__ . '/modules/gestion-equipes/includes/Capabilities.php';

function monclubtt_desinstaller_site()
{
    global $wpdb;

    if (get_option('monclubtt_effacer_donnees') !== '1') {
        return;
    }

    // Tables de la gestion des équipes.
    $tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix . 'monclubtt_ge_') . '%')); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
    foreach ($tables as $table) {
        $wpdb->query('DROP TABLE IF EXISTS `' . esc_sql($table) . '`'); // phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- nom lu dans SHOW TABLES, préfixe du plugin.
    }

    // Options et transients, tous préfixés monclubtt_ ; delete_option() et
    // delete_transient() vident aussi le cache objet.
    $noms = $wpdb->get_col($wpdb->prepare( // phpcs:ignore WordPress.DB.DirectDatabaseQuery
        "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s OR option_name LIKE %s",
        $wpdb->esc_like('monclubtt_') . '%',
        $wpdb->esc_like('_transient_monclubtt_') . '%',
        $wpdb->esc_like('_transient_timeout_monclubtt_') . '%'
    ));
    foreach ($noms as $nom) {
        if (strpos($nom, '_transient_') === 0 && strpos($nom, '_transient_timeout_') !== 0) {
            delete_transient(substr($nom, strlen('_transient_')));
        } else {
            delete_option($nom);
        }
    }

    foreach (array('monclubtt_synchro_auto', 'monclubtt_ge_send_availability_reminders', 'monclubtt_ge_import_appearances') as $hook) {
        wp_unschedule_hook($hook);
    }

    // Rôle Membre commission équipe et droit Gérer les équipes.
    MonClubTT\GestionEquipes\Capabilities::uninstall();
    foreach (array('availability', 'confirmation') as $sms) {
        delete_metadata('user', 0, 'monclubtt_ge_sms_template_' . $sms, '', true);
    }

    // Événements du calendrier et membres du comité, avec leurs métadonnées.
    $posts = get_posts(array(
        'post_type'        => array('monclubtt_evenement', 'monclubtt_membre'),
        'post_status'      => 'any',
        'numberposts'      => -1,
        'fields'           => 'ids',
    ));
    foreach ($posts as $postId) {
        wp_delete_post($postId, true);
    }

    // Séries et catégories du calendrier, commissions du comité. Les
    // taxonomies ne sont pas enregistrées pendant la désinstallation :
    // wp_delete_term() en a besoin.
    foreach (array('monclubtt_serie', 'monclubtt_categorie', 'monclubtt_commission') as $taxonomie) {
        register_taxonomy($taxonomie, array());
        $termes = get_terms(array('taxonomy' => $taxonomie, 'hide_empty' => false, 'fields' => 'ids'));
        foreach (is_array($termes) ? $termes : array() as $termId) {
            wp_delete_term($termId, $taxonomie);
        }
    }

    // Marques laissées sur les pages d'équipe générées et les articles réseaux sociaux.
    foreach (array('_monclubtt_iddiv', '_monclubtt_idpoule', '_monclubtt_social_cle', '_monclubtt_social_periode', '_monclubtt_social_publications', '_monclubtt_social_visuel') as $cle) {
        delete_post_meta_by_key($cle);
    }

    // Images de partage du pong.
    $uploads = wp_upload_dir(null, false);
    $dossier = trailingslashit($uploads['basedir']) . 'monclubtt-pong';
    if (is_dir($dossier)) {
        foreach ((array) glob($dossier . '/*.png') as $fichier) {
            wp_delete_file($fichier);
        }
        global $wp_filesystem;
        if (!$wp_filesystem) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            WP_Filesystem();
        }
        if ($wp_filesystem) {
            $wp_filesystem->rmdir($dossier);
        }
    }
}

if (is_multisite()) {
    foreach (get_sites(array('fields' => 'ids', 'number' => 0)) as $monclubtt_site) {
        switch_to_blog((int) $monclubtt_site);
        monclubtt_desinstaller_site();
        restore_current_blog();
    }
} else {
    monclubtt_desinstaller_site();
}
