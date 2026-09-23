<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap">
    <h1 class="monclubtt-title">Les joueurs </h1>
    <h2>Shortcodes</h2>
    <p>Insérez le shortcode dans la page ou l'article où vous désirez afficher la liste des joueurs</p>
    <form class="monclubtt-liste-admin">
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
                <tr>
                    <td id="cb" class="manage-column column-cb check-column"><label class="screen-reader-text" for="cb-select-all-1">Select All</label><input id="cb-select-all-1" type="checkbox"></td>
                    <th>Type</th>
                    <th>Shortcode</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <th scope="row" class="check-column"></th>
                    <td>Ensemble des joueurs et joueuses</td>
                    <td>[monclubtt_joueurs]</td>
                </tr>
                <tr>
                    <th scope="row" class="check-column"></th>
                    <td>Ensemble des joueuses</td>
                    <td>[monclubtt_joueurs type='F']</td>
                </tr>
                <tr>
                    <th scope="row" class="check-column"></th>
                    <td>Ensemble des joueurs</td>
                    <td>[monclubtt_joueurs type='M']</td>
                </tr>
            </tbody>
        </table>
    </form>

    <h2>Liste des joueurs</h2>
    <?php
    $monclubtt_api     = MonClubTT_AccesFFTTApi::getInstance();
    $monclubtt_numClub = MonClubTT_ParametresPlugin::getNumClub();
    $monclubtt_updatedAt = $monclubtt_api->getCacheUpdatedAt('joueurs_club', array('numclu' => $monclubtt_numClub));
    if ($monclubtt_updatedAt !== false):
    ?>
        <p><em>Dernière mise à jour du cache : <?php echo esc_html(monclubtt_date_locale($monclubtt_updatedAt, 'd/m/Y à H:i:s')); ?></em></p>
    <?php endif; ?>
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Classement Off.</th>
            <th>Points Off.</th>
            <th>Points mensuels</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php
        $monclubtt_joueurs = new MonClubTT_Joueurs();
        foreach($monclubtt_joueurs->getJoueurs('MF') as $monclubtt_joueur):?>
            <?php
                /** @var MonClubTT_Joueur $monclubtt_joueur */
            ?>
        <tr class="<?php echo esc_attr($monclubtt_joueur->getSexe()); ?>" data-licence="<?php echo esc_attr($monclubtt_joueur->getLicence()); ?>">
            <td class="bold"><?php echo esc_html($monclubtt_joueur->getNom()); ?></td>
            <td class="bold"><?php echo esc_html($monclubtt_joueur->getPrenom()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getClassementOfficiel()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getPointsOfficiels()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getPointsMensuels()); ?></td>
            <td>
                <button type="button" class="button monclubtt-exclude-joueur" data-licence="<?php echo esc_attr($monclubtt_joueur->getLicence()); ?>">
                    Retirer de la liste
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>

<?php
wp_add_inline_script('monclubtt-js', 'jQuery(document).ready(function($) {
    $(".monclubtt-exclude-joueur").on("click", function() {
        var $button = $(this);
        var $row = $button.closest("tr");
        var licence = $button.data("licence");

        if (!window.confirm("Retirer ce joueur de la liste ? Il ne réapparaîtra plus, même après une synchronisation, jusqu\'à ce que vous retiriez son numéro de licence dans les réglages du plugin.")) {
            return;
        }

        $button.prop("disabled", true);

        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "monclubtt_exclude_joueur",
                nonce: ' . wp_json_encode(wp_create_nonce('monclubtt_exclude_joueur_nonce')) . ',
                licence: licence
            },
            success: function(response) {
                if (response.success) {
                    $row.fadeOut(200, function() { $row.remove(); });
                } else {
                    window.alert(response.data && response.data.message ? response.data.message : "Erreur lors du retrait du joueur");
                    $button.prop("disabled", false);
                }
            },
            error: function() {
                window.alert("Erreur de communication avec le serveur");
                $button.prop("disabled", false);
            }
        });
    });
});');
?>
