<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_Constantes' ) ) {

    class MonClubTT_Constantes {

        const MONCLUBTT_ID_APPLICATION = 'monclubtt_id_application';
        const MONCLUBTT_MOT_DE_PASSE = 'monclubtt_mot_de_passe';
        const MONCLUBTT_NUM_CLUB = 'monclubtt_num_club';
        const MONCLUBTT_LICENCES_EXCLUES = 'monclubtt_licences_exclues';
        const MONCLUBTT_JOUEUR_PHOTOS = 'monclubtt_joueur_photos';
        /** '1' (défaut) : photos des joueurs affichées sur le site, le jeu et les visuels ; '0' : avatars dessinés. */
        const MONCLUBTT_AFFICHER_PHOTOS = 'monclubtt_afficher_photos';
        const MONCLUBTT_COULEURS = 'monclubtt_couleurs';
        const MONCLUBTT_LOGO = 'monclubtt_logo';
        const MONCLUBTT_PONG_ADVERSAIRES = 'monclubtt_pong_adversaires';
        const MONCLUBTT_PONG_SCORES = 'monclubtt_pong_scores';
        /** Musique de fond du jeu de pong : ID de média audio ou URL d'un fichier audio. */
        const MONCLUBTT_PONG_MUSIQUE = 'monclubtt_pong_musique';
        /** '1' (défaut) : top 10 mondial proposé comme adversaires du jeu de pong ; '0' : joueurs du club seulement. */
        const MONCLUBTT_PONG_PROS = 'monclubtt_pong_pros';
        /** Matchs récents partageables (id => données), pour les pages et images de partage. */
        const MONCLUBTT_PONG_MATCHS = 'monclubtt_pong_matchs';
        /** Compteurs du jeu de pong : parties jouées, parties terminées, date de départ. */
        const MONCLUBTT_PONG_COMPTEURS = 'monclubtt_pong_compteurs';
        /** Ancienne option (1.6.2), relue tant que les réglages n'ont pas été enregistrés. */
        const MONCLUBTT_SOCIAL_COULEURS = 'monclubtt_social_couleurs';

        /** Couleurs par défaut du club (tableaux et podium du site, visuels réseaux sociaux). */
        const COULEURS_DEFAUT = array(
            'primaire'   => '#2b7cb5',
            'secondaire' => '#d34328',
            'fond'       => '#f4f1ea',
            'maillot'    => '#2b7cb5',
        );

        /**
         * Adversaires par défaut du jeu de pong : top 10 mondial simple
         * (classement mondial WTT/ITTF, semaine 40 de 2026, vérifié sur le site
         * de la WTT le 4 octobre 2026), modifiable dans les réglages
         * car il change chaque semaine. Noms écrits comme sur le site de la WTT,
         * affichés tels quels dans le jeu.
         */
        const PONG_ADVERSAIRES_DEFAUT = array(
            'M' => array('WANG Chuqin', 'Felix LEBRUN', 'Sora MATSUSHIMA', 'Tomokazu HARIMOTO', 'Truls MOREGARD',
                         'LIN Yun-Ju', 'Hugo CALDERANO', 'LIN Shidong', 'Alexis LEBRUN', 'Dang QIU'),
            'F' => array('WANG Manyu', 'SUN Yingsha', 'Miwa HARIMOTO', 'KUAI Man', 'WANG Yidi',
                         'Hina HAYATA', 'CHEN Xingtong', 'ZHU Yuling', 'Sabine WINTER', 'CHEN Yi'),
        );

        /** Pays des adversaires par défaut, dans le même ordre (codes de PONG_PAYS). */
        const PONG_PAYS_DEFAUT = array(
            'M' => array('CHN', 'FRA', 'JPN', 'JPN', 'SWE', 'TPE', 'BRA', 'CHN', 'FRA', 'GER'),
            'F' => array('CHN', 'CHN', 'JPN', 'CHN', 'CHN', 'JPN', 'CHN', 'MAC', 'GER', 'CHN'),
        );

        /**
         * Sélections proposées pour les adversaires du pong (codes du CIO, comme
         * sur le site de la WTT) : le joueur porte une tenue à ses couleurs,
         * dessinée dans assets/mon-club-tt.js (MonClubTTAvatars SELECTIONS).
         */
        const PONG_PAYS = array(
            'CHN' => 'Chine',
            'JPN' => 'Japon',
            'KOR' => 'Corée du Sud',
            'TPE' => 'Taipei chinois',
            'HKG' => 'Hong Kong',
            'MAC' => 'Macao',
            'IND' => 'Inde',
            'FRA' => 'France',
            'GER' => 'Allemagne',
            'SWE' => 'Suède',
            'POR' => 'Portugal',
            'ROU' => 'Roumanie',
            'SLO' => 'Slovénie',
            'EGY' => 'Égypte',
            'BRA' => 'Brésil',
            'USA' => 'États-Unis',
        );

    }

}

