<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_ComiteWP' ) ) {

/**
 * Le comité directeur du club dans WordPress : type de contenu « Membre »
 * (photo, prénom, nom, poste au bureau, commissions), saisie dans l'admin et
 * shortcode [monclubtt_comite] : le bureau, puis les autres membres avec
 * leurs commissions.
 *
 * Les règles (répartition, tri, libellés) sont dans MonClubTT_Comite ; cette
 * classe ne fait que le lien avec WordPress.
 */
class MonClubTT_ComiteWP {

    const TYPE       = 'monclubtt_membre';
    const COMMISSION = 'monclubtt_commission';

    /** Champ du membre => clé de métadonnée. */
    const META = array(
        'prenom'      => '_monclubtt_prenom',
        'nom'         => '_monclubtt_nom',
        'photo'       => '_monclubtt_photo',
        'posteBureau' => '_monclubtt_poste_bureau',
    );

    /** Ordre d'affichage d'une commission dans les pastilles (métadonnée de terme). */
    const META_ORDRE = '_monclubtt_ordre';

    /** Postes du bureau proposés à la saisie (le champ reste libre). */
    const POSTES_SUGGERES = array(
        'Président', 'Présidente', 'Vice-président', 'Vice-présidente',
        'Secrétaire', 'Secrétaire adjoint', 'Secrétaire adjointe',
        'Trésorier', 'Trésorière', 'Trésorier adjoint', 'Trésorière adjointe',
    );

    /** @var string Plugin principal (pour plugins_url). */
    private $fichierPlugin;

    /** @var array|null Membres publiés, chargés une fois par requête. */
    private $membres = null;

    public function __construct($fichierPlugin)
    {
        $this->fichierPlugin = $fichierPlugin;

        add_action('init', array($this, 'enregistrer_types'), 5);
        add_action('init', array($this, 'enregistrer_assets'));

        add_action('admin_menu', array($this, 'menus'), 25);
        add_filter('parent_file', array($this, 'menu_parent'));
        add_filter('submenu_file', array($this, 'sous_menu_actif'));
        add_action('add_meta_boxes_' . self::TYPE, array($this, 'meta_boxes'));
        add_action('admin_enqueue_scripts', array($this, 'assets_admin'));
        add_filter('wp_insert_post_data', array($this, 'titre_depuis_nom'), 10, 2);
        add_action('save_post_' . self::TYPE, array($this, 'enregistrer_meta'), 10, 2);
        add_filter('manage_' . self::TYPE . '_posts_columns', array($this, 'colonnes'));
        add_action('manage_' . self::TYPE . '_posts_custom_column', array($this, 'colonne'), 10, 2);
        add_filter('manage_edit-' . self::TYPE . '_sortable_columns', array($this, 'colonnes_triables'));
        add_action('pre_get_posts', array($this, 'tri_admin'));
        add_filter('post_updated_messages', array($this, 'messages'));

        // Commissions : ordre d'affichage.
        add_action(self::COMMISSION . '_add_form_fields', array($this, 'champ_ordre_ajout'));
        add_action(self::COMMISSION . '_edit_form_fields', array($this, 'champ_ordre_modif'));
        add_action('created_' . self::COMMISSION, array($this, 'enregistrer_ordre'));
        add_action('edited_' . self::COMMISSION, array($this, 'enregistrer_ordre'));
        add_filter('manage_edit-' . self::COMMISSION . '_columns', array($this, 'colonnes_commission'));
        add_filter('manage_' . self::COMMISSION . '_custom_column', array($this, 'colonne_commission'), 10, 3);
        add_filter('get_terms_args', array($this, 'tri_commissions_admin'), 10, 2);

        add_shortcode('monclubtt_comite', array($this, 'shortcode_comite'));

        // Comité exposé aux autres plugins et au thème : apply_filters('monclubtt_get_comite', array(), 'slug1,slug2').
        add_filter('monclubtt_get_comite', array($this, 'filtre_comite'), 10, 2);
    }

    /* ------------------------------------------------------------------
     * Types de contenu
     * ------------------------------------------------------------------ */

