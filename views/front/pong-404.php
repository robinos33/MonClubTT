<?php
/**
 * Page 404 avec le jeu de pong (réglage Pong › Page 404).
 *
 * Chargé par MonClubTT_Plugin::pong_404() via template_include : le statut
 * HTTP reste 404. Thème classique : en-tête et pied du thème ; thème en
 * blocs : zones d'en-tête et de pied de l'éditeur de site.
 */
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

$monclubtt_bloc = function_exists('wp_is_block_theme') && wp_is_block_theme();

if ($monclubtt_bloc) {
    ?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="wp-site-blocks">
    <?php
    block_header_area();
} else {
    get_header();
}
?>
<main id="main" class="monclubtt-div monclubtt-404">
    <div class="monclubtt-404-entete">
        <h1 class="monclubtt-404-titre">Balle dehors&nbsp;!</h1>
        <p class="monclubtt-404-message">
            Cette page n'existe pas, ou plus. Le temps de retrouver ton chemin, une petite partie&nbsp;?
        </p>
        <div class="monclubtt-404-liens">
            <a class="monclubtt-404-accueil" href="<?php echo esc_url(home_url('/')); ?>">Retour à l'accueil</a>
            <?php get_search_form(); ?>
        </div>
    </div>
    <?php echo do_shortcode('[monclubtt_pong]'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- HTML du shortcode, échappé dans la vue ?>
</main>
<?php
if ($monclubtt_bloc) {
    block_footer_area();
    ?>
</div>
<?php wp_footer(); ?>
</body>
</html>
    <?php
} else {
    get_footer();
}
