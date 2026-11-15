<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_AppliWP' ) ) {

/**
 * Appli d'équipe : une page [monclubtt_equipe] peut être ajoutée à l'écran
 * d'accueil du téléphone, sous le nom et le logo du club.
 *
 * - Manifest propre à chaque page (route REST publique), aux couleurs du club.
 * - L'appli s'ouvre sur une adresse stable (/?monclubtt_appli=M3) qui renvoie
 *   vers la page actuelle de l'équipe : la division, la poule et la page
 *   générée changent à chaque phase, l'icône installée continue de marcher.
 * - Bandeau en bas d'écran qui propose l'installation (bouton natif sur
 *   Android, mode d'emploi sur iPhone).
 *
 * Les règles (noms, sigle, clé d'équipe) sont dans MonClubTT_Appli.
 */
class MonClubTT_AppliWP {

    const REST_NS    = 'monclubtt/v1';
    const PARAM      = 'monclubtt_appli';
    const SHORTCODE  = 'monclubtt_equipe';

    /** @var string Fichier principal du plugin (URL des assets). */
    private $fichierPlugin;

    /** @var array<string, mixed>|null|false Contexte de la page courante (false : pas encore calculé). */
    private $contexteCourant = false;

    public function __construct($fichierPlugin) {
        $this->fichierPlugin = $fichierPlugin;
        add_action('rest_api_init', array($this, 'enregistrer_routes'));
        add_action('template_redirect', array($this, 'rediriger_vers_equipe'), 0);
        add_action('wp_head', array($this, 'balises_head'), 2);
        add_action('wp_enqueue_scripts', array($this, 'charger_bandeau'));
    }

    /** Réglage global (Mon Club TT › Réglages), activé par défaut. */
    public static function active() {
        return get_option(MonClubTT_Constantes::MONCLUBTT_APPLI_EQUIPES, '1') === '1';
    }

    // ─── Contexte d'une page ────────────────────────────────────────────────

    /**
     * Ce qu'il faut savoir d'une page pour en faire une appli, null si elle n'en
     * est pas une (réglage désactivé, pas de shortcode, appli="non", page
     * protégée, application de gestion des équipes).
     *
     * @param WP_Post|null $page
     * @return array<string, mixed>|null
     */
    public function contexte($page) {
        if (!self::active() || !($page instanceof WP_Post) || $page->post_status !== 'publish' || post_password_required($page)) {
            return null;
        }
        $contenu = (string) $page->post_content;
        if (strpos($contenu, '[' . self::SHORTCODE) === false || strpos($contenu, '[tt_team_planner') !== false) {
            return null;
        }
        $shortcodes = MonClubTT_Appli::shortcodesEquipe($contenu);
        if (empty($shortcodes)) {
            return null;
        }
        foreach ($shortcodes as $attributs) {
            if (MonClubTT_Appli::desactiveeParAttribut($attributs)) {
                return null;
            }
        }

        // Une seule équipe : appli de l'équipe. Plusieurs : appli de la page.
        $equipe = null;
        $type   = 'M';
        if (count($shortcodes) === 1) {
            $trouvee = $this->trouverEquipe($shortcodes[0]['iddiv'] ?? '', $shortcodes[0]['idpoule'] ?? '');
            if ($trouvee !== null) {
                list($equipe, $type) = $trouvee;
            }
        }

        $identite = MonClubTT_Appli::identite(monclubtt_nom_club(), $equipe, $type, $shortcodes[0]);
        $lien     = (string) get_permalink($page);

        // L'adresse stable n'est retenue que si elle ramène bien ici (pas un
        // doublon de la page de l'équipe, page en cours de remplacement…).
        $stable = false;
        if ($identite['cle'] !== '') {
            $cle    = MonClubTT_Appli::lireCle($identite['cle']);
            $stable = $cle !== null && $this->pageEquipe($cle['type'], $cle['numero']) === $lien;
        }

        return array(
            'page'     => $page,
            'identite' => $identite,
            'stable'   => $stable,
            'depart'   => $stable ? self::urlStable($identite['cle']) : $lien,
            'lien'     => $lien,
        );
    }

    /** Contexte de la page affichée (calculé une fois par requête). */
    private function contexteCourant() {
        if ($this->contexteCourant === false) {
            $this->contexteCourant = is_singular() ? $this->contexte(get_queried_object()) : null;
        }
        return $this->contexteCourant;
    }

    /** Adresse stable d'une équipe : /?monclubtt_appli=M3. */
    public static function urlStable($cle) {
        return add_query_arg(self::PARAM, $cle, home_url('/'));
    }

