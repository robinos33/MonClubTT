<?php
/*
  Plugin Name: Mon Club TT
  Plugin URI: https://github.com/robinos33/MonClubTT
  Description: Display your table tennis club's players, teams, and rankings from the official FFTT Smartping API. Not affiliated with or endorsed by the FFTT.
  Version: 2.0.0
  Author: Robin Aldasoro
  Author URI: https://github.com/robinos33
  License: GPLv2
  Text Domain: mon-club-tt
  Domain Path: /languages
  Requires at least: 6.4
  Requires PHP: 8.4
 */

if ( ! defined( 'ABSPATH' ) ) exit;

// Une seule instance du plugin peut être chargée (copie en double, mu-plugin…).
// La garde doit englober la déclaration : une classe au premier niveau d'un fichier
// est liée dès la compilation, donc une garde placée avant se déclencherait sur
// elle-même et empêcherait l'instanciation en bas de fichier.
if ( ! class_exists( 'MonClubTT_Plugin' ) ) {

require_once( __DIR__ . '/Utils.php' );

/** Fichier principal du plugin (en-tête, URL des assets des modules). */
define( 'MONCLUBTT_PLUGIN_FILE', __FILE__ );

// Gestion des équipes (ex-TT Team Planner).
require_once( __DIR__ . '/modules/gestion-equipes/gestion-equipes.php' );
register_deactivation_hook( __FILE__, 'monclubtt_ge_desactiver' );

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
     * Suffixes de hook des pages d'admin qui utilisent la médiathèque
     * (photos des joueurs, logo du club, photos et musique du pong).
     * @var string[]
     */
    private $media_page_hooks = array();

    /**
     * Suffixe de hook de la page d'admin « Réseaux sociaux », pour n'y charger
     * que là le script de génération des visuels.
     * @var string
     */
    private $reseaux_sociaux_page_hook = '';

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_menu', array($this, 'add_admin_menu_fin'), 30);
        add_action('admin_menu', array($this, 'rediriger_anciennes_pages'), 1);
        add_action('admin_post_monclubtt_clear_logs', array($this, 'handle_clear_logs'));
        add_action('admin_init', array($this, 'register_settings'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_media'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_reseaux_sociaux'));
        add_action('init', array($this, 'monclubtt_style_scripts'));
        add_shortcode('monclubtt_equipe', array($this, 'equipes_front'));
        add_shortcode('monclubtt_joueurs', array($this, 'joueurs_front'));
        add_shortcode('monclubtt_pong', array($this, 'pong_front'));

        // Hooks pour exposer les données en cache aux autres plugins
        add_filter('monclubtt_get_joueurs', array($this, 'get_joueurs_data'), 10, 1);
        add_filter('monclubtt_get_equipes', array($this, 'get_equipes_data'), 10, 1);
        add_filter('monclubtt_get_classement_poule', array($this, 'get_classement_poule_data'), 10, 2);
        add_filter('monclubtt_get_rencontres_poule', array($this, 'get_rencontres_poule_data'), 10, 2);
        add_filter('monclubtt_get_feuille_rencontre', array($this, 'get_feuille_rencontre_data'), 10, 2);

        // AJAX handlers
        add_action('wp_ajax_monclubtt_sync', array($this, 'handle_ajax_sync'));
        add_action('wp_ajax_monclubtt_exclude_joueur', array($this, 'handle_ajax_exclude_joueur'));
        add_action('wp_ajax_monclubtt_set_joueur_photo', array($this, 'handle_ajax_set_joueur_photo'));
        add_action('wp_ajax_monclubtt_remove_joueur_photo', array($this, 'handle_ajax_remove_joueur_photo'));
        add_action('wp_ajax_monclubtt_generate_pages', array($this, 'handle_ajax_generate_pages'));
        add_action('wp_ajax_monclubtt_top_perfs', array($this, 'handle_ajax_top_perfs'));
        add_action('wp_ajax_monclubtt_feuille_match',        array($this, 'handle_ajax_feuille_match'));
        add_action('wp_ajax_nopriv_monclubtt_feuille_match', array($this, 'handle_ajax_feuille_match'));
        add_action('wp_ajax_monclubtt_pong_scores',          array($this, 'handle_ajax_pong_scores'));
        add_action('wp_ajax_nopriv_monclubtt_pong_scores',   array($this, 'handle_ajax_pong_scores'));
        add_action('wp_ajax_monclubtt_pong_debut',           array($this, 'handle_ajax_pong_debut'));
        add_action('wp_ajax_nopriv_monclubtt_pong_debut',    array($this, 'handle_ajax_pong_debut'));
        add_action('wp_ajax_monclubtt_pong_fin',             array($this, 'handle_ajax_pong_fin'));
        add_action('wp_ajax_nopriv_monclubtt_pong_fin',      array($this, 'handle_ajax_pong_fin'));
        // Partage d'un match : image générée et balises Open Graph.
        add_action('init', array($this, 'pong_image'), 20);
        add_action('wp_head', array($this, 'pong_og'), 1);

        // Widget dashboard
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));

        // Nouveaux identifiants FFTT : le serial sera réinitialisé au prochain appel.
        foreach (array(MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION, MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE) as $option) {
            add_action('update_option_' . $option, array($this, 'reinitialiser_api'));
            add_action('add_option_' . $option, array($this, 'reinitialiser_api'));
        }

        // Calendrier du club : événements, import, flux .ics et shortcodes.
        new MonClubTT_CalendrierWP(__FILE__);
    }

    /** Force l'initialisation FFTT au prochain appel (identifiants modifiés, synchro). */
    public function reinitialiser_api()
    {
        delete_transient(MonClubTT_AccesFFTTApi::TRANSIENT_INIT);
    }

    /**
     * Haut du menu « Mon Club TT » : Données FFTT. Le calendrier s'ajoute
     * ensuite (priorité 20), puis la fin du menu (priorité 30).
     */
    public function add_admin_menu()
    {
        $parent = MonClubTT_Constantes::MENU_PARENT;
        $this->media_page_hooks[] = add_menu_page('Données FFTT', 'Mon Club TT', 'manage_options', $parent, array($this, 'donnees_admin'));
        add_submenu_page($parent, 'Données FFTT', 'Données FFTT', 'manage_options', $parent, array($this, 'donnees_admin'));
    }

    /** Fin du menu « Mon Club TT », après le calendrier. */
    public function add_admin_menu_fin()
    {
        $parent = MonClubTT_Constantes::MENU_PARENT;
        $this->reseaux_sociaux_page_hook = add_submenu_page($parent, 'Réseaux sociaux', 'Réseaux sociaux', 'manage_options', 'monclubtt_reseaux_sociaux', array($this, 'reseaux_sociaux_admin'));
        $this->media_page_hooks[] = add_submenu_page($parent, 'Jeu de pong', 'Pong', 'manage_options', 'monclubtt_pong', array($this, 'pong_admin'));
        $this->media_page_hooks[] = add_submenu_page($parent, 'Réglages', 'Réglages', 'manage_options', 'monclubtt_parametres', array($this, 'admin_module'));
    }

    /**
     * Anciennes pages « Joueurs » et « Equipes » (avant 1.23) : redirigées vers
     * l'onglet correspondant de « Données FFTT » (favoris, liens existants).
     */
    public function rediriger_anciennes_pages()
    {
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $onglets = array('monclubtt_joueurs' => 'joueurs', 'monclubtt_equipes' => 'equipes');
        if (isset($onglets[$page])) {
            wp_safe_redirect(monclubtt_admin_url_donnees($onglets[$page]));
            exit;
        }
    }

    /** Bouton « Effacer les logs » de l'onglet Connexion. */
    public function handle_clear_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('Sorry, you are not allowed to access this page.', 'mon-club-tt'), 403);
        }
        check_admin_referer('monclubtt_clear_logs', 'monclubtt_clear_logs_nonce');
        MonClubTT_AccesFFTTApi::clearApiLogs();
        wp_safe_redirect(add_query_arg('logs_cleared', '1', monclubtt_admin_url_donnees('connexion')));
        exit;
    }

    public function admin_module()
    {
        $this->_getLayout('reglages');
    }

    public function donnees_admin()
    {
        $this->_getLayout('donnees');
    }

    public function pong_admin()
    {
        $this->_getLayout('pong');
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
        $couleurs = monclubtt_get_couleurs();
        wp_add_inline_style('mon-club-tt-css', '.monclubtt-div{--monclubtt-primaire:' . $couleurs['primaire'] . ';--monclubtt-secondaire:' . $couleurs['secondaire'] . ';}');
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
        // Jeu de pong : chargé seulement par le shortcode [monclubtt_pong].
        $pongCssVer = filemtime(plugin_dir_path(__FILE__) . 'assets/mon-club-tt-pong.css');
        $pongJsVer  = filemtime(plugin_dir_path(__FILE__) . 'assets/mon-club-tt-pong.js');
        wp_register_style('monclubtt-pong-css', plugins_url('/assets/mon-club-tt-pong.css', __FILE__), array(), $pongCssVer);
        wp_register_script('monclubtt-pong-js', plugins_url('/assets/mon-club-tt-pong.js', __FILE__), array('monclubtt-js'), $pongJsVer, true);
    }

    /**
     * Un groupe de réglages par formulaire : options.php remet à vide toutes
     * les options du groupe envoyé, un groupe ne doit donc contenir que les
     * champs de son formulaire.
     */
    public function register_settings()
    {
        // Données FFTT › Connexion
        $groupe = 'monclubtt_fftt_settings';
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION, array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE,    array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_NUM_CLUB,        array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, array('sanitize_callback' => 'monclubtt_sanitize_licences_exclues'));
        add_settings_section('monclubtt_section', '', array($this, 'section_html'), $groupe);
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_ID_APPLICATION, 'Id Application', array($this, 'id_application_html'), $groupe, 'monclubtt_section');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE, 'Mot de passe Application', array($this, 'mot_de_passe_html'), $groupe, 'monclubtt_section');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_NUM_CLUB, 'Numéro de club', array($this, 'equipe_num_html'), $groupe, 'monclubtt_section');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_LICENCES_EXCLUES, 'Licences exclues de la liste des joueurs', array($this, 'licences_exclues_html'), $groupe, 'monclubtt_section');

        // Données FFTT › Connexion : journal des appels (formulaire à part, dans la section des logs)
        register_setting('monclubtt_logs_settings', MonClubTT_Constantes::MONCLUBTT_API_LOGS_ACTIFS, array('sanitize_callback' => array($this, 'sanitize_logs_actifs')));

        // Données FFTT › Joueurs
        $groupe = 'monclubtt_joueurs_settings';
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_AFFICHER_PHOTOS, array('sanitize_callback' => array($this, 'sanitize_case')));
        add_settings_section('monclubtt_section_joueurs', '', '__return_false', $groupe);
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_AFFICHER_PHOTOS, 'Photos des joueurs', array($this, 'afficher_photos_html'), $groupe, 'monclubtt_section_joueurs');

        // Pong
        $groupe = 'monclubtt_pong_settings';
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_PONG_PROS, array('sanitize_callback' => array($this, 'sanitize_case')));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_PONG_ADVERSAIRES, array('sanitize_callback' => array($this, 'sanitize_pong_adversaires')));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_PONG_MUSIQUE, array('sanitize_callback' => array($this, 'sanitize_pong_musique')));
        register_setting($groupe, 'monclubtt_pong_raz_compteurs', array('sanitize_callback' => array($this, 'sanitize_pong_raz_compteurs')));
        register_setting($groupe, 'monclubtt_pong_vider_scores', array('sanitize_callback' => array($this, 'sanitize_pong_vider_scores')));
        register_setting($groupe, 'monclubtt_pong_vider_matchs', array('sanitize_callback' => array($this, 'sanitize_pong_vider_matchs')));
        add_settings_section('monclubtt_section_pong', '', '__return_false', $groupe);
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_PONG_PROS, 'Top 10 mondial', array($this, 'pong_pros_html'), $groupe, 'monclubtt_section_pong');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_PONG_ADVERSAIRES, 'Adversaires', array($this, 'pong_adversaires_html'), $groupe, 'monclubtt_section_pong');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_PONG_MUSIQUE, 'Musique', array($this, 'pong_musique_html'), $groupe, 'monclubtt_section_pong');
        add_settings_field('monclubtt_pong_raz_compteurs', 'Parties', array($this, 'pong_compteurs_html'), $groupe, 'monclubtt_section_pong');
        add_settings_field('monclubtt_pong_vider_scores', 'Meilleurs scores', array($this, 'pong_vider_scores_html'), $groupe, 'monclubtt_section_pong');
        add_settings_field('monclubtt_pong_vider_matchs', 'Matchs partagés', array($this, 'pong_vider_matchs_html'), $groupe, 'monclubtt_section_pong');

        // Réglages : identité du club
        $groupe = 'monclubtt_settings';
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_NOM_CLUB, array('sanitize_callback' => 'sanitize_text_field'));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_LOGO, array('sanitize_callback' => array($this, 'sanitize_logo')));
        register_setting($groupe, MonClubTT_Constantes::MONCLUBTT_COULEURS, array('sanitize_callback' => array($this, 'sanitize_couleurs')));
        add_settings_section('monclubtt_section_reglages', '', '__return_false', $groupe);
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_NOM_CLUB, 'Nom du club', array($this, 'nom_club_html'), $groupe, 'monclubtt_section_reglages');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_LOGO, 'Logo du club', array($this, 'logo_html'), $groupe, 'monclubtt_section_reglages');
        add_settings_field(MonClubTT_Constantes::MONCLUBTT_COULEURS, 'Couleurs du club', array($this, 'couleurs_html'), $groupe, 'monclubtt_section_reglages');
    }

    /** Journal désactivé : les logs déjà enregistrés sont effacés. */
    public function sanitize_logs_actifs($valeur)
    {
        $valeur = $this->sanitize_case($valeur);
        if ($valeur !== '1') {
            MonClubTT_AccesFFTTApi::clearApiLogs();
        }
        return $valeur;
    }

    public function nom_club_html()
    {
        ?>
        <input type="text" class="regular-text" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_NOM_CLUB); ?>"
               value="<?php echo esc_attr(get_option(MonClubTT_Constantes::MONCLUBTT_NOM_CLUB, '')); ?>"
               placeholder="<?php echo esc_attr(get_bloginfo('name')); ?>">
        <p class="description">Repris par l'application de gestion des équipes, ses e-mails et les visuels réseaux sociaux. Vide : nom du site.</p>
        <?php
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

    /**
     * Mot de passe masqué par défaut, avec un bouton « œil » pour l'afficher
     * (même présentation que le mot de passe du profil WordPress).
     */
    public function mot_de_passe_html()
    {
        ?>
        <span class="wp-pwd monclubtt-mot-de-passe">
            <input type="password" id="monclubtt_mot_de_passe" name="monclubtt_mot_de_passe" class="regular-text" spellcheck="false"
                   autocomplete="off" value="<?php echo esc_attr(get_option(MonClubTT_Constantes::MONCLUBTT_MOT_DE_PASSE)); ?>"/>
            <button type="button" class="button wp-hide-pw hide-if-no-js" aria-controls="monclubtt_mot_de_passe" aria-label="Afficher le mot de passe">
                <span class="dashicons dashicons-visibility" aria-hidden="true"></span>
            </button>
        </span>
        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(function($) {
            $(".monclubtt-mot-de-passe .wp-hide-pw").on("click", function() {
                var $champ = $("#monclubtt_mot_de_passe"), visible = $champ.attr("type") === "password";
                $champ.attr("type", visible ? "text" : "password");
                $(this).attr("aria-label", visible ? "Masquer le mot de passe" : "Afficher le mot de passe")
                    .find(".dashicons").toggleClass("dashicons-visibility", !visible).toggleClass("dashicons-hidden", visible);
            });
        });');
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

    public function couleurs_html()
    {
        $couleurs = monclubtt_get_couleurs();
        $libelles = array('primaire' => 'Primaire', 'secondaire' => 'Secondaire', 'maillot' => 'Maillot des joueurs', 'fond' => 'Fond des visuels');
        ?>
        <fieldset class="monclubtt-couleurs">
            <?php foreach ($libelles as $cle => $libelle): ?>
                <label>
                    <input type="color" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_COULEURS . '[' . $cle . ']'); ?>"
                           value="<?php echo esc_attr($couleurs[$cle]); ?>"
                           data-defaut="<?php echo esc_attr(MonClubTT_Constantes::COULEURS_DEFAUT[$cle]); ?>">
                    <?php echo esc_html($libelle); ?>
                </label>
            <?php endforeach; ?>
            <button type="button" class="button-link monclubtt-couleurs-defaut">Couleurs par défaut</button>
        </fieldset>
        <p class="description">
            Primaire et secondaire habillent les tableaux du site ; avec le maillot, ils habillent aussi le podium « Top Progression » et les visuels réseaux sociaux.
            Le fond ne sert qu'aux visuels réseaux sociaux.
        </p>
        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(function($) {
            $(".monclubtt-couleurs-defaut").on("click", function() {
                $(this).closest(".monclubtt-couleurs").find("input[type=color]").each(function() {
                    this.value = $(this).data("defaut");
                });
            });
        });');
    }

    public function logo_html()
    {
        $logoId  = (int) get_option(MonClubTT_Constantes::MONCLUBTT_LOGO, 0);
        $apercu  = $logoId ? wp_get_attachment_image_url($logoId, 'thumbnail') : '';
        $iconeSite = get_site_icon_url(96);
        ?>
        <div class="monclubtt-logo">
            <input type="hidden" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_LOGO); ?>" value="<?php echo esc_attr($logoId ? $logoId : ''); ?>">
            <span class="monclubtt-logo-apercu" data-icone-site="<?php echo esc_url($iconeSite); ?>">
                <?php if ($apercu || $iconeSite): ?>
                    <img src="<?php echo esc_url($apercu ? $apercu : $iconeSite); ?>" alt="">
                <?php endif; ?>
            </span>
            <button type="button" class="button monclubtt-logo-choisir"><?php echo $logoId ? 'Changer' : 'Choisir un logo'; ?></button>
            <button type="button" class="button-link monclubtt-logo-retirer"<?php echo $logoId ? '' : ' style="display:none"'; ?>>Retirer</button>
        </div>
        <p class="description">
            Affiché dans l'en-tête des visuels réseaux sociaux.
            Sans logo, l'icône du site (Réglages › Général) est utilisée. PNG à fond transparent conseillé.
        </p>
        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(function($) {
            var $bloc = $(".monclubtt-logo"), $champ = $bloc.find("input[type=hidden]"), $apercu = $bloc.find(".monclubtt-logo-apercu");
            function afficher(url) { $apercu.html(url ? $("<img>", { src: url, alt: "" }) : ""); }
            $bloc.find(".monclubtt-logo-choisir").on("click", function() {
                var frame = wp.media({ title: "Logo du club", button: { text: "Utiliser ce logo" }, library: { type: "image" }, multiple: false });
                frame.on("select", function() {
                    var att = frame.state().get("selection").first().toJSON();
                    $champ.val(att.id);
                    afficher(att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url);
                    $bloc.find(".monclubtt-logo-choisir").text("Changer");
                    $bloc.find(".monclubtt-logo-retirer").show();
                });
                frame.open();
            });
            $bloc.find(".monclubtt-logo-retirer").on("click", function() {
                $champ.val("");
                afficher($apercu.data("icone-site"));
                $bloc.find(".monclubtt-logo-choisir").text("Choisir un logo");
                $(this).hide();
            });
        });');
    }

    /**
     * Logo du club : identifiant d'une image de la médiathèque, sinon 0.
     *
     * @param mixed $valeur
     * @return int
     */
    public function pong_adversaires_html()
    {
        $adversaires = monclubtt_get_pong_adversaires();
        $option      = MonClubTT_Constantes::MONCLUBTT_PONG_ADVERSAIRES;
        $titres      = array('M' => 'Top 10 messieurs', 'F' => 'Top 10 dames');
        ?>
        <div class="monclubtt-pong-adversaires" style="display:flex;flex-wrap:wrap;gap:24px">
            <?php foreach ($titres as $sexe => $titre): ?>
                <table class="widefat striped" style="width:auto">
                    <thead><tr><th colspan="4"><?php echo esc_html($titre); ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($adversaires[$sexe] as $i => $adv):
                        $base   = $option . '[' . $sexe . '][' . $i . ']';
                        $apercu = $adv['photo'] ? wp_get_attachment_image_url($adv['photo'], 'thumbnail') : ''; ?>
                        <tr class="monclubtt-pong-adversaire">
                            <td><?php echo (int) $i + 1; ?></td>
                            <td><input type="text" class="regular-text" style="width:13em" name="<?php echo esc_attr($base . '[nom]'); ?>" value="<?php echo esc_attr($adv['nom']); ?>" placeholder="Felix LEBRUN"></td>
                            <td>
                                <select name="<?php echo esc_attr($base . '[pays]'); ?>" aria-label="<?php echo esc_attr('Pays du N°' . ((int) $i + 1)); ?>">
                                    <option value=""<?php selected($adv['pays'], ''); ?>>Couleurs du club</option>
                                    <?php foreach (MonClubTT_Constantes::PONG_PAYS as $code => $pays): ?>
                                        <option value="<?php echo esc_attr($code); ?>"<?php selected($adv['pays'], $code); ?>><?php echo esc_html($pays); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="white-space:nowrap">
                                <input type="hidden" name="<?php echo esc_attr($base . '[photo]'); ?>" value="<?php echo esc_attr($adv['photo'] ? $adv['photo'] : ''); ?>">
                                <span class="monclubtt-pong-apercu" style="display:inline-block;width:32px;height:32px;vertical-align:middle"><?php if ($apercu): ?><img src="<?php echo esc_url($apercu); ?>" alt="" style="width:32px;height:32px;object-fit:contain"><?php endif; ?></span>
                                <button type="button" class="button button-small monclubtt-pong-choisir"><?php echo $adv['photo'] ? 'Changer' : 'Photo'; ?></button>
                                <button type="button" class="button-link monclubtt-pong-retirer"<?php echo $adv['photo'] ? '' : ' style="display:none"'; ?>>Retirer</button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
        <p class="description">
            Shortcode <code>[monclubtt_pong]</code> : l'adversaire est tiré au hasard dans ces 20 joueurs. Noms écrits comme sur le site de la WTT (affichés tels quels), dans l'ordre du classement mondial (à mettre à jour, il change chaque semaine).
            Pays : le joueur porte une tenue aux couleurs de sa sélection (inspirée du drapeau) ; « Couleurs du club » sinon.
            Photo : PNG détouré à fond transparent, dont le club a les droits d'utilisation ; sans photo, avatar dessiné. Ligne vide = joueur ignoré.<br>
            Crédit : renseignez la <strong>légende</strong> de l'image dans la médiathèque, affichée sous le jeu. Pour une photo de Wikimedia Commons, auteur et licence sont obligatoires,
            ex. « Jean Dupont, CC BY-SA 4.0, via Wikimedia Commons, détourée », liens vers la page de la photo et la licence acceptés.
            Les crédits sont regroupés sous le jeu, dans « Crédit photo ».
        </p>
        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(function($) {
            $(".monclubtt-pong-adversaires").on("click", ".monclubtt-pong-choisir", function() {
                var $ligne = $(this).closest("tr");
                var frame = wp.media({ title: "Photo détourée du joueur", button: { text: "Utiliser cette photo" }, library: { type: "image" }, multiple: false });
                frame.on("select", function() {
                    var att = frame.state().get("selection").first().toJSON();
                    $ligne.find("input[type=hidden]").val(att.id);
                    $ligne.find(".monclubtt-pong-apercu").html($("<img>", { src: att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url, alt: "", css: { width: 32, height: 32, objectFit: "contain" } }));
                    $ligne.find(".monclubtt-pong-choisir").text("Changer");
                    $ligne.find(".monclubtt-pong-retirer").show();
                });
                frame.open();
            }).on("click", ".monclubtt-pong-retirer", function() {
                var $ligne = $(this).closest("tr");
                $ligne.find("input[type=hidden]").val("");
                $ligne.find(".monclubtt-pong-apercu").empty();
                $ligne.find(".monclubtt-pong-choisir").text("Photo");
                $(this).hide();
            });
        });');
    }

    public function afficher_photos_html()
    {
        ?>
        <input type="hidden" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_AFFICHER_PHOTOS); ?>" value="0">
        <label>
            <input type="checkbox" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_AFFICHER_PHOTOS); ?>" value="1"<?php checked(monclubtt_photos_affichees()); ?>>
            Afficher les photos des joueurs
        </label>
        <p class="description">
            Décoché : aucun visage sur le site (podium, jeu de pong et ses images de partage, visuels réseaux sociaux), tous les joueurs ont leur avatar dessiné,
            adversaires du top 10 compris. Les photos restent enregistrées et visibles dans la liste ci-dessous.
        </p>
        <?php
    }

    public function pong_pros_html()
    {
        ?>
        <input type="hidden" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_PONG_PROS); ?>" value="0">
        <label>
            <input type="checkbox" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_PONG_PROS); ?>" value="1"<?php checked(monclubtt_pong_pros()); ?>>
            Proposer le top 10 mondial comme adversaires
        </label>
        <p class="description">Décoché : on ne joue que contre les joueurs du club (et l'invité d'un shortcode).</p>
        <?php
    }

    /** Case à cocher : '1' ou '0'. */
    public function sanitize_case($valeur)
    {
        return (string) $valeur === '1' ? '1' : '0';
    }

    public function pong_musique_html()
    {
        $valeur = (string) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_MUSIQUE, '');
        $url    = monclubtt_get_pong_musique_url();
        $option = MonClubTT_Constantes::MONCLUBTT_PONG_MUSIQUE;
        ?>
        <div class="monclubtt-pong-musique">
            <input type="text" class="regular-text" name="<?php echo esc_attr($option); ?>" value="<?php echo esc_attr($valeur); ?>" placeholder="https://…/musique.mp3">
            <button type="button" class="button monclubtt-pong-musique-choisir">Choisir dans la médiathèque</button>
            <?php if ($url): ?>
                <p><audio controls preload="none" src="<?php echo esc_url($url); ?>" style="max-width:100%"></audio></p>
            <?php endif; ?>
        </div>
        <p class="description">
            Musique de fond du jeu, en boucle : fichier audio de la médiathèque ou adresse directe d'un fichier MP3, OGG, M4A ou WAV (un lien YouTube, Spotify ou Deezer ne fonctionne pas).
            Elle démarre au premier toucher du visiteur et se coupe avec le bouton musique du jeu. Vide = pas de musique.
            N'utilisez qu'une musique dont le club a les droits (libre de droits, ou licence adaptée).
        </p>
        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(function($) {
            $(".monclubtt-pong-musique-choisir").on("click", function() {
                var $champ = $(this).siblings("input[type=text]");
                var frame = wp.media({ title: "Musique du jeu de pong", button: { text: "Utiliser cette musique" }, library: { type: "audio" }, multiple: false });
                frame.on("select", function() {
                    $champ.val(frame.state().get("selection").first().toJSON().id);
                });
                frame.open();
            });
        });');
    }

    /**
     * Musique du jeu : ID d'une pièce jointe audio, ou URL http(s) ; sinon vide.
     */
    public function sanitize_pong_musique($valeur)
    {
        $valeur = trim((string) $valeur);
        if (ctype_digit($valeur)) {
            $id = (int) $valeur;
            return get_post_type($id) === 'attachment' && strpos((string) get_post_mime_type($id), 'audio/') === 0 ? (string) $id : '';
        }
        $url = esc_url_raw($valeur, array('http', 'https'));
        return $url ? $url : '';
    }

    public function pong_compteurs_html()
    {
        $c         = $this->compteursPong();
        $jouees    = (int) $c['jouees'];
        $terminees = (int) $c['terminees'];
        ?>
        <p>
            <?php /* translators: %s: nombre de parties jouées */ ?>
            <strong><?php echo esc_html(sprintf(_n('%s partie jouée', '%s parties jouées', $jouees, 'mon-club-tt'), number_format_i18n($jouees))); ?></strong>,
            <?php /* translators: %s: nombre de parties terminées */ ?>
            <strong><?php echo esc_html(sprintf(_n('%s partie terminée', '%s parties terminées', $terminees, 'mon-club-tt'), number_format_i18n($terminees))); ?></strong>
            <?php if ($jouees > 0): ?>
                (<?php echo esc_html(number_format_i18n(min(100, 100 * $terminees / $jouees))); ?> %)
            <?php endif; ?>
            <?php if (!empty($c['depuis'])): ?>
                depuis le <?php echo esc_html(wp_date(get_option('date_format'), (int) $c['depuis'])); ?>
            <?php endif; ?>
        </p>
        <label>
            <input type="checkbox" name="monclubtt_pong_raz_compteurs" value="1">
            Remettre les compteurs à zéro
        </label>
        <p class="description">
            Une partie est jouée à chaque match lancé (« Rejouer » compris), terminée quand le match va jusqu'au bout.
            Les visites des robots qui n'exécutent pas le jeu ne sont pas comptées.
        </p>
        <?php
    }

    /** Case « Remettre à zéro » : rien n'est conservé dans l'option elle-même. */
    public function sanitize_pong_raz_compteurs($valeur)
    {
        if ($valeur === '1') {
            delete_option(MonClubTT_Constantes::MONCLUBTT_PONG_COMPTEURS);
        }
        return '';
    }

    /** @return array{jouees: int, terminees: int, depuis: int} */
    private function compteursPong()
    {
        $c = get_option(MonClubTT_Constantes::MONCLUBTT_PONG_COMPTEURS, array());
        $c = is_array($c) ? $c : array();
        return array(
            'jouees'    => (int) ($c['jouees'] ?? 0),
            'terminees' => (int) ($c['terminees'] ?? 0),
            'depuis'    => (int) ($c['depuis'] ?? 0),
        );
    }

    /** Ajoute une partie au compteur « jouees » ou « terminees ». */
    private function compterPong($cle)
    {
        $c = $this->compteursPong();
        $c[$cle]++;
        if (!$c['depuis']) {
            $c['depuis'] = time();
        }
        update_option(MonClubTT_Constantes::MONCLUBTT_PONG_COMPTEURS, $c, false);
    }

    public function pong_vider_scores_html()
    {
        $nb = count(MonClubTT_PongScores::classer((array) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_SCORES, array())));
        ?>
        <label>
            <input type="checkbox" name="monclubtt_pong_vider_scores" value="1">
            <?php /* translators: %d: nombre de victoires */ ?>
            Vider le tableau des meilleurs scores (<?php echo esc_html(sprintf(_n('%d victoire enregistrée', '%d victoires enregistrées', $nb, 'mon-club-tt'), $nb)); ?>)
        </label>
        <p class="description">
            Les scores sont envoyés par les navigateurs des visiteurs : un petit malin peut en inventer un, et les visiteurs hors club choisissent leur nom. Videz le tableau s'il contient une entrée douteuse.
        </p>
        <?php
    }

    /**
     * Case « Vider le tableau » : supprime les scores, rien n'est conservé
     * dans l'option elle-même.
     */
    public function sanitize_pong_vider_scores($valeur)
    {
        if ($valeur === '1') {
            delete_option(MonClubTT_Constantes::MONCLUBTT_PONG_SCORES);
        }
        return '';
    }

    public function pong_vider_matchs_html()
    {
        $nb = count((array) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_MATCHS, array()));
        ?>
        <label>
            <input type="checkbox" name="monclubtt_pong_vider_matchs" value="1">
            <?php /* translators: %d: nombre de matchs */ ?>
            Supprimer les matchs partagés et leurs images (<?php echo esc_html(sprintf(_n('%d match', '%d matchs', $nb, 'mon-club-tt'), $nb)); ?>)
        </label>
        <p class="description">
            Chaque fin de match crée un lien de partage (image et aperçu sur les réseaux) avec le nom saisi par le joueur. Les 300 plus récents sont gardés.
            Videz-les si un nom inapproprié circule : les liens déjà partagés afficheront alors simplement la page du jeu.
        </p>
        <?php
    }

    /**
     * Case « Supprimer les matchs partagés » : supprime les matchs et les
     * images en cache, rien n'est conservé dans l'option elle-même.
     */
    public function sanitize_pong_vider_matchs($valeur)
    {
        if ($valeur === '1') {
            delete_option(MonClubTT_Constantes::MONCLUBTT_PONG_MATCHS);
            $uploads = wp_upload_dir(null, false);
            foreach ((array) glob(trailingslashit($uploads['basedir']) . 'monclubtt-pong/*.png') as $fichier) {
                if (is_string($fichier) && preg_match('/^[a-z0-9]{12}(-sans-photo)?\.png$/', basename($fichier))) {
                    wp_delete_file($fichier);
                }
            }
        }
        return '';
    }

    public function sanitize_pong_adversaires($valeur)
    {
        $adversaires = monclubtt_normaliser_adversaires($valeur);
        foreach ($adversaires as $sexe => $liste) {
            foreach ($liste as $i => $adv) {
                $adversaires[$sexe][$i]['nom'] = sanitize_text_field($adv['nom']);
                if ($adv['photo'] && $this->sanitize_logo($adv['photo']) === 0) {
                    $adversaires[$sexe][$i]['photo'] = 0;
                }
            }
        }
        return $adversaires;
    }

    public function sanitize_logo($valeur)
    {
        $id = absint($valeur);
        if ($id && (get_post_type($id) !== 'attachment' || strpos((string) get_post_mime_type($id), 'image/') !== 0)) {
            return 0;
        }
        return $id;
    }

    /**
     * Couleurs du club : normalisées, et l'ancienne option des visuels
     * (1.6.2) est retirée une fois les réglages enregistrés.
     *
     * @param mixed $valeur
     * @return array
     */
    public function sanitize_couleurs($valeur)
    {
        delete_option(MonClubTT_Constantes::MONCLUBTT_SOCIAL_COULEURS);
        return monclubtt_normaliser_couleurs($valeur);
    }

    /**
     * Formulaire d'un groupe de réglages (voir register_settings()).
     *
     * @param string $groupe Groupe de réglages, qui sert aussi d'identifiant de page des sections.
     * @param string $bouton Libellé du bouton d'envoi.
     */
    public function getForm($groupe = 'monclubtt_settings', $bouton = 'Enregistrer')
    {
        echo '<form action="options.php" method="POST" class="monclubtt_settings_form">';
        settings_fields($groupe);
        do_settings_sections($groupe);
        submit_button($bouton);
        echo '</form>';
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

    /**
     * Jeu de pong : sélection d'un joueur du club (photo détourée), match
     * contre un adversaire paramétrable.
     * @param array $atts adversaire : nom d'un adversaire imposé (sinon tirage au sort
     *                    dans le top 10 mondial des réglages) ; adversaire_titre : sous-titre ;
     *                    adversaire_photo : ID de média ou URL d'une photo détourée ;
     *                    adversaire_sexe : M | F ; adversaire_pays : code de
     *                    MonClubTT_Constantes::PONG_PAYS ; manches : 1 | 3 | 5
     * @return string
     */
    public function pong_front($atts)
    {
        $atts = shortcode_atts(array(
            'adversaire'       => '',
            'adversaire_titre' => '',
            'adversaire_photo' => '',
            'adversaire_sexe'  => 'M',
            'adversaire_pays'  => '',
            'manches'          => '1',
        ), (array) $atts, 'monclubtt_pong');

        // Adversaire imposé par le shortcode (joué d'office, les autres restent
        // accessibles ensuite) et top 10 mondial des réglages.
        $impose      = null;
        $adversaires = array();
        $credits     = array();
        if (trim((string) $atts['adversaire']) !== '') {
            $photo  = trim((string) $atts['adversaire_photo']);
            $pays   = strtoupper(trim((string) $atts['adversaire_pays']));
            $credit = '';
            if (!monclubtt_photos_affichees()) {
                $photo = '';
            } elseif (ctype_digit($photo)) {
                $credit = $this->creditPhoto((int) $photo);
                $photo  = (string) wp_get_attachment_image_url((int) $photo, 'medium');
            }
            if ($credit !== '') {
                $credits[] = array('nom' => sanitize_text_field($atts['adversaire']), 'html' => $credit);
            }
            $impose = array(
                'nom'    => sanitize_text_field($atts['adversaire']),
                'prenom' => '',
                'titre'  => sanitize_text_field($atts['adversaire_titre']),
                'sex'    => $atts['adversaire_sexe'] === 'F' ? 'F' : 'M',
                'photo'  => $photo !== '' ? esc_url_raw($photo) : '',
                'pays'   => isset(MonClubTT_Constantes::PONG_PAYS[$pays]) ? $pays : '',
            );
        }
        foreach (monclubtt_pong_pros() ? monclubtt_get_pong_adversaires() : array() as $sexe => $liste) {
            foreach ($liste as $i => $adv) {
                if ($adv['nom'] === '') {
                    continue;
                }
                $photo = $adv['photo'] && monclubtt_photos_affichees() ? wp_get_attachment_image_url($adv['photo'], 'medium') : '';
                $credit = $photo ? $this->creditPhoto($adv['photo']) : '';
                if ($credit !== '') {
                    $credits[] = array('nom' => $adv['nom'], 'html' => $credit);
                }
                $adversaires[] = array(
                    'nom'      => $adv['nom'],
                    'prenom'   => '',
                    'tel_quel' => true,
                    'titre'    => 'N°' . ($i + 1) . ($sexe === 'F' ? ' mondiale' : ' mondial'),
                    'sex'      => $sexe,
                    'photo'    => $photo ? $photo : '',
                    'pays'     => $adv['pays'],
                );
            }
        }
        $manches = in_array((int) $atts['manches'], array(1, 3, 5), true) ? (int) $atts['manches'] : 1;
        $joueurs = new MonClubTT_Joueurs();
        $scores  = $this->classementPong();
        $musique = monclubtt_get_pong_musique_url();

        // Arrivée par un lien partagé (?pong=ID) : le match devient un défi.
        $defi  = null;
        $match = isset($_GET['pong']) ? $this->matchPong(sanitize_key(wp_unslash($_GET['pong']))) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture publique
        if ($match && (int) $match['post'] === (int) get_the_ID()) {
            $defi = array(
                'joueur'     => $match['joueur'],
                'adversaire' => $match['adversaire'],
                'pj'         => (int) $match['pj'],
                'pa'         => (int) $match['pa'],
                'victoire'   => (bool) $match['victoire'],
                'niveau'     => $match['niveau'],
                'adv_type'   => $match['adv_type'] ?? '',
                'adv_nom'    => $match['adv_nom'] ?? '',
                'adv_prenom' => $match['adv_prenom'] ?? '',
            );
        }

        ob_start();
        require __DIR__ . '/views/front/pong.php';
        return ob_get_clean();
    }

    /**
     * Crédit d'une photo d'adversaire : légende de l'image dans la
     * médiathèque (auteur, licence et source, obligatoires pour Wikimedia
     * Commons). Seuls les liens sont gardés comme HTML.
     * @param int $attachmentId
     * @return string HTML sûr
     */
    private function creditPhoto($attachmentId)
    {
        $legende = wp_get_attachment_caption($attachmentId);
        return $legende ? trim(wp_kses($legende, array('a' => array('href' => array())))) : '';
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
     * Hook pour récupérer la feuille de match d'une rencontre (mise en cache 7 jours)
     * Usage: $feuille = apply_filters('monclubtt_get_feuille_rencontre', null, array('renc_id' => '6595431', 'is_retour' => 0));
     * Les identifiants se lisent dans le champ « lien » des rencontres de poule.
     * @param mixed $value Valeur par défaut (ignorée)
     * @param array $params Paramètres avec 'renc_id' et 'is_retour' (0 par défaut)
     * @return array|false Feuille brute (resultat, joueur, partie) ou false si indisponible
     */
    public function get_feuille_rencontre_data($value, $params)
    {
        $api = MonClubTT_AccesFFTTApi::getInstance();
        if (!is_object($api) || empty($params['renc_id'])) {
            return false;
        }
        return $api->getRencontreDetail((string) $params['renc_id'], (int) ($params['is_retour'] ?? 0));
    }

    /**
     * Handler AJAX (public) : tableau des meilleurs scores du jeu de pong,
     * relu à l'ouverture de la page (qui peut venir d'un cache).
     */
    public function handle_ajax_pong_scores()
    {
        wp_send_json_success(array('classement' => $this->classementPong()));
    }

    /**
     * Handler AJAX (public) : début d'un match de pong, pour le compteur des
     * parties jouées. Un envoi toutes les 2 secondes au plus par adresse IP.
     */
    public function handle_ajax_pong_debut()
    {
        if (!check_ajax_referer('monclubtt_pong', 'nonce', false)) {
            wp_send_json_error(null, 403);
            return;
        }
        $cle = 'monclubtt_pong_debut_' . $this->cleIpPong();
        if (get_transient($cle)) {
            wp_send_json_error(null, 429);
            return;
        }
        set_transient($cle, 1, 2);
        $this->compterPong('jouees');
        wp_send_json_success();
    }

    /**
     * Handler AJAX (public) : fin d'un match de pong.
     *
     * Chaque match est enregistré pour être partagé (page avec balises
     * Open Graph et image générée). Une victoire contre un joueur du top 10
     * mondial ou du club entre en plus au tableau des meilleurs scores. Noms
     * (licenciés, adversaires des réglages, invité du shortcode de la page)
     * et score sont vérifiés ; le score reste déclaré par le navigateur, d'où
     * la limite d'un envoi toutes les 15 secondes par adresse IP.
     */
    public function handle_ajax_pong_fin()
    {
        if (!check_ajax_referer('monclubtt_pong', 'nonce', false)) {
            wp_send_json_error(array('message' => 'Session expirée, rechargez la page.'), 403);
            return;
        }
        $champ = function ($cle) {
            return sanitize_text_field(wp_unslash($_POST[$cle] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié ci-dessus
        };
        $pj      = (int) $champ('pj');
        $pa      = (int) $champ('pa');
        $niveau  = $champ('niveau');
        $advType = $champ('adv_type');
        $postId  = (int) $champ('page');

        $cle = 'monclubtt_pong_' . $this->cleIpPong();
        if (get_transient($cle)) {
            wp_send_json_error(array('message' => 'Trop de matchs envoyés, réessayez dans un instant.'), 429);
            return;
        }

        $victoire   = $pj > $pa;
        $visiteur   = $champ('joueur_type') === 'visiteur';
        $joueur     = $visiteur ? $this->visiteurPong($champ('joueur_nom')) : $this->joueurPong($champ('joueur_nom'), $champ('joueur_prenom'));
        $adversaire = null;
        if ($advType === 'club') {
            $adversaire = $this->joueurPong($champ('adv_nom'), $champ('adv_prenom'));
        } elseif ($advType === 'monde' && monclubtt_pong_pros()) {
            foreach (monclubtt_get_pong_adversaires() as $liste) {
                foreach ($liste as $adv) {
                    if ($adv['nom'] !== '' && $adv['nom'] === $champ('adv_nom')) {
                        $adversaire = array('affiche' => $adv['nom'], 'photo' => monclubtt_photos_affichees() ? (int) $adv['photo'] : 0);
                    }
                }
            }
        } elseif ($advType === 'invite') {
            $adversaire = $this->invitePong($postId, $champ('adv_nom'));
        }
        $scoreOk = $victoire ? MonClubTT_PongScores::scoreValide($pj, $pa) : MonClubTT_PongScores::scoreValide($pa, $pj);
        if (!$joueur || !$adversaire || !in_array($niveau, MonClubTT_PongScores::NIVEAUX, true) || !$scoreOk) {
            wp_send_json_error(array('message' => 'Match non enregistré.'), 400);
            return;
        }
        set_transient($cle, 1, 15);
        $this->compterPong('terminees');

        $reponse = array('classement' => null, 'rang' => null, 'partage' => null);

        // Visiteurs hors club compris, signalés comme tels au tableau.
        if ($victoire && $advType !== 'invite') {
            $resultat = MonClubTT_PongScores::ajouter(
                (array) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_SCORES, array()),
                array(
                    'joueur'     => $joueur['affiche'],
                    'visiteur'   => $visiteur,
                    'adversaire' => $adversaire['affiche'],
                    'niveau'     => $niveau,
                    'pj'         => $pj,
                    'pa'         => $pa,
                    'date'       => time(),
                )
            );
            update_option(MonClubTT_Constantes::MONCLUBTT_PONG_SCORES, $resultat['tableau'], false);
            $reponse['classement'] = array_map(array('MonClubTT_PongScores', 'publique'), $resultat['tableau']);
            $reponse['rang']       = $resultat['rang'];
        }

        // Partage : seulement depuis une page publiée qui contient le jeu.
        if ($this->pagePong($postId)) {
            $id = $this->enregistrerMatchPong(array(
                'joueur'      => $joueur['affiche'],
                'adversaire'  => $adversaire['affiche'],
                'photo_j'     => $joueur['photo'],
                'photo_a'     => $adversaire['photo'],
                'pj'          => $pj,
                'pa'          => $pa,
                'victoire'    => $victoire,
                'niveau'      => $niveau,
                // Pour rejouer le même match depuis le lien partagé (défi).
                'adv_type'    => $advType,
                'adv_nom'     => $champ('adv_nom'),
                'adv_prenom'  => $champ('adv_prenom'),
                'post'        => $postId,
                'date'        => time(),
            ));
            $club = get_bloginfo('name');
            $reponse['partage'] = array(
                'url'   => add_query_arg('pong', $id, get_permalink($postId)),
                'image' => add_query_arg('monclubtt_pong_image', $id, home_url('/')),
                'texte' => $victoire
                    ? sprintf('J\'ai battu %s %d–%d au Pong du club %s. Tu fais mieux ?', $adversaire['affiche'], $pj, $pa, $club)
                    : sprintf('%s m\'a battu %d–%d au Pong du club %s. Qui me venge ?', $adversaire['affiche'], $pa, $pj, $club),
            );
        }

        wp_send_json_success($reponse);
    }

    /**
     * Clé de limite de débit du pong : adresse IPv4 du visiteur, ou son
     * préfixe /64 en IPv6 (un appareil y dispose de milliards d'adresses).
     * Derrière un proxy, REMOTE_ADDR est celle du proxy : la limite devient
     * alors commune à tous les visiteurs.
     */
    private function cleIpPong()
    {
        $ip      = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
        $binaire = filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) ? inet_pton($ip) : false;
        return md5($binaire !== false ? substr($binaire, 0, 8) : $ip);
    }

    /**
     * Licencié du club proposé dans le jeu, retrouvé par nom et prénom.
     * @return array{affiche: string, photo: int}|null photo = ID de la pièce jointe (0 sans photo)
     */
    private function joueurPong($nom, $prenom)
    {
        $joueurs = new MonClubTT_Joueurs();
        $photos  = (array) get_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, array());
        foreach ($joueurs->getJoueurs('MF') as $joueur) {
            if ($joueur->getNom() === $nom && $joueur->getPrenom() === $prenom) {
                return array(
                    'affiche' => trim($joueur->getPrenom() . ' ' . strtoupper($joueur->getNom())),
                    'photo'   => monclubtt_photos_affichees() ? (int) ($photos[$joueur->getLicence()] ?? 0) : 0,
                );
            }
        }
        return null;
    }

    /**
     * Visiteur hors club : prénom libre, nettoyé et limité à 20 caractères,
     * sans photo.
     * @return array{affiche: string, photo: int}|null
     */
    private function visiteurPong($nom)
    {
        $nom = trim((string) preg_replace('/\s+/u', ' ', sanitize_text_field($nom)));
        $nom = function_exists('mb_substr') ? mb_substr($nom, 0, 20) : substr($nom, 0, 20);
        return $nom !== '' ? array('affiche' => $nom, 'photo' => 0) : null;
    }

    /**
     * Shortcodes [monclubtt_pong] d'une page publiée (attributs bruts), ou
     * null si la page n'existe pas, n'est pas publiée ou ne contient pas le jeu.
     * @return array|null
     */
    private function pagePong($postId)
    {
        $post = $postId ? get_post($postId) : null;
        if (!$post || $post->post_status !== 'publish' || !has_shortcode($post->post_content, 'monclubtt_pong')) {
            return null;
        }
        preg_match_all('/' . get_shortcode_regex(array('monclubtt_pong')) . '/', $post->post_content, $trouves, PREG_SET_ORDER);
        return array_map(function ($trouve) {
            $atts = shortcode_parse_atts($trouve[3]);
            return is_array($atts) ? $atts : array();
        }, $trouves);
    }

    /**
     * Adversaire imposé (« invité ») : son nom doit être celui d'un shortcode
     * de la page, pour qu'aucun texte libre ne soit enregistré.
     * @return array{affiche: string, photo: int}|null
     */
    private function invitePong($postId, $nom)
    {
        foreach ((array) $this->pagePong($postId) as $atts) {
            if (isset($atts['adversaire']) && $nom !== '' && sanitize_text_field($atts['adversaire']) === $nom) {
                $photo = monclubtt_photos_affichees() && isset($atts['adversaire_photo']) && ctype_digit((string) $atts['adversaire_photo']) ? (int) $atts['adversaire_photo'] : 0;
                return array('affiche' => $nom, 'photo' => $photo);
            }
        }
        return null;
    }

    /**
     * Mémorise un match partageable (les 300 plus récents) et supprime
     * l'image en cache des matchs écartés.
     * @return string Identifiant du match.
     */
    private function enregistrerMatchPong(array $match)
    {
        $matchs = (array) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_MATCHS, array());
        $id     = strtolower(wp_generate_password(12, false));
        $matchs[$id] = $match;
        while (count($matchs) > 300) {
            $ancien = array_key_first($matchs);
            unset($matchs[$ancien]);
            foreach (array(true, false) as $photos) {
                $fichier = $this->cheminImagePong($ancien, $photos);
                if ($fichier && file_exists($fichier)) {
                    wp_delete_file($fichier);
                }
            }
        }
        update_option(MonClubTT_Constantes::MONCLUBTT_PONG_MATCHS, $matchs, false);
        return $id;
    }

    /** Match partageable par identifiant, null s'il est inconnu. */
    private function matchPong($id)
    {
        if (!is_string($id) || !preg_match('/^[a-z0-9]{12}$/', $id)) {
            return null;
        }
        $matchs = (array) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_MATCHS, array());
        return isset($matchs[$id]) && is_array($matchs[$id]) ? $matchs[$id] : null;
    }

    /**
     * Fichier de l'image de partage en cache (uploads/monclubtt-pong/ID.png),
     * ID-sans-photo.png quand les photos sont désactivées : décocher le réglage
     * ne ressert pas une image déjà générée avec les visages.
     */
    private function cheminImagePong($id, $photos = null)
    {
        $photos = $photos === null ? monclubtt_photos_affichees() : $photos;
        if (!preg_match('/^[a-z0-9]{12}$/', (string) $id)) {
            return '';
        }
        $uploads = wp_upload_dir(null, false);
        return trailingslashit($uploads['basedir']) . 'monclubtt-pong/' . $id . ($photos ? '' : '-sans-photo') . '.png';
    }

    /** Fichier d'une photo pour l'image de partage : taille « medium » si elle existe. */
    private function fichierPhotoPong($attachmentId)
    {
        if (!$attachmentId || !monclubtt_photos_affichees()) {
            return '';
        }
        $taille = image_get_intermediate_size($attachmentId, 'medium');
        if ($taille && !empty($taille['path'])) {
            $uploads = wp_upload_dir(null, false);
            return trailingslashit($uploads['basedir']) . $taille['path'];
        }
        $original = get_attached_file($attachmentId);
        return $original ? $original : '';
    }

    /**
     * Image de partage d'un match (?monclubtt_pong_image=ID) : générée une
     * fois avec GD puis servie depuis le cache. Sans GD, logo du club.
     */
    public function pong_image()
    {
        if (!isset($_GET['monclubtt_pong_image'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture publique
            return;
        }
        $id    = sanitize_key(wp_unslash($_GET['monclubtt_pong_image'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $match = $this->matchPong($id);
        if (!$match) {
            status_header(404);
            exit;
        }
        $fichier = $this->cheminImagePong($id);
        if (!file_exists($fichier)) {
            $niveaux = array('normal' => 'Normal', 'mondial' => 'Expert');
            $png = MonClubTT_PongImage::rendre(array(
                'joueur'           => $match['joueur'],
                'adversaire'       => $match['adversaire'],
                'pj'               => (int) $match['pj'],
                'pa'               => (int) $match['pa'],
                'victoire'         => (bool) $match['victoire'],
                'niveau_libelle'   => $niveaux[$match['niveau']] ?? '',
                'photo_joueur'     => $this->fichierPhotoPong((int) $match['photo_j']),
                'photo_adversaire' => $this->fichierPhotoPong((int) $match['photo_a']),
                'club'             => get_bloginfo('name'),
                'site'             => (string) wp_parse_url(home_url(), PHP_URL_HOST),
                'couleurs'         => monclubtt_get_couleurs(),
                'police'           => __DIR__ . '/assets/fonts/LiberationSans-Bold.ttf',
            ));
            if ($png === null) {
                $logo = monclubtt_get_logo_url(512);
                if ($logo) {
                    wp_safe_redirect($logo);
                    exit;
                }
                status_header(404);
                exit;
            }
            wp_mkdir_p(dirname($fichier));
            file_put_contents($fichier, $png); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        }
        header('Content-Type: image/png');
        header('Cache-Control: public, max-age=604800');
        header('Content-Length: ' . filesize($fichier));
        readfile($fichier); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
        exit;
    }

    /**
     * Balises Open Graph / Twitter d'un match partagé (?pong=ID sur la page
     * du jeu), pour que Facebook, X et WhatsApp affichent l'image du match.
     */
    public function pong_og()
    {
        if (!is_singular() || !isset($_GET['pong'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lecture publique
            return;
        }
        $id    = sanitize_key(wp_unslash($_GET['pong'])); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $match = $this->matchPong($id);
        if (!$match || (int) $match['post'] !== (int) get_queried_object_id()) {
            return;
        }
        $titre = $match['victoire']
            ? sprintf('%s bat %s %d–%d', $match['joueur'], $match['adversaire'], $match['pj'], $match['pa'])
            : sprintf('%s bat %s %d–%d', $match['adversaire'], $match['joueur'], $match['pa'], $match['pj']);
        $desc  = $match['victoire']
            ? sprintf('À toi : bats %s au Pong du club %s. Gratuit, au doigt sur mobile.', $match['adversaire'], get_bloginfo('name'))
            : sprintf('Venge %s : bats %s au Pong du club %s. Gratuit, au doigt sur mobile.', $match['joueur'], $match['adversaire'], get_bloginfo('name'));
        $url   = add_query_arg('pong', $id, get_permalink((int) $match['post']));
        $image = add_query_arg('monclubtt_pong_image', $id, home_url('/'));
        $balises = array(
            'og:type'             => 'website',
            'og:title'            => $titre,
            'og:description'      => $desc,
            'og:url'              => $url,
            'og:image'            => $image,
            'og:image:width'      => (string) MonClubTT_PongImage::LARGEUR,
            'og:image:height'     => (string) MonClubTT_PongImage::HAUTEUR,
            'og:image:alt'        => $titre,
            'twitter:card'        => 'summary_large_image',
            'twitter:title'       => $titre,
            'twitter:description' => $desc,
            'twitter:image'       => $image,
        );
        foreach ($balises as $nom => $valeur) {
            $attribut = strpos($nom, 'twitter:') === 0 ? 'name' : 'property';
            printf('<meta %s="%s" content="%s">' . "\n", esc_attr($attribut), esc_attr($nom), esc_attr($valeur));
        }
    }

    /** Tableau des meilleurs scores, version publique. */
    private function classementPong()
    {
        $tableau = MonClubTT_PongScores::classer((array) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_SCORES, array()));
        return array_map(array('MonClubTT_PongScores', 'publique'), $tableau);
    }

    /**
     * Handler AJAX (public) : feuille de match d'une rencontre affichée sur une
     * page d'équipe. Liste blanche : la poule doit être celle d'une équipe du
     * club et la rencontre doit y figurer (données déjà en cache), sinon
     * aucune requête n'est faite à l'API FFTT.
     */
    public function handle_ajax_feuille_match()
    {
        // Lecture publique, sans nonce : les paramètres sont contrôlés par liste blanche.
        // phpcs:disable WordPress.Security.NonceVerification.Missing
        $champ = function ($cle) {
            return isset($_POST[$cle]) && is_string($_POST[$cle]) ? sanitize_text_field(wp_unslash($_POST[$cle])) : '';
        };
        $iddiv    = $champ('iddiv');
        $idpoule  = $champ('idpoule');
        $rencId   = $champ('renc_id');
        $isRetour = $champ('is_retour');
        // phpcs:enable

        if (!MonClubTT_FeuilleMatch::parametresValides($iddiv, $idpoule, $rencId, $isRetour)) {
            wp_send_json_error(array('message' => 'Rencontre invalide'), 400);
            return;
        }

        $api = MonClubTT_AccesFFTTApi::getInstance();
        // La poule est vérifiée avant de lire ses rencontres : une poule
        // étrangère au club ne doit déclencher aucun appel à l'API.
        if (!is_object($api)
            || !MonClubTT_FeuilleMatch::pouleDuClub($this->get_equipes_data(null), $iddiv, $idpoule)
            || !MonClubTT_FeuilleMatch::rencontreDeLaPoule((array) $api->getPouleRencontres($iddiv, $idpoule), $rencId, (int) $isRetour)) {
            wp_send_json_error(array('message' => 'Rencontre invalide'), 400);
            return;
        }

        $data = $api->getRencontreDetail($rencId, (int) $isRetour);

        if (!$data) {
            wp_send_json_error(array('message' => 'Feuille de match non disponible'));
            return;
        }

        wp_send_json_success($data);
    }

    /**
     * Charge la médiathèque WordPress uniquement sur les pages qui s'en
     * servent : Données FFTT (photos), Pong (photos, musique) et Réglages (logo).
     *
     * @param string $hook Suffixe de hook de la page admin courante.
     */
    public function admin_enqueue_media($hook)
    {
        if (in_array($hook, $this->media_page_hooks, true)) {
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
        $players    = $joueurs->getDonneesTopProgression('MF');

        // Paliers de points franchis ce mois-ci, du plus haut au plus bas.
        $paliers = array();
        foreach ($players as $player) {
            $palier = MonClubTT_StatsReseaux::palierFranchi($player['mens'], $player['dm']);
            if ($palier !== null) {
                $paliers[] = $player + array('palier' => $palier);
            }
        }
        usort($paliers, function ($a, $b) {
            return array($b['palier'], $b['mens']) <=> array($a['palier'], $a['mens']);
        });

        wp_localize_script('monclubtt-social-js', 'MonClubTTSocial', array(
            'ajaxurl'     => admin_url('admin-ajax.php'),
            'nonce'       => wp_create_nonce('monclubtt_top_perfs_nonce'),
            'players'     => $players,
            'paliers'     => $paliers,
            'moisLabel'   => $moisFr[(int) date_i18n('n') - 1] . ' ' . date_i18n('Y'),
            'saisonLabel' => 'Saison ' . $saison . '–' . ($saison + 1),
            'clubName'    => get_bloginfo('name'),
            'siteHost'    => $siteHost ? $siteHost : '',
            'logo'        => monclubtt_get_logo_url(256),
            'couleurs'    => monclubtt_get_couleurs(),
        ));
    }

    /**
     * Handler AJAX : statistiques du dernier week-end de championnat par
     * équipes, lues sur les feuilles de match (mises en cache 7 jours par l'API) :
     * top perfs (victoires contre mieux classé, points au barème FFTT, regroupées
     * par joueur), résultats des équipes avec leur place en poule, cartons
     * pleins et victoires à la belle.
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

        $rencontres  = array();
        $classements = array();
        $equipes     = new MonClubTT_Equipes();
        foreach ($equipes->getEquipesSeniorChampionnat('MF') as $equipe) {
            if ($equipe->getIddiv() && $equipe->getIdpoule()) {
                $rencontres  = array_merge($rencontres, (array) $api->getPouleRencontres($equipe->getIddiv(), $equipe->getIdpoule()));
                $classements = array_merge($classements, MonClubTT_TopPerfs::liste($api->getPouleClassement($equipe->getIddiv(), $equipe->getIdpoule())));
            }
        }

        $journee = MonClubTT_TopPerfs::rencontresDerniereJournee($rencontres, MonClubTT_ParametresPlugin::getNumClub());
        if (empty($journee)) {
            wp_send_json_error(array('message' => 'Aucune rencontre de championnat jouée trouvée. Lancez une synchronisation si la journée vient d\'avoir lieu.'));
            return;
        }

        $perfs   = array();
        $parties = array();
        foreach ($journee as $rencontre) {
            $feuille = $api->getRencontreDetail($rencontre['renc_id'], $rencontre['is_retour']);
            if (is_array($feuille)) {
                $perfs   = array_merge($perfs, MonClubTT_TopPerfs::extrairePerfs($feuille, $rencontre['equipes_club']));
                $parties = array_merge($parties, MonClubTT_StatsReseaux::partiesDuClub($feuille, $rencontre['equipes_club']));
            }
        }

        // Nom et photo depuis la liste des joueurs du club (la feuille donne « NOM Prénom »).
        $joueursParNom = array();
        $joueurs       = new MonClubTT_Joueurs();
        foreach ($joueurs->getJoueurs('MF') as $joueur) {
            $joueursParNom[$this->cleNomJoueur($joueur->getNom() . ' ' . $joueur->getPrenom())] = $joueur;
        }
        $identite = function ($ligne) use ($joueursParNom) {
            $joueur = $joueursParNom[$this->cleNomJoueur($ligne['joueur'])] ?? null;
            return array(
                'nom'    => $joueur ? $joueur->getNom() : $ligne['joueur'],
                'prenom' => $joueur ? $joueur->getPrenom() : '',
                'sex'    => $joueur ? $joueur->getSexe() : $ligne['sexe'],
                'photo'  => $joueur ? $joueur->getPhotoUrl() : '',
            );
        };

        $bilan    = MonClubTT_TopPerfs::bilanParJoueur($perfs);
        $resultat = array();
        foreach (MonClubTT_TopPerfs::classer($bilan, 8) as $perf) {
            $resultat[] = $identite($perf) + array(
                'points'            => $perf['points'],
                'adversaire_points' => $perf['adversaire_points'],
                'ecart'             => $perf['ecart'],
                'gain'              => $perf['gain'],
                'nb_perfs'          => $perf['nb_perfs'],
                'equipe'            => $perf['equipe'],
            );
        }

        $recap = array();
        foreach (MonClubTT_StatsReseaux::recapEquipes($journee) as $ligne) {
            $recap[] = $ligne + array('rang' => MonClubTT_StatsReseaux::rangDansPoule($classements, $ligne['equipe']));
        }

        $cartons = array();
        foreach (array_slice(MonClubTT_StatsReseaux::cartonsPleins($parties), 0, 24) as $carton) {
            $cartons[] = $identite($carton) + array(
                'victoires' => $carton['victoires'],
                'equipe'    => $carton['equipe'],
            );
        }

        $belles = array();
        foreach (array_slice(MonClubTT_StatsReseaux::victoiresALaBelle($parties), 0, 8) as $belle) {
            $belles[] = $identite($belle) + array(
                'adversaire_points' => $belle['adversaire_points'],
                'equipe'            => $belle['equipe'],
                'sets'              => $belle['sets'],
                'remontada'         => $belle['remontada'],
                'finish'            => $belle['finish'],
            );
        }

        $dates = array_column($journee, 'date');
        $tours = array_filter(array_column($journee, 'tour'));
        wp_send_json_success(array(
            'perfs'         => $resultat,
            'equipes'       => $recap,
            'cartons'       => $cartons,
            'belles'        => $belles,
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

        $this->reinitialiser_api();
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

            /** Données FFTT synchronisées : la gestion des équipes met à jour ses joueurs. */
            do_action('monclubtt_apres_synchro');

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

        $teamsCreate = isset($_POST['teams_create']) ? $this->sanitizeTeamsList(wp_unslash((array) $_POST['teams_create'])) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est nettoyé par sanitizeTeamsList()
        $teamsDelete = isset($_POST['teams_delete']) ? $this->sanitizeTeamsList(wp_unslash((array) $_POST['teams_delete'])) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est nettoyé par sanitizeTeamsList()

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
            // Identifiants FFTT numériques : rien ne doit pouvoir sortir des guillemets du shortcode.
            if (!ctype_digit($iddiv) || ($idpoule !== '' && !ctype_digit($idpoule)) || $title === '') {
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
        if (!current_user_can('manage_options')) {
            return;
        }
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