    public function enregistrer_types()
    {
        register_post_type(self::TYPE, array(
            'labels' => array(
                'name'               => 'Membres du comité directeur',
                'singular_name'      => 'Membre',
                'menu_name'          => 'Comité directeur',
                'all_items'          => 'Comité directeur',
                'add_new'            => 'Ajouter',
                'add_new_item'       => 'Ajouter un membre',
                'edit_item'          => 'Modifier le membre',
                'new_item'           => 'Nouveau membre',
                'search_items'       => 'Rechercher un membre',
                'not_found'          => 'Aucun membre pour l\'instant.',
                'not_found_in_trash' => 'Aucun membre dans la corbeille.',
                'item_updated'       => 'Membre enregistré.',
                'item_published'     => 'Membre enregistré.',
            ),
            'public'          => false,
            'show_ui'         => true,
            // Sous-menu ajouté à la main dans menus(), comme le calendrier.
            'show_in_menu'    => false,
            'show_in_rest'    => false,
            'supports'        => false,
            'capability_type' => 'post',
            'map_meta_cap'    => true,
            'rewrite'         => false,
            'query_var'       => false,
        ));

        register_taxonomy(self::COMMISSION, self::TYPE, array(
            'labels' => array(
                'name'              => 'Commissions',
                'singular_name'     => 'Commission',
                'search_items'      => 'Rechercher une commission',
                'all_items'         => 'Toutes les commissions',
                'edit_item'         => 'Modifier la commission',
                'add_new_item'      => 'Ajouter une commission',
                'new_item_name'     => 'Nom de la commission',
                'not_found'         => 'Aucune commission.',
                'back_to_items'     => '← Retour aux commissions',
                'parent_item'       => 'Commission parente',
                'parent_item_colon' => 'Commission parente :',
            ),
            'description'        => 'Commissions du comité (Communication, Matériel, Équipes…), affichées en pastille sous les autres membres : « Commission Communication / Équipes ». '
                . 'Le slug sert dans le shortcode : [monclubtt_comite commission="slug"].',
            'public'             => false,
            'show_ui'            => true,
            'show_in_menu'       => false,
            'show_admin_column'  => true,
            'show_in_quick_edit' => true,
            'show_in_rest'       => false,
            // Hiérarchique pour la boîte à cases à cocher dans la fiche du membre.
            'hierarchical'       => true,
            'rewrite'            => false,
            'query_var'          => false,
        ));
    }

    public function enregistrer_assets()
    {
        $dir = plugin_dir_path($this->fichierPlugin);
        wp_register_style('monclubtt-comite-css', plugins_url('/assets/mon-club-tt-comite.css', $this->fichierPlugin), array('mon-club-tt-css'), (string) filemtime($dir . 'assets/mon-club-tt-comite.css'));
    }

    /* ------------------------------------------------------------------
     * Lecture
     * ------------------------------------------------------------------ */

    /**
     * Membres publiés, normalisés (voir MonClubTT_Comite).
     *
     * @return array
     */
    public function membres()
    {
        if ($this->membres !== null) {
            return $this->membres;
        }
        $posts = get_posts(array(
            'post_type'        => self::TYPE,
            'post_status'      => 'publish',
            'numberposts'      => -1,
            'suppress_filters' => false,
        ));
        $this->membres = array();
        foreach ($posts as $post) {
            $this->membres[] = $this->normaliser($post);
        }
        return $this->membres;
    }

    /** @param WP_Post $post */
    private function normaliser($post)
    {
        $photo       = (int) get_post_meta($post->ID, self::META['photo'], true);
        $termes      = get_the_terms($post->ID, self::COMMISSION);
        $commissions = array();
        foreach (is_array($termes) ? $termes : array() as $terme) {
            $commissions[] = array(
                'id'    => (int) $terme->term_id,
                'nom'   => wp_specialchars_decode($terme->name, ENT_QUOTES),
                'slug'  => $terme->slug,
                'ordre' => (int) get_term_meta($terme->term_id, self::META_ORDRE, true),
            );
        }
        return array(
            'id'          => (int) $post->ID,
            'prenom'      => (string) get_post_meta($post->ID, self::META['prenom'], true),
            'nom'         => (string) get_post_meta($post->ID, self::META['nom'], true),
            'ordre'       => (int) $post->menu_order,
            'photoId'     => $photo,
            'photo'       => $photo ? (string) wp_get_attachment_image_url($photo, 'thumbnail') : '',
            'posteBureau' => (string) get_post_meta($post->ID, self::META['posteBureau'], true),
            'commissions' => $commissions,
        );
    }