if ( ! function_exists( 'monclubtt_date_locale' ) ) {

    /**
     * Formate un horodatage UTC dans le fuseau horaire configuré dans WordPress.
     *
     * Les horodatages du plugin sont stockés avec time() (donc en UTC), alors que
     * date_i18n() attend un timestamp déjà décalé : l'appeler directement affichait
     * l'heure UTC et non l'heure du site (ex. -2h pour Paris en été).
     *
     * @param int         $timestamp Horodatage UTC (issu de time()).
     * @param string|null $format    Format de date ; par défaut les formats du site.
     * @return string
     */
    function monclubtt_date_locale($timestamp, $format = null) {
        $timestamp = (int) $timestamp;

        if (null === $format) {
            $format = get_option('date_format') . ' à ' . get_option('time_format');
        }

        // wp_date() est la seule fonction WP qui convertit un timestamp UTC
        // vers le fuseau horaire du site (option « Fuseau horaire », DST incluse).
        if (function_exists('wp_date')) {
            $formatted = wp_date($format, $timestamp);
            if (false !== $formatted) {
                return $formatted;
            }
        }

        // Repli pour WordPress < 5.3 : on applique manuellement le décalage du site.
        return date_i18n($format, $timestamp + (int) (get_option('gmt_offset') * HOUR_IN_SECONDS));
    }

}

if ( ! function_exists( 'monclubtt_debut_saison' ) ) {

    /**
     * Une saison FFTT court du 1er juillet au 30 juin suivant.
     *
     * @param int|null $timestamp Horodatage à évaluer (par défaut : maintenant).
     * @return int Année de début de la saison en cours (ex. 2026 pour la saison 2026–2027).
     */
    function monclubtt_debut_saison($timestamp = null) {
        $timestamp = $timestamp ?? (function_exists('current_time') ? current_time('timestamp') : time());
        $mois  = (int) date_i18n('n', $timestamp);
        $annee = (int) date_i18n('Y', $timestamp);

        return ($mois >= 7) ? $annee : $annee - 1;
    }

}

if ( ! function_exists( 'monclubtt_mois_sans_competition' ) ) {

    /**
     * Juillet à septembre : aucune compétition FFTT n'a encore eu lieu depuis le
     * début de saison (1er juillet), donc les progressions mensuelles n'ont pas
     * de sens (les points affichés sont encore ceux de la saison précédente).
     *
     * @param int|null $timestamp Horodatage à évaluer (par défaut : maintenant).
     * @return bool
     */
    function monclubtt_mois_sans_competition($timestamp = null) {
        $timestamp = $timestamp ?? (function_exists('current_time') ? current_time('timestamp') : time());
        $mois = (int) date_i18n('n', $timestamp);

        return in_array($mois, array(7, 8, 9), true);
    }

}

if ( ! function_exists( 'monclubtt_photos_affichees' ) ) {

    /** Photos des joueurs (club et adversaires du pong) affichées ? Oui par défaut. */
    function monclubtt_photos_affichees() {
        return get_option(MonClubTT_Constantes::MONCLUBTT_AFFICHER_PHOTOS, '1') !== '0';
    }

}

if ( ! function_exists( 'monclubtt_pong_pros' ) ) {

    /** Top 10 mondial proposé dans le jeu de pong ? Oui par défaut. */
    function monclubtt_pong_pros() {
        return get_option(MonClubTT_Constantes::MONCLUBTT_PONG_PROS, '1') !== '0';
    }

}

if ( ! function_exists( 'monclubtt_get_joueur_photo_url' ) ) {

    /**
     * Retourne l'URL de la photo associée à un numéro de licence, ou une chaîne
     * vide si aucune photo n'est mappée. Les photos sont des pièces jointes de
     * la médiathèque WordPress, indexées par numéro de licence (identifiant
     * stable, contrairement au nom du joueur).
     *
     * Photos désactivées dans les réglages : '' partout, sauf avec $toujours
     * (liste des joueurs de l'administration, pour suivre les photos fournies).
     *
     * @param string $licence  Numéro de licence du joueur.
     * @param string $size     Taille WordPress souhaitée (thumbnail, medium, full…).
     * @param bool   $toujours Ignore le réglage « Afficher les photos ».
     * @return string URL de la photo ou '' si absente.
     */
    function monclubtt_get_joueur_photo_url($licence, $size = 'medium', $toujours = false) {
        if (empty($licence) || !function_exists('get_option')) {
            return '';
        }
        if (!$toujours && !monclubtt_photos_affichees()) {
            return '';
        }

        $map = get_option(MonClubTT_Constantes::MONCLUBTT_JOUEUR_PHOTOS, array());
        if (!is_array($map) || empty($map[$licence])) {
            return '';
        }

        $url = wp_get_attachment_image_url((int) $map[$licence], $size);
        return $url ? $url : '';
    }

}

