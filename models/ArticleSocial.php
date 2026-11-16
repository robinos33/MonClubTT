<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_ArticleSocial' ) ) {

/**
 * Service de domaine (sans état) : article créé depuis un visuel de la page
 * « Réseaux sociaux » — titre proposé, contenu (légende puis données en
 * texte), texte alternatif de l'image et clé de détection des doublons.
 *
 * Sans dépendance à WordPress (hors esc_*), pour rester testable unitairement.
 * Le lien avec WordPress (médiathèque, brouillon, réglages) est dans
 * MonClubTT_ArticleSocialWP.
 */
class MonClubTT_ArticleSocial {

    /** Visuels de la page « Réseaux sociaux » => libellé (réglage des catégories). */
    const VISUELS = array(
        'prog-mens' => 'Top Progression du mois',
        'prog-ann'  => 'Top Progression de la saison',
        'paliers'   => 'Nouveaux paliers de points',
        'resultats' => 'Résultats des équipes',
        'perfs'     => 'Top perfs',
        'cartons'   => 'Carton plein',
        'belles'    => 'Victoires à la belle',
    );

    /** Visuels du dernier week-end de championnat (période = date du dernier jour). */
    const VISUELS_WEEKEND = array('resultats', 'perfs', 'cartons', 'belles');

    /** Nombre de lignes de données reprises dans le texte alternatif. */
    const ALT_MAX_LIGNES = 5;

    /**
     * Visuel connu ?
     *
     * @param string $visuel
     * @return bool
     */
    public static function visuelValide($visuel) {
        return isset(self::VISUELS[$visuel]);
    }