    /** Filtre monclubtt_get_comite : [ 'bureau' => membres, 'comite' => membres ] (voir MonClubTT_Comite::repartir). */
    public function filtre_comite($defaut = array(), $commissions = '')
    {
        return MonClubTT_Comite::repartir($this->membres(), $this->liste($commissions));
    }

    /** Liste « a, b, c » en tableau. */
    private function liste($valeur)
    {
        return array_values(array_filter(array_map('trim', explode(',', (string) $valeur)), 'strlen'));
    }

    /* ------------------------------------------------------------------
     * Admin : menus
     * ------------------------------------------------------------------ */

    public function menus()
    {
        $type = self::TYPE;
        add_submenu_page(MonClubTT_Constantes::MENU_PARENT, 'Comité directeur', 'Comité directeur', 'edit_posts', 'edit.php?post_type=' . $type);
        add_submenu_page(MonClubTT_Constantes::MENU_PARENT, 'Ajouter un membre', '— Ajouter', 'edit_posts', 'post-new.php?post_type=' . $type);
        add_submenu_page(MonClubTT_Constantes::MENU_PARENT, 'Commissions', '— Commissions', 'manage_categories', 'edit-tags.php?taxonomy=' . self::COMMISSION . '&post_type=' . $type);
    }

    /** Garde le menu « Mon Club TT » ouvert sur les écrans du comité directeur. */
    public function menu_parent($parent)
    {
        $ecran = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($ecran && $ecran->post_type === self::TYPE) {
            return MonClubTT_Constantes::MENU_PARENT;
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
            return 'edit-tags.php?taxonomy=' . self::COMMISSION . '&post_type=' . self::TYPE;
        }
        if ($ecran->base === 'post' && $ecran->action === 'add') {
            return 'post-new.php?post_type=' . self::TYPE;
        }
        return 'edit.php?post_type=' . self::TYPE;
    }

    public function messages($messages)
    {
        $messages[self::TYPE] = array_fill(1, 10, 'Membre enregistré.');
        $messages[self::TYPE][2] = $messages[self::TYPE][3] = 'Champ mis à jour.';
        return $messages;
    }

    /* ------------------------------------------------------------------
     * Admin : fiche d'un membre
     * ------------------------------------------------------------------ */

    /** Médiathèque sur la fiche d'un membre (choix de la photo). */
    public function assets_admin($hook)
    {
        $ecran = function_exists('get_current_screen') ? get_current_screen() : null;
        if (($hook === 'post.php' || $hook === 'post-new.php') && $ecran && $ecran->post_type === self::TYPE) {
            wp_enqueue_media();
        }
    }

    public function meta_boxes()
    {
        add_meta_box('monclubtt_membre', 'Membre', array($this, 'boite_membre'), self::TYPE, 'normal', 'high');
    }

