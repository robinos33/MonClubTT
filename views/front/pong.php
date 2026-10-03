<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
wp_enqueue_style('monclubtt-pong-css');
wp_enqueue_script('monclubtt-pong-js');

$couleurs = monclubtt_get_couleurs();
$config   = array(
    'joueurs'    => $joueurs->getDonneesPong(),
    'adversaire' => $adversaire,
    'manches'    => $manches,
    'couleurs'   => $couleurs,
);
// Couleurs normalisées en « #rrggbb » par monclubtt_get_couleurs().
$style = '--pong-primaire:' . $couleurs['primaire'] . ';--pong-secondaire:' . $couleurs['secondaire'] . ';--pong-fond:' . $couleurs['fond'] . ';';
?>
<div class="monclubtt-pong" style="<?php echo esc_attr($style); ?>">
    <script type="application/json" class="monclubtt-pong-config"><?php
        // JSON_HEX_TAG : aucun « </script> » possible dans les noms.
        echo wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    ?></script>
    <noscript><p>Le jeu nécessite JavaScript.</p></noscript>
</div>