if ( ! function_exists( 'monclubtt_sanitize_licences_exclues' ) ) {

    /**
     * Nettoie la liste des licences à exclure manuellement (un numéro par
     * ligne dans le formulaire) : ne garde que les suites de chiffres,
     * dédoublonnées et triées, une par ligne.
     *
     * @param string $valeur Valeur brute soumise par le formulaire.
     * @return string
     */
    function monclubtt_sanitize_licences_exclues($valeur) {
        if (!is_string($valeur)) {
            return '';
        }

        preg_match_all('/\d+/', $valeur, $matches);
        $licences = array_unique($matches[0]);
        sort($licences);

        return implode("\n", $licences);
    }

}

if ( ! function_exists( 'monclubtt_normaliser_couleurs' ) ) {

    /**
     * Couleurs du club (primaire, secondaire, fond, maillot) : uniquement des
     * « #rrggbb », en minuscules, repli sur la couleur par défaut pour toute
     * valeur invalide. Sert aussi de sanitize_callback du réglage.
     *
     * @param mixed $valeur Tableau brut (option ou saisie).
     * @return array{primaire: string, secondaire: string, fond: string, maillot: string}
     */
    function monclubtt_normaliser_couleurs($valeur) {
        $couleurs = array();
        foreach (MonClubTT_Constantes::COULEURS_DEFAUT as $cle => $defaut) {
            $saisie = is_array($valeur) && isset($valeur[$cle]) && is_string($valeur[$cle]) ? trim($valeur[$cle]) : '';
            $couleurs[$cle] = preg_match('/^#[0-9a-f]{6}$/i', $saisie) ? strtolower($saisie) : $defaut;
        }
        return $couleurs;
    }

}

if ( ! function_exists( 'monclubtt_normaliser_adversaires' ) ) {

    /**
     * Adversaires du jeu de pong : 10 hommes et 10 femmes, dans l'ordre du
     * classement, chacun avec un nom, l'ID d'une photo de la médiathèque
     * (0 = avatar dessiné) et le code de sa sélection ('' = couleurs du club).
     * Sans valeur enregistrée, la liste par défaut. Une ligne enregistrée avant
     * l'arrivée du champ pays reprend celui du joueur par défaut de même nom.
     *
     * @param mixed $valeur Tableau brut (option ou saisie).
     * @return array{M: array<int, array{nom: string, photo: int, pays: string}>, F: array<int, array{nom: string, photo: int, pays: string}>}
     */
    function monclubtt_normaliser_adversaires($valeur) {
        $paysParNom = array();
        foreach (MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT as $sexe => $noms) {
            foreach ($noms as $i => $nom) {
                $paysParNom[strtolower($nom)] = MonClubTT_Constantes::PONG_PAYS_DEFAUT[$sexe][$i];
            }
        }
        $adversaires = array();
        foreach (MonClubTT_Constantes::PONG_ADVERSAIRES_DEFAUT as $sexe => $noms) {
            $saisies = is_array($valeur) && isset($valeur[$sexe]) && is_array($valeur[$sexe]) ? array_values($valeur[$sexe]) : null;
            foreach ($noms as $i => $defaut) {
                $ligne = $saisies !== null && isset($saisies[$i]) && is_array($saisies[$i]) ? $saisies[$i] : null;
                if ($ligne === null) {
                    $adversaires[$sexe][] = $saisies === null
                        ? array('nom' => $defaut, 'photo' => 0, 'pays' => MonClubTT_Constantes::PONG_PAYS_DEFAUT[$sexe][$i])
                        : array('nom' => '', 'photo' => 0, 'pays' => '');
                    continue;
                }
                $nom = isset($ligne['nom']) && is_string($ligne['nom']) ? trim(preg_replace('/\s+/u', ' ', strip_tags($ligne['nom']))) : '';
                $nom = function_exists('mb_substr') ? mb_substr($nom, 0, 60) : substr($nom, 0, 60);
                if (array_key_exists('pays', $ligne)) {
                    $pays = is_string($ligne['pays']) ? strtoupper(trim($ligne['pays'])) : '';
                    $pays = isset(MonClubTT_Constantes::PONG_PAYS[$pays]) ? $pays : '';
                } else {
                    $pays = $paysParNom[strtolower($nom)] ?? '';
                }
                $adversaires[$sexe][] = array(
                    'nom'   => $nom,
                    'photo' => isset($ligne['photo']) && is_numeric($ligne['photo']) ? max(0, (int) $ligne['photo']) : 0,
                    'pays'  => $pays,
                );
            }
        }
        return $adversaires;
    }

}

