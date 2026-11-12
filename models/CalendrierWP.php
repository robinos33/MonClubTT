<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_CalendrierWP' ) ) {

/**
 * Calendrier du club dans WordPress : type de contenu « Événement » (séries et
 * catégories), saisie et import dans l'admin, flux iCalendar et shortcodes
 * [monclubtt_calendrier], [monclubtt_prochain] et [monclubtt_dates].
 *
 * Les règles (dates en toutes lettres, prochain événement, import, .ics) sont
 * dans MonClubTT_Calendrier ; cette classe ne fait que le lien avec WordPress.
 */
class MonClubTT_CalendrierWP {

    const TYPE      = 'monclubtt_evenement';
    const SERIE     = 'monclubtt_serie';
    const CATEGORIE = 'monclubtt_categorie';

    /** Champ de l'événement => clé de métadonnée. */
    const META = array(
        'debut'    => '_monclubtt_debut',
        'fin'      => '_monclubtt_fin',
        'heure'    => '_monclubtt_heure',
        'heureFin' => '_monclubtt_heure_fin',
        'lieu'     => '_monclubtt_lieu',
        'lien'     => '_monclubtt_lien',
        'note'     => '_monclubtt_note',
        'saison'   => '_monclubtt_saison',
        'source'   => '_monclubtt_source',
    );

    /** Paramètre d'URL du flux iCalendar (?monclubtt_calendrier=ics). */
    const FLUX = 'monclubtt_calendrier';

    /** Catégories créées à la première activation. */
    const OPTION_INIT = 'monclubtt_calendrier_init';

    /** Compte rendu du dernier import, affiché après redirection. */
    const TRANSIENT_IMPORT = 'monclubtt_import_calendrier_';

    /** @var array|null Événements du site, chargés une fois par requête. */
    private $evenements = null;

    /** @var int Numéro du calendrier dans la page (identifiants uniques). */
    private $instances = 0;

    /** @var string Plugin principal (pour plugins_url). */
    private $fichierPlugin;

    public function __construct($fichierPlugin)
    {
        $this->fichierPlugin = $fichierPlugin;

        add_action('init', array($this, 'enregistrer_types'), 5);
        add_action('init', array($this, 'enregistrer_assets'));
        add_action('init', array($this, 'servir_ics'), 30);

        add_action('admin_menu', array($this, 'menus'), 20);
        add_filter('parent_file', array($this, 'menu_parent'));
        add_filter('submenu_file', array($this, 'sous_menu_actif'));
        add_action('add_meta_boxes_' . self::TYPE, array($this, 'meta_boxes'));
        add_action('save_post_' . self::TYPE, array($this, 'enregistrer_meta'), 10, 2);
        add_filter('manage_' . self::TYPE . '_posts_columns', array($this, 'colonnes'));
        add_action('manage_' . self::TYPE . '_posts_custom_column', array($this, 'colonne'), 10, 2);
        add_filter('manage_edit-' . self::TYPE . '_sortable_columns', array($this, 'colonnes_triables'));
        add_action('pre_get_posts', array($this, 'tri_admin'));
        add_action('restrict_manage_posts', array($this, 'filtre_saison'));
        add_filter('enter_title_here', array($this, 'invite_titre'), 10, 2);
        add_action('admin_post_monclubtt_import_calendrier', array($this, 'traiter_import'));

        add_shortcode('monclubtt_calendrier', array($this, 'shortcode_calendrier'));
        add_shortcode('monclubtt_prochain', array($this, 'shortcode_prochain'));
        add_shortcode('monclubtt_dates', array($this, 'shortcode_dates'));

        // Dates exposées aux autres plugins et au thème : apply_filters('monclubtt_get_evenements', array(), $criteres).
        add_filter('monclubtt_get_evenements', array($this, 'filtre_evenements'), 10, 2);
    }

    /* ------------------------------------------------------------------
     * Types de contenu
     * ------------------------------------------------------------------ */

    public function enregistrer_types()
    {
        register_post_type(self::TYPE, array(
            'labels' => array(
                'name'               => 'Événements',
                'singular_name'      => 'Événement',
                'menu_name'          => 'Calendrier',
                'all_items'          => 'Calendrier',
                'add_new'            => 'Ajouter',
                'add_new_item'       => 'Ajouter un événement',
                'edit_item'          => 'Modifier l\'événement',
                'new_item'           => 'Nouvel événement',
                'search_items'       => 'Rechercher un événement',
                'not_found'          => 'Aucun événement. Importez le tableur de la saison ou ajoutez un événement.',
                'not_found_in_trash' => 'Aucun événement dans la corbeille.',
                'item_updated'       => 'Événement enregistré.',
                'item_published'     => 'Événement enregistré.',
            ),
            'public'          => false,
            'show_ui'         => true,
            // Sous-menu ajouté à la main dans menus() : avec show_in_menu sur le menu du plugin,
            // WordPress l'insère avant que « Mon Club TT » existe et l'entrée des réglages disparaît.
            'show_in_menu'    => false,
            'show_in_rest'    => false,
            'supports'        => array('title'),
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'rewrite'         => false,
            'query_var'       => false,
        ));

        $commun = array(
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_admin_column'  => true,
            'show_in_quick_edit' => true,
            'show_in_rest'       => false,
            'hierarchical'       => false,
            'rewrite'            => false,
            'query_var'          => false,
            'meta_box_cb'        => array($this, 'boite_terme'),
        );
        register_taxonomy(self::SERIE, self::TYPE, $commun + array(
            'labels' => array(
                'name'          => 'Séries',
                'singular_name' => 'Série',
                'search_items'  => 'Rechercher une série',
                'all_items'     => 'Toutes les séries',
                'edit_item'     => 'Modifier la série',
                'add_new_item'  => 'Ajouter une série',
                'not_found'     => 'Aucune série.',
                'back_to_items' => '← Retour aux séries',
            ),
            'description' => 'Rendez-vous qui reviennent dans la saison (tours du Grand Prix, stages…). '
                . 'Le slug sert dans les shortcodes : [monclubtt_prochain serie="slug"].',
        ));
        register_taxonomy(self::CATEGORIE, self::TYPE, $commun + array(
            'labels' => array(
                'name'          => 'Catégories',
                'singular_name' => 'Catégorie',
                'search_items'  => 'Rechercher une catégorie',
                'all_items'     => 'Toutes les catégories',
                'edit_item'     => 'Modifier la catégorie',
                'add_new_item'  => 'Ajouter une catégorie',
                'not_found'     => 'Aucune catégorie.',
                'back_to_items' => '← Retour aux catégories',
            ),
        ));

        if (!get_option(self::OPTION_INIT)) {
            foreach (array_keys(MonClubTT_Calendrier::CATEGORIES) as $nom) {
                if (!term_exists($nom, self::CATEGORIE)) {
                    wp_insert_term($nom, self::CATEGORIE);
                }
            }
            update_option(self::OPTION_INIT, '1', false);
        }
    }

