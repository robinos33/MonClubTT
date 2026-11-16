<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_ArticleSocialWP' ) ) {

/**
 * « Créer un article » depuis la page « Réseaux sociaux » : le visuel (JPEG
 * exporté du canvas) va dans la médiathèque et devient l'image à la une d'un
 * brouillon dont le contenu reprend la légende et les données du visuel.
 * Rien n'est publié : l'admin relit et publie depuis l'éditeur.
 *
 * Les règles (titre, contenu, texte alternatif, doublons) sont dans
 * MonClubTT_ArticleSocial ; cette classe ne fait que le lien avec WordPress.
 */
class MonClubTT_ArticleSocialWP {

    const ACTION = 'monclubtt_creer_article';
    const NONCE  = 'monclubtt_creer_article';

    /** Métadonnées de l'article. */
    const META_VISUEL  = '_monclubtt_social_visuel';
    const META_PERIODE = '_monclubtt_social_periode';
    const META_CLE     = '_monclubtt_social_cle';

    /** Réglages (page « Réseaux sociaux », formulaire « Articles »). */
    const GROUPE          = 'monclubtt_articles_settings';
    const OPT_LIEN_PAGE   = 'monclubtt_article_lien_page';
    const OPT_LIEN_TEXTE  = 'monclubtt_article_lien_texte';
    const OPT_CATEGORIES  = 'monclubtt_article_categories';

    const LIEN_TEXTE_DEFAUT = 'Envie de jouer avec nous ? Venez faire une séance d\'essai !';

    /** Lignes de données reprises au plus (le visuel en affiche bien moins). */
    const MAX_LIGNES = 60;

    public function __construct()
    {
        add_action('wp_ajax_' . self::ACTION, array($this, 'creer'));
        add_action('admin_init', array($this, 'reglages'));
        add_filter('render_block_core/post-featured-image', array($this, 'masquer_une_bloc'), 10, 3);
        add_filter('post_thumbnail_html', array($this, 'masquer_une_classique'), 10, 3);
    }

    /* ------------------------------------------- image à la une sur l'article */

    /**
     * Sur la page de l'article, l'image à la une est masquée quand le même
     * visuel est déjà en entier dans le contenu : les thèmes la recadrent
     * souvent en paysage, ce qui coupe le visuel 4:5. Elle reste affichée
     * dans les listes d'articles, l'accueil et les aperçus de partage.
     *
     * @param int $postId
     * @return bool
     */
    public static function visuelDansContenu($postId)
    {
        $postId = (int) $postId;
        if (!$postId || !is_singular('post') || $postId !== (int) get_queried_object_id() || !get_post_meta($postId, self::META_VISUEL, true)) {
            return false;
        }
        $une = (int) get_post_thumbnail_id($postId);
        return $une && strpos((string) get_post_field('post_content', $postId), 'wp-image-' . $une . '"') !== false;
    }

    /** Thèmes à blocs : bloc « Image mise en avant » du modèle. */
    public function masquer_une_bloc($html, $block, $instance = null)
    {
        $postId = is_object($instance) && isset($instance->context['postId']) ? $instance->context['postId'] : get_the_ID();
        return self::visuelDansContenu($postId) ? '' : $html;
    }

    /** Thèmes classiques : the_post_thumbnail(). */
    public function masquer_une_classique($html, $postId, $thumbId)
    {
        return self::visuelDansContenu($postId) ? '' : $html;
    }

    /* ------------------------------------------------------------ réglages */

    public function reglages()
    {
        register_setting(self::GROUPE, self::OPT_LIEN_PAGE, array('sanitize_callback' => 'absint'));
        register_setting(self::GROUPE, self::OPT_LIEN_TEXTE, array('sanitize_callback' => 'sanitize_text_field'));
        register_setting(self::GROUPE, self::OPT_CATEGORIES, array('sanitize_callback' => array($this, 'sanitize_categories')));
        add_settings_section('monclubtt_section_articles', '', '__return_false', self::GROUPE);
        add_settings_field(self::OPT_LIEN_PAGE, 'Lien en fin d\'article', array($this, 'lien_html'), self::GROUPE, 'monclubtt_section_articles');
        add_settings_field(self::OPT_CATEGORIES, 'Catégorie par visuel', array($this, 'categories_html'), self::GROUPE, 'monclubtt_section_articles');
    }

    /**
     * @param mixed $valeur
     * @return array<string, int>
     */
    public function sanitize_categories($valeur)
    {
        $propre = array();
        foreach (array_keys(MonClubTT_ArticleSocial::VISUELS) as $visuel) {
            $id = is_array($valeur) ? absint($valeur[$visuel] ?? 0) : 0;
            if ($id && term_exists($id, 'category')) {
                $propre[$visuel] = $id;
            }
        }
        return $propre;
    }