    /**
     * Équipe du club dans cette poule (listes en cache), avec son type.
     *
     * @return array{0: array<string, mixed>, 1: string}|null
     */
    private function trouverEquipe($iddiv, $idpoule) {
        $iddiv   = (string) $iddiv;
        $idpoule = (string) $idpoule;
        if ($iddiv === '' || $idpoule === '') {
            return null;
        }
        foreach ($this->equipesDuClub() as $type => $equipes) {
            foreach ($equipes as $equipe) {
                if ((string) ($equipe['iddiv'] ?? '') === $iddiv && (string) ($equipe['idpoule'] ?? '') === $idpoule) {
                    return array($equipe, $type);
                }
            }
        }
        return null;
    }

    /** @return array{M: array<int, array<string, mixed>>, F: array<int, array<string, mixed>>} */
    private function equipesDuClub() {
        $resultat = array('M' => array(), 'F' => array());
        $numClub  = MonClubTT_ParametresPlugin::getNumClub();
        $api      = MonClubTT_AccesFFTTApi::getInstance();
        if (!$numClub || !is_object($api)) {
            return $resultat;
        }
        foreach (array('M', 'F') as $type) {
            $equipes = $api->getEquipesByClub($numClub, $type);
            $resultat[$type] = is_array($equipes) ? array_values(array_filter($equipes, 'is_array')) : array();
        }
        return $resultat;
    }

    // ─── Redirection de l'adresse stable ────────────────────────────────────

    /**
     * /?monclubtt_appli=M3 : page actuelle de l'équipe 3, l'accueil du site
     * si elle n'en a pas (fin de saison, page retirée).
     */
    public function rediriger_vers_equipe() {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- lien public, lecture seule
        if (!isset($_GET[self::PARAM])) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $cle = MonClubTT_Appli::lireCle(sanitize_text_field(wp_unslash($_GET[self::PARAM])));
        $url = $cle !== null ? $this->pageEquipe($cle['type'], $cle['numero']) : '';

        nocache_headers();
        wp_safe_redirect($url !== '' ? $url : home_url('/'), 302);
        exit;
    }

    /** URL de la page publiée qui affiche l'équipe, '' si aucune. */
    private function pageEquipe($type, $numero) {
        $equipes = $this->equipesDuClub();
        foreach ($equipes[$type] as $equipe) {
            if (MonClubTT_Appli::numeroEquipe((string) ($equipe['libequipe'] ?? '')) !== $numero) {
                continue;
            }
            $url = $this->pagePoule((string) ($equipe['iddiv'] ?? ''), (string) ($equipe['idpoule'] ?? ''));
            if ($url !== '') {
                return $url;
            }
        }
        return '';
    }

