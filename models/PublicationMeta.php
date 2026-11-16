<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_PublicationMeta' ) ) {

/**
 * Service de domaine (sans état) : publication d'un article sur Facebook et
 * Instagram via l'API Graph de Meta — construction des requêtes, texte
 * pré-rempli depuis l'article, messages d'erreur lisibles, historique.
 *
 * Sans dépendance à WordPress (hors wp_strip_all_tags et wp_parse_url,
 * simulées dans les tests), pour rester testable unitairement. Les appels
 * HTTP sont dans MonClubTT_ClientMeta, le lien avec l'éditeur dans
 * MonClubTT_PublicationMetaWP.
 */
class MonClubTT_PublicationMeta {

    /** Version de l'API Graph utilisée. */
    const VERSION = 'v23.0';
    const GRAPH   = 'https://graph.facebook.com/';

    /** Limites Instagram. */
    const IG_MAX_CARACTERES = 2200;
    const MAX_PHOTOS        = 10;

    const RESEAUX = array('facebook' => 'Facebook', 'instagram' => 'Instagram');

    /** Mention ajoutée au texte Instagram (pas de lien cliquable dans une publication). */
    const MENTION_BIO = 'Lien en bio 🔗';

    /** Modes de partage d'un réseau. */
    const MODE_AUTO   = 'auto';
    const MODE_MANUEL = 'manuel';

    /* ---------------------------------------------------------------- modes */

    /**
     * Mode de partage d'un réseau. Publication automatique seulement si la
     * connexion Meta est renseignée pour ce réseau ET qu'un test (ou une
     * publication) l'a validée ; sinon — compte non compatible, connexion
     * jamais testée ou jeton expiré — partage manuel (copier-coller).
     *
     * @param string     $reseau facebook ou instagram.
     * @param array      $config ['page' => id, 'instagram' => id, 'jeton' => bool (jeton enregistré)].
     * @param array|null $etat   État de la connexion : ['ok', 'jeton_invalide', 'reseaux' => [reseau => bool]].
     * @return string MODE_AUTO ou MODE_MANUEL.
     */
    public static function modeReseau($reseau, array $config, $etat) {
        $identifiant = $reseau === 'instagram' ? ($config['instagram'] ?? '') : ($config['page'] ?? '');
        if (empty($config['jeton']) || (string) $identifiant === '' || !is_array($etat)) {
            return self::MODE_MANUEL;
        }
        if (empty($etat['ok']) || !empty($etat['jeton_invalide']) || empty($etat['reseaux'][$reseau])) {
            return self::MODE_MANUEL;
        }
        return self::MODE_AUTO;
    }

    /** Fenêtre de partage Facebook d'un lien (profil, page ou groupe, au choix de l'utilisateur). */
    public static function lienPartageFacebook($url) {
        return 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode((string) $url);
    }

    /**
     * Début d'un texte, coupé à un espace (description des aperçus de lien).
     *
     * @param string $texte
     * @param int    $max Nombre de caractères au plus, points de suspension compris.
     * @return string
     */
    public static function extrait($texte, $max = 200) {
        $texte = trim(preg_replace('/\s+/u', ' ', (string) $texte));
        if (mb_strlen($texte) <= $max) {
            return $texte;
        }
        $coupe = mb_substr($texte, 0, $max - 1);
        $espace = mb_strrpos($coupe, ' ');
        if ($espace !== false && $espace > $max / 2) {
            $coupe = mb_substr($coupe, 0, $espace);
        }
        return rtrim($coupe, " ,;:.–—") . '…';
    }

    /* ------------------------------------------------------------ requêtes */

    /**
     * @param string $chemin   Chemin Graph sans « / » initial (« 123/photos »).
     * @param string $methode  GET ou POST.
     * @param array  $params   Paramètres (corps en POST, chaîne de requête en GET), jeton compris.
     * @return array{methode: string, url: string, params: array}
     */
    private static function requete($chemin, $methode, array $params) {
        return array('methode' => $methode, 'url' => self::GRAPH . self::VERSION . '/' . ltrim($chemin, '/'), 'params' => $params);
    }