    public function enregistrer_assets()
    {
        $dir = plugin_dir_path($this->fichierPlugin);
        wp_register_style('monclubtt-calendrier-css', plugins_url('/assets/mon-club-tt-calendrier.css', $this->fichierPlugin), array('mon-club-tt-css'), (string) filemtime($dir . 'assets/mon-club-tt-calendrier.css'));
        wp_register_script('monclubtt-calendrier-js', plugins_url('/assets/mon-club-tt-calendrier.js', $this->fichierPlugin), array(), (string) filemtime($dir . 'assets/mon-club-tt-calendrier.js'), true);
    }

    /* ------------------------------------------------------------------
     * Lecture des événements
     * ------------------------------------------------------------------ */

    /**
     * Tous les événements publiés, normalisés (voir MonClubTT_Calendrier).
     *
     * @return array
     */
    public function evenements()
    {
        if ($this->evenements !== null) {
            return $this->evenements;
        }
        $posts = get_posts(array(
            'post_type'        => self::TYPE,
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'suppress_filters' => false,
        ));
        $evenements = array();
        foreach ($posts as $post) {
            $evenement = $this->normaliser($post);
            if ($evenement !== null) {
                $evenements[] = $evenement;
            }
        }
        $this->evenements = MonClubTT_Calendrier::numeroter($evenements);
        return $this->evenements;
    }

    /**
     * @param WP_Post $post
     * @return array|null null si l'événement n'a pas de date valide.
     */
    private function normaliser($post)
    {
        $meta  = array();
        foreach (self::META as $champ => $cle) {
            $meta[$champ] = (string) get_post_meta($post->ID, $cle, true);
        }
        $debut = MonClubTT_Calendrier::date($meta['debut']);
        if ($debut === null) {
            return null;
        }
        $fin   = MonClubTT_Calendrier::date($meta['fin']);
        $serie = $this->terme($post->ID, self::SERIE);
        $cat   = $this->terme($post->ID, self::CATEGORIE);
        return array(
            'id'        => (int) $post->ID,
            'debut'     => $debut,
            'fin'       => ($fin !== null && $fin >= $debut) ? $fin : $debut,
            'heure'     => MonClubTT_Calendrier::heure($meta['heure']),
            'heureFin'  => MonClubTT_Calendrier::heure($meta['heureFin']),
            'titre'     => wp_specialchars_decode($post->post_title, ENT_QUOTES),
            'categorie' => $cat ? $cat->name : '',
            'serie'     => $serie ? $serie->slug : '',
            'serieNom'  => $serie ? $serie->name : '',
            'lieu'      => $meta['lieu'],
            'lien'      => $meta['lien'],
            'note'      => $meta['note'],
            'saison'    => $meta['saison'] !== '' ? (int) $meta['saison'] : MonClubTT_Calendrier::saisonDe($debut),
        );
    }

    /** Premier terme d'une taxonomie pour un événement. */
    private function terme($postId, $taxonomie)
    {
        $termes = get_the_terms($postId, $taxonomie);
        return (is_array($termes) && $termes) ? $termes[0] : null;
    }

    /** Date du jour dans le fuseau du site. */
    private function aujourdhui()
    {
        return current_time('Y-m-d');
    }

    /**
     * Filtre les événements selon des critères de shortcode.
     *
     * @param array $criteres serie (slugs séparés par des virgules), categorie
     *                        (noms ou slugs), saison (année, « toutes » ou vide = saison de référence).
     * @return array [ 'evenements' => [...], 'saison' => int|null ]
     */
    private function selection(array $criteres)
    {
        $evenements = $this->evenements();
        $series     = $this->liste($criteres['serie'] ?? '');
        $categories = array_map('sanitize_title', $this->liste($criteres['categorie'] ?? ''));
        if ($series) {
            $series     = array_map('sanitize_title', $series);
            $evenements = array_filter($evenements, function ($e) use ($series) {
                return in_array($e['serie'], $series, true);
            });
        }
        if ($categories) {
            $evenements = array_filter($evenements, function ($e) use ($categories) {
                return in_array(sanitize_title($e['categorie']), $categories, true);
            });
        }
        $evenements = array_values($evenements);

        $saison = trim((string) ($criteres['saison'] ?? ''));
        if ($saison === 'toutes') {
            return array('evenements' => $evenements, 'saison' => null);
        }
        $saison = ctype_digit($saison) ? (int) $saison : MonClubTT_Calendrier::saisonDeReference($evenements, $this->aujourdhui());
        return array(
            'evenements' => $saison === null ? array() : MonClubTT_Calendrier::deLaSaison($evenements, $saison),
            'saison'     => $saison,
        );
    }

