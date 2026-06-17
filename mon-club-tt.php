<?php
/*
  Plugin Name: Mon Club TT
  Plugin URI: https://github.com/robinos33/MonClubTT
  Description: Display your table tennis club's players, teams, and rankings from the official FFTT Smartping API. Not affiliated with or endorsed by the FFTT.
  Version: 1.0.0
  Author: Robin Aldasoro
  Author URI: https://github.com/robinos33
  License: GPLv2
  Text Domain: mon-club-tt
  Requires at least: 5.0
  Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

require_once('Utils.php');

class MonClubTT
{

    /**
     * Types possibles  de listes de joueurs  à insérer dans les shortcodes
     * @var array
     */
    private $typeListeJoueurs = array(
        'M', 'F', 'MF'
    );

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('init', array($this, 'monclubtt_style_scripts'));
        add_shortcode('monclubtt_equipe', array($this, 'equipes_front'));
        add_shortcode('monclubtt_joueurs', array($this, 'joueurs_front'));

        // Hooks pour exposer les données en cache aux autres plugins
        add_filter('monclubtt_get_joueurs', array($this, 'get_joueurs_data'), 10, 1);
        add_filter('monclubtt_get_equipes', array($this, 'get_equipes_data'), 10, 1);
        add_filter('monclubtt_get_classement_poule', array($this, 'get_classement_poule_data'), 10, 2);
        add_filter('monclubtt_get_rencontres_poule', array($this, 'get_rencontres_poule_data'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_monclubtt_sync', array($this, 'handle_ajax_sync'));
        add_action('wp_ajax_monclubtt_generate_pages', array($this, 'handle_ajax_generate_pages'));
        add_action('wp_ajax_monclubtt_feuille_match',        array($this, 'handle_ajax_feuille_match'));
        add_action('wp_ajax_nopriv_monclubtt_feuille_match', array($this, 'handle_ajax_feuille_match'));

        // Widget dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function add_admin_menu()
    {
        add_menu_page('Mon Club TT', 'Mon Club TT', 'manage_options', 'monclubtt_parametres', array($this, 'admin_module'));
        add_submenu_page('monclubtt_parametres', 'Equipes', 'Equipes', 'manage_options', 'monclubtt_equipes', array($this, 'equipes_admin'));
        add_submenu_page('monclubtt_parametres', 'Joueurs', 'Joueurs', 'manage_options', 'monclubtt_joueurs', array($this, 'joueurs_admin'));
    }

    public function admin_module()
    {
        $this->_getLayout('admin');
    }

    public static function getPluginData()
    {
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $datas = get_plugin_data(__FILE__);
        return $datas;
    }

    public function monclubtt_style_scripts()
    {
        // Styles
        $cssVer = filemtime(plugin_dir_path(__FILE__) . 'assets/mon-club-tt.css');
        $jsVer  = filemtime(plugin_dir_path(__FILE__) . 'assets/mon-club-tt.js');
        wp_register_style('mon-club-tt-css', plugins_url('/assets/mon-club-tt.css', __FILE__), array(), $cssVer);
        wp_enqueue_style('mon-club-tt-css');
        // Javascript
        wp_register_script('monclubtt-js', plugins_url('/assets/mon-club-tt.js', __FILE__), array('jquery'), $jsVer, true);
        wp_register_script('table-sorter', plugins_url('/assets/tablesorter/jquery.tablesorter.min.js', __FILE__), array('jquery'), '1.0', true);
        wp_register_script('table-sorter-pager', plugins_url('/assets/tablesorter/jquery.tablesorter.pager.js', __FILE__), array('jquery', 'table-sorter'), '1.0', true);
        wp_enqueue_script('monclubtt-js');
        wp_enqueue_script('table-sorter');
        wp_enqueue_script('table-sorter-pager');
        wp_localize_script('monclubtt-js', 'MonClubTTAjax', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
        ));
    }

    public function register_settings()
    {
        register_setting('monclubtt_settings', ConstantesMonClubTT::MONCLUBTT_ID_APPLICATION, array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('monclubtt_settings', ConstantesMonClubTT::MONCLUBTT_MOT_DE_PASSE,    array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('monclubtt_settings', ConstantesMonClubTT::MONCLUBTT_NUM_CLUB,        array('sanitize_callback' => 'sanitize_text_field'));

        add_settings_section('monclubtt_section', '', array($this, 'section_html'), 'monclubtt_settings');
        add_settings_field(ConstantesMonClubTT::MONCLUBTT_ID_APPLICATION, 'Id Application', array($this, 'id_application_html'), 'monclubtt_settings', 'monclubtt_section');
        add_settings_field(ConstantesMonClubTT::MONCLUBTT_MOT_DE_PASSE, 'Mot de passe Application', array($this, 'mot_de_passe_html'), 'monclubtt_settings', 'monclubtt_section');
        add_settings_field(ConstantesMonClubTT::MONCLUBTT_NUM_CLUB, 'Numéro de club', array($this, 'equipe_num_html'), 'monclubtt_settings', 'monclubtt_section');
    }

    public function section_html()
    {
        echo '<p>Entrez les paramètres de l\'application fournis par la FFTT</p>';
        echo '<p>Si vous n\'en avez pas, vous devrez faire la demande suivante en suivant la procédure décrite ici : <a target="_blank" href="http://www.fftt.com/actus/ouverture_interfaces_smartping_2015_06_30-1362.html">http://www.fftt.com/actus/ouverture_interfaces_smartping_2015_06_30-1362.html</a></p>';
    }

    public function id_application_html()
    {
        ?>
        <input type="text" name="monclubtt_id_application"
               value="<?php echo esc_attr(get_option(ConstantesMonClubTT::MONCLUBTT_ID_APPLICATION)); ?>"/>
        <?php
    }

    public function mot_de_passe_html()
    {
        ?>
        <input type="text" name="monclubtt_mot_de_passe"
               value="<?php echo esc_attr(get_option(ConstantesMonClubTT::MONCLUBTT_MOT_DE_PASSE)); ?>"/>
        <?php
    }

    public function equipe_num_html()
    {
        ?>
        <input type="text" name="monclubtt_num_club"
               value="<?php echo esc_attr(get_option(ConstantesMonClubTT::MONCLUBTT_NUM_CLUB)); ?>"/>
        <?php
    }

    public function getForm()
    {
        echo '<form action="options.php" method="POST" name="monclubtt_settings" class="monclubtt_settings_form">';
        do_settings_sections('monclubtt_settings');
        settings_fields('monclubtt_settings');
        echo '<div>' . submit_button('Valider la saisie') . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
        echo '</form>';
    }

    public function equipes_admin()
    {
        $this->_getLayout('equipes');
    }

    public function joueurs_admin()
    {
        $this->_getLayout('joueurs');
    }

    private function _getLayout($view){
        include_once(__DIR__ . '/views/admin/layout.php');
    }

    public function equipes_front($atts, $content)
    {
        $api = AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            return esc_html__('Problème lors de la récupération des résultats', 'mon-club-tt'); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        }

        // Normalise et valide les attributs du shortcode
        $atts = shortcode_atts(array('iddiv' => '', 'idpoule' => ''), (array) $atts, 'monclubtt_equipe');
        $atts['iddiv'] = (string) $atts['iddiv'];
        $atts['idpoule'] = (string) $atts['idpoule'];
        if ($atts['iddiv'] === '' || $atts['idpoule'] === '') {
            return esc_html__('Poule ou division incorrecte', 'mon-club-tt'); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
        }

        $listeEquipesM = $api->getEquipesByClub(ParametresPlugin::getNumClub(), 'M');
        $listeEquipesF = $api->getEquipesByClub(ParametresPlugin::getNumClub(), 'F');
        $listeEquipes = array_merge((array) $listeEquipesM, (array) $listeEquipesF);

        ob_start();
        require __DIR__ . '/views/front/equipes.php';
        return ob_get_clean();
    }

    /**
     * Méthode qui gère les liste de joueurs coté front
     * @param type $atts type: M | F | MF
     * @param type $content
     * @return string
     */
    public function joueurs_front($atts, $content)
    {
        $atts = shortcode_atts(array('type' => 'MF'), (array) $atts, 'monclubtt_joueurs');
        if (in_array($atts['type'], $this->getTypeListeJoueurs(), true)) {
            $listeJoueurs = array();
            $joueurs = new Joueurs();
            $api = AccesFFTTApi::getInstance();
            $numClub = ParametresPlugin::getNumClub();
            $updatedAt = $api->getCacheUpdatedAt('joueurs_club', array('numclu' => $numClub));
            ob_start();
            require __DIR__ . '/views/front/joueurs.php';
            return ob_get_clean();
        }
        return esc_html__('Erreur de paramètres du shortcode', 'mon-club-tt'); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch
    }

    private function getTypeListeJoueurs()
    {
        return $this->typeListeJoueurs;
    }

    /**
     * Hook pour récupérer les données des joueurs en cache
     * Usage: $joueurs = apply_filters('monclubtt_get_joueurs', 'MF');
     * @param string $type Type de joueurs ('M', 'F', ou 'MF')
     * @return array Tableau d'objets Joueur
     */
    public function get_joueurs_data($type = 'MF')
    {
        if (!in_array($type, $this->typeListeJoueurs, true)) {
            $type = 'MF';
        }
        $joueurs = new Joueurs($type);
        return $joueurs->getJoueurs($type);
    }

    /**
     * Hook pour récupérer les données des équipes en cache
     * Usage: $equipes = apply_filters('monclubtt_get_equipes', 'M');
     * @param string $type Type d'équipes ('M' ou 'F', null pour toutes)
     * @return array Tableau d'équipes
     */
    public function get_equipes_data($type = null)
    {
        $api = AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            return array();
        }

        if ($type === 'M' || $type === 'F') {
            return $api->getEquipesByClub(ParametresPlugin::getNumClub(), $type);
        }

        // Retourne toutes les équipes (M et F)
        $equipesM = $api->getEquipesByClub(ParametresPlugin::getNumClub(), 'M');
        $equipesF = $api->getEquipesByClub(ParametresPlugin::getNumClub(), 'F');
        return array_merge((array) $equipesM, (array) $equipesF);
    }

    /**
     * Hook pour récupérer le classement d'une poule en cache
     * Usage: $classement = apply_filters('monclubtt_get_classement_poule', null, array('division' => 'D1', 'poule' => 'A'));
     * @param mixed $value Valeur par défaut (ignorée)
     * @param array $params Paramètres avec 'division' et 'poule'
     * @return array Classement de la poule
     */
    public function get_classement_poule_data($value, $params)
    {
        $api = AccesFFTTApi::getInstance();
        if (!is_object($api) || !isset($params['division']) || !isset($params['poule'])) {
            return array();
        }
        return $api->getPouleClassement($params['division'], $params['poule']);
    }

    /**
     * Hook pour récupérer les rencontres d'une poule en cache
     * Usage: $rencontres = apply_filters('monclubtt_get_rencontres_poule', null, array('division' => 'D1', 'poule' => 'A'));
     * @param mixed $value Valeur par défaut (ignorée)
     * @param array $params Paramètres avec 'division' et 'poule'
     * @return array Rencontres de la poule
     */
    public function get_rencontres_poule_data($value, $params)
    {
        $api = AccesFFTTApi::getInstance();
        if (!is_object($api) || !isset($params['division']) || !isset($params['poule'])) {
            return array();
        }
        return $api->getPouleRencontres($params['division'], $params['poule']);
    }

    /**
     * Handler AJAX public : retourne le détail d'une rencontre (feuille de match).
     * Accessible aux visiteurs non connectés (wp_ajax_nopriv).
     */
    public function handle_ajax_feuille_match()
    {
        $rencId   = sanitize_text_field(wp_unslash($_POST['renc_id']   ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing
        $isRetour = (int) wp_unslash($_POST['is_retour'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

        if (empty($rencId)) {
            wp_send_json_error(array('message' => 'ID de rencontre manquant'));
            return;
        }

        $api  = AccesFFTTApi::getInstance();
        $data = $api->getRencontreDetail($rencId, $isRetour);

        if (!$data) {
            wp_send_json_error(array('message' => 'Feuille de match non disponible'));
            return;
        }

        wp_send_json_success($data);
    }

    /**
     * Handler AJAX pour la synchronisation manuelle des données
     */
    public function handle_ajax_sync()
    {
        check_ajax_referer('monclubtt_sync_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $api = AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            wp_send_json_error(array('message' => 'Erreur de connexion à l\'API FFTT'));
            return;
        }

        try {
            $numClub = ParametresPlugin::getNumClub();
            $syncResults = array();
            $debugLog = array();

            // Vérifier les paramètres de connexion
            if (empty($numClub)) {
                throw new Exception('Numéro de club non configuré');
            }
            $debugLog[] = "Numéro de club: " . $numClub;

            $idApp = ParametresPlugin::getIdApplication();
            $motDePasse = ParametresPlugin::getMotDePasse();
            if (empty($idApp) || empty($motDePasse)) {
                throw new Exception('Identifiants API FFTT non configurés');
            }
            $debugLog[] = "ID Application: configuré";

            // Synchronisation des joueurs
            $api->clearJoueursCache($numClub);
            $debugLog[] = "Cache joueurs effacé";

            $joueurs = new Joueurs();
            $joueursListe = $joueurs->getJoueurs('MF');
            $syncResults['joueurs'] = count($joueursListe);
            $debugLog[] = "Joueurs récupérés: " . $syncResults['joueurs'];

            // Synchronisation des équipes
            $api->clearEquipesCache($numClub);
            $debugLog[] = "Cache équipes effacé";

            $equipesM = $api->getEquipesByClub($numClub, 'M');
            $equipesF = $api->getEquipesByClub($numClub, 'F');
            $syncResults['equipes'] = count($equipesM) + count($equipesF);
            $debugLog[] = "Équipes M récupérées: " . count($equipesM);
            $debugLog[] = "Équipes F récupérées: " . count($equipesF);

            // Vérifier si aucune donnée n'a été récupérée
            if ($syncResults['joueurs'] === 0 && $syncResults['equipes'] === 0) {
                throw new Exception('Aucune donnée récupérée. Vérifiez vos identifiants API et le numéro de club.');
            }

            // Pour chaque équipe, synchroniser classements et rencontres
            $allEquipes = array_merge($equipesM, $equipesF);
            foreach ($allEquipes as $equipe) {
                if (isset($equipe['iddiv']) && isset($equipe['idpoule'])) {
                    $api->clearPouleCache($equipe['iddiv'], $equipe['idpoule']);
                    $api->getPouleClassement($equipe['iddiv'], $equipe['idpoule']);
                    $api->getPouleRencontres($equipe['iddiv'], $equipe['idpoule']);
                }
            }

            // Enregistrer l'horodatage de la dernière synchronisation
            update_option('monclubtt_last_sync', time());

            wp_send_json_success(array(
                'message' => 'Synchronisation réussie',
                'timestamp' => current_time('mysql'),
                'results' => $syncResults,
                'debug' => $debugLog
            ));
        } catch (Exception $e) {
            wp_send_json_error(array(
                'message' => 'Erreur lors de la synchronisation: ' . $e->getMessage(),
                'debug' => isset($debugLog) ? $debugLog : array()
            ));
        }
    }

    /**
     * Handler AJAX : synchronise les pages WordPress avec la sélection d'équipes.
     * - Crée ou remet en ligne les pages des équipes cochées.
     * - Met à la corbeille les pages des équipes décochées.
     * Les pages générées sont tracées via le meta _monclubtt_iddiv / _monclubtt_idpoule.
     */
    public function handle_ajax_generate_pages()
    {
        check_ajax_referer('monclubtt_generate_pages_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $teamsCreate = isset($_POST['teams_create']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['teams_create'])) : array();
        $teamsDelete = isset($_POST['teams_delete']) ? array_map('sanitize_text_field', wp_unslash((array) $_POST['teams_delete'])) : array();

        if (empty($teamsCreate) && empty($teamsDelete)) {
            wp_send_json_error(array('message' => 'Aucune équipe dans la liste'));
            return;
        }

        $parentId = $this->getOrCreateEquipesParentPage();
        $created  = 0;
        $updated  = 0;
        $deleted  = 0;

        // ── 1. Supprimer (corbeille) les pages des équipes décochées ─────────
        foreach ($teamsDelete as $team) {
            $iddiv   = sanitize_text_field($team['iddiv']   ?? '');
            $idpoule = sanitize_text_field($team['idpoule'] ?? '');
            if (empty($iddiv)) {
                continue;
            }
            $page = $this->findMonClubTTPage($iddiv, $idpoule);
            if ($page) {
                wp_trash_post($page->ID);
                $deleted++;
            }
        }

        // ── 2. Créer ou remettre en ligne les pages des équipes cochées ──────
        foreach ($teamsCreate as $team) {
            $iddiv   = sanitize_text_field($team['iddiv']    ?? '');
            $idpoule = sanitize_text_field($team['idpoule']  ?? '');
            $title   = sanitize_text_field($team['libequipe'] ?? '');
            if (empty($iddiv) || empty($title)) {
                continue;
            }

            $content = '[monclubtt_equipe iddiv="' . $iddiv . '" idpoule="' . $idpoule . '"]';
            // Chercher d'abord par meta (page déjà gérée par MonClubTT, y.c. à la corbeille)
            $page = $this->findMonClubTTPage($iddiv, $idpoule, true);

            if ($page) {
                wp_update_post(array(
                    'ID'           => $page->ID,
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_parent'  => $parentId,
                    'post_status'  => 'publish',
                ));
                $updated++;
            } else {
                $pageId = (int) wp_insert_post(array(
                    'post_title'   => $title,
                    'post_content' => $content,
                    'post_status'  => 'publish',
                    'post_type'    => 'page',
                    'post_parent'  => $parentId,
                ));
                update_post_meta($pageId, '_monclubtt_iddiv',   $iddiv);
                update_post_meta($pageId, '_monclubtt_idpoule', $idpoule);
                $created++;
                continue; // meta déjà posé, on passe
            }

            // Mettre à jour les metas (au cas où elles manquaient)
            update_post_meta($page->ID, '_monclubtt_iddiv',   $iddiv);
            update_post_meta($page->ID, '_monclubtt_idpoule', $idpoule);
        }

        $parentUrl = get_permalink($parentId);
        $parts = array();
        if ($created) { $parts[] = $created . ' créée(s)'; }
        if ($updated) { $parts[] = $updated . ' mise(s) à jour'; }
        if ($deleted) { $parts[] = $deleted . ' mise(s) à la corbeille'; }
        $message = $parts ? implode(', ', $parts) . '.' : 'Aucune modification.';

        wp_send_json_success(array(
            'message'    => $message,
            'created'    => $created,
            'updated'    => $updated,
            'deleted'    => $deleted,
            'parent_url' => $parentUrl,
        ));
    }

    /**
     * Recherche une page WP générée par MonClubTT via ses metas iddiv/idpoule.
     *
     * @param string $iddiv
     * @param string $idpoule
     * @param bool   $includeTrashed Inclure les pages à la corbeille
     * @return WP_Post|null
     */
    private function findMonClubTTPage($iddiv, $idpoule, $includeTrashed = false)
    {
        $statuses = array('publish', 'draft', 'private');
        if ($includeTrashed) {
            $statuses[] = 'trash';
        }
        $query = new WP_Query(array(
            'post_type'      => 'page',
            'post_status'    => $statuses,
            'posts_per_page' => 1,
            'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
                array('key' => '_monclubtt_iddiv',   'value' => $iddiv),
                array('key' => '_monclubtt_idpoule', 'value' => $idpoule),
            ),
        ));
        return $query->have_posts() ? $query->posts[0] : null;
    }

    /**
     * Trouve ou crée la page parent "Équipes" pour les pages d'équipe.
     * @return int ID de la page parent
     */
    private function getOrCreateEquipesParentPage()
    {
        $existing = get_page_by_path('equipes', OBJECT, 'page');
        if ($existing) {
            return $existing->ID;
        }

        return (int) wp_insert_post(array(
            'post_title'   => 'Équipes',
            'post_name'    => 'equipes',
            'post_content' => '',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ));
    }

    /**
     * Récupère l'horodatage de la dernière synchronisation
     * @return int|false Timestamp de la dernière sync ou false
     */
    public static function getLastSyncTimestamp()
    {
        return get_option('monclubtt_last_sync', false);
    }

    /**
     * Ajoute le widget au dashboard WordPress
     */
    public function add_dashboard_widget()
    {
        wp_add_dashboard_widget(
            'monclubtt_sync_widget',
            'Mon Club TT - Synchronisation',
            array($this, 'render_dashboard_widget')
        );
    }

    /**
     * Affiche le contenu du widget dashboard
     */
    public function render_dashboard_widget()
    {
        $lastSync = self::getLastSyncTimestamp();
        ?>
        <div class="monclubtt-dashboard-widget">
            <?php if ($lastSync): ?>
                <?php
                $syncDate = date_i18n(get_option('date_format') . ' à ' . get_option('time_format'), $lastSync);
                $timeDiff = human_time_diff($lastSync, current_time('timestamp'));
                ?>
                <p>
                    <strong>Dernière synchronisation :</strong><br>
                    <?php echo esc_html($syncDate); ?><br>
                    <small style="color: #666;">(il y a <?php echo esc_html($timeDiff); ?>)</small>
                </p>
            <?php else: ?>
                <p><em>Aucune synchronisation effectuée</em></p>
            <?php endif; ?>

            <p>
                <button id="monclubtt-dashboard-sync-button" class="button button-primary button-large" style="width:100%; display:inline-flex; align-items:center; justify-content:center; gap:6px; line-height:1;">
                    <span aria-hidden="true" style="font-family:dashicons; display:inline-block; font-size:20px; line-height:1; width:20px; height:20px; flex-shrink:0; speak:none; -webkit-font-smoothing:antialiased;">&#xf463;</span>
                    Synchroniser les données
                </button>
            </p>

            <div id="monclubtt-dashboard-sync-loading" style="display: none; text-align: center; margin: 10px 0;">
                <span class="spinner is-active" style="float: none; margin: 0;"></span>
                <span style="vertical-align: middle; margin-left: 5px;">Synchronisation en cours...</span>
            </div>

            <div id="monclubtt-dashboard-sync-message" style="margin-top: 10px;"></div>
        </div>

        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(document).ready(function($) {
            $("#monclubtt-dashboard-sync-button").on("click", function(e) {
                e.preventDefault();
                var $button = $(this);
                var $loading = $("#monclubtt-dashboard-sync-loading");
                var $message = $("#monclubtt-dashboard-sync-message");
                $button.prop("disabled", true);
                $loading.show();
                $message.html("");
                $.ajax({
                    url: ajaxurl, type: "POST",
                    data: { action: "monclubtt_sync", nonce: ' . wp_json_encode(wp_create_nonce('monclubtt_sync_nonce')) . ' },
                    success: function(response) {
                        $loading.hide();
                        $button.prop("disabled", false);
                        if (response.success) {
                            $message.html("<div class=\"notice notice-success inline\"><p><strong>Succès !</strong> " + response.data.message + "</p></div>");
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            $message.html("<div class=\"notice notice-error inline\"><p><strong>Erreur :</strong> " + response.data.message + "</p></div>");
                        }
                    },
                    error: function() {
                        $loading.hide();
                        $button.prop("disabled", false);
                        $message.html("<div class=\"notice notice-error inline\"><p><strong>Erreur :</strong> Erreur de communication avec le serveur</p></div>");
                    }
                });
            });
        });');

        wp_add_inline_style('mon-club-tt-css', '
            .monclubtt-dashboard-widget p { margin: 10px 0; }
            .monclubtt-dashboard-widget .notice.inline { margin: 10px 0 0 0; padding: 8px 12px; }
            .monclubtt-dashboard-widget .spinner { visibility: visible; }
            .monclubtt-icon { font-family: dashicons; display: inline-block; width: 20px; height: 20px; font-size: 20px; line-height: 1; font-weight: 400; font-style: normal; speak: never; -webkit-font-smoothing: antialiased; flex-shrink: 0; }
        ');
    }
}

new MonClubTT();