    public function boite_membre($post)
    {
        wp_nonce_field('monclubtt_membre', 'monclubtt_membre_nonce');
        $prenom = (string) get_post_meta($post->ID, self::META['prenom'], true);
        $nom    = (string) get_post_meta($post->ID, self::META['nom'], true);
        $poste  = (string) get_post_meta($post->ID, self::META['posteBureau'], true);
        $photo  = (int) get_post_meta($post->ID, self::META['photo'], true);
        $apercu = $photo ? wp_get_attachment_image_url($photo, 'thumbnail') : '';
        ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="monclubtt_prenom">Prénom</label></th>
                <td><input type="text" id="monclubtt_prenom" name="monclubtt_membre[prenom]" value="<?php echo esc_attr($prenom); ?>" class="regular-text" autocomplete="off" required></td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_nom">Nom</label></th>
                <td><input type="text" id="monclubtt_nom" name="monclubtt_membre[nom]" value="<?php echo esc_attr($nom); ?>" class="regular-text" autocomplete="off"></td>
            </tr>
            <tr>
                <th scope="row">Photo</th>
                <td>
                    <div class="monclubtt-membre-photo" style="display:flex;align-items:center;gap:12px">
                        <input type="hidden" name="monclubtt_membre[photo]" value="<?php echo esc_attr($photo ? (string) $photo : ''); ?>">
                        <span class="monclubtt-membre-apercu" style="display:inline-block;width:72px;height:72px;border-radius:50%;overflow:hidden;background:#f0f0f1;flex:none"><?php if ($apercu) : ?><img src="<?php echo esc_url($apercu); ?>" alt="" style="width:72px;height:72px;object-fit:cover"><?php endif; ?></span>
                        <button type="button" class="button monclubtt-membre-choisir"><?php echo $photo ? 'Changer' : 'Choisir une photo'; ?></button>
                        <button type="button" class="button-link monclubtt-membre-retirer"<?php echo $photo ? '' : ' style="display:none"'; ?>>Retirer</button>
                    </div>
                    <p class="description">Portrait recadré en rond sur le site. Sans photo, les initiales s'affichent à la place.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_poste_bureau">Poste au bureau</label></th>
                <td>
                    <input type="text" id="monclubtt_poste_bureau" name="monclubtt_membre[posteBureau]" value="<?php echo esc_attr($poste); ?>" class="regular-text" list="monclubtt-postes-bureau" autocomplete="off" placeholder="Président, Trésorière…">
                    <datalist id="monclubtt-postes-bureau">
                        <?php foreach (self::POSTES_SUGGERES as $suggestion) : ?>
                            <option value="<?php echo esc_attr($suggestion); ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                    <p class="description">Rempli : le membre apparaît dans <strong>le bureau</strong>, avec ce poste. Vide : il apparaît dans <strong>les autres membres</strong>, avec ses commissions (cases à cocher à droite).</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="monclubtt_ordre">Ordre</label></th>
                <td>
                    <input type="number" id="monclubtt_ordre" name="monclubtt_membre[ordre]" value="<?php echo esc_attr((string) (int) $post->menu_order); ?>" class="small-text" step="1">
                    <p class="description">Du plus petit au plus grand (ex. 1 pour le président, 2 pour la vice-présidente…), puis par nom.</p>
                </td>
            </tr>
        </table>
        <script>
            jQuery(function ($) {
                var $bloc = $('.monclubtt-membre-photo'), frame;
                $bloc.on('click', '.monclubtt-membre-choisir', function () {
                    if (!frame) {
                        frame = wp.media({ title: 'Photo du membre', button: { text: 'Utiliser cette photo' }, library: { type: 'image' }, multiple: false });
                        frame.on('select', function () {
                            var att = frame.state().get('selection').first().toJSON();
                            var url = att.sizes && att.sizes.thumbnail ? att.sizes.thumbnail.url : att.url;
                            $bloc.find('input[type=hidden]').val(att.id);
                            $bloc.find('.monclubtt-membre-apercu').html($('<img>', { src: url, alt: '', css: { width: 72, height: 72, objectFit: 'cover' } }));
                            $bloc.find('.monclubtt-membre-choisir').text('Changer');
                            $bloc.find('.monclubtt-membre-retirer').show();
                        });
                    }
                    frame.open();
                }).on('click', '.monclubtt-membre-retirer', function () {
                    $bloc.find('input[type=hidden]').val('');
                    $bloc.find('.monclubtt-membre-apercu').empty();
                    $bloc.find('.monclubtt-membre-choisir').text('Choisir une photo');
                    $(this).hide();
                });
            });
        </script>
        <?php
    }

