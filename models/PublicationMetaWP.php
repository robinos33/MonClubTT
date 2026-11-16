<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_PublicationMetaWP' ) ) {

/**
 * « Publier sur les réseaux » depuis l'éditeur d'un article : Facebook (texte,
 * lien et photos) et Instagram (photo seule ou carrousel), avec un texte par
 * réseau et l'historique des publications sur l'article.
 *
 * Connexion « mode expert » : le club crée sa propre app Meta et renseigne
 * l'identifiant de la page, celui du compte Instagram professionnel et un
 * jeton de page longue durée (page « Réseaux sociaux »).
 *
 * Les requêtes et messages sont dans MonClubTT_PublicationMeta, les appels
 * HTTP dans MonClubTT_ClientMeta.
 */
class MonClubTT_PublicationMetaWP {

    /** Réglages (page « Réseaux sociaux », formulaire « Connexion Meta »). */
    const GROUPE      = 'monclubtt_meta_settings';
    const OPT_PAGE    = 'monclubtt_meta_page_id';
    const OPT_IG      = 'monclubtt_meta_instagram_id';
    const OPT_JETON   = 'monclubtt_meta_jeton';
    /** État de la connexion : ['ok' => bool, 'jeton_invalide' => bool, 'message', 'date']. */
    const OPT_ETAT    = 'monclubtt_meta_etat';

    /** Historique des publications de l'article. */
    const META_HISTORIQUE = '_monclubtt_social_publications';

    /** Taille d'image dédiée à Instagram : JPEG portrait 4:5. */
    const TAILLE_IG = 'monclubtt-instagram';
    const IG_LARGEUR = 1080;
    const IG_HAUTEUR = 1350;

    const NONCE = 'monclubtt_meta';

    public function __construct()
    {
        add_action('init', array($this, 'taille_image'));
        add_action('admin_init', array($this, 'reglages'));
        add_action('add_meta_boxes_post', array($this, 'meta_box'));
        add_action('admin_enqueue_scripts', array($this, 'assets'));
        add_action('admin_notices', array($this, 'alerte_jeton'));
        add_action('wp_ajax_monclubtt_meta_tester', array($this, 'ajax_tester'));
        add_action('wp_ajax_monclubtt_meta_preparer', array($this, 'ajax_preparer'));
        add_action('wp_ajax_monclubtt_meta_publier', array($this, 'ajax_publier'));
        add_action('wp_ajax_monclubtt_meta_fichiers', array($this, 'ajax_fichiers'));
        add_action('wp_ajax_monclubtt_meta_marquer', array($this, 'ajax_marquer'));
        add_action('wp_head', array($this, 'open_graph'), 5);
    }

    /**
     * Mode de partage de chaque réseau (voir MonClubTT_PublicationMeta::modeReseau()).
     *
     * @return array<string, string> reseau => auto|manuel
     */
    public static function modes()
    {
        $config = array(
            'page'      => (string) get_option(self::OPT_PAGE, ''),
            'instagram' => (string) get_option(self::OPT_IG, ''),
            'jeton'     => (string) get_option(self::OPT_JETON, '') !== '',
        );
        $modes = array();
        foreach (array_keys(MonClubTT_PublicationMeta::RESEAUX) as $reseau) {
            $modes[$reseau] = MonClubTT_PublicationMeta::modeReseau($reseau, $config, self::etat());
        }
        return $modes;
    }

    /**
     * Capacité requise pour publier sur les comptes du club ou changer la
     * connexion (administrateurs par défaut).
     */
    public static function capacite()
    {
        return (string) apply_filters('monclubtt_meta_capacite', 'manage_options');
    }

    public function taille_image()
    {
        add_image_size(self::TAILLE_IG, self::IG_LARGEUR, self::IG_HAUTEUR, true);
    }

    /* ------------------------------------------------------------ réglages */

