<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_Constantes' ) ) {

    class MonClubTT_Constantes {

        const MONCLUBTT_ID_APPLICATION = 'monclubtt_id_application';
        const MONCLUBTT_MOT_DE_PASSE = 'monclubtt_mot_de_passe';
        const MONCLUBTT_NUM_CLUB = 'monclubtt_num_club';
        const MONCLUBTT_AFFICHAGE_PROGRESSIONS = 'monclubtt_affichage_progressions';

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

if ( ! function_exists( 'monclubtt_saison_debut_annee' ) ) {

    /**
     * Annee de debut de la saison sportive en cours.
     *
     * La saison FFTT court du 1er juillet au 30 juin : les licences de la
     * saison N/N+1 sont validees a partir du 1er juillet N. C'est ce seuil qui
     * sert a la fois au libelle de saison et a la detection des licences non
     * renouvelees.
     *
     * @param int|null $timestamp Horodatage UTC ; par defaut, maintenant.
     * @return int Annee de debut (ex. 2026 pour la saison 2026-2027).
     */
    function monclubtt_saison_debut_annee( $timestamp = null ) {
        if ( null === $timestamp ) {
            $mois  = (int) date_i18n( 'n' );
            $annee = (int) date_i18n( 'Y' );
        } else {
            $mois  = (int) date_i18n( 'n', (int) $timestamp );
            $annee = (int) date_i18n( 'Y', (int) $timestamp );
        }

        return ( $mois >= 7 ) ? $annee : $annee - 1;
    }

}

if ( ! function_exists( 'monclubtt_saison_libelle' ) ) {

    /**
     * Libelle de la saison en cours, ex. « Saison 2026–2027 ».
     *
     * @return string
     */
    function monclubtt_saison_libelle() {
        $debut = monclubtt_saison_debut_annee();

        return 'Saison ' . $debut . '–' . ( $debut + 1 );
    }

}

if ( ! function_exists( 'monclubtt_saison_debut_timestamp' ) ) {

    /**
     * Horodatage du 1er juillet ouvrant la saison en cours.
     *
     * @return int
     */
    function monclubtt_saison_debut_timestamp() {
        // Dates calendaires sans heure : on reste en UTC de bout en bout pour
        // que la comparaison avec les dates de validation ne depende pas du
        // fuseau de PHP ni de celui de WordPress.
        return (int) gmmktime( 0, 0, 0, 7, 1, monclubtt_saison_debut_annee() );
    }

}

if ( ! function_exists( 'monclubtt_parse_date_fftt' ) ) {

    /**
     * Convertit une date renvoyee par l'API FFTT en horodatage.
     *
     * Les dates de l'API sont au format JJ/MM/AAAA ; on accepte aussi la forme
     * ISO AAAA-MM-JJ par prudence, les endpoints n'etant pas homogenes.
     *
     * @param mixed $valeur Valeur brute issue de l'API.
     * @return int|null Horodatage, ou null si la date est absente ou illisible.
     */
    function monclubtt_parse_date_fftt( $valeur ) {
        $valeur = monclubtt_api_texte( $valeur );
        if ( '' === $valeur ) {
            return null;
        }

        if ( preg_match( '#^(\d{2})/(\d{2})/(\d{4})$#', $valeur, $m ) ) {
            return (int) gmmktime( 0, 0, 0, (int) $m[2], (int) $m[1], (int) $m[3] );
        }

        if ( preg_match( '#^(\d{4})-(\d{2})-(\d{2})#', $valeur, $m ) ) {
            return (int) gmmktime( 0, 0, 0, (int) $m[2], (int) $m[3], (int) $m[1] );
        }

        return null;
    }

}

if ( ! function_exists( 'monclubtt_progressions_visibles' ) ) {

    /**
     * Les progressions mensuelle et annuelle sont-elles pertinentes aujourd'hui ?
     *
     * La FFTT ne publie pas de classement mensuel en juillet ni en aout, et le
     * premier classement de la saison n'arrive qu'en cours de septembre :
     * jusque-la l'API sert encore les valeurs de juin, si bien que « pointm -
     * apointm » et « pointm - initm » decrivent la saison precedente. Une fois
     * la bascule faite, initm vaut pointm et la progression annuelle est nulle
     * pour tout le monde. Dans les deux cas l'affichage n'apprend rien, d'ou le
     * masquage par defaut de juillet a septembre inclus.
     *
     * Le reglage « Progressions et Top Progression » permet de forcer
     * l'affichage ou le masquage, la date de publication FFTT pouvant glisser.
     *
     * @return bool
     */
    function monclubtt_progressions_visibles() {
        $reglage = get_option( MonClubTT_Constantes::MONCLUBTT_AFFICHAGE_PROGRESSIONS, 'auto' );

        if ( 'oui' === $reglage ) {
            return true;
        }

        if ( 'non' === $reglage ) {
            return false;
        }

        return ! in_array( (int) date_i18n( 'n' ), array( 7, 8, 9 ), true );
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