    /** Page publiée contenant [monclubtt_equipe] de cette poule ; celle de la seule équipe en priorité. */
    private function pagePoule($iddiv, $idpoule) {
        if ($iddiv === '' || $idpoule === '') {
            return '';
        }
        global $wpdb;
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- ouverture de l'appli seulement
        $lignes = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts}
                 WHERE post_status = 'publish'
                   AND post_type IN ('page', 'post')
                   AND post_content LIKE %s
                   AND post_content LIKE %s
                 ORDER BY ID DESC
                 LIMIT 20",
                '%' . $wpdb->esc_like('[' . self::SHORTCODE) . '%',
                '%' . $wpdb->esc_like($idpoule) . '%'
            )
        );

        $repli = '';
        foreach ((array) $lignes as $ligne) {
            $shortcodes = MonClubTT_Appli::shortcodesEquipe((string) $ligne->post_content);
            foreach ($shortcodes as $attributs) {
                if (($attributs['iddiv'] ?? '') !== $iddiv || ($attributs['idpoule'] ?? '') !== $idpoule) {
                    continue;
                }
                $url = (string) get_permalink((int) $ligne->ID);
                if (count($shortcodes) === 1) {
                    return $url;
                }
                if ($repli === '') {
                    $repli = $url;
                }
            }
        }
        return $repli;
    }

    // ─── Manifest ───────────────────────────────────────────────────────────

    public function enregistrer_routes() {
        register_rest_route(self::REST_NS, '/appli', array(
            'methods'             => 'GET',
            'callback'            => array($this, 'manifest'),
            'permission_callback' => '__return_true',
            'args'                => array(
                'page' => array('required' => true, 'sanitize_callback' => 'absint'),
            ),
        ));
    }

    /** GET /monclubtt/v1/appli?page=ID : Web App Manifest de la page. */
    public function manifest(WP_REST_Request $requete) {
        $contexte = $this->contexte(get_post((int) $requete->get_param('page')));
        if ($contexte === null) {
            return new WP_REST_Response(array('message' => 'Pas d’appli pour cette page.'), 404);
        }

        $identite = $contexte['identite'];
        $couleurs = monclubtt_get_couleurs();
        $racine   = (string) wp_parse_url(home_url('/'), PHP_URL_PATH);

        $reponse = new WP_REST_Response(array(
            // L'identifiant de l'appli installée : stable d'une phase à l'autre.
            'id'               => $contexte['depart'],
            'name'             => $identite['nom'],
            'short_name'       => $identite['nom_court'],
            'description'      => $identite['description'],
            'start_url'        => $contexte['depart'],
            'scope'            => $racine !== '' ? $racine : '/',
            'display'          => 'standalone',
            'background_color' => '#ffffff',
            'theme_color'      => $couleurs['primaire'],
            'lang'             => 'fr',
            'dir'              => 'ltr',
            'categories'       => array('sports'),
            'icons'            => $this->icones(),
        ), 200);
        $reponse->header('Content-Type', 'application/manifest+json; charset=utf-8');
        $reponse->header('Cache-Control', 'public, max-age=3600');
        return $reponse;
    }

    /**
     * Icônes de l'appli : le logo du club s'il est carré (192 px au moins),
     * sinon l'icône du site, sinon l'icône du plugin. Les tailles déclarées
     * doivent être les vraies, sinon Chrome refuse l'installation.
     *
     * @return array<int, array<string, string>>
     */
    private function icones() {
        $logo = $this->logoCarre();
        if ($logo !== null) {
            return array(array('src' => $logo[0], 'sizes' => $logo[1] . 'x' . $logo[1], 'type' => self::typeImage($logo[0]), 'purpose' => 'any'));
        }
        $icone192 = get_site_icon_url(192);
        $icone512 = get_site_icon_url(512);
        if ($icone192 && $icone512) {
            return array(
                array('src' => $icone192, 'sizes' => '192x192', 'type' => self::typeImage($icone192), 'purpose' => 'any'),
                array('src' => $icone512, 'sizes' => '512x512', 'type' => self::typeImage($icone512), 'purpose' => 'any'),
            );
        }
        return array(
            array('src' => $this->iconePlugin(192), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'),
            array('src' => $this->iconePlugin(512), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'),
        );
    }

    /** @return array{0: string, 1: int}|null URL et côté du logo du club, s'il est carré et assez grand. */
    private function logoCarre() {
        $logoId = (int) get_option(MonClubTT_Constantes::MONCLUBTT_LOGO, 0);
        if (!$logoId) {
            return null;
        }
        $image = wp_get_attachment_image_src($logoId, 'full');
        if (!is_array($image) || empty($image[0]) || (int) $image[1] !== (int) $image[2] || (int) $image[1] < 192) {
            return null;
        }
        return array((string) $image[0], (int) $image[1]);
    }

    /** Icône pour l'iPhone (apple-touch-icon) et le bandeau. */
    private function iconeTactile() {
        $logo = $this->logoCarre();
        if ($logo !== null) {
            return $logo[0];
        }
        $icone = get_site_icon_url(180);
        return $icone ? $icone : $this->iconePlugin(192);
    }

    private function iconePlugin($taille) {
        return plugins_url('modules/gestion-equipes/assets/icons/icon-' . (int) $taille . '.png', $this->fichierPlugin);
    }

    private static function typeImage($url) {
        $type = wp_check_filetype((string) wp_parse_url($url, PHP_URL_PATH));
        return $type['type'] ? (string) $type['type'] : 'image/png';
    }

    // ─── Page ───────────────────────────────────────────────────────────────

    public function balises_head() {
        $contexte = $this->contexteCourant();
        if ($contexte === null) {
            return;
        }
        $couleurs = monclubtt_get_couleurs();
        $manifest = add_query_arg('page', (int) $contexte['page']->ID, rest_url(self::REST_NS . '/appli'));

        echo '<link rel="manifest" href="' . esc_url($manifest) . '">' . "\n";
        echo '<meta name="theme-color" content="' . esc_attr($couleurs['primaire']) . '">' . "\n";
        echo '<meta name="mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-capable" content="yes">' . "\n";
        echo '<meta name="apple-mobile-web-app-title" content="' . esc_attr($contexte['identite']['nom_court']) . '">' . "\n";
        echo '<link rel="apple-touch-icon" href="' . esc_url($this->iconeTactile()) . '">' . "\n";
    }

    public function charger_bandeau() {
        $contexte = $this->contexteCourant();
        if ($contexte === null) {
            return;
        }
        $fichier = plugin_dir_path($this->fichierPlugin) . 'assets/mon-club-tt-appli.js';
        wp_enqueue_script('monclubtt-appli-js', plugins_url('/assets/mon-club-tt-appli.js', $this->fichierPlugin), array(), (string) filemtime($fichier), true);
        $identite = $contexte['identite'];
        wp_localize_script('monclubtt-appli-js', 'MonClubTTAppli', array(
            // Bandeau fermé : mémorisé par équipe (ou par page).
            'cle'      => $identite['cle'] !== '' ? $identite['cle'] : 'p' . (int) $contexte['page']->ID,
            'nom'      => $identite['nom'],
            'equipe'   => $identite['cle'] !== '',
            'icone'    => $this->iconeTactile(),
            // Sur iPhone, l'écran d'accueil garde l'adresse affichée : on y met
            // l'adresse stable pour que l'icône survive au changement de phase.
            'stable'   => $contexte['stable'] ? $contexte['depart'] : '',
        ));
    }
}

}