    /** « a, b ,c » → ['a', 'b', 'c']. */
    private function liste($valeur)
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $valeur)), 'strlen'));
    }

    /**
     * Filtre monclubtt_get_evenements.
     *
     * @param mixed $defaut
     * @param array $criteres serie, categorie, saison (voir selection()).
     * @return array
     */
    public function filtre_evenements($defaut = array(), $criteres = array())
    {
        return $this->selection((array) $criteres + array('saison' => 'toutes'))['evenements'];
    }

    /* ------------------------------------------------------------------
     * Flux iCalendar
     * ------------------------------------------------------------------ */

    /**
     * Adresse du flux ou d'un export.
     *
     * @param array $parametres saison, categorie, serie, evenement, du, au, telecharger.
     * @return string
     */
    public static function url_ics(array $parametres = array())
    {
        return add_query_arg(array_filter(array(self::FLUX => 'ics') + $parametres, 'strlen'), home_url('/'));
    }

    public function servir_ics()
    {
        // Flux public en lecture seule : pas de nonce, les paramètres ne font que filtrer.
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        if (!isset($_GET[self::FLUX]) || 'ics' !== $_GET[self::FLUX]) {
            return;
        }
        $param = function ($nom) {
            return isset($_GET[$nom]) ? sanitize_text_field(wp_unslash($_GET[$nom])) : '';
        };
        $evenement = absint($param('evenement'));
        $du        = MonClubTT_Calendrier::date($param('du'));
        $au        = MonClubTT_Calendrier::date($param('au'));
        // phpcs:enable

        if ($evenement) {
            $evenements = array_values(array_filter($this->evenements(), function ($e) use ($evenement) {
                return $e['id'] === $evenement;
            }));
        } else {
            $saison     = $param('saison');
            $evenements = $this->selection(array(
                'serie'     => $param('serie'),
                'categorie' => $param('categorie'),
                'saison'    => ctype_digit($saison) ? $saison : 'toutes',
            ))['evenements'];
            if (!ctype_digit($saison) && !$du && !$au) {
                // Abonnement : ce qui est passé depuis plus de 3 mois ne sert plus.
                $limite     = gmdate('Y-m-d', strtotime($this->aujourdhui() . ' -90 days'));
                $evenements = array_filter($evenements, function ($e) use ($limite) {
                    return $e['fin'] >= $limite;
                });
            }
            if ($du || $au) {
                $evenements = array_filter($evenements, function ($e) use ($du, $au) {
                    return (!$au || $e['debut'] <= $au) && (!$du || $e['fin'] >= $du);
                });
            }
        }

        $nom  = get_bloginfo('name');
        $ics  = MonClubTT_Calendrier::ics(array_values($evenements), array(
            'nom'     => $nom,
            'domaine' => (string) wp_parse_url(home_url('/'), PHP_URL_HOST),
            'url'     => home_url('/'),
        ));
        $fichier = sanitize_file_name(sanitize_title($nom) . ($evenement ? '-' . $evenement : '') . '.ics');

        header('Content-Type: text/calendar; charset=utf-8');
        header('Content-Disposition: ' . ($param('telecharger') ? 'attachment' : 'inline') . '; filename="' . $fichier . '"');
        header('Cache-Control: public, max-age=3600');
        echo $ics; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- texte iCalendar, échappé par MonClubTT_Calendrier::ics().
        exit;
    }

    /* ------------------------------------------------------------------
     * Admin : menus, fiche d'un événement, liste
     * ------------------------------------------------------------------ */

    public function menus()
    {
        $type = self::TYPE;
        add_submenu_page('monclubtt_parametres', 'Calendrier', 'Calendrier', 'edit_posts', 'edit.php?post_type=' . $type);
        add_submenu_page('monclubtt_parametres', 'Ajouter un événement', '— Ajouter', 'edit_posts', 'post-new.php?post_type=' . $type);
        add_submenu_page('monclubtt_parametres', 'Séries', '— Séries', 'manage_categories', 'edit-tags.php?taxonomy=' . self::SERIE . '&post_type=' . $type);
        add_submenu_page('monclubtt_parametres', 'Catégories', '— Catégories', 'manage_categories', 'edit-tags.php?taxonomy=' . self::CATEGORIE . '&post_type=' . $type);
        add_submenu_page('monclubtt_parametres', 'Importer le calendrier', '— Importer', 'edit_posts', 'monclubtt_import_calendrier', array($this, 'page_import'));
    }

    /** Garde le menu « Mon Club TT » ouvert sur les écrans du calendrier. */
    public function menu_parent($parent)
    {
        $ecran = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($ecran && $ecran->post_type === self::TYPE) {
            return 'monclubtt_parametres';
        }
        return $parent;
    }

    public function sous_menu_actif($actif)
    {
        $ecran = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$ecran || $ecran->post_type !== self::TYPE) {
            return $actif;
        }
        if ($ecran->base === 'edit-tags' || $ecran->base === 'term') {
            return 'edit-tags.php?taxonomy=' . $ecran->taxonomy . '&post_type=' . self::TYPE;
        }
        if ($ecran->base === 'post' && $ecran->action === 'add') {
            return 'post-new.php?post_type=' . self::TYPE;
        }
        return 'edit.php?post_type=' . self::TYPE;
    }

    public function invite_titre($texte, $post)
    {
        return $post->post_type === self::TYPE ? 'Intitulé (ex. Grand Prix du Conseil départemental – Tour 1)' : $texte;
    }

    public function meta_boxes()
    {
        add_meta_box('monclubtt_evenement_dates', 'Date et infos pratiques', array($this, 'boite_dates'), self::TYPE, 'normal', 'high');
    }

    /** Saisons proposées : de la plus ancienne enregistrée à la saison prochaine. */
    private function saisons_proposees($incluse = null)
    {
        $courante = MonClubTT_Calendrier::saisonDe($this->aujourdhui());
        $saisons  = array_merge(array($courante - 1, $courante, $courante + 1), array_column($this->evenements(), 'saison'));
        if ($incluse !== null) {
            $saisons[] = (int) $incluse;
        }
        $saisons = array_unique(array_map('intval', $saisons));
        rsort($saisons);
        return $saisons;
    }

    public function boite_dates($post)
    {
        wp_nonce_field('monclubtt_evenement', 'monclubtt_evenement_nonce');
        $v = array();
        foreach (self::META as $champ => $cle) {
            $v[$champ] = (string) get_post_meta($post->ID, $cle, true);
        }
        $saison = $v['saison'] !== '' ? (int) $v['saison']
            : MonClubTT_Calendrier::saisonDe($v['debut'] !== '' ? $v['debut'] : $this->aujourdhui());
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="monclubtt_debut">Dates</label></th>
                <td>
                    <label>du <input type="date" id="monclubtt_debut" name="monclubtt[debut]" value="<?php echo esc_attr($v['debut']); ?>" required></label>
                    <label>au <input type="date" id="monclubtt_fin" name="monclubtt[fin]" value="<?php echo esc_attr($v['fin']); ?>"></label>
                    <p class="description">Laisser « au » vide pour un événement sur une journée.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_heure">Horaire</label></th>
                <td>
                    <label>de <input type="time" id="monclubtt_heure" name="monclubtt[heure]" value="<?php echo esc_attr($v['heure']); ?>"></label>
                    <label>à <input type="time" name="monclubtt[heureFin]" value="<?php echo esc_attr($v['heureFin']); ?>"></label>
                    <p class="description">Facultatif. Sans horaire, l'événement est ajouté en journée entière dans les agendas.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_saison">Saison</label></th>
                <td>
                    <select id="monclubtt_saison" name="monclubtt[saison]">
                        <?php foreach ($this->saisons_proposees($saison) as $s) : ?>
                            <option value="<?php echo esc_attr($s); ?>" <?php selected($s, $saison); ?>><?php echo esc_html($s . '–' . ($s + 1)); ?></option>
                        <?php endforeach; ?>
                    </select>
                    <p class="description">Saison du calendrier où il apparaît (un stage d'août peut appartenir à la saison qui se termine).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_lieu">Lieu</label></th>
                <td>
                    <textarea id="monclubtt_lieu" name="monclubtt[lieu]" rows="2" class="large-text" placeholder="Jeunes (garçons) : TALENCE&#10;Féminines : CESTAS"><?php echo esc_textarea($v['lieu']); ?></textarea>
                    <p class="description">Une ligne par lieu si besoin.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_lien">Lien</label></th>
                <td>
                    <input type="url" id="monclubtt_lien" name="monclubtt[lien]" value="<?php echo esc_attr($v['lien']); ?>" class="large-text" placeholder="https://www.helloasso.com/…">
                    <p class="description">Inscription, horaires (PDF)…</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_note">Note</label></th>
                <td><textarea id="monclubtt_note" name="monclubtt[note]" rows="2" class="large-text"><?php echo esc_textarea($v['note']); ?></textarea></td>
            </tr>
        </table>
        <?php if ($v['source'] !== '') : ?>
            <p class="description">Importé du tableur (référence <code><?php echo esc_html($v['source']); ?></code>). Un nouvel import met à jour les dates, l'intitulé et la catégorie ; le lieu, le lien et la note ne sont remplacés que si le tableur les renseigne.</p>
        <?php endif; ?>
        <script>
            (function () {
                var d = document.getElementById('monclubtt_debut'), f = document.getElementById('monclubtt_fin');
                d.addEventListener('change', function () { f.min = d.value; if (f.value && f.value < d.value) { f.value = d.value; } });
                f.min = d.value;
            })();
        </script>
        <?php
    }

    /**
     * Boîte des séries et catégories : un seul terme, choisi dans la liste ou
     * saisi (un nouveau nom crée la série).
     */
    public function boite_terme($post, $boite)
    {
        $taxonomie = $boite['args']['taxonomy'];
        $tax       = get_taxonomy($taxonomie);
        $actuel    = $this->terme($post->ID, $taxonomie);
        $termes    = get_terms(array('taxonomy' => $taxonomie, 'hide_empty' => false));
        $id        = 'monclubtt-' . $taxonomie;
        if (!current_user_can($tax->cap->assign_terms)) {
            echo esc_html($actuel ? $actuel->name : '—');
            return;
        }
        ?>
        <input type="text" list="<?php echo esc_attr($id); ?>" name="tax_input[<?php echo esc_attr($taxonomie); ?>]" value="<?php echo esc_attr($actuel ? $actuel->name : ''); ?>" class="widefat" autocomplete="off" placeholder="Aucune">
        <datalist id="<?php echo esc_attr($id); ?>">
            <?php foreach ((array) $termes as $terme) : ?>
                <option value="<?php echo esc_attr($terme->name); ?>"></option>
            <?php endforeach; ?>
        </datalist>
        <p class="description">
            <?php echo $taxonomie === self::SERIE ? 'Choisir ou saisir une série (un nouveau nom la crée).' : 'Choisir ou saisir une catégorie.'; ?>
            <a href="<?php echo esc_url(admin_url('edit-tags.php?taxonomy=' . $taxonomie . '&post_type=' . self::TYPE)); ?>">Gérer</a>
        </p>
        <?php
    }

    public function enregistrer_meta($postId, $post)
    {
        if (!isset($_POST['monclubtt_evenement_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['monclubtt_evenement_nonce'])), 'monclubtt_evenement')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $postId)) {
            return;
        }
        $saisie = isset($_POST['monclubtt']) && is_array($_POST['monclubtt']) ? wp_unslash($_POST['monclubtt']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est assaini ci-dessous.
        $debut  = MonClubTT_Calendrier::date($saisie['debut'] ?? '');
        $fin    = MonClubTT_Calendrier::date($saisie['fin'] ?? '');
        if ($debut === null) {
            return;
        }
        $this->ecrire_meta($postId, array(
            'debut'    => $debut,
            'fin'      => ($fin !== null && $fin >= $debut) ? $fin : $debut,
            'heure'    => MonClubTT_Calendrier::heure($saisie['heure'] ?? ''),
            'heureFin' => MonClubTT_Calendrier::heure($saisie['heureFin'] ?? ''),
            'saison'   => absint($saisie['saison'] ?? 0) ?: MonClubTT_Calendrier::saisonDe($debut),
            'lieu'     => sanitize_textarea_field($saisie['lieu'] ?? ''),
            'lien'     => esc_url_raw($saisie['lien'] ?? ''),
            'note'     => sanitize_textarea_field($saisie['note'] ?? ''),
        ));
    }

    private function ecrire_meta($postId, array $valeurs)
    {
        foreach ($valeurs as $champ => $valeur) {
            if (!isset(self::META[$champ])) {
                continue;
            }
            if ($valeur === '' || $valeur === null) {
                delete_post_meta($postId, self::META[$champ]);
            } else {
                update_post_meta($postId, self::META[$champ], $valeur);
            }
        }
        $this->evenements = null;
    }

    public function colonnes($colonnes)
    {
        $nouvelles = array();
        foreach ($colonnes as $cle => $libelle) {
            if ($cle === 'date') {
                continue;
            }
            $nouvelles[$cle] = $libelle;
            if ($cle === 'title') {
                $nouvelles['monclubtt_dates']  = 'Dates';
                $nouvelles['monclubtt_saison'] = 'Saison';
            }
        }
        return $nouvelles;
    }

    public function colonne($colonne, $postId)
    {
        $debut = (string) get_post_meta($postId, self::META['debut'], true);
        if ($colonne === 'monclubtt_dates' && $debut !== '') {
            $fin   = (string) get_post_meta($postId, self::META['fin'], true);
            $heure = MonClubTT_Calendrier::heure(get_post_meta($postId, self::META['heure'], true));
            $passe = ($fin !== '' ? $fin : $debut) < $this->aujourdhui();
            printf(
                '<span style="%s">%s%s</span>',
                $passe ? 'color:#8c8f94' : '',
                esc_html(ucfirst(MonClubTT_Calendrier::periode($debut, $fin, true))),
                $heure !== '' ? esc_html(' · ' . MonClubTT_Calendrier::heureLisible($heure)) : ''
            );
        } elseif ($colonne === 'monclubtt_saison') {
            $saison = (string) get_post_meta($postId, self::META['saison'], true);
            echo esc_html($saison !== '' ? $saison . '–' . ((int) $saison + 1) : '—');
        }
    }

    public function colonnes_triables($colonnes)
    {
        $colonnes['monclubtt_dates'] = 'monclubtt_debut';
        return $colonnes;
    }

    /** Liste de l'admin : par date de l'événement (la plus proche d'abord), filtrable par saison. */
    public function tri_admin($requete)
    {
        if (!is_admin() || !$requete->is_main_query() || $requete->get('post_type') !== self::TYPE) {
            return;
        }
        $tri = $requete->get('orderby');
        if ($tri === '' || $tri === 'monclubtt_debut') {
            $requete->set('meta_key', self::META['debut']);
            $requete->set('orderby', 'meta_value');
            if ($tri === '') {
                $requete->set('order', 'ASC');
            }
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple filtre d'affichage.
        $saison = isset($_GET['monclubtt_saison']) ? absint($_GET['monclubtt_saison']) : 0;
        if ($saison) {
            $requete->set('meta_query', array(array('key' => self::META['saison'], 'value' => $saison)));
        }
    }

    public function filtre_saison($type)
    {
        if ($type !== self::TYPE) {
            return;
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple filtre d'affichage.
        $actuelle = isset($_GET['monclubtt_saison']) ? absint($_GET['monclubtt_saison']) : 0;
        echo '<select name="monclubtt_saison"><option value="">Toutes les saisons</option>';
        foreach ($this->saisons_proposees() as $s) {
            printf('<option value="%1$d" %2$s>%1$d–%3$d</option>', (int) $s, selected($s, $actuelle, false), (int) $s + 1);
        }
        echo '</select>';
    }

    /* ------------------------------------------------------------------
     * Admin : import du tableur
     * ------------------------------------------------------------------ */

    public function page_import()
    {
        if (!current_user_can('edit_posts')) {
            return;
        }
        $rapport = get_transient(self::TRANSIENT_IMPORT . get_current_user_id());
        if ($rapport) {
            delete_transient(self::TRANSIENT_IMPORT . get_current_user_id());
        }
        $courante = MonClubTT_Calendrier::saisonDe($this->aujourdhui());
        $flux     = self::url_ics();
        ?>
        <div class="wrap">
            <h1>Importer le calendrier</h1>
            <?php if (is_array($rapport)) : ?>
                <div class="notice notice-<?php echo $rapport['erreurs'] ? 'warning' : 'success'; ?>">
                    <p><strong><?php echo esc_html($rapport['titre']); ?></strong></p>
                    <?php if ($rapport['erreurs']) : ?>
                        <p>Lignes ignorées :</p>
                        <ul style="list-style:disc;margin-left:20px">
                            <?php foreach (array_slice($rapport['erreurs'], 0, 30) as $erreur) : ?>
                                <li><?php echo esc_html($erreur); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <?php if (!empty($rapport['simulation'])) : ?>
                        <p>Rien n'a été enregistré : choisissez à nouveau le fichier et cliquez sur « Importer ».</p>
                    <?php else : ?>
                        <p><a href="<?php echo esc_url(admin_url('edit.php?post_type=' . self::TYPE . '&monclubtt_saison=' . (int) $rapport['saison'])); ?>">Voir les événements de la saison</a></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <p>Le tableur de la saison s'importe ici, puis chaque événement se complète dans <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . self::TYPE)); ?>">Calendrier</a> (lieu, lien d'inscription, série…).</p>

            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                <input type="hidden" name="action" value="monclubtt_import_calendrier">
                <?php wp_nonce_field('monclubtt_import_calendrier'); ?>
                <table class="form-table" role="presentation">
                    <tr>
                        <th scope="row"><label for="monclubtt_fichier">Fichier</label></th>
                        <td>
                            <input type="file" id="monclubtt_fichier" name="fichier" accept=".csv,.txt,.json,text/csv,application/json">
                            <p class="description">CSV exporté du tableur (séparateur <code>;</code> ou <code>,</code>, en UTF-8) ou JSON de l'ancien calendrier.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="monclubtt_contenu">… ou coller</label></th>
                        <td><textarea id="monclubtt_contenu" name="contenu" rows="8" class="large-text code" placeholder="debut;fin;titre;categorie;serie;heure;lieu;lien&#10;28/11/2026;29/11/2026;Grand Prix – Tour 1;Jeunes et féminines;Grand Prix CD;;Talence;"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="monclubtt_saison_import">Saison</label></th>
                        <td>
                            <select id="monclubtt_saison_import" name="saison">
                                <?php foreach ($this->saisons_proposees() as $s) : ?>
                                    <option value="<?php echo esc_attr($s); ?>" <?php selected($s, $courante); ?>><?php echo esc_html($s . '–' . ($s + 1)); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <p class="description">Tous les événements du fichier sont rangés dans cette saison, y compris les stages de juillet et d'août.</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">Options</th>
                        <td>
                            <label><input type="checkbox" name="remplacer" value="1"> Mettre à la corbeille les événements importés de cette saison qui ne sont plus dans le fichier</label>
                            <p class="description">Les événements ajoutés à la main ne sont jamais touchés. Un événement déjà importé est reconnu par sa colonne <code>source</code> (sinon par sa date et son intitulé) et mis à jour.</p>
                        </td>
                    </tr>
                </table>
                <p class="submit">
                    <button type="submit" name="mode" value="importer" class="button button-primary">Importer</button>
                    <button type="submit" name="mode" value="verifier" class="button">Vérifier sans importer</button>
                </p>
            </form>

            <h2>Colonnes reconnues</h2>
            <p>Première ligne du fichier : <code>debut</code> et <code>titre</code> obligatoires ; <code>fin</code>, <code>categorie</code>, <code>serie</code>, <code>heure</code>, <code>heure fin</code>, <code>lieu</code>, <code>lien</code>, <code>note</code>, <code>source</code> facultatives (accents et majuscules indifférents). Dates au format <code>28/11/2026</code> ou <code>2026-11-28</code>, heures <code>19h30</code> ou <code>19:30</code>.</p>

            <h2>Agenda des adhérents</h2>
            <p>Adresse d'abonnement (Google Agenda, Apple Calendrier, Outlook), mise à jour automatiquement :<br><code><?php echo esc_html($flux); ?></code></p>
            <p>Shortcodes : <code>[monclubtt_calendrier]</code>, <code>[monclubtt_prochain serie="slug-de-la-serie"]</code>, <code>[monclubtt_dates serie="slug-de-la-serie"]</code> (voir le README).</p>
        </div>
        <?php
    }

    public function traiter_import()
    {
        if (!current_user_can('edit_posts')) {
            wp_die(esc_html__('Sorry, you are not allowed to do that.', 'mon-club-tt'), 403);
        }
        check_admin_referer('monclubtt_import_calendrier');

        $contenu = '';
        if (!empty($_FILES['fichier']['tmp_name']) && is_uploaded_file($_FILES['fichier']['tmp_name'])) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- fichier temporaire vérifié par is_uploaded_file().
            $contenu = (string) file_get_contents($_FILES['fichier']['tmp_name']); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents,WordPress.Security.ValidatedSanitizedInput
        }
        if (trim($contenu) === '' && isset($_POST['contenu'])) {
            $contenu = (string) wp_unslash($_POST['contenu']); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- analysé puis assaini champ par champ.
        }
        if (!mb_check_encoding($contenu, 'UTF-8')) {
            // Excel enregistre souvent les CSV en Windows-1252.
            $contenu = mb_convert_encoding($contenu, 'UTF-8', 'Windows-1252');
        }
        $saison     = isset($_POST['saison']) ? absint($_POST['saison']) : MonClubTT_Calendrier::saisonDe($this->aujourdhui());
        $simulation = isset($_POST['mode']) && 'verifier' === $_POST['mode'];
        $remplacer  = !empty($_POST['remplacer']);

        $analyse = MonClubTT_Calendrier::analyserImport($contenu);
        $bilan   = $this->importer($analyse['lignes'], $saison, $remplacer, $simulation);

        $titre = sprintf(
            '%s%d événement(s) lu(s) pour la saison %d–%d : %d créé(s), %d mis à jour%s.',
            $simulation ? 'Vérification : ' : '',
            count($analyse['lignes']),
            $saison,
            $saison + 1,
            $bilan['crees'],
            $bilan['maj'],
            $remplacer ? ', ' . $bilan['supprimes'] . ' mis à la corbeille' : ''
        );
        set_transient(self::TRANSIENT_IMPORT . get_current_user_id(), array(
            'titre'      => $titre,
            'erreurs'    => $analyse['erreurs'],
            'saison'     => $saison,
            'simulation' => $simulation,
        ), 5 * MINUTE_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=monclubtt_import_calendrier'));
        exit;
    }

    /**
     * Crée ou met à jour les événements importés d'une saison.
     *
     * @return array crees, maj, supprimes
     */
    private function importer(array $lignes, $saison, $remplacer, $simulation)
    {
        $existants = array();
        foreach (get_posts(array(
            'post_type'   => self::TYPE,
            'post_status' => array('publish', 'draft', 'pending', 'future', 'private'),
            'numberposts' => -1,
            'fields'      => 'ids',
            // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- import ponctuel, en admin.
            'meta_query'  => array(
                array('key' => self::META['saison'], 'value' => (int) $saison),
                array('key' => self::META['source'], 'compare' => 'EXISTS'),
            ),
        )) as $id) {
            $existants[(string) get_post_meta($id, self::META['source'], true)] = (int) $id;
        }

        $bilan = array('crees' => 0, 'maj' => 0, 'supprimes' => 0);
        $vus   = array();
        foreach ($lignes as $ligne) {
            $vus[$ligne['source']] = true;
            $id = $existants[$ligne['source']] ?? 0;
            $bilan[$id ? 'maj' : 'crees']++;
            if ($simulation) {
                continue;
            }
            $donnees = array(
                'post_type'   => self::TYPE,
                'post_title'  => sanitize_text_field($ligne['titre']),
                'post_status' => 'publish',
            );
            if ($id) {
                $donnees['ID'] = $id;
                wp_update_post(wp_slash($donnees));
            } else {
                $id = (int) wp_insert_post(wp_slash($donnees));
                if (!$id) {
                    continue;
                }
            }
            $meta = array(
                'debut'  => $ligne['debut'],
                'fin'    => $ligne['fin'],
                'saison' => (int) $saison,
                'source' => sanitize_text_field($ligne['source']),
            );
            // Infos complétées dans l'admin : gardées si le tableur ne les renseigne pas.
            foreach (array('heure', 'heureFin', 'lieu', 'note') as $champ) {
                if ($ligne[$champ] !== '') {
                    $meta[$champ] = sanitize_textarea_field($ligne[$champ]);
                }
            }
            if ($ligne['lien'] !== '') {
                $meta['lien'] = esc_url_raw($ligne['lien']);
            }
            $this->ecrire_meta($id, $meta);
            if ($ligne['categorie'] !== '') {
                wp_set_object_terms($id, sanitize_text_field($ligne['categorie']), self::CATEGORIE);
            }
            if ($ligne['serie'] !== '') {
                wp_set_object_terms($id, $this->terme_par_nom_ou_slug($ligne['serie'], self::SERIE), self::SERIE);
            }
        }

        if ($remplacer) {
            foreach ($existants as $source => $id) {
                if (!isset($vus[$source])) {
                    $bilan['supprimes']++;
                    if (!$simulation) {
                        wp_trash_post($id);
                    }
                }
            }
        }
        $this->evenements = null;
        return $bilan;
    }

    /** Série existante désignée par son slug ou son nom ; sinon le nom (création). */
    private function terme_par_nom_ou_slug($valeur, $taxonomie)
    {
        $valeur = sanitize_text_field($valeur);
        $terme  = get_term_by('slug', sanitize_title($valeur), $taxonomie);
        return $terme ? (int) $terme->term_id : $valeur;
    }

    /* ------------------------------------------------------------------
     * Shortcodes
     * ------------------------------------------------------------------ */

    private function charger_assets($script = false)
    {
        wp_enqueue_style('monclubtt-calendrier-css');
        if ($script) {
            wp_enqueue_script('monclubtt-calendrier-js');
        }
    }

    /** Statut d'un événement : passe, en-cours ou a-venir. */
    private function statut(array $e)
    {
        $aujourdhui = $this->aujourdhui();
        if ($e['fin'] < $aujourdhui) {
            return 'passe';
        }
        return $e['debut'] <= $aujourdhui ? 'en-cours' : 'a-venir';
    }

    /** Lieu sur plusieurs lignes, échappé. */
    private function lieu_html($lieu)
    {
        return nl2br(esc_html($lieu), false);
    }

    /**
     * [monclubtt_prochain serie="grand-prix"] : le prochain rendez-vous d'une
     * série (ou d'une catégorie), qui passe tout seul au suivant.
     *
     * Attributs : serie, categorie, libelle (%n rang, %t titre, %s série),
     * format (bloc | texte), champ (en texte : periode, libelle, titre, numero,
     * ordinal, heure, lieu, lien, note, debut, fin), annee (0 | 1), vide.
     */
    public function shortcode_prochain($atts)
    {
        $atts = shortcode_atts(array(
            'serie'     => '',
            'categorie' => '',
            'libelle'   => '',
            'format'    => 'bloc',
            'champ'     => 'periode',
            'annee'     => '0',
            'vide'      => 'Dates à venir',
        ), (array) $atts, 'monclubtt_prochain');

        $evenements = $this->selection(array('serie' => $atts['serie'], 'categorie' => $atts['categorie'], 'saison' => 'toutes'))['evenements'];
        $e          = MonClubTT_Calendrier::prochain($evenements, $this->aujourdhui());
        $annee      = $atts['annee'] === '1';

        if ($atts['format'] === 'texte') {
            if ($e === null) {
                return esc_html($atts['vide']);
            }
            switch ($atts['champ']) {
                case 'libelle':
                    return esc_html(MonClubTT_Calendrier::libelle($e, $atts['libelle']));
                case 'titre':
                    return esc_html($e['titre']);
                case 'numero':
                    return esc_html((string) $e['numero']);
                case 'ordinal':
                    return esc_html($e['numero'] === 1 ? '1er' : $e['numero'] . 'e');
                case 'heure':
                    return esc_html(MonClubTT_Calendrier::heureLisible($e['heure']));
                case 'lieu':
                    return $this->lieu_html($e['lieu']);
                case 'note':
                    return esc_html($e['note']);
                case 'lien':
                    return esc_url($e['lien']);
                case 'debut':
                case 'fin':
                    return esc_html(MonClubTT_Calendrier::periode($e[$atts['champ']], $e[$atts['champ']], $annee));
                default:
                    return esc_html(MonClubTT_Calendrier::periode($e['debut'], $e['fin'], $annee));
            }
        }

        $this->charger_assets();
        if ($e === null) {
            return '<div class="monclubtt-div monclubtt-prochain monclubtt-prochain--vide"><p>' . esc_html($atts['vide']) . '</p></div>';
        }
        $statut = $this->statut($e);
        ob_start();
        ?>
        <div class="monclubtt-div monclubtt-prochain">
            <p class="monclubtt-prochain__surtitre"><?php echo $statut === 'en-cours' ? 'En ce moment' : 'Prochain rendez-vous'; ?></p>
            <p class="monclubtt-prochain__titre"><?php echo esc_html(MonClubTT_Calendrier::libelle($e, $atts['libelle'])); ?></p>
            <p class="monclubtt-prochain__date">
                <?php echo esc_html(ucfirst(MonClubTT_Calendrier::periode($e['debut'], $e['fin'], $annee))); ?>
                <?php if ($e['heure'] !== '') : ?>
                    <span>· <?php echo esc_html(MonClubTT_Calendrier::heureLisible($e['heure'])); ?></span>
                <?php endif; ?>
            </p>
            <?php if ($e['lieu'] !== '') : ?>
                <p class="monclubtt-prochain__lieu"><?php echo $this->lieu_html($e['lieu']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par lieu_html(). ?></p>
            <?php endif; ?>
            <?php if ($e['note'] !== '') : ?>
                <p class="monclubtt-prochain__note"><?php echo esc_html($e['note']); ?></p>
            <?php endif; ?>
            <?php if ($e['lien'] !== '') : ?>
                <p class="monclubtt-prochain__lien"><a href="<?php echo esc_url($e['lien']); ?>" target="_blank" rel="noopener">En savoir plus →</a></p>
            <?php endif; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }

    /**
     * [monclubtt_dates serie="grand-prix"] : les dates de la saison, la
     * prochaine mise en avant et les passées grisées. Avec plusieurs séries
     * (serie="multisports,perfectionnement"), tableau par mois.
     *
     * Attributs : serie, categorie, saison, libelle, titres (en-têtes des
     * colonnes, séparés par des virgules), grouper (auto | liste | mois),
     * annee (0 | 1), passes (afficher | masquer), vide.
     */
    public function shortcode_dates($atts)
    {
        $atts = shortcode_atts(array(
            'serie'     => '',
            'categorie' => '',
            'saison'    => '',
            'libelle'   => '',
            'titres'    => '',
            'grouper'   => 'auto',
            'annee'     => '0',
            'passes'    => 'afficher',
            'vide'      => 'Les dates de la saison seront bientôt annoncées.',
        ), (array) $atts, 'monclubtt_dates');

        $this->charger_assets();
        $selection  = $this->selection($atts);
        $evenements = $selection['evenements'];
        // Prochain rendez-vous de chaque série (Multisports et Perfectionnement ont chacun le leur).
        $parSerie = array();
        foreach ($evenements as $e) {
            $parSerie[$e['serie'] !== '' ? $e['serie'] : '#'][] = $e;
        }
        $prochains = array();
        foreach ($parSerie as $groupe) {
            $prochain = MonClubTT_Calendrier::prochain($groupe, $this->aujourdhui());
            if ($prochain) {
                $prochains[] = $prochain['id'];
            }
        }
        $annee      = $atts['annee'] === '1';
        if ($atts['passes'] === 'masquer') {
            $evenements = array_values(array_filter($evenements, function ($e) {
                return $this->statut($e) !== 'passe';
            }));
        }
        if (!$evenements) {
            return '<div class="monclubtt-div monclubtt-dates monclubtt-dates--vide"><p>' . esc_html($atts['vide']) . '</p></div>';
        }

        $series = array_map('sanitize_title', $this->liste($atts['serie']));
        $mois   = $atts['grouper'] === 'mois' || ($atts['grouper'] === 'auto' && count($series) > 1);

        ob_start();
        if ($mois) {
            if (!$series) {
                $series = array_values(array_unique(array_filter(array_column($evenements, 'serie'), 'strlen')));
            }
            $titres = $this->liste($atts['titres']);
            $noms   = array();
            foreach ($series as $i => $slug) {
                $terme      = get_term_by('slug', $slug, self::SERIE);
                $noms[$slug] = $titres[$i] ?? ($terme ? $terme->name : $slug);
            }
            ?>
            <div class="monclubtt-div monclubtt-dates monclubtt-dates--mois">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Période</th>
                            <?php foreach ($noms as $nom) : ?>
                                <th scope="col"><?php echo esc_html($nom); ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach (MonClubTT_Calendrier::parMois($evenements, $series) as $ligne) :
                            $estProchaine = (bool) array_intersect($prochains, array_column(array_merge(...array_values($ligne['cellules'])), 'id'));
                            ?>
                            <tr class="<?php echo $estProchaine ? 'is-prochain' : ''; ?>">
                                <th scope="row"><?php echo esc_html($ligne['nom']); ?></th>
                                <?php foreach ($ligne['cellules'] as $cellule) : ?>
                                    <td>
                                        <?php if (!$cellule) : ?>
                                            <span aria-label="Aucun">—</span>
                                        <?php endif; ?>
                                        <?php foreach ($cellule as $e) : ?>
                                            <span class="monclubtt-dates__periode is-<?php echo esc_attr($this->statut($e)); ?><?php echo in_array($e['id'], $prochains, true) ? ' is-prochain' : ''; ?>">
                                                <?php echo esc_html(MonClubTT_Calendrier::periode($e['debut'], $e['fin'], $annee)); ?>
                                                <?php if (in_array($e['id'], $prochains, true)) : ?>
                                                    <em class="monclubtt-dates__badge"><?php echo $this->statut($e) === 'en-cours' ? 'En cours' : 'Prochain'; ?></em>
                                                <?php endif; ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php
        } else {
            ?>
            <div class="monclubtt-div monclubtt-dates monclubtt-dates--liste">
                <ol>
                    <?php foreach ($evenements as $e) :
                        $statut = $this->statut($e);
                        ?>
                        <li class="is-<?php echo esc_attr($statut); ?><?php echo in_array($e['id'], $prochains, true) ? ' is-prochain' : ''; ?>">
                            <span class="monclubtt-dates__titre">
                                <?php echo esc_html(MonClubTT_Calendrier::libelle($e, $atts['libelle'])); ?>
                                <?php if (in_array($e['id'], $prochains, true)) : ?>
                                    <em class="monclubtt-dates__badge"><?php echo $statut === 'en-cours' ? 'En cours' : 'Prochain'; ?></em>
                                <?php elseif ($statut === 'passe') : ?>
                                    <em class="monclubtt-dates__badge monclubtt-dates__badge--passe">Terminé</em>
                                <?php endif; ?>
                            </span>
                            <span class="monclubtt-dates__date">
                                <?php echo esc_html(ucfirst(MonClubTT_Calendrier::periode($e['debut'], $e['fin'], $annee))); ?>
                                <?php echo $e['heure'] !== '' ? esc_html(' · ' . MonClubTT_Calendrier::heureLisible($e['heure'])) : ''; ?>
                            </span>
                            <?php if ($e['lieu'] !== '') : ?>
                                <span class="monclubtt-dates__lieu"><?php echo $this->lieu_html($e['lieu']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé par lieu_html(). ?></span>
                            <?php endif; ?>
                            <?php if ($e['lien'] !== '' && $statut !== 'passe') : ?>
                                <a class="monclubtt-dates__lien" href="<?php echo esc_url($e['lien']); ?>" target="_blank" rel="noopener">En savoir plus →</a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </div>
            <?php
        }
        return (string) ob_get_clean();
    }

    /**
     * [monclubtt_calendrier] : agenda de la saison (mois ou liste, filtres par
     * catégorie, période, export et abonnement .ics).
     *
     * Attributs : saison (année, défaut : celle du prochain rendez-vous),
     * categorie (restreindre à certaines catégories), serie.
     */
    public function shortcode_calendrier($atts)
    {
        $atts      = shortcode_atts(array('saison' => '', 'categorie' => '', 'serie' => ''), (array) $atts, 'monclubtt_calendrier');
        $selection = $this->selection($atts);
        $saison    = $selection['saison'] ?? MonClubTT_Calendrier::saisonDe($this->aujourdhui());
        $this->charger_assets(true);
        $this->instances++;

        $evenements = array();
        foreach ($selection['evenements'] as $e) {
            $evenements[] = array(
                'id'        => $e['id'],
                'debut'     => $e['debut'],
                'fin'       => $e['fin'],
                'heure'     => $e['heure'],
                'heureFin'  => $e['heureFin'],
                'titre'     => $e['titre'],
                'categorie' => $e['categorie'],
                'serie'     => $e['serieNom'],
                'lieu'      => $e['lieu'],
                'lien'      => esc_url_raw($e['lien']),
                'note'      => $e['note'],
            );
        }
        $categories = MonClubTT_Calendrier::couleurs(array_column($evenements, 'categorie'));
        $debuts     = array_column($evenements, 'debut');
        $fins       = array_column($evenements, 'fin');
        $flux       = self::url_ics(array('categorie' => $atts['categorie'], 'serie' => $atts['serie']));

        $donnees = array(
            'evenements' => $evenements,
            'categories' => $categories,
            'saison'     => $saison,
            'premierMois' => $debuts ? substr(min($debuts), 0, 7) : substr($this->aujourdhui(), 0, 7),
            'dernierMois' => $fins ? substr(max($fins), 0, 7) : substr($this->aujourdhui(), 0, 7),
            'aujourdhui' => $this->aujourdhui(),
            'ics'        => array(
                'base'     => self::url_ics(array('telecharger' => '1', 'categorie' => $atts['categorie'], 'serie' => $atts['serie'])),
                'saison'   => self::url_ics(array('telecharger' => '1', 'saison' => (string) $saison, 'categorie' => $atts['categorie'], 'serie' => $atts['serie'])),
                'flux'     => $flux,
                'webcal'   => preg_replace('#^https?://#', 'webcal://', $flux),
                'google'   => 'https://calendar.google.com/calendar/render?cid=' . rawurlencode(preg_replace('#^https?://#', 'webcal://', $flux)),
            ),
        );
        $id = 'monclubtt-calendrier-' . $this->instances;

        ob_start();
        require __DIR__ . '/../views/front/calendrier.php';
        return (string) ob_get_clean();
    }
}

}