    public function lien_html()
    {
        wp_dropdown_pages(array(
            'name'              => esc_attr(self::OPT_LIEN_PAGE),
            'id'                => 'monclubtt-article-lien-page',
            'selected'          => absint(get_option(self::OPT_LIEN_PAGE, 0)),
            'show_option_none'  => '— Aucun lien —',
            'option_none_value' => '0',
        ));
        ?>
        <p>
            <label for="monclubtt-article-lien-texte">Texte du lien</label><br>
            <input type="text" class="large-text" id="monclubtt-article-lien-texte" name="<?php echo esc_attr(self::OPT_LIEN_TEXTE); ?>"
                   value="<?php echo esc_attr(self::lienTexte()); ?>">
        </p>
        <p class="description">Par exemple la page Inscriptions ou Séance d'essai, ajoutée à la fin de chaque article créé depuis un visuel.</p>
        <?php
    }

    public function categories_html()
    {
        $categories = self::categories();
        echo '<table class="monclubtt-article-categories"><tbody>';
        foreach (MonClubTT_ArticleSocial::VISUELS as $visuel => $libelle) {
            $id = 'monclubtt-article-cat-' . $visuel;
            echo '<tr><th scope="row"><label for="' . esc_attr($id) . '">' . esc_html($libelle) . '</label></th><td>';
            wp_dropdown_categories(array(
                'name'              => esc_attr(self::OPT_CATEGORIES . '[' . $visuel . ']'),
                'id'                => esc_attr($id),
                'selected'          => $categories[$visuel] ?? 0,
                'hide_empty'        => 0,
                'hierarchical'      => 1,
                'show_option_none'  => '— Catégorie par défaut —',
                'option_none_value' => '0',
            ));
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    /** @return array<string, int> */
    public static function categories()
    {
        $categories = get_option(self::OPT_CATEGORIES, array());
        return is_array($categories) ? array_map('absint', $categories) : array();
    }

    public static function lienTexte()
    {
        $texte = trim((string) get_option(self::OPT_LIEN_TEXTE, ''));
        return $texte !== '' ? $texte : self::LIEN_TEXTE_DEFAUT;
    }

    /* ---------------------------------------------------------------- AJAX */

    /**
     * Crée le brouillon. Paramètres POST : visuel, periode, periode_label,
     * tour, sexe, legende, lignes (JSON), forcer ; fichier « image » (JPEG).
     * Un article existe déjà pour cette clé et « forcer » est absent : on le
     * renvoie (doublon) sans rien créer.
     */
    public function creer()
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can('manage_options') || !current_user_can('upload_files')) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'), 403);
        }

        // phpcs:disable WordPress.Security.NonceVerification.Missing -- nonce vérifié ci-dessus.
        $visuel  = isset($_POST['visuel']) ? sanitize_key(wp_unslash($_POST['visuel'])) : '';
        $periode = isset($_POST['periode']) ? sanitize_text_field(wp_unslash($_POST['periode'])) : '';
        $label   = isset($_POST['periode_label']) ? sanitize_text_field(wp_unslash($_POST['periode_label'])) : '';
        $tour    = isset($_POST['tour']) ? absint($_POST['tour']) : 0;
        $sexe    = isset($_POST['sexe']) ? sanitize_key(wp_unslash($_POST['sexe'])) : 'mf';
        $sexe    = in_array(strtoupper($sexe), array('M', 'F'), true) ? strtoupper($sexe) : 'MF';
        $legende = isset($_POST['legende']) ? sanitize_textarea_field(wp_unslash($_POST['legende'])) : '';
        $forcer  = !empty($_POST['forcer']);
        $lignes  = array();
        if (isset($_POST['lignes'])) {
            $brut = json_decode(wp_unslash($_POST['lignes']), true); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- chaque ligne est assainie ci-dessous.
            if (is_array($brut)) {
                foreach (array_slice($brut, 0, self::MAX_LIGNES) as $ligne) {
                    if (is_string($ligne)) {
                        $lignes[] = sanitize_text_field($ligne);
                    }
                }
            }
        }
        // phpcs:enable

        if (!MonClubTT_ArticleSocial::visuelValide($visuel) || !MonClubTT_ArticleSocial::periodeValide($visuel, $periode)) {
            wp_send_json_error(array('message' => 'Visuel ou période inconnus.'), 400);
        }