    public function reglages()
    {
        // Le jeton n'est lu qu'à la publication : pas de chargement automatique.
        if (get_option(self::OPT_JETON, null) === null) {
            add_option(self::OPT_JETON, '', '', false);
        }
        register_setting(self::GROUPE, self::OPT_PAGE, array('sanitize_callback' => array($this, 'sanitize_identifiant')));
        register_setting(self::GROUPE, self::OPT_IG, array('sanitize_callback' => array($this, 'sanitize_identifiant')));
        register_setting(self::GROUPE, self::OPT_JETON, array('sanitize_callback' => array($this, 'sanitize_jeton')));
        add_settings_section('monclubtt_section_meta', '', array($this, 'section_html'), self::GROUPE);
        add_settings_field(self::OPT_PAGE, 'Identifiant de la page Facebook', array($this, 'page_html'), self::GROUPE, 'monclubtt_section_meta', array('label_for' => 'monclubtt-meta-page'));
        add_settings_field(self::OPT_IG, 'Identifiant du compte Instagram pro', array($this, 'instagram_html'), self::GROUPE, 'monclubtt_section_meta', array('label_for' => 'monclubtt-meta-ig'));
        add_settings_field(self::OPT_JETON, 'Jeton de page longue durée', array($this, 'jeton_html'), self::GROUPE, 'monclubtt_section_meta', array('label_for' => 'monclubtt-meta-jeton'));
    }

    public function sanitize_identifiant($valeur)
    {
        return preg_replace('/\D/', '', (string) $valeur);
    }

    /** Champ vide : le jeton enregistré est conservé. Nouveau jeton : état remis à zéro. */
    public function sanitize_jeton($valeur)
    {
        $valeur = trim(sanitize_text_field((string) $valeur));
        $actuel = (string) get_option(self::OPT_JETON, '');
        if ($valeur === '') {
            return $actuel;
        }
        if ($valeur !== $actuel) {
            delete_option(self::OPT_ETAT);
        }
        return $valeur;
    }

    /** « Facebook : partage manuel · Instagram : … » (réglages, après un test). */
    public static function modesTexte()
    {
        $libelles = array(
            MonClubTT_PublicationMeta::MODE_AUTO   => 'publication automatique',
            MonClubTT_PublicationMeta::MODE_MANUEL => 'partage manuel',
        );
        $morceaux = array();
        foreach (self::modes() as $reseau => $mode) {
            $morceaux[] = MonClubTT_PublicationMeta::RESEAUX[$reseau] . ' : ' . $libelles[$mode];
        }
        return implode(' · ', $morceaux);
    }

    public function section_html()
    {
        ?>
        <div class="monclubtt-modes">
            <p><strong>Deux façons de partager un article depuis l'éditeur</strong> (boîte « Réseaux sociaux », bouton « Publier sur les réseaux »), choisies réseau par réseau :</p>
            <ul>
                <li><strong>Partage manuel</strong>, par défaut, sans rien configurer : texte prêt à copier, photos prêtes à poster (format 4:5 pour Instagram), partage du lien sur Facebook et, sur téléphone, envoi direct vers l'appli Facebook ou Instagram. Convient à tous les comptes, y compris un profil Facebook personnel ou un compte Instagram personnel.</li>
                <li><strong>Publication automatique</strong> par l'API de Meta, en un clic : il faut une <strong>Page</strong> Facebook (pas un profil), un compte Instagram <strong>professionnel</strong> (Entreprise ou Créateur) relié à cette Page, et une app Meta du club. Renseignez la connexion ci-dessous puis cliquez sur « Tester la connexion » : chaque réseau validé passe en automatique. Si le jeton expire, le réseau repasse en partage manuel.</li>
            </ul>
            <p class="monclubtt-modes-actuels"><strong>Mode actuel :</strong>
                <span id="monclubtt-modes-texte"><?php echo esc_html(self::modesTexte()); ?></span>
            </p>
        </div>
        <h3>Connexion Meta (facultative, pour la publication automatique)</h3>
        <p>Créez une app Meta (developers.facebook.com, type « Entreprise »), reliez le compte Instagram professionnel à la Page, puis générez un
            <strong>jeton de page longue durée</strong> avec les permissions <code>pages_manage_posts</code>, <code>pages_read_engagement</code>,
            <code>instagram_basic</code> et <code>instagram_content_publish</code>.
            Les appels à Meta ne partent que lorsque vous publiez un article ou testez la connexion.</p>
        <?php
    }

