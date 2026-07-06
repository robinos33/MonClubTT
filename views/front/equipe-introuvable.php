<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
?>
<div class="monclubtt-div">
    <div class="monclubtt-introuvable" role="status">
        <span class="monclubtt-introuvable-icon" aria-hidden="true">&#9888;</span>
        <h4 class="monclubtt-introuvable-titre">
            <?php echo esc_html__( 'Poule not available', 'mon-club-tt' ); ?>
        </h4>
        <p class="monclubtt-introuvable-message">
            <?php echo esc_html__( 'The standings and results for this team are not available at the moment. This usually happens between two phases of the championship or once the season is over, when the federation removes the pools from its database.', 'mon-club-tt' ); ?>
        </p>
        <p class="monclubtt-introuvable-message">
            <?php echo esc_html__( 'This page will display the data again automatically as soon as a new pool is available.', 'mon-club-tt' ); ?>
        </p>
    </div>
</div>