    /** Photo envoyée sur la page sans être publiée, pour l'attacher ensuite au message. */
    public static function requetePhotoFacebook($pageId, $jeton, $imageUrl) {
        return self::requete($pageId . '/photos', 'POST', array(
            'url'          => $imageUrl,
            'published'    => 'false',
            'access_token' => $jeton,
        ));
    }

    /**
     * Message sur le fil de la page. Sans photo, le lien est passé à part
     * (aperçu de l'article) ; avec des photos, Facebook ignore « link » : il est
     * alors ajouté à la fin du message.
     *
     * @param string   $pageId
     * @param string   $jeton
     * @param string   $message
     * @param string   $lien
     * @param string[] $photoIds Identifiants renvoyés par requetePhotoFacebook().
     */
    public static function requeteFilFacebook($pageId, $jeton, $message, $lien, array $photoIds) {
        $message = trim((string) $message);
        $params = array('access_token' => $jeton);
        if ($photoIds) {
            if ($lien !== '' && strpos($message, $lien) === false) {
                $message .= ($message === '' ? '' : "\n\n") . $lien;
            }
            foreach (array_values($photoIds) as $i => $id) {
                $params['attached_media[' . $i . ']'] = json_encode(array('media_fbid' => (string) $id));
            }
        } elseif ($lien !== '') {
            $params['link'] = $lien;
        }
        $params['message'] = $message;
        return self::requete($pageId . '/feed', 'POST', $params);
    }

    /**
     * Conteneur Instagram d'une image : publication seule (avec légende) ou
     * élément de carrousel (sans légende).
     */
    public static function requeteConteneurInstagram($igId, $jeton, $imageUrl, $legende, $carrousel) {
        $params = array('image_url' => $imageUrl, 'access_token' => $jeton);
        if ($carrousel) {
            $params['is_carousel_item'] = 'true';
        } else {
            $params['caption'] = (string) $legende;
        }
        return self::requete($igId . '/media', 'POST', $params);
    }

    /** Conteneur carrousel regroupant les conteneurs d'images (2 à 10). */
    public static function requeteCarrouselInstagram($igId, $jeton, array $enfants, $legende) {
        return self::requete($igId . '/media', 'POST', array(
            'media_type'   => 'CAROUSEL',
            'children'     => implode(',', $enfants),
            'caption'      => (string) $legende,
            'access_token' => $jeton,
        ));
    }

    /** Statut d'un conteneur (IN_PROGRESS, FINISHED, ERROR, EXPIRED). */
    public static function requeteStatutInstagram($conteneurId, $jeton) {
        return self::requete($conteneurId, 'GET', array('fields' => 'status_code,status', 'access_token' => $jeton));
    }

    public static function requetePublicationInstagram($igId, $jeton, $conteneurId) {
        return self::requete($igId . '/media_publish', 'POST', array('creation_id' => $conteneurId, 'access_token' => $jeton));
    }

    /** Lien public d'une publication Instagram. */
    public static function requeteLienInstagram($mediaId, $jeton) {
        return self::requete($mediaId, 'GET', array('fields' => 'permalink', 'access_token' => $jeton));
    }

    /** Test de connexion : la page (jeton de page) et le compte Instagram relié. */
    public static function requeteTestPage($pageId, $jeton) {
        return self::requete($pageId, 'GET', array('fields' => 'id,name,instagram_business_account', 'access_token' => $jeton));
    }

    public static function requeteTestInstagram($igId, $jeton) {
        return self::requete($igId, 'GET', array('fields' => 'id,username', 'access_token' => $jeton));
    }

    /** Lien public d'une publication Facebook (identifiant « page_post »). */
    public static function lienFacebook($postId) {
        return 'https://www.facebook.com/' . rawurlencode((string) $postId);
    }

    /* --------------------------------------------------------------- textes */