    public function page_html()
    {
        printf('<input type="text" inputmode="numeric" class="regular-text" id="monclubtt-meta-page" name="%s" value="%s">',
            esc_attr(self::OPT_PAGE), esc_attr(get_option(self::OPT_PAGE, '')));
    }

    public function instagram_html()
    {
        printf('<input type="text" inputmode="numeric" class="regular-text" id="monclubtt-meta-ig" name="%s" value="%s">',
            esc_attr(self::OPT_IG), esc_attr(get_option(self::OPT_IG, '')));
        echo '<p class="description">Champ <code>instagram_business_account</code> de la page. Vide : pas de publication Instagram.</p>';
    }

    public function jeton_html()
    {
        $enregistre = (string) get_option(self::OPT_JETON, '') !== '';
        printf('<input type="password" class="regular-text" id="monclubtt-meta-jeton" name="%s" value="" autocomplete="off" spellcheck="false" placeholder="%s">',
            esc_attr(self::OPT_JETON), esc_attr($enregistre ? 'Jeton enregistré : laisser vide pour le conserver' : ''));
        $etat = self::etat();
        ?>
        <p>
            <button type="button" class="button" id="monclubtt-meta-tester" <?php disabled(!$enregistre); ?>>Tester la connexion</button>
            <span id="monclubtt-meta-test-resultat" role="status" aria-live="polite">
                <?php if ($etat): ?>
                    <?php echo esc_html(($etat['ok'] ? '✓ ' : '⚠ ') . $etat['message']); ?>
                <?php endif; ?>
            </span>
        </p>
        <?php
        wp_add_inline_script('monclubtt-js', 'jQuery(function($) {
            $("#monclubtt-meta-tester").on("click", function() {
                var $b = $(this).prop("disabled", true), $r = $("#monclubtt-meta-test-resultat").text("Test en cours…");
                $.post(ajaxurl, { action: "monclubtt_meta_tester", nonce: ' . wp_json_encode(wp_create_nonce(self::NONCE)) . ' })
                    .done(function(res) {
                        $r.text((res.success ? "✓ " : "⚠ ") + (res.data && res.data.message ? res.data.message : "Erreur."));
                        if (res.data && res.data.modes) { $("#monclubtt-modes-texte").text(res.data.modes); }
                    })
                    .fail(function() { $r.text("⚠ Test impossible : erreur réseau."); })
                    .always(function() { $b.prop("disabled", false); });
            });
        });');
    }

    /** @return array|null */
    public static function etat()
    {
        $etat = get_option(self::OPT_ETAT, null);
        return is_array($etat) ? $etat : null;
    }

    /**
     * @param bool   $ok
     * @param string $message
     * @param bool   $jetonInvalide
     * @param array  $reseaux reseau => bool (validé). Vide : réseaux de l'état précédent conservés.
     */
    private static function enregistrerEtat($ok, $message, $jetonInvalide = false, array $reseaux = array())
    {
        $precedent = self::etat();
        update_option(self::OPT_ETAT, array(
            'ok'             => (bool) $ok,
            'jeton_invalide' => (bool) $jetonInvalide,
            'message'        => (string) $message,
            'date'           => current_time('mysql'),
            'reseaux'        => $reseaux ? $reseaux : (array) ($precedent['reseaux'] ?? array()),
        ), false);
    }

    private static function client()
    {
        return new MonClubTT_ClientMeta(get_option(self::OPT_PAGE, ''), get_option(self::OPT_IG, ''), get_option(self::OPT_JETON, ''));
    }

    /** Alerte dans l'admin tant que le jeton est signalé invalide. */
    public function alerte_jeton()
    {
        $etat = self::etat();
        if (!$etat || empty($etat['jeton_invalide']) || !current_user_can(self::capacite())) {
            return;
        }
        printf('<div class="notice notice-error"><p><strong>Mon Club TT › Connexion Meta :</strong> %s En attendant, le partage sur les réseaux se fait en mode manuel. <a href="%s">Mettre à jour le jeton</a></p></div>',
            esc_html($etat['message']),
            esc_url(admin_url('admin.php?page=monclubtt_reseaux_sociaux#monclubtt-meta')));
    }

    /* ---------------------------------------------------------- éditeur */

    public function meta_box($post)
    {
        if (!current_user_can(self::capacite())) {
            return;
        }
        add_meta_box('monclubtt-publication-reseaux', 'Réseaux sociaux', array($this, 'meta_box_html'), 'post', 'side', 'default');
    }

    public function meta_box_html($post)
    {
        ?>
        <p>
            <button type="button" class="button button-primary" id="monclubtt-publier-reseaux" data-post="<?php echo esc_attr($post->ID); ?>">Publier sur les réseaux</button>
        </p>
        <div id="monclubtt-publications"><?php echo wp_kses_post(self::historiqueHtml(self::historique($post->ID))); ?></div>
        <?php
    }

    public function assets($hook)
    {
        $ecran = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!in_array($hook, array('post.php', 'post-new.php'), true) || !$ecran || $ecran->post_type !== 'post' || !current_user_can(self::capacite())) {
            return;
        }
        $fichier = dirname(__DIR__) . '/assets/mon-club-tt-publication.js';
        wp_enqueue_script('monclubtt-publication-js', plugins_url('assets/mon-club-tt-publication.js', MONCLUBTT_PLUGIN_FILE), array(), (string) filemtime($fichier), true);
        wp_localize_script('monclubtt-publication-js', 'MonClubTTPublication', array(
            'ajaxurl'  => admin_url('admin-ajax.php'),
            'nonce'    => wp_create_nonce(self::NONCE),
            'max'      => MonClubTT_PublicationMeta::MAX_PHOTOS,
            'maxIg'    => MonClubTT_PublicationMeta::IG_MAX_CARACTERES,
            'reglages' => admin_url('admin.php?page=monclubtt_reseaux_sociaux#monclubtt-meta'),
        ));
    }

    /** @return array */
    public static function historique($postId)
    {
        $historique = get_post_meta($postId, self::META_HISTORIQUE, true);
        return is_array($historique) ? $historique : array();
    }

    public static function historiqueHtml(array $historique)
    {
        if (!$historique) {
            return '<p class="description">Pas encore publié sur les réseaux.</p>';
        }
        $html = '<ul class="monclubtt-publications">';
        foreach (array_reverse($historique) as $entree) {
            $reseau = MonClubTT_PublicationMeta::RESEAUX[$entree['reseau']] ?? $entree['reseau'];
            $date   = mysql2date('j M Y à H:i', $entree['date']);
            $auteur = get_the_author_meta('display_name', (int) $entree['auteur']);
            $html  .= '<li>';
            $verbe  = ($entree['mode'] ?? '') === MonClubTT_PublicationMeta::MODE_MANUEL ? 'partagé à la main le ' : 'publié le ';
            if (($entree['erreur'] ?? '') !== '') {
                $html .= '<strong>' . esc_html($reseau) . '</strong> : échec le ' . esc_html($date) . ' — ' . esc_html($entree['erreur']);
            } else {
                $html .= '<strong>' . esc_html($reseau) . '</strong> : '
                    . ($entree['lien'] !== '' ? '<a href="' . esc_url($entree['lien']) . '" target="_blank" rel="noopener">' . esc_html($verbe . $date) . '</a>' : esc_html($verbe . $date));
            }
            $html .= $auteur ? ' (' . esc_html($auteur) . ')' : '';
            $html .= '</li>';
        }
        return $html . '</ul>';
    }

    /* ---------------------------------------------------------------- AJAX */

    /** Contrôles communs : nonce, droits, article. Retourne l'article. */
    private function verifier($avecArticle = true)
    {
        check_ajax_referer(self::NONCE, 'nonce');
        if (!current_user_can(self::capacite())) {
            wp_send_json_error(array('message' => 'Permissions insuffisantes.'), 403);
        }
        if (!$avecArticle) {
            return null;
        }
        $id   = isset($_POST['post_id']) ? absint($_POST['post_id']) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- vérifié ci-dessus.
        $post = $id ? get_post($id) : null;
        if (!$post || $post->post_type !== 'post' || !current_user_can('edit_post', $id)) {
            wp_send_json_error(array('message' => 'Article introuvable.'), 404);
        }
        return $post;
    }

    public function ajax_tester()
    {
        $this->verifier(false);
        try {
            $resultat = self::client()->tester();
            $message  = 'Facebook : connecté à la page « ' . $resultat['page'] . ' », publication automatique. ';
            if ($resultat['instagram'] !== '') {
                $message .= 'Instagram : connecté au compte @' . $resultat['instagram'] . ', publication automatique.';
            } elseif ($resultat['erreur_instagram'] !== '') {
                $message .= 'Instagram : ' . $resultat['erreur_instagram'] . ' Le partage Instagram reste manuel.';
            } else {
                $message .= 'Instagram : pas de compte renseigné, partage manuel.';
            }
            self::enregistrerEtat(true, $message, false, array('facebook' => true, 'instagram' => $resultat['instagram'] !== ''));
            wp_send_json_success(array('message' => $message, 'modes' => self::modesTexte()));
        } catch (MonClubTT_ErreurMeta $e) {
            self::enregistrerEtat(false, $e->getMessage(), $e->jetonInvalide, array('facebook' => false, 'instagram' => false));
            wp_send_json_error(array('message' => $e->getMessage() . ' Le partage reste manuel.', 'modes' => self::modesTexte()));
        }
    }

    /**
     * Données de la fenêtre : textes pré-remplis, photos proposées (image à
     * la une puis images de l'article), réseaux configurés, historique.
     */
    public function ajax_preparer()
    {
        $post = $this->verifier();
        if ($post->post_status !== 'publish') {
            wp_send_json_error(array('message' => 'Publiez d\'abord l\'article sur le site (puis rechargez la page) : la publication sur les réseaux renvoie vers lui.'));
        }

        $texte = MonClubTT_PublicationMeta::texteArticle(get_the_title($post), $post->post_content);
        $photos = array();
        foreach (self::imagesArticle($post) as $i => $id) {
            $photos[] = array(
                'id'     => $id,
                'apercu' => wp_get_attachment_image_url($id, 'medium'),
                'alt'    => (string) get_post_meta($id, '_wp_attachment_image_alt', true),
                'une'    => $i === 0 && (int) get_post_thumbnail_id($post) === $id,
            );
        }
        $historique = self::historique($post->ID);
        $deja = array();
        foreach (array_keys(MonClubTT_PublicationMeta::RESEAUX) as $reseau) {
            $derniere = MonClubTT_PublicationMeta::dernierePublication($historique, $reseau);
            $deja[$reseau] = $derniere ? array('date' => mysql2date('j F Y à H:i', $derniere['date']), 'lien' => $derniere['lien']) : null;
        }

        $lien = get_permalink($post);
        wp_send_json_success(array(
            'textes' => array(
                'facebook'  => $texte,
                'instagram' => MonClubTT_PublicationMeta::texteInstagram($texte),
            ),
            'lien'    => $lien,
            'photos'  => $photos,
            'modes'   => self::modes(),
            'partage' => array(
                'facebook'  => MonClubTT_PublicationMeta::lienPartageFacebook($lien),
                'instagram' => 'https://www.instagram.com/',
            ),
            'deja'    => $deja,
            'public'  => MonClubTT_PublicationMeta::urlPublique(home_url('/')),
        ));
    }

    public function ajax_publier()
    {
        $post = $this->verifier();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifié dans verifier().
        $reseau = isset($_POST['reseau']) ? sanitize_key(wp_unslash($_POST['reseau'])) : '';
        $texte  = isset($_POST['texte']) ? sanitize_textarea_field(wp_unslash($_POST['texte'])) : '';
        $ids    = isset($_POST['images']) ? array_map('absint', (array) wp_unslash($_POST['images'])) : array();
        // phpcs:enable
        if ($post->post_status !== 'publish') {
            wp_send_json_error(array('message' => 'Publiez d\'abord l\'article sur le site.'));
        }
        if ((self::modes()[$reseau] ?? '') !== MonClubTT_PublicationMeta::MODE_AUTO) {
            wp_send_json_error(array('message' => 'Publication automatique indisponible pour ce réseau : utilisez le partage manuel.'));
        }

        // Seules les photos proposées pour cet article sont acceptées.
        $ids = array_values(array_intersect(array_unique(array_filter($ids)), self::imagesArticle($post)));
        $erreur = MonClubTT_PublicationMeta::valider($reseau, $texte, $ids);
        if ($erreur !== '') {
            wp_send_json_error(array('message' => $erreur));
        }

        try {
            $urls = array();
            foreach ($ids as $id) {
                $url = $reseau === 'instagram' ? self::urlInstagram($id) : wp_get_attachment_image_url($id, 'full');
                if (!$url) {
                    throw new MonClubTT_ErreurMeta('Image introuvable dans la médiathèque (#' . $id . ').');
                }
                if (!MonClubTT_PublicationMeta::urlPublique($url)) {
                    throw new MonClubTT_ErreurMeta('Meta télécharge les photos depuis leur adresse, qui doit être publique : ce site n\'est pas accessible depuis Internet (' . wp_parse_url($url, PHP_URL_HOST) . ').');
                }
                $urls[] = $url;
            }
            $client = self::client();
            $resultat = $reseau === 'facebook'
                ? $client->publierFacebook($texte, get_permalink($post), $urls)
                : $client->publierInstagram($texte, $urls);
            $entree = array('reseau' => $reseau, 'lien' => $resultat['lien'], 'erreur' => '');
        } catch (MonClubTT_ErreurMeta $e) {
            $entree = array('reseau' => $reseau, 'lien' => '', 'erreur' => $e->getMessage());
            if ($e->jetonInvalide) {
                self::enregistrerEtat(false, $e->getMessage(), true);
            }
        }

        $historique = MonClubTT_PublicationMeta::ajouterHistorique(self::historique($post->ID), $entree + array(
            'date'   => current_time('mysql'),
            'auteur' => get_current_user_id(),
        ));
        update_post_meta($post->ID, self::META_HISTORIQUE, $historique);

        $reponse = array('historique' => self::historiqueHtml($historique), 'lien' => $entree['lien']);
        if ($entree['erreur'] !== '') {
            wp_send_json_error($reponse + array('message' => $entree['erreur']));
        }
        wp_send_json_success($reponse + array('message' => 'Publié sur ' . MonClubTT_PublicationMeta::RESEAUX[$reseau] . '.'));
    }

    /**
     * Partage manuel : adresses des photos choisies, prêtes à poster
     * (Instagram : JPEG 4:5 1080 × 1350 ; Facebook : image d'origine).
     */
    public function ajax_fichiers()
    {
        $post = $this->verifier();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifié dans verifier().
        $reseau = isset($_POST['reseau']) ? sanitize_key(wp_unslash($_POST['reseau'])) : '';
        $ids    = isset($_POST['images']) ? array_map('absint', (array) wp_unslash($_POST['images'])) : array();
        // phpcs:enable
        if (!isset(MonClubTT_PublicationMeta::RESEAUX[$reseau])) {
            wp_send_json_error(array('message' => 'Réseau inconnu.'));
        }
        $ids = array_slice(array_values(array_intersect(array_unique(array_filter($ids)), self::imagesArticle($post))), 0, MonClubTT_PublicationMeta::MAX_PHOTOS);
        $fichiers = array();
        try {
            foreach ($ids as $i => $id) {
                $url = $reseau === 'instagram' ? self::urlInstagram($id) : wp_get_attachment_image_url($id, 'full');
                if (!$url) {
                    continue;
                }
                $extension = strtolower(pathinfo((string) wp_parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                $fichiers[] = array(
                    'url' => $url,
                    'nom' => sanitize_file_name($post->post_name . '-' . $reseau . '-' . ($i + 1) . '.' . ($extension ? $extension : 'jpg')),
                );
            }
        } catch (MonClubTT_ErreurMeta $e) {
            wp_send_json_error(array('message' => $e->getMessage()));
        }
        wp_send_json_success(array('fichiers' => $fichiers));
    }

    /** Partage manuel : « Marquer comme publié », avec le lien de la publication s'il est donné. */
    public function ajax_marquer()
    {
        $post = $this->verifier();
        // phpcs:disable WordPress.Security.NonceVerification.Missing -- vérifié dans verifier().
        $reseau = isset($_POST['reseau']) ? sanitize_key(wp_unslash($_POST['reseau'])) : '';
        $lien   = isset($_POST['lien']) ? sanitize_url(trim(wp_unslash($_POST['lien'])), array('https', 'http')) : '';
        // phpcs:enable
        if (!isset(MonClubTT_PublicationMeta::RESEAUX[$reseau])) {
            wp_send_json_error(array('message' => 'Réseau inconnu.'));
        }
        $historique = MonClubTT_PublicationMeta::ajouterHistorique(self::historique($post->ID), array(
            'reseau' => $reseau,
            'mode'   => MonClubTT_PublicationMeta::MODE_MANUEL,
            'lien'   => (string) $lien,
            'date'   => current_time('mysql'),
            'auteur' => get_current_user_id(),
        ));
        update_post_meta($post->ID, self::META_HISTORIQUE, $historique);
        wp_send_json_success(array(
            'historique' => self::historiqueHtml($historique),
            'message'    => 'Partage sur ' . MonClubTT_PublicationMeta::RESEAUX[$reseau] . ' noté dans l\'historique.',
        ));
    }

    /* --------------------------------------------------------- Open Graph */

    /**
     * Balises Open Graph d'un article (titre, extrait, image à la une) : le
     * lien partagé à la main sur Facebook affiche le bon aperçu. Rien si une
     * extension SEO ou Jetpack les fournit déjà ; filtre monclubtt_open_graph
     * pour les désactiver.
     */
    public function open_graph()
    {
        if (!is_singular('post') || !apply_filters('monclubtt_open_graph', !self::extensionOpenGraph())) {
            return;
        }
        $post = get_queried_object();
        if (!$post instanceof WP_Post || post_password_required($post)) {
            return;
        }
        $titre = wp_strip_all_tags(get_the_title($post));
        $texte = has_excerpt($post) ? get_the_excerpt($post) : MonClubTT_PublicationMeta::texteArticle('', $post->post_content);
        $balises = array(
            'og:type'        => 'article',
            'og:site_name'   => get_bloginfo('name'),
            'og:title'       => $titre,
            'og:description' => MonClubTT_PublicationMeta::extrait($texte, 200),
            'og:url'         => get_permalink($post),
            'twitter:card'   => 'summary',
        );
        $image = (int) get_post_thumbnail_id($post);
        $source = $image ? wp_get_attachment_image_src($image, 'full') : false;
        if ($source) {
            $balises['og:image']        = $source[0];
            $balises['og:image:width']  = (string) $source[1];
            $balises['og:image:height'] = (string) $source[2];
            $balises['og:image:alt']    = (string) get_post_meta($image, '_wp_attachment_image_alt', true);
            $balises['twitter:card']    = 'summary_large_image';
        }
        foreach ($balises as $propriete => $valeur) {
            if ((string) $valeur === '') {
                continue;
            }
            $attribut = strpos($propriete, 'twitter:') === 0 ? 'name' : 'property';
            printf("<meta %s=\"%s\" content=\"%s\">\n", esc_attr($attribut), esc_attr($propriete), esc_attr($valeur));
        }
    }

    /** Une extension active fournit-elle déjà les balises Open Graph ? */
    private static function extensionOpenGraph()
    {
        return defined('WPSEO_VERSION')            // Yoast SEO
            || defined('RANK_MATH_VERSION')        // Rank Math
            || defined('SEOPRESS_VERSION')         // SEOPress
            || defined('AIOSEO_VERSION')           // All in One SEO
            || defined('THE_SEO_FRAMEWORK_VERSION')
            || defined('SQ_VERSION')               // Squirrly
            || (function_exists('jetpack_og_tags') && apply_filters('jetpack_enable_open_graph', false));
    }

    /* -------------------------------------------------------------- images */

    /**
     * Images proposées : l'image à la une, puis celles du contenu (blocs
     * Image et Galerie, classes wp-image-N), sans doublon.
     *
     * @return int[]
     */
    public static function imagesArticle($post)
    {
        $ids = array();
        $une = (int) get_post_thumbnail_id($post);
        if ($une) {
            $ids[] = $une;
        }
        if (preg_match_all('/wp-image-(\d+)|"id":(\d+)/', (string) $post->post_content, $m, PREG_SET_ORDER)) {
            foreach ($m as $trouve) {
                $ids[] = (int) ($trouve[1] !== '' ? $trouve[1] : $trouve[2]);
            }
        }
        $images = array();
        foreach (array_unique($ids) as $id) {
            if ($id && wp_attachment_is_image($id)) {
                $images[] = $id;
            }
        }
        return $images;
    }

    /**
     * URL de l'image au format Instagram (JPEG 1080 × 1350, recadrage centré
     * 4:5). La taille dédiée est créée à l'envoi dans la médiathèque ; pour
     * une image plus ancienne, plus petite ou dans un autre format, elle est
     * générée à la demande et ajoutée aux tailles de la pièce jointe (supprimée
     * avec elle).
     *
     * @param int $id
     * @return string
     * @throws MonClubTT_ErreurMeta
     */
    public static function urlInstagram($id)
    {
        $meta = wp_get_attachment_metadata($id);
        $base = wp_get_attachment_url($id);
        if (!is_array($meta) || !$base) {
            throw new MonClubTT_ErreurMeta('Image introuvable dans la médiathèque (#' . $id . ').');
        }
        $taille = $meta['sizes'][self::TAILLE_IG] ?? null;
        if ($taille && ($taille['mime-type'] ?? '') === 'image/jpeg') {
            return trailingslashit(dirname($base)) . rawurlencode($taille['file']);
        }
        // Image d'origine déjà en JPEG 4:5 et pas plus large : utilisée telle quelle.
        $largeur = (int) ($meta['width'] ?? 0);
        $hauteur = (int) ($meta['height'] ?? 0);
        if (get_post_mime_type($id) === 'image/jpeg' && $largeur && $largeur <= self::IG_LARGEUR && $largeur * 5 === $hauteur * 4) {
            return $base;
        }

        $chemin  = get_attached_file($id);
        if ($taille && !empty($taille['file'])) {
            // Taille générée dans un autre format (PNG…) : remplacée par le JPEG.
            wp_delete_file(trailingslashit(dirname((string) $chemin)) . $taille['file']);
        }
        $editeur = $chemin ? wp_get_image_editor($chemin) : null;
        if (!$editeur || is_wp_error($editeur)) {
            throw new MonClubTT_ErreurMeta('Impossible de préparer l\'image pour Instagram (#' . $id . ').');
        }
        $dim = $editeur->get_size();
        $l = (int) $dim['width'];
        $h = (int) $dim['height'];
        if ($l * 5 > $h * 4) { // plus large que 4:5 : on coupe les côtés
            $cl = (int) round($h * 4 / 5);
            $ch = $h;
        } else {                // plus haut : on coupe en haut et en bas
            $cl = $l;
            $ch = (int) round($l * 5 / 4);
        }
        $dl = min(self::IG_LARGEUR, $cl);
        $dh = (int) round($dl * 5 / 4);
        $resultat = $editeur->crop((int) (($l - $cl) / 2), (int) (($h - $ch) / 2), $cl, $ch, $dl, $dh);
        if (is_wp_error($resultat)) {
            throw new MonClubTT_ErreurMeta('Recadrage de l\'image impossible (#' . $id . ').');
        }
        $editeur->set_quality(90);
        $fichier = $editeur->generate_filename('ig-' . $dl . 'x' . $dh, null, 'jpg');
        $sauve = $editeur->save($fichier, 'image/jpeg');
        if (is_wp_error($sauve)) {
            throw new MonClubTT_ErreurMeta('Enregistrement de l\'image Instagram impossible : ' . $sauve->get_error_message());
        }
        $meta['sizes'][self::TAILLE_IG] = array(
            'file'      => $sauve['file'],
            'width'     => $sauve['width'],
            'height'    => $sauve['height'],
            'mime-type' => 'image/jpeg',
            'filesize'  => (int) @filesize($sauve['path']), // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
        );
        wp_update_attachment_metadata($id, $meta);
        return trailingslashit(dirname($base)) . rawurlencode($sauve['file']);
    }
}

}