if ( ! function_exists( 'monclubtt_get_couleurs' ) ) {

    /**
     * Couleurs du club enregistrées dans les réglages du plugin. Primaire,
     * secondaire et maillot habillent les tableaux, le podium du site et les visuels ; le
     * fond ne sert qu'aux visuels réseaux sociaux.
     *
     * @return array{primaire: string, secondaire: string, fond: string, maillot: string}
     */
    function monclubtt_get_couleurs() {
        $couleurs = get_option(MonClubTT_Constantes::MONCLUBTT_COULEURS, null);
        if (null === $couleurs) {
            $couleurs = get_option(MonClubTT_Constantes::MONCLUBTT_SOCIAL_COULEURS, null);
        }
        return monclubtt_normaliser_couleurs($couleurs);
    }

}

if ( ! function_exists( 'monclubtt_get_pong_adversaires' ) ) {

    /**
     * Adversaires du jeu de pong enregistrés dans les réglages (top 10
     * mondial messieurs et dames), liste par défaut sinon.
     *
     * @return array{M: array<int, array{nom: string, photo: int, pays: string}>, F: array<int, array{nom: string, photo: int, pays: string}>}
     */
    function monclubtt_get_pong_adversaires() {
        return monclubtt_normaliser_adversaires(get_option(MonClubTT_Constantes::MONCLUBTT_PONG_ADVERSAIRES, null));
    }

}

if ( ! function_exists( 'monclubtt_get_pong_musique_url' ) ) {

    /**
     * URL de la musique de fond du jeu de pong (réglages), '' sans musique.
     *
     * @return string
     */
    function monclubtt_get_pong_musique_url() {
        $valeur = trim((string) get_option(MonClubTT_Constantes::MONCLUBTT_PONG_MUSIQUE, ''));
        if ($valeur === '') {
            return '';
        }
        if (ctype_digit($valeur)) {
            $url = wp_get_attachment_url((int) $valeur);
            return $url ? $url : '';
        }
        return esc_url_raw($valeur, array('http', 'https'));
    }

}

if ( ! function_exists( 'monclubtt_get_logo_url' ) ) {

    /**
     * Logo du club (en-tête des visuels réseaux sociaux) : image choisie dans
     * les réglages du plugin, à défaut l'icône du site (Réglages › Général),
     * sinon chaîne vide.
     *
     * @param int $taille Taille souhaitée en pixels (côté).
     * @return string URL du logo ou ''.
     */
    function monclubtt_get_logo_url($taille = 256) {
        $logoId = (int) get_option(MonClubTT_Constantes::MONCLUBTT_LOGO, 0);
        if ($logoId) {
            $url = wp_get_attachment_image_url($logoId, array($taille, $taille));
            if ($url) {
                return $url;
            }
        }
        return (string) get_site_icon_url($taille);
    }

}

if ( ! function_exists( 'monclubtt_api_texte' ) ) {

    /**
     * Normalise un champ scalaire renvoye par l'API FFTT.
     *
     * Les reponses sont converties via json_decode(json_encode($xml), true) :
     * un element XML vide (<equb/>, renvoye par exemple pour une equipe exempte)
     * devient un tableau vide. esc_html() castant son argument en chaine, ce
     * tableau s'affichait litteralement « Array ».
     *
     * @param mixed  $valeur Valeur issue de l'API.
     * @param string $defaut Valeur de repli si le champ est vide.
     * @return string
     */
    function monclubtt_api_texte($valeur, $defaut = '') {
        if (is_array($valeur) || is_object($valeur) || null === $valeur) {
            return $defaut;
        }

        $valeur = trim((string) $valeur);

        return '' === $valeur ? $defaut : $valeur;
    }

}

/**
 * Autoloading des models
 */
if ( ! function_exists( 'monclubtt_autoload_models' ) ) {

    function monclubtt_autoload_models() {
        $repertoireModels = __DIR__ . '/models/';
        $models = glob($repertoireModels . "*.php");
        if ( ! is_array( $models ) ) {
            return;
        }
        foreach ($models as $model) {
            require_once $model;
        }
    }

}

monclubtt_autoload_models();