    /**
     * Texte brut d'un article : titre, puis contenu (paragraphes séparés par
     * une ligne vide, listes en « • »), sans balises ni commentaires de blocs.
     *
     * @param string $titre
     * @param string $contenuHtml post_content (blocs Gutenberg ou HTML).
     * @return string
     */
    public static function texteArticle($titre, $contenuHtml) {
        $html = preg_replace('/<!--.*?-->/s', '', (string) $contenuHtml);
        $html = preg_replace('/<br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<li[^>]*>/i', "\n• ", $html);
        $html = preg_replace('/<\/(p|h[1-6]|ul|ol|blockquote|figure|div)>/i', "\n\n", $html);
        $texte = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        $lignes = array_map('trim', explode("\n", str_replace(array("\r\n", "\r"), "\n", $texte)));
        $texte = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lignes));
        $texte = preg_replace("/^(• [^\n]*)\n\n(?=• )/m", "$1\n", $texte); // pas de ligne vide entre deux éléments de liste

        $titre = trim(html_entity_decode((string) $titre, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $texte = trim($texte);
        return trim($titre . ($titre !== '' && $texte !== '' ? "\n\n" : '') . $texte);
    }

    /** Texte Instagram : le texte de l'article suivi de « Lien en bio ». */
    public static function texteInstagram($texte) {
        $texte = trim((string) $texte);
        return $texte === '' ? self::MENTION_BIO : $texte . "\n\n" . self::MENTION_BIO;
    }

    /** Nombre de caractères tel que compté par Instagram (points de code). */
    public static function longueur($texte) {
        return mb_strlen((string) $texte, 'UTF-8');
    }

    /* -------------------------------------------------------------- erreurs */

    /**
     * Le jeton est-il expiré, révoqué ou invalide ? (codes 190 et 102 de l'API Graph)
     *
     * @param array $erreur Objet « error » de la réponse Graph.
     */
    public static function jetonInvalide(array $erreur) {
        return in_array((int) ($erreur['code'] ?? 0), array(190, 102), true);
    }

    /**
     * Message compréhensible pour une erreur de l'API Graph.
     *
     * @param array $erreur Objet « error » de la réponse Graph (code, error_subcode, message, error_user_msg).
     * @return string
     */
    public static function messageErreur(array $erreur) {
        $code    = (int) ($erreur['code'] ?? 0);
        $sousCode = (int) ($erreur['error_subcode'] ?? 0);

        if (self::jetonInvalide($erreur)) {
            return 'Le jeton d\'accès Meta a expiré ou a été révoqué. Générez un nouveau jeton de page longue durée et enregistrez-le dans Réseaux sociaux › Connexion Meta.';
        }
        $images = array(
            2207004 => 'Image trop lourde pour Instagram (8 Mo au maximum).',
            2207009 => 'Format d\'image refusé par Instagram : le ratio doit être compris entre 4:5 (portrait) et 1,91:1 (paysage).',
            2207026 => 'Format d\'image refusé par Instagram : seul le JPEG est accepté.',
            2207052 => 'Instagram n\'a pas pu télécharger l\'image : elle doit être accessible publiquement (site en ligne, sans protection par mot de passe).',
            2207003 => 'Instagram n\'a pas pu télécharger l\'image dans le temps imparti. Réessayez dans quelques minutes.',
            2207020 => 'Le conteneur Instagram a expiré. Relancez la publication.',
            2207042 => 'Limite de publications Instagram atteinte pour aujourd\'hui (100 par 24 heures).',
        );
        if (isset($images[$sousCode])) {
            return $images[$sousCode];
        }
        if (in_array($code, array(10, 200, 3), true) || ($code >= 200 && $code < 300)) {
            return 'Autorisation refusée par Meta : vérifiez que le jeton est un jeton de la page, avec les permissions pages_manage_posts, instagram_basic et instagram_content_publish, et que le compte Instagram professionnel est relié à la page.';
        }
        if ($code === 100 && stripos((string) ($erreur['message'] ?? ''), 'does not exist') !== false) {
            return 'Page Facebook ou compte Instagram introuvable : vérifiez les identifiants dans Réseaux sociaux › Connexion Meta.';
        }
        if (in_array($code, array(4, 17, 32, 613), true)) {
            return 'Trop d\'appels à l\'API Meta en peu de temps. Réessayez dans une heure.';
        }
        if ($code === 368) {
            return 'Meta a bloqué temporairement la publication (contenu signalé ou action jugée abusive).';
        }
        if ($code === 9004) {
            return 'Meta n\'a pas pu télécharger l\'image : elle doit être accessible publiquement (site en ligne).';
        }

        $detail = trim((string) ($erreur['error_user_msg'] ?? $erreur['message'] ?? ''));
        return 'Erreur de l\'API Meta' . ($code ? ' (code ' . $code . ')' : '') . ($detail !== '' ? ' : ' . $detail : '.');
    }

    /* ----------------------------------------------------------- historique */

    /**
     * Dernière publication réussie sur ce réseau, ou null.
     *
     * @param array  $historique Liste de ['reseau', 'lien', 'date', 'auteur', 'erreur'].
     * @param string $reseau     facebook ou instagram.
     * @return array|null
     */
    public static function dernierePublication(array $historique, $reseau) {
        $trouve = null;
        foreach ($historique as $entree) {
            if (($entree['reseau'] ?? '') === $reseau && empty($entree['erreur'])
                && ($trouve === null || strcmp((string) $entree['date'], (string) $trouve['date']) > 0)) {
                $trouve = $entree;
            }
        }
        return $trouve;
    }

    /**
     * Ajoute une entrée à l'historique (les 50 dernières sont gardées).
     *
     * @param array $historique
     * @param array $entree ['reseau', 'lien', 'date', 'auteur', 'erreur', 'mode' (auto par défaut)].
     * @return array
     */
    public static function ajouterHistorique(array $historique, array $entree) {
        $historique[] = array(
            'reseau' => (string) ($entree['reseau'] ?? ''),
            'mode'   => ($entree['mode'] ?? '') === self::MODE_MANUEL ? self::MODE_MANUEL : self::MODE_AUTO,
            'lien'   => (string) ($entree['lien'] ?? ''),
            'date'   => (string) ($entree['date'] ?? ''),
            'auteur' => (int) ($entree['auteur'] ?? 0),
            'erreur' => (string) ($entree['erreur'] ?? ''),
        );
        return array_slice($historique, -50);
    }

    /**
     * Vérifie la sélection avant envoi.
     *
     * @param string   $reseau
     * @param string   $texte
     * @param string[] $images
     * @return string Message d'erreur, vide si tout va bien.
     */
    public static function valider($reseau, $texte, array $images) {
        if (!isset(self::RESEAUX[$reseau])) {
            return 'Réseau inconnu.';
        }
        if (count($images) > self::MAX_PHOTOS) {
            return self::MAX_PHOTOS . ' photos au maximum.';
        }
        if ($reseau === 'instagram') {
            if (!$images) {
                return 'Instagram demande au moins une photo.';
            }
            if (self::longueur($texte) > self::IG_MAX_CARACTERES) {
                return 'Texte trop long pour Instagram (' . self::IG_MAX_CARACTERES . ' caractères au maximum).';
            }
        }
        if ($reseau === 'facebook' && trim((string) $texte) === '' && !$images) {
            return 'Ajoutez un texte ou une photo.';
        }
        return '';
    }

    /**
     * L'URL est-elle joignable depuis Internet ? Meta télécharge les images
     * depuis leur adresse : localhost ou un réseau privé ne fonctionnent pas.
     *
     * @param string $url
     * @return bool
     */
    public static function urlPublique($url) {
        $hote = strtolower((string) wp_parse_url((string) $url, PHP_URL_HOST));
        if ($hote === '' || !preg_match('#^https?://#i', (string) $url)) {
            return false;
        }
        if ($hote === 'localhost' || preg_match('/\.(local|localhost|test|internal|lan)$/', $hote)) {
            return false;
        }
        if (filter_var($hote, FILTER_VALIDATE_IP)) {
            return (bool) filter_var($hote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
        }
        return strpos($hote, '.') !== false;
    }
}

}