    /**
     * Période valide pour le visuel : mois (2026-11) pour la progression du
     * mois et les paliers, saison (2026-2027) pour la progression annuelle,
     * dernier jour du week-end (2026-11-15) pour les visuels de championnat.
     *
     * @param string $visuel
     * @param string $periode
     * @return bool
     */
    public static function periodeValide($visuel, $periode) {
        $periode = (string) $periode;
        if ($visuel === 'prog-ann') {
            return (bool) preg_match('/^(\d{4})-(\d{4})$/', $periode, $m) && (int) $m[2] === (int) $m[1] + 1;
        }
        if (in_array($visuel, self::VISUELS_WEEKEND, true)) {
            return (bool) preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $periode, $m) && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
        }
        if (self::visuelValide($visuel)) {
            return (bool) preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $periode);
        }
        return false;
    }

    /**
     * Clé d'un article : un seul article par visuel, période et public
     * (tous / hommes / femmes pour la progression et les paliers).
     *
     * @param string $visuel
     * @param string $periode
     * @param string $sexe MF, M ou F (ignoré pour les visuels du week-end).
     * @return string
     */
    public static function cle($visuel, $periode, $sexe = 'MF') {
        $cle = $visuel . '|' . $periode;
        if (!in_array($visuel, self::VISUELS_WEEKEND, true) && in_array($sexe, array('M', 'F'), true)) {
            $cle .= '|' . $sexe;
        }
        return $cle;
    }

    /**
     * Article déjà créé pour cette clé, hors corbeille : le plus récent.
     *
     * @param array  $articles Liste de ['id', 'cle', 'statut', 'date' (Y-m-d H:i:s)].
     * @param string $cle      Voir cle().
     * @return array|null L'article trouvé, ou null.
     */
    public static function doublon(array $articles, $cle) {
        $trouve = null;
        foreach ($articles as $article) {
            if (($article['cle'] ?? '') !== $cle || ($article['statut'] ?? '') === 'trash') {
                continue;
            }
            if ($trouve === null || strcmp((string) ($article['date'] ?? ''), (string) ($trouve['date'] ?? '')) > 0) {
                $trouve = $article;
            }
        }
        return $trouve;
    }

    /**
     * Titre proposé selon le visuel.
     *
     * @param string   $visuel
     * @param string   $periodeLabel « novembre 2026 », « Saison 2026–2027 », « Week-end du 14–15 novembre 2026 »…
     * @param int|null $tour         Numéro de journée (visuels du week-end), si connu.
     * @param string   $sexe         MF, M ou F.
     * @return string
     */
    public static function titre($visuel, $periodeLabel, $tour = null, $sexe = 'MF') {
        $periodeLabel = trim((string) $periodeLabel);
        $public = array('M' => ' (messieurs)', 'F' => ' (dames)')[$sexe] ?? '';

        switch ($visuel) {
            case 'prog-mens':
                return 'Top Progression' . $public . ' – ' . $periodeLabel;
            case 'prog-ann':
                return 'Top Progression' . $public . ' – ' . self::minusculeInitiale($periodeLabel);
            case 'paliers':
                return 'Nouveaux paliers de points' . $public . ' – ' . $periodeLabel;
        }

        $sujets = array(
            'resultats' => 'les résultats des équipes',
            'perfs'     => 'les perfs du week-end',
            'cartons'   => 'les cartons pleins du week-end',
            'belles'    => 'victoires à la belle et remontadas',
        );
        $sujet = $sujets[$visuel] ?? '';
        if ($tour) {
            return 'Journée ' . (int) $tour . ' : ' . $sujet;
        }
        $sujet = self::majusculeInitiale($sujet);
        return $periodeLabel === '' ? $sujet : $sujet . ' – ' . self::minusculeInitiale($periodeLabel);
    }

    /**
     * Texte alternatif de l'image à la une : type de visuel, période, puis les
     * premières lignes de données (« Podium Top Progression de novembre 2026 :
     * 1. Léa MARTIN : +45 pts, … »).
     *
     * @param string   $visuel
     * @param string   $periodeLabel
     * @param string[] $lignes Données du visuel en texte (voir contenu()).
     * @param int|null $tour
     * @return string
     */
    public static function texteAlternatif($visuel, $periodeLabel, array $lignes, $tour = null) {
        $periode = self::minusculeInitiale(trim((string) $periodeLabel));
        $quand = $tour ? 'journée ' . (int) $tour . ' (' . $periode . ')' : $periode;
        $prefixes = array(
            'prog-mens' => 'Podium Top Progression de ' . $periode,
            'prog-ann'  => 'Podium Top Progression de la ' . $periode,
            'paliers'   => 'Nouveaux paliers de points de ' . $periode,
            'resultats' => 'Résultats des équipes, ' . $quand,
            'perfs'     => 'Top perfs, ' . $quand,
            'cartons'   => 'Joueurs invaincus, ' . $quand,
            'belles'    => 'Victoires à la belle, ' . $quand,
        );
        $alt = $prefixes[$visuel] ?? 'Visuel ' . $periode;

        $lignes = array_values(array_filter(array_map('trim', array_map('strval', $lignes)), 'strlen'));
        if (!$lignes) {
            return $alt;
        }
        $extrait = array_slice($lignes, 0, self::ALT_MAX_LIGNES);
        if (in_array($visuel, array('prog-mens', 'prog-ann'), true)) {
            foreach ($extrait as $i => $ligne) {
                $extrait[$i] = ($i + 1) . '. ' . $ligne;
            }
        }
        $reste = count($lignes) - count($extrait);
        return $alt . ' : ' . implode(', ', $extrait) . ($reste > 0 ? ' et ' . $reste . ' autre' . ($reste > 1 ? 's' : '') : '') . '.';
    }

    /**
     * Contenu de l'article en blocs Gutenberg : le visuel en entier (bloc
     * Image, sans le recadrage que les thèmes appliquent souvent à l'image à
     * la une), la légende en paragraphes
     * (une ligne vide sépare deux paragraphes, un retour à la ligne simple
     * reste dans le paragraphe), puis les données du visuel en liste — le
     * texte d'une image n'est lu ni par les moteurs de recherche ni par les
     * lecteurs d'écran —, puis le lien de fin.
     *
     * @param string   $legende   Texte de la publication (page « Réseaux sociaux »).
     * @param string[] $lignes    Données du visuel, une ligne par joueur ou équipe.
     * @param string   $titreListe Intertitre de la liste (vide : pas d'intertitre).
     * @param string   $lienUrl   Lien de fin (vide : pas de lien).
     * @param string   $lienTexte Texte du lien de fin.
     * @param array    $image     Visuel : ['id', 'url', 'alt'] (vide : pas d'image).
     * @return string
     */
    public static function contenu($legende, array $lignes = array(), $titreListe = '', $lienUrl = '', $lienTexte = '', array $image = array()) {
        $blocs = array();
        $id = (int) ($image['id'] ?? 0);
        $url = trim((string) ($image['url'] ?? ''));
        if ($id > 0 && preg_match('#^https?://#i', $url)) {
            $blocs[] = '<!-- wp:image {"id":' . $id . ',"sizeSlug":"full","linkDestination":"none"} -->' . "\n"
                . '<figure class="wp-block-image size-full"><img src="' . self::echapper($url, true) . '" alt="' . self::echapper((string) ($image['alt'] ?? ''), true) . '" class="wp-image-' . $id . '"/></figure>' . "\n"
                . '<!-- /wp:image -->';
        }
        foreach (self::paragraphes($legende) as $paragraphe) {
            $blocs[] = self::blocParagraphe(implode('<br>', array_map(array(__CLASS__, 'echapper'), $paragraphe)));
        }

        $lignes = array_values(array_filter(array_map('trim', array_map('strval', $lignes)), 'strlen'));
        if ($lignes) {
            if (trim((string) $titreListe) !== '') {
                $blocs[] = "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">" . self::echapper(trim($titreListe)) . "</h2>\n<!-- /wp:heading -->";
            }
            $items = array();
            foreach ($lignes as $ligne) {
                $items[] = "<!-- wp:list-item -->\n<li>" . self::echapper($ligne) . "</li>\n<!-- /wp:list-item -->";
            }
            $blocs[] = "<!-- wp:list -->\n<ul class=\"wp-block-list\">" . implode("\n", $items) . "</ul>\n<!-- /wp:list -->";
        }

        $lienUrl = trim((string) $lienUrl);
        if ($lienUrl !== '' && preg_match('#^https?://#i', $lienUrl)) {
            $texte = trim((string) $lienTexte) !== '' ? trim($lienTexte) : $lienUrl;
            $blocs[] = self::blocParagraphe('<a href="' . self::echapper($lienUrl, true) . '">' . self::echapper($texte) . '</a>');
        }

        return implode("\n\n", $blocs);
    }

    /**
     * Légende découpée en paragraphes : liste de paragraphes, chacun liste de
     * lignes non vides. Fins de ligne Windows et espaces en trop ignorés.
     *
     * @param string $legende
     * @return array<int, string[]>
     */
    public static function paragraphes($legende) {
        $texte = str_replace(array("\r\n", "\r"), "\n", (string) $legende);
        $paragraphes = array();
        foreach (preg_split('/\n[ \t]*\n/', $texte) as $bloc) {
            $lignes = array_values(array_filter(array_map('trim', explode("\n", $bloc)), 'strlen'));
            if ($lignes) {
                $paragraphes[] = $lignes;
            }
        }
        return $paragraphes;
    }

    private static function blocParagraphe($html) {
        return "<!-- wp:paragraph -->\n<p>" . $html . "</p>\n<!-- /wp:paragraph -->";
    }

    /**
     * Échappement HTML sans dépendre de WordPress. Les guillemets ne sont
     * encodés que dans un attribut : l'apostrophe reste lisible dans l'éditeur.
     */
    private static function echapper($texte, $attribut = false) {
        return htmlspecialchars((string) $texte, ($attribut ? ENT_QUOTES : ENT_NOQUOTES) | ENT_SUBSTITUTE, 'UTF-8');
    }

    private static function majusculeInitiale($texte) {
        return $texte === '' ? '' : mb_strtoupper(mb_substr($texte, 0, 1)) . mb_substr($texte, 1);
    }

    private static function minusculeInitiale($texte) {
        return $texte === '' ? '' : mb_strtolower(mb_substr($texte, 0, 1)) . mb_substr($texte, 1);
    }
}

}
