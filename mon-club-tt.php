<?php
/*
  Plugin Name: Mon Club TT
  Plugin URI: https://github.com/robinos33/MonClubTT
  Description: Display your table tennis club's players, teams, and rankings from the official FFTT Smartping API. Not affiliated with or endorsed by the FFTT.
  Version: 1.6.2
  Author: Robin Aldasoro
  Author URI: https://github.com/robinos33
  License: GPLv2
  Text Domain: mon-club-tt
  Domain Path: /languages
  Requires at least: 5.0
  Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Une seule instance du plugin peut être chargée (copie en double, mu-plugin…).
// La garde doit englober la déclaration : une classe au premier niveau d'un fichier
// est liée dès la compilation, donc une garde placée avant se déclencherait sur
// elle-même et empêcherait l'instanciation en bas de fichier.
if ( ! class_exists( 'MonClubTT_Plugin' ) ) {

require_once( __DIR__ . '/Utils.php' );

class MonClubTT_Plugin
{

    /**
     * Types possibles  de listes de joueurs  à insérer dans les shortcodes
     * @var array
     */
    private $typeListeJoueurs = array(
        'M', 'F', 'MF'
    );

    /**
     * Suffixe de hook de la page d'admin « Joueurs », mémorisé pour n'y charger
     * la médiathèque (upload de photos) que sur cette page.
     * @var string
     */
    private $joueurs_page_hook = '';

    /**
     * Suffixe de hook de la page d'admin « Réseaux sociaux », pour n'y charger
     * que là le script de génération des visuels.
     * @var string
     */
    private $reseaux_sociaux_page_hook = '';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_media'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_reseaux_sociaux'));
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
        add_action('wp_ajax_monclubtt_exclude_joueur', array($this, 'handle_ajax_exclude_joueur'));
        add_action('wp_ajax_monclubtt_set_joueur_photo', array($this, 'handle_ajax_set_joueur_photo'));
        add_action('wp_ajax_monclubtt_remove_joueur_photo', array($this, 'handle_ajax_remove_joueur_photo'));
        add_action('wp_ajax_monclubtt_generate_pages', array($this, 'handle_ajax_generate_pages'));
        add_action('wp_ajax_monclubtt_top_perfs', array($this, 'handle_ajax_top_perfs'));
        add_action('wp_ajax_monclubtt_social_couleurs', array($this, 'handle_ajax_social_couleurs'));
        add_action('wp_ajax_monclubtt_feuille_match',        array($this, 'handle_ajax_feuille_match'));
        add_action('wp_ajax_nopriv_monclubtt_feuille_match', array($this, 'handle_ajax_feuille_match'));

        // Widget dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
    }

    public function add_admin_menu()
    {
        add_menu_page('Mon Club TT', 'Mon Club TT', 'manage_options', 'monclubtt_parametres', array($this, 'admin_module'));
        add_submenu_page('monclubtt_parametres', 'Equipes', 'Equipes', 'manage_options', 'monclubtt_equipes', array($this, 'equipes_admin'));
        $this->joueurs_page_hook = add_submenu_page('monclubtt_parametres', 'Joueurs', 'Joueurs', 'manage_options', 'monclubtt_joueurs', array($this, 'joueurs_admin'));
        $this->reseaux_sociaux_page_hook = add_submenu_page('monclubtt_parametres', 'Réseaux sociaux', 'Réseaux sociaux', 'manage_options', 'monclubtt_reseaux_sociaux', array($this, 'reseaux_sociaux_admin'));
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
        register_setting('monclubtt_settings', MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION, array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('monclubtt_settings', MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE,    array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('monclubtt_settings', MonClubTT_Constantes::MONCLUBTT_NUM_CLUB,        array('sanitize_callback' => 'sanitize_text_field'));
        register_setting('monclubtt_settings', MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, array('sanitize_callback' => 'monclubtt_sanitize_licences_exclues'));

        add_settings_section('monclubtt_section', '', array($this, 'section_html'), 'monclubtt_settings');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION, 'Id Application', array($this, 'id_application_html'), 'monclubtt_settings', 'monclubtt_section');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE, 'Mot de passe Application', array($this, 'mot_de_passe_html'), 'monclubtt_settings', 'monclubtt_section');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_NUM_CLUB, 'Numéro de club', array($this, 'equipe_num_html'), 'monclubtt_settings', 'monclubtt_section');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, 'Licences exclues de la liste des joueurs', array($this, 'licences_exclues_html'), 'monclubtt_settings', 'monclubtt_section');
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
               value="<?php echo esc_attr(get_option(MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION)); ?>"/>
        <?php
    }

    public function mot_de_passe_html()
    {
        ?>
        <input type="text" name="monclubtt_mot_de_passe"
               value="<?php echo esc_attr(get_option(MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE)); ?>"/>
        <?php
    }

    public function equipe_num_html()
    {
        ?>
        <input type="text" name="monclubtt_num_club"
               value="<?php echo esc_attr(get_option(MonClubTT_Constantes::MONCLUBTT_NUM_CLUB)); ?>"/>
        <?php
    }

    public function licences_exclues_html()
    {
        ?>
        <textarea name="monclubtt_licences_exclues" rows="4" cols="30"
                  placeholder="Un numéro de licence par ligne"><?php echo esc_textarea(get_option(MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES)); ?></textarea>
        <p class="description">
            Joueurs à masquer de la liste des joueurs (ex : partis du club) même si
            la FFTT les rattache encore au club. Un numéro de licence par ligne.
        </p>
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

    public function reseaux_sociaux_admin()
    {
        $this->_getLayout('reseaux-sociaux');
    }

    private function _getLayout($view){
        include_once(__DIR__ . '/views/admin/layout.php');
    }

    public function equipes_front($atts, $content)
    {
        $api = MonClubTT_AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            return esc_html__('There was a problem retrieving the results', 'mon-club-tt');
        }

        // Normalise et valide les attributs du shortcode
        $atts = shortcode_atts(array('iddiv' => '', 'idpoule' => ''), (array) $atts, 'monclubtt_equipe');
        $atts['iddiv'] = (string) $atts['iddiv'];
        $atts['idpoule'] = (string) $atts['idpoule'];
        if ($atts['iddiv'] === '' || $atts['idpoule'] === '') {
            return esc_html__('Invalid division or pool', 'mon-club-tt');
        }

        $listeEquipesM = $api->getEquipesByClub(MonClubTT_ParametresPlugin::getNumClub(), 'M');
        $listeEquipesF = $api->getEquipesByClub(MonClubTT_ParametresPlugin::getNumClub(), 'F');
        $listeEquipes = array_merge((array) $listeEquipesM, (array) $listeEquipesF);

        // Recherche de la poule référencée par le shortcode. En fin de saison ou
        // entre deux phases, la FFTT supprime les poules : l'API ne les renvoie
        // plus et le shortcode pointe alors vers des identifiants obsolètes. On
        // affiche dans ce cas un message clair plutôt qu'un bloc vide.
        $equipeTrouvee = null;
        foreach ($listeEquipes as $equipeCourante) {
            if (isset($equipeCourante['iddiv'], $equipeCourante['idpoule'])
                && $atts['iddiv'] === (string) $equipeCourante['iddiv']
                && $atts['idpoule'] === (string) $equipeCourante['idpoule']) {
                $equipeTrouvee = $equipeCourante;
                break;
            }
        }

        if ($equipeTrouvee === null) {
            ob_start();
            require __DIR__ . '/views/front/equipe-introuvable.php';
            return ob_get_clean();
        }

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
            $joueurs = new MonClubTT_Joueurs();
            $api = MonClubTT_AccesFFTTApi::getInstance();
            $numClub = MonClubTT_ParametresPlugin::getNumClub();
            $updatedAt = $api->getCacheUpdatedAt('joueurs_club', array('numclu' => $numClub));
            ob_start();
            require __DIR__ . '/views/front/joueurs.php';
            return ob_get_clean();
        }
        return esc_html__('Invalid shortcode parameters', 'mon-club-tt');
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
        $joueurs = new MonClubTT_Joueurs($type);
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
        $api = MonClubTT_AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            return array();
        }

        if ($type === 'M' || $type === 'F') {
            return $api->getEquipesByClub(MonClubTT_ParametresPlugin::getNumClub(), $type);
        }

        // Retourne toutes les équipes (M et F)
        $equipesM = $api->getEquipesByClub(MonClubTT_ParametresPlugin::getNumClub(), 'M');
        $equipesF = $api->getEquipesByClub(MonClubTT_ParametresPlugin::getNumClub(), 'F');
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
        $api = MonClubTT_AccesFFTTApi::getInstance();
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
        $api = MonClubTT_AccesFFTTApi::getInstance();
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

        $api  = MonClubTT_AccesFFTTApi::getInstance();
        $data = $api->getRencontreDetail($rencId, $isRetour);

        if (!$data) {
            wp_send_json_error(array('message' => 'Feuille de match non disponible'));
            return;
        }

        wp_send_json_success($data);
    }

    /**
     * Charge la médiathèque WordPress (pour l'upload de photos) uniquement sur
     * la page d'admin « Joueurs ».
     *
     * @param string $hook Suffixe de hook de la page admin courante.
     */
    public function admin_enqueue_media($hook)
    {
        if ($hook === $this->joueurs_page_hook) {
            wp_enqueue_media();
        }
    }

    /**
     * Charge le générateur de visuels (canvas) uniquement sur la page d'admin
     * « Réseaux sociaux », avec les données du podium Top Progression.
     *
     * @param string $hook Suffixe de hook de la page admin courante.
     */
    public function admin_enqueue_reseaux_sociaux($hook)
    {
        if ($hook !== $this->reseaux_sociaux_page_hook) {
            return;
        }

        $jsVer = filemtime(plugin_dir_path(__FILE__) . 'assets/mon-club-tt-social.js');
        wp_enqueue_script('monclubtt-social-js', plugins_url('/assets/mon-club-tt-social.js', __FILE__), array('monclubtt-js'), $jsVer, true);

        $moisFr     = array('janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre');
        $saison     = monclubtt_debut_saison();
        $joueurs    = new MonClubTT_Joueurs();
        $siteHost   = wp_parse_url(home_url(), PHP_URL_HOST);

        wp_localize_script('monclubtt-social-js', 'MonClubTTSocial', array(
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('monclubtt_top_perfs_nonce'),
            'players'     => $joueurs->getDonneesTopProgression('MF'),
            'moisLabel'   => $moisFr[(int) date_i18n('n') - 1] . ' ' . date_i18n('Y'),
            'saisonLabel' => 'Saison ' . $saison . '–' . ($saison + 1),
            'clubName'    => get_bloginfo('name'),
            'siteHost'    => $siteHost ? $siteHost : '',
            'logo'        => get_site_icon_url(256),
            'couleurs'    => monclubtt_normaliser_couleurs_social(get_option(MonClubTT_Constantes::MONCLUBTT_SOCIAL_COULEURS)),
            'couleursDefaut' => MonClubTT_Constantes::COULEURS_SOCIAL_DEFAUT,
            'couleursNonce'  => wp_create_nonce('monclubtt_social_couleurs_nonce'),
        ));
    }

    /**
     * Handler AJAX : « top perfs » du dernier week-end de championnat par
     * équipes (victoires contre mieux classé, points gagnés au barème FFTT),
     * regroupées par joueur. Les feuilles de match sont mises en cache 7 jours par l'API.
     */
    public function handle_ajax_top_perfs()
    {
        check_ajax_referer('monclubtt_top_perfs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $api = MonClubTT_AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            wp_send_json_error(array('message' => 'Erreur de connexion à l\'API FFTT'));
            return;
        }

        $rencontres = array();
        $equipes    = new MonClubTT_Equipes();
        foreach ($equipes->getEquipesSeniorChampionnat('MF') as $equipe) {
            if ($equipe->getIddiv() && $equipe->getIdpoule()) {
                $rencontres = array_merge($rencontres, (array) $api->getPouleRencontres($equipe->getIddiv(), $equipe->getIdpoule()));
            }
        }

        $journee = MonClubTT_TopPerfs::rencontresDerniereJournee($rencontres, MonClubTT_ParametresPlugin::getNumClub());
        if (empty($journee)) {
            wp_send_json_error(array('message' => 'Aucune rencontre de championnat jouée trouvée. Lancez une synchronisation si la journée vient d\'avoir lieu.'));
            return;
        }

        $perfs = array();
        foreach ($journee as $rencontre) {
            $feuille = $api->getRencontreDetail($rencontre['renc_id'], $rencontre['is_retour']);
            if (is_array($feuille)) {
                $perfs = array_merge($perfs, MonClubTT_TopPerfs::extrairePerfs($feuille, $rencontre['equipes_club']));
            }
        }

        // Nom et photo depuis la liste des joueurs du club (la feuille donne « NOM Prénom »).
        $joueursParNom = array();
        $joueurs       = new MonClubTT_Joueurs();
        foreach ($joueurs->getJoueurs('MF') as $joueur) {
            $joueursParNom[$this->cleNomJoueur($joueur->getNom() . ' ' . $joueur->getPrenom())] = $joueur;
        }

        $bilan    = MonClubTT_TopPerfs::bilanParJoueur($perfs);
        $resultat = array();
        foreach (MonClubTT_TopPerfs::classer($bilan, 8) as $perf) {
            $joueur = $joueursParNom[$this->cleNomJoueur($perf['joueur'])] ?? null;
            $resultat[] = array(
                'nom'               => $joueur ? $joueur->getNom() : $perf['joueur'],
                'prenom'            => $joueur ? $joueur->getPrenom() : '',
                'sex'               => $joueur ? $joueur->getSexe() : $perf['sexe'],
                'photo'             => $joueur ? $joueur->getPhotoUrl() : '',
                'points'            => $perf['points'],
                'adversaire_points' => $perf['adversaire_points'],
                'ecart'             => $perf['ecart'],
                'gain'              => $perf['gain'],
                'nb_perfs'          => $perf['nb_perfs'],
                'equipe'            => $perf['equipe'],
            );
        }

        $dates = array_column($journee, 'date');
        $tours = array_filter(array_column($journee, 'tour'));
        wp_send_json_success(array(
            'perfs'         => $resultat,
            'date_debut'    => min($dates),
            'date_fin'      => max($dates),
            'tour'          => $tours ? max($tours) : null,
            'rencontres'    => count($journee),
            'total_perfs'   => count($perfs),
            'total_joueurs' => count($bilan),
            'total_gain'    => array_sum(array_column($perfs, 'gain')),
        ));
    }

    /**
     * Handler AJAX : enregistre les couleurs des visuels réseaux sociaux
     * (primaire, secondaire, fond), communes à tous les administrateurs.
     */
    public function handle_ajax_social_couleurs()
    {
        check_ajax_referer('monclubtt_social_couleurs_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $saisie = array();
        foreach (array_keys(MonClubTT_Constantes::COULEURS_SOCIAL_DEFAUT) as $cle) {
            $saisie[$cle] = isset($_POST[$cle]) ? sanitize_text_field(wp_unslash($_POST[$cle])) : '';
        }
        $couleurs = monclubtt_normaliser_couleurs_social($saisie);
        update_option(MonClubTT_Constantes::MONCLUBTT_SOCIAL_COULEURS, $couleurs, false);

        wp_send_json_success(array('couleurs' => $couleurs));
    }

    /**
     * Clé de rapprochement d'un nom de joueur (feuille de match ↔ liste du
     * club) : sans accents, majuscules, espaces normalisés.
     *
     * @param string $nom
     * @return string
     */
    private function cleNomJoueur($nom)
    {
        return strtoupper(preg_replace('/\s+/', ' ', trim(remove_accents((string) $nom))));
    }

    /**
     * Handler AJAX : associe une photo (pièce jointe de la médiathèque) à un
     * joueur, indexée par numéro de licence. Remplace et supprime l'ancienne
     * photo le cas échéant.
     */
    public function handle_ajax_set_joueur_photo()
    {
        check_ajax_referer('monclubtt_photo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $licence      = isset($_POST['licence']) ? sanitize_text_field(wp_unslash($_POST['licence'])) : '';
        $attachmentId = isset($_POST['attachment_id']) ? absint($_POST['attachment_id']) : 0;

        if ($licence === '' || $attachmentId === 0) {
            wp_send_json_error(array('message' => 'Paramètres manquants'));
            return;
        }

        if (get_post_type($attachmentId) !== 'attachment'
            || strpos((string) get_post_mime_type($attachmentId), 'image/') !== 0) {
            wp_send_json_error(array('message' => 'Le fichier sélectionné n\'est pas une image'));
            return;
        }

        $map = get_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, array());
        if (!is_array($map)) {
            $map = array();
        }

        // Remplacement : supprimer l'ancienne pièce jointe pour ne pas laisser d'orphelin.
        $old = isset($map[$licence]) ? (int) $map[$licence] : 0;
        if ($old && $old !== $attachmentId) {
            wp_delete_attachment($old, true);
        }

        $map[$licence] = $attachmentId;
        update_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, $map, false);

        wp_send_json_success(array(
            'thumbnail' => wp_get_attachment_image_url($attachmentId, 'thumbnail'),
        ));
    }

    /**
     * Handler AJAX : retire la photo d'un joueur et supprime la pièce jointe
     * associée (photo dédiée, pas de réutilisation ailleurs attendue).
     */
    public function handle_ajax_remove_joueur_photo()
    {
        check_ajax_referer('monclubtt_photo_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $licence = isset($_POST['licence']) ? sanitize_text_field(wp_unslash($_POST['licence'])) : '';
        if ($licence === '') {
            wp_send_json_error(array('message' => 'Numéro de licence manquant'));
            return;
        }

        $map = get_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, array());
        if (is_array($map) && isset($map[$licence])) {
            wp_delete_attachment((int) $map[$licence], true);
            unset($map[$licence]);
            update_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, $map, false);
        }

        wp_send_json_success();
    }

    /**
     * Handler AJAX : exclut un joueur (par numéro de licence) de la liste des
     * joueurs, depuis le bouton « Retirer » de l'admin. Le prochain sync (ou
     * simplement le prochain chargement de la liste) le laissera de côté, la
     * FFTT continuant sinon de le rattacher au club dans son API.
     */
    public function handle_ajax_exclude_joueur()
    {
        check_ajax_referer('monclubtt_exclude_joueur_nonce', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes'));
            return;
        }

        $licence = isset($_POST['licence']) ? sanitize_text_field(wp_unslash($_POST['licence'])) : '';
        if (empty($licence)) {
            wp_send_json_error(array('message' => 'Numéro de licence manquant'));
            return;
        }

        $licencesExclues = get_option(MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, '');
        $licencesExclues = monclubtt_sanitize_licences_exclues($licencesExclues . "\n" . $licence);
        update_option(MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, $licencesExclues);

        wp_send_json_success(array('message' => 'Joueur retiré de la liste'));
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

        $api = MonClubTT_AccesFFTTApi::getInstance();
        if (!is_object($api)) {
            wp_send_json_error(array('message' => 'Erreur de connexion à l\'API FFTT'));
            return;
        }

        try {
            $numClub = MonClubTT_ParametresPlugin::getNumClub();
            $syncResults = array();
            $debugLog = array();

            // Vérifier les paramètres de connexion
            if (empty($numClub)) {
                throw new Exception('Numéro de club non configuré');
            }
            $debugLog[] = "Numéro de club: " . $numClub;

            $idApp = MonClubTT_ParametresPlugin::getIdApplication();
            $motDePasse = MonClubTT_ParametresPlugin::getMotDePasse();
            if (empty($idApp) || empty($motDePasse)) {
                throw new Exception('Identifiants API FFTT non configurés');
            }
            $debugLog[] = "ID Application: configuré";

            // Synchronisation des joueurs
            $api->clearJoueursCache($numClub);
            $debugLog[] = "Cache joueurs effacé";

            $joueurs = new MonClubTT_Joueurs();
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
     * Sanitize un tableau d'équipes reçu en POST (chacune sous forme de tableau
     * associatif iddiv/idpoule/libequipe). sanitize_text_field() attend une
     * chaîne : on l'applique donc champ par champ, jamais sur le sous-tableau entier.
     *
     * @param array $teams
     * @return array
     */
    private function sanitizeTeamsList($teams)
    {
        $sanitized = array();
        foreach ((array) $teams as $team) {
            if (!is_array($team)) {
                continue;
            }
            $sanitized[] = array(
                'iddiv'     => sanitize_text_field($team['iddiv']     ?? ''),
                'idpoule'   => sanitize_text_field($team['idpoule']   ?? ''),
                'libequipe' => sanitize_text_field($team['libequipe'] ?? ''),
            );
        }
        return $sanitized;
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

        $teamsCreate = isset($_POST['teams_create']) ? $this->sanitizeTeamsList(wp_unslash((array) $_POST['teams_create'])) : array();
        $teamsDelete = isset($_POST['teams_delete']) ? $this->sanitizeTeamsList(wp_unslash((array) $_POST['teams_delete'])) : array();

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
                $syncDate = monclubtt_date_locale($lastSync);
                $timeDiff = human_time_diff($lastSync);
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

new MonClubTT_Plugin();

}