        $cle = MonClubTT_ArticleSocial::cle($visuel, $periode, $sexe);
        if (!$forcer) {
            $existant = $this->articleExistant($cle);
            if ($existant) {
                wp_send_json_success(array(
                    'doublon' => true,
                    'id'      => $existant->ID,
                    'titre'   => get_the_title($existant),
                    'statut'  => get_post_status_object($existant->post_status) ? get_post_status_object($existant->post_status)->label : $existant->post_status,
                    'edition' => get_edit_post_link($existant->ID, 'raw'),
                ));
            }
        }

        if (empty($_FILES['image']['tmp_name'])) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
            wp_send_json_error(array('message' => 'Image du visuel manquante.'), 400);
        }

        $lien = '';
        $page = absint(get_option(self::OPT_LIEN_PAGE, 0));
        if ($page && get_post_status($page) === 'publish') {
            $lien = get_permalink($page);
        }

        $titreListe = in_array($visuel, array('resultats'), true) ? 'Les résultats en détail' : 'En détail';
        $article = array(
            'post_type'    => 'post',
            'post_status'  => 'draft',
            'post_author'  => get_current_user_id(),
            'post_title'   => MonClubTT_ArticleSocial::titre($visuel, $label, $tour ?: null, $sexe),
            'post_content' => '',
            'meta_input'   => array(
                self::META_VISUEL  => $visuel,
                self::META_PERIODE => $periode,
                self::META_CLE     => $cle,
            ),
        );
        $categorie = self::categories()[$visuel] ?? 0;
        if ($categorie && term_exists($categorie, 'category')) {
            $article['post_category'] = array($categorie);
        }

        $id = wp_insert_post(wp_slash($article), true);
        if (is_wp_error($id)) {
            wp_send_json_error(array('message' => 'Création de l\'article impossible : ' . $id->get_error_message()), 500);
        }

        $alt   = MonClubTT_ArticleSocial::texteAlternatif($visuel, $label, $lignes, $tour ?: null);
        $image = $this->enregistrerImage($id, $alt);
        if (is_wp_error($image)) {
            wp_delete_post($id, true);
            wp_send_json_error(array('message' => 'Envoi de l\'image impossible : ' . $image->get_error_message()), 500);
        }
        set_post_thumbnail($id, $image);

        // Contenu écrit une fois l'image dans la médiathèque : le visuel y figure en entier.
        $contenu = MonClubTT_ArticleSocial::contenu($legende, $lignes, $titreListe, $lien ? $lien : '', self::lienTexte(), array(
            'id'  => $image,
            'url' => wp_get_attachment_url($image),
            'alt' => $alt,
        ));
        wp_update_post(wp_slash(array('ID' => $id, 'post_content' => $contenu)));

        wp_send_json_success(array(
            'id'      => $id,
            'edition' => get_edit_post_link($id, 'raw'),
        ));
    }

    /**
     * Article déjà créé pour cette clé (hors corbeille), le plus récent.
     *
     * @param string $cle
     * @return WP_Post|null
     */
    private function articleExistant($cle)
    {
        $posts = get_posts(array(
            'post_type'        => 'post',
            'post_status'      => array('publish', 'future', 'draft', 'pending', 'private'),
            'meta_key'         => self::META_CLE, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_key -- clé indexée, action ponctuelle de l'admin.
            'meta_value'       => $cle,           // phpcs:ignore WordPress.DB.SlowDBQuery.slow_query_meta_value
            'numberposts'      => 10,
            'suppress_filters' => false,
        ));
        $articles = array();
        foreach ($posts as $post) {
            $articles[] = array('id' => $post->ID, 'cle' => $cle, 'statut' => $post->post_status, 'date' => $post->post_date, 'post' => $post);
        }
        $trouve = MonClubTT_ArticleSocial::doublon($articles, $cle);
        return $trouve ? $trouve['post'] : null;
    }

    /**
     * Visuel JPEG envoyé (champ « image ») dans la médiathèque, rattaché à
     * l'article, avec son texte alternatif.
     *
     * @param int    $postId
     * @param string $alt
     * @return int|WP_Error Identifiant de la pièce jointe.
     */
    private function enregistrerImage($postId, $alt)
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $id = media_handle_upload('image', $postId, array('post_title' => get_the_title($postId)), array(
            'test_form' => false,
            'mimes'     => array('jpg|jpeg|jpe' => 'image/jpeg'),
        ));
        if (!is_wp_error($id)) {
            update_post_meta($id, '_wp_attachment_image_alt', wp_slash($alt));
        }
        return $id;
    }
}

}
