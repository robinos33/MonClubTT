<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_ErreurMeta' ) ) {

/** Erreur de l'API Graph, avec un message compréhensible pour l'admin. */
class MonClubTT_ErreurMeta extends Exception {

    /** @var bool */
    public $jetonInvalide = false;

    public function __construct($message, $jetonInvalide = false) {
        parent::__construct($message);
        $this->jetonInvalide = (bool) $jetonInvalide;
    }
}

}

if ( ! class_exists( 'MonClubTT_ClientMeta' ) ) {

/**
 * Appels à l'API Graph de Meta (Facebook et Instagram) pour une page et son
 * compte Instagram professionnel. Les requêtes sont construites par
 * MonClubTT_PublicationMeta ; les appels passent par wp_remote_request(),
 * donc par le filtre pre_http_request (tests, proxy).
 *
 * Aucun appel n'est fait sans action explicite de l'admin (publication,
 * test de connexion).
 */
class MonClubTT_ClientMeta {

    /** Attente du traitement d'un conteneur Instagram : essais × secondes. */
    const IG_ESSAIS  = 15;
    const IG_ATTENTE = 2;

    private $pageId;
    private $igId;
    private $jeton;

    /** @var callable Pause entre deux lectures du statut (remplaçable en test). */
    private $pause;

    public function __construct($pageId, $igId, $jeton, $pause = null) {
        $this->pageId = (string) $pageId;
        $this->igId   = (string) $igId;
        $this->jeton  = (string) $jeton;
        $this->pause  = $pause ?: 'sleep';
    }

    /**
     * Publie sur le fil de la page : photos envoyées non publiées, puis
     * message avec attached_media.
     *
     * @param string   $message
     * @param string   $lien      Lien vers l'article.
     * @param string[] $imageUrls URL publiques des photos (0 à 10).
     * @return array{id: string, lien: string}
     * @throws MonClubTT_ErreurMeta
     */
    public function publierFacebook($message, $lien, array $imageUrls) {
        $this->exiger($this->pageId, 'Identifiant de la page Facebook manquant.');
        $photos = array();
        foreach ($imageUrls as $url) {
            $reponse  = $this->appel(MonClubTT_PublicationMeta::requetePhotoFacebook($this->pageId, $this->jeton, $url));
            $photos[] = (string) ($reponse['id'] ?? '');
        }
        $reponse = $this->appel(MonClubTT_PublicationMeta::requeteFilFacebook($this->pageId, $this->jeton, $message, $lien, $photos));
        $id = (string) ($reponse['id'] ?? '');
        return array('id' => $id, 'lien' => MonClubTT_PublicationMeta::lienFacebook($id));
    }

    /**
     * Publie sur Instagram : un conteneur par image, puis (à partir de deux)
     * un conteneur carrousel, attente du statut FINISHED, puis media_publish.
     *
     * @param string   $legende
     * @param string[] $imageUrls URL publiques des images JPEG (1 à 10).
     * @return array{id: string, lien: string}
     * @throws MonClubTT_ErreurMeta
     */
    public function publierInstagram($legende, array $imageUrls) {
        $this->exiger($this->igId, 'Identifiant du compte Instagram manquant.');
        $carrousel = count($imageUrls) > 1;
        $enfants = array();
        foreach ($imageUrls as $url) {
            $reponse   = $this->appel(MonClubTT_PublicationMeta::requeteConteneurInstagram($this->igId, $this->jeton, $url, $legende, $carrousel));
            $enfants[] = (string) ($reponse['id'] ?? '');
        }
        if ($carrousel) {
            foreach ($enfants as $enfant) {
                $this->attendreConteneur($enfant);
            }
            $reponse   = $this->appel(MonClubTT_PublicationMeta::requeteCarrouselInstagram($this->igId, $this->jeton, $enfants, $legende));
            $conteneur = (string) ($reponse['id'] ?? '');
        } else {
            $conteneur = $enfants[0];
        }
        $this->attendreConteneur($conteneur);

        $reponse = $this->appel(MonClubTT_PublicationMeta::requetePublicationInstagram($this->igId, $this->jeton, $conteneur));
        $id   = (string) ($reponse['id'] ?? '');
        $lien = '';
        try {
            $media = $this->appel(MonClubTT_PublicationMeta::requeteLienInstagram($id, $this->jeton));
            $lien  = (string) ($media['permalink'] ?? '');
        } catch (MonClubTT_ErreurMeta $e) {
            // Publié : seul le lien manque, ce n'est pas une erreur.
        }
        return array('id' => $id, 'lien' => $lien);
    }

    /**
     * Test de connexion : page, puis compte Instagram s'il est renseigné.
     * Une erreur sur le compte Instagram n'empêche pas de valider la page
     * (Facebook en automatique, Instagram en partage manuel).
     *
     * @return array{page: string, instagram: string, erreur_instagram: string}
     * @throws MonClubTT_ErreurMeta Page inaccessible ou jeton invalide.
     */
    public function tester() {
        $this->exiger($this->pageId, 'Renseignez l\'identifiant de la page Facebook.');
        $page = $this->appel(MonClubTT_PublicationMeta::requeteTestPage($this->pageId, $this->jeton));
        $instagram = '';
        $erreurInstagram = '';
        if ($this->igId !== '') {
            try {
                $compte    = $this->appel(MonClubTT_PublicationMeta::requeteTestInstagram($this->igId, $this->jeton));
                $instagram = (string) ($compte['username'] ?? $compte['id'] ?? '');
            } catch (MonClubTT_ErreurMeta $e) {
                if ($e->jetonInvalide) {
                    throw $e;
                }
                $erreurInstagram = $e->getMessage();
            }
        }
        return array('page' => (string) ($page['name'] ?? $page['id'] ?? ''), 'instagram' => $instagram, 'erreur_instagram' => $erreurInstagram);
    }

    /** @throws MonClubTT_ErreurMeta */
    private function attendreConteneur($conteneur) {
        for ($essai = 0; $essai < self::IG_ESSAIS; $essai++) {
            $statut = $this->appel(MonClubTT_PublicationMeta::requeteStatutInstagram($conteneur, $this->jeton));
            $code   = (string) ($statut['status_code'] ?? '');
            if ($code === 'FINISHED' || $code === 'PUBLISHED') {
                return;
            }
            if ($code === 'ERROR' || $code === 'EXPIRED') {
                throw new MonClubTT_ErreurMeta(self::messageStatut((string) ($statut['status'] ?? '')));
            }
            call_user_func($this->pause, self::IG_ATTENTE);
        }
        throw new MonClubTT_ErreurMeta('Instagram traite encore les images. Réessayez dans quelques minutes.');
    }

    /**
     * Statut d'erreur d'un conteneur (« Error: 2207009 ... ») → message lisible.
     */
    private static function messageStatut($statut) {
        if (preg_match('/\b(22\d{5})\b/', $statut, $m)) {
            return MonClubTT_PublicationMeta::messageErreur(array('code' => 0, 'error_subcode' => (int) $m[1], 'message' => $statut));
        }
        return 'Instagram a refusé une image' . ($statut !== '' ? ' : ' . $statut : '.') . ' Vérifiez le format (JPEG) et le ratio (entre 4:5 et 1,91:1).';
    }

    /** @throws MonClubTT_ErreurMeta */
    private function exiger($valeur, $message) {
        if ($this->jeton === '') {
            throw new MonClubTT_ErreurMeta('Connexion Meta non configurée : renseignez le jeton de page dans Réseaux sociaux › Connexion Meta.');
        }
        if ((string) $valeur === '') {
            throw new MonClubTT_ErreurMeta($message);
        }
    }

    /**
     * @param array{methode: string, url: string, params: array} $requete
     * @return array Réponse décodée.
     * @throws MonClubTT_ErreurMeta
     */
    private function appel(array $requete) {
        $args = array('method' => $requete['methode'], 'timeout' => 30);
        $url  = $requete['url'];
        if ($requete['methode'] === 'GET') {
            $url .= '?' . http_build_query($requete['params']);
        } else {
            $args['body'] = $requete['params'];
        }

        $reponse = wp_remote_request($url, $args);
        if (is_wp_error($reponse)) {
            throw new MonClubTT_ErreurMeta('Impossible de joindre Meta : ' . $reponse->get_error_message());
        }
        $donnees = json_decode((string) wp_remote_retrieve_body($reponse), true);
        if (!is_array($donnees)) {
            throw new MonClubTT_ErreurMeta('Réponse inattendue de Meta (HTTP ' . (int) wp_remote_retrieve_response_code($reponse) . ').');
        }
        if (isset($donnees['error']) && is_array($donnees['error'])) {
            throw new MonClubTT_ErreurMeta(MonClubTT_PublicationMeta::messageErreur($donnees['error']), MonClubTT_PublicationMeta::jetonInvalide($donnees['error']));
        }
        return $donnees;
    }
}

}