    /** Titre du membre (liste de l'admin, recherche) : « Prénom Nom » ; ordre dans menu_order. */
    public function titre_depuis_nom($data, $postarr)
    {
        if (($data['post_type'] ?? '') !== self::TYPE
            || !isset($_POST['monclubtt_membre_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['monclubtt_membre_nonce'])), 'monclubtt_membre')) {
            return $data;
        }
        $saisie = isset($_POST['monclubtt_membre']) && is_array($_POST['monclubtt_membre']) ? wp_unslash($_POST['monclubtt_membre']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est assaini ci-dessous.
        $titre  = MonClubTT_Comite::nomComplet(sanitize_text_field($saisie['prenom'] ?? ''), sanitize_text_field($saisie['nom'] ?? ''));
        if ($titre !== '') {
            $data['post_title'] = $titre;
            $data['post_name']  = '';
        }
        $data['menu_order'] = isset($saisie['ordre']) ? (int) $saisie['ordre'] : 0;
        return $data;
    }

    public function enregistrer_meta($postId, $post)
    {
        if (!isset($_POST['monclubtt_membre_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['monclubtt_membre_nonce'])), 'monclubtt_membre')
            || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
            || !current_user_can('edit_post', $postId)) {
            return;
        }
        $saisie = isset($_POST['monclubtt_membre']) && is_array($_POST['monclubtt_membre']) ? wp_unslash($_POST['monclubtt_membre']) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque champ est assaini ci-dessous.

        $photo = absint($saisie['photo'] ?? 0);
        if ($photo && !wp_attachment_is_image($photo)) {
            $photo = 0;
        }
        $valeurs = array(
            'prenom'      => sanitize_text_field($saisie['prenom'] ?? ''),
            'nom'         => sanitize_text_field($saisie['nom'] ?? ''),
            'photo'       => $photo ?: '',
            'posteBureau' => MonClubTT_Comite::poste(sanitize_text_field($saisie['posteBureau'] ?? '')),
        );
        foreach ($valeurs as $champ => $valeur) {
            if ($valeur === '') {
                delete_post_meta($postId, self::META[$champ]);
            } else {
                update_post_meta($postId, self::META[$champ], $valeur);
            }
        }
        $this->membres = null;
    }

    /* ------------------------------------------------------------------
     * Admin : liste des membres
     * ------------------------------------------------------------------ */

    public function colonnes($colonnes)
    {
        $nouvelles = array();
        foreach ($colonnes as $cle => $libelle) {
            if ($cle === 'date') {
                continue;
            }
            if ($cle === 'title') {
                $nouvelles['monclubtt_photo'] = '<span class="screen-reader-text">Photo</span>';
                $nouvelles[$cle]              = 'Nom';
                $nouvelles['monclubtt_poste'] = 'Poste au bureau';
                continue;
            }
            $nouvelles[$cle] = $libelle;
        }
        $nouvelles['monclubtt_ordre'] = 'Ordre';
        return $nouvelles;
    }

    public function colonne($colonne, $postId)
    {
        if ($colonne === 'monclubtt_photo') {
            $photo = (int) get_post_meta($postId, self::META['photo'], true);
            $url   = $photo ? wp_get_attachment_image_url($photo, 'thumbnail') : '';
            if ($url) {
                printf('<img src="%s" alt="" style="width:40px;height:40px;border-radius:50%%;object-fit:cover;display:block">', esc_url($url));
            } else {
                printf(
                    '<span aria-hidden="true" style="display:flex;align-items:center;justify-content:center;width:40px;height:40px;border-radius:50%%;background:#f0f0f1;color:#50575e;font-weight:600">%s</span>',
                    esc_html(MonClubTT_Comite::initiales(get_post_meta($postId, self::META['prenom'], true), get_post_meta($postId, self::META['nom'], true)))
                );
            }
        } elseif ($colonne === 'monclubtt_poste') {
            $poste = (string) get_post_meta($postId, self::META['posteBureau'], true);
            echo $poste !== '' ? esc_html($poste) : '—';
        } elseif ($colonne === 'monclubtt_ordre') {
            echo esc_html((string) (int) get_post_field('menu_order', $postId));
        }
    }

    public function colonnes_triables($colonnes)
    {
        $colonnes['monclubtt_ordre'] = 'menu_order';
        return $colonnes;
    }

    /** Liste de l'admin : par ordre puis par nom. */
    public function tri_admin($requete)
    {
        if (!is_admin() || !$requete->is_main_query() || $requete->get('post_type') !== self::TYPE) {
            return;
        }
        if ($requete->get('orderby') === '') {
            $requete->set('orderby', array('menu_order' => 'ASC', 'title' => 'ASC'));
        }
    }

    /* ------------------------------------------------------------------
     * Admin : commissions
     * ------------------------------------------------------------------ */

    public function champ_ordre_ajout()
    {
        ?>
        <div class="form-field">
            <label for="monclubtt_ordre_commission">Ordre</label>
            <input type="number" id="monclubtt_ordre_commission" name="monclubtt_ordre_commission" value="<?php echo esc_attr((string) ($this->ordre_max() + 1)); ?>" step="1" style="width:6em">
            <?php wp_nonce_field('monclubtt_commission', 'monclubtt_commission_nonce'); ?>
            <p>Place dans la pastille d'un membre de plusieurs commissions : du plus petit au plus grand.</p>
        </div>
        <?php
    }

    public function champ_ordre_modif($terme)
    {
        ?>
        <tr class="form-field">
            <th scope="row"><label for="monclubtt_ordre_commission">Ordre</label></th>
            <td>
                <input type="number" id="monclubtt_ordre_commission" name="monclubtt_ordre_commission" value="<?php echo esc_attr((string) (int) get_term_meta($terme->term_id, self::META_ORDRE, true)); ?>" step="1" style="width:6em">
                <?php wp_nonce_field('monclubtt_commission', 'monclubtt_commission_nonce'); ?>
                <p class="description">Place dans la pastille d'un membre de plusieurs commissions : du plus petit au plus grand.</p>
            </td>
        </tr>
        <?php
    }

    public function enregistrer_ordre($termId)
    {
        if (isset($_POST['monclubtt_commission_nonce'], $_POST['monclubtt_ordre_commission'])
            && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['monclubtt_commission_nonce'])), 'monclubtt_commission')
            && current_user_can('manage_categories')) {
            update_term_meta($termId, self::META_ORDRE, (int) $_POST['monclubtt_ordre_commission']);
        } elseif (get_term_meta($termId, self::META_ORDRE, true) === '') {
            // Toujours un ordre (commission créée depuis la fiche d'un membre) : la liste trie dessus.
            update_term_meta($termId, self::META_ORDRE, $this->ordre_max() + 1);
        }
    }

    private function ordre_max()
    {
        $max    = 0;
        $termes = get_terms(array('taxonomy' => self::COMMISSION, 'hide_empty' => false, 'fields' => 'ids'));
        foreach (is_array($termes) ? $termes : array() as $id) {
            $max = max($max, (int) get_term_meta($id, self::META_ORDRE, true));
        }
        return $max;
    }

    public function colonnes_commission($colonnes)
    {
        $nouvelles = array();
        foreach ($colonnes as $cle => $libelle) {
            $nouvelles[$cle] = $libelle;
            if ($cle === 'name') {
                $nouvelles['monclubtt_ordre'] = 'Ordre';
            }
        }
        unset($nouvelles['description']);
        $nouvelles['posts'] = 'Membres';
        return $nouvelles;
    }

    public function colonne_commission($contenu, $colonne, $termId)
    {
        if ($colonne === 'monclubtt_ordre') {
            return esc_html((string) (int) get_term_meta($termId, self::META_ORDRE, true));
        }
        return $contenu;
    }

    /** Liste des commissions dans l'admin : dans l'ordre d'affichage. */
    public function tri_commissions_admin($args, $taxonomies)
    {
        if (!is_admin() || !in_array(self::COMMISSION, (array) $taxonomies, true)) {
            return $args;
        }
        $ecran = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($ecran && $ecran->base === 'edit-tags' && !isset($_GET['orderby'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple tri d'affichage.
            $args['meta_key'] = self::META_ORDRE; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- quelques termes.
            $args['orderby']  = 'meta_value_num';
            $args['order']    = 'ASC';
        }
        return $args;
    }

    /* ------------------------------------------------------------------
     * Shortcode
     * ------------------------------------------------------------------ */

    /**
     * [monclubtt_comite] : le bureau (poste de chacun), puis les autres membres
     * du comité (leurs commissions), en cartes avec photo.
     *
     * Attributs : partie (tout | bureau | comite), commission (slugs : ne
     * garder que leurs membres), surtitre_bureau, titre_bureau,
     * surtitre_comite, titre_comite (vide = masqué), niveau (2 à 5 : titres,
     * les noms un niveau en dessous), photos (1 | 0), classe, vide.
     */
    public function shortcode_comite($atts)
    {
        $atts = shortcode_atts(array(
            'partie'          => 'tout',
            'commission'      => '',
            'surtitre_bureau' => 'Le bureau',
            'titre_bureau'    => 'Qui fait quoi ?',
            'surtitre_comite' => 'Le comité',
            'titre_comite'    => 'Les autres membres',
            'niveau'          => '2',
            'photos'          => '1',
            'classe'          => '',
            'vide'            => '',
        ), (array) $atts, 'monclubtt_comite');

        $parties = MonClubTT_Comite::repartir($this->membres(), $this->liste($atts['commission']));
        if ($atts['partie'] === 'bureau' || $atts['partie'] === 'comite') {
            $parties = array($atts['partie'] => $parties[$atts['partie']]);
        }
        $parties = array_filter($parties);
        if (!$parties) {
            return $atts['vide'] !== '' ? '<p class="monclubtt-comite-vide">' . esc_html($atts['vide']) . '</p>' : '';
        }

        wp_enqueue_style('monclubtt-comite-css');
        $niveau  = min(5, max(2, (int) $atts['niveau']));
        $photos  = $atts['photos'] !== '0';
        $classes = trim('monclubtt-div monclubtt-comite ' . implode(' ', array_map('sanitize_html_class', preg_split('/\s+/', (string) $atts['classe']))));

        ob_start();
        ?>
        <div class="<?php echo esc_attr($classes); ?>">
            <?php foreach ($parties as $partie => $membres) :
                $surtitre  = trim((string) $atts['surtitre_' . $partie]);
                $titre     = trim((string) $atts['titre_' . $partie]);
                $avecTitre = $titre !== '' || $surtitre !== '';
                $niveauNom = $avecTitre ? $niveau + 1 : $niveau;
                $idTitre   = 'monclubtt-comite-' . $partie; ?>
                <section class="monclubtt-comite__partie monclubtt-comite__partie--<?php echo esc_attr($partie); ?>"<?php echo $avecTitre ? ' aria-labelledby="' . esc_attr($idTitre) . '"' : ''; ?>>
                    <?php if ($avecTitre) : ?>
                        <header class="monclubtt-comite__entete">
                            <?php if ($surtitre !== '' && $titre !== '') : ?>
                                <p class="monclubtt-comite__surtitre"><?php echo esc_html($surtitre); ?></p>
                            <?php endif; ?>
                            <h<?php echo (int) $niveau; ?> class="monclubtt-comite__titre" id="<?php echo esc_attr($idTitre); ?>"><?php echo esc_html($titre !== '' ? $titre : $surtitre); ?></h<?php echo (int) $niveau; ?>>
                        </header>
                    <?php endif; ?>
                    <ul class="monclubtt-comite__membres" role="list">
                        <?php foreach ($membres as $m) :
                            $nom      = MonClubTT_Comite::nomComplet($m['prenom'], $m['nom']);
                            $pastille = $partie === 'bureau' ? $m['posteBureau'] : $m['libelleCommissions']; ?>
                            <li class="monclubtt-comite__membre">
                                <h<?php echo (int) $niveauNom; ?> class="monclubtt-comite__nom"><?php echo esc_html($nom); ?></h<?php echo (int) $niveauNom; ?>>
                                <?php if ($photos) : ?>
                                    <?php if ($m['photoId']) : ?>
                                        <?php echo wp_get_attachment_image($m['photoId'], 'thumbnail', false, array('class' => 'monclubtt-comite__photo', 'alt' => '', 'loading' => 'lazy')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- balise générée par WordPress. ?>
                                    <?php else : ?>
                                        <span class="monclubtt-comite__photo monclubtt-comite__photo--initiales" aria-hidden="true"><?php echo esc_html(MonClubTT_Comite::initiales($m['prenom'], $m['nom'])); ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                                <?php if ($pastille !== '') : ?>
                                    <p class="monclubtt-comite__poste"><?php echo esc_html($pastille); ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </section>
            <?php endforeach; ?>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}

}
