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
                    <td>[joueurs]</td>
                </tr>
                <tr>
                    <th scope="row" class="check-column"></th>
                    <td>Ensemble des joueuses</td>
                    <td>[joueurs type='F']</td>
                </tr>
                <tr>
                    <th scope="row" class="check-column"></th>
                    <td>Ensemble des joueurs</td>
                    <td>[joueurs type='M']</td>
                </tr>
            </tbody>
        </table>
    </form>

    <h2>Liste des joueurs</h2>
    <?php
    $monclubtt_api     = AccesFFTTApi::getInstance();
    $monclubtt_numClub = ParametresPlugin::getNumClub();
    $monclubtt_updatedAt = $monclubtt_api->getCacheUpdatedAt('joueurs_club', array('numclu' => $monclubtt_numClub));
    if ($monclubtt_updatedAt !== false):
    ?>
        <p><em>Dernière mise à jour du cache : <?php echo esc_html(date_i18n('d/m/Y à H:i:s', $monclubtt_updatedAt)); ?></em></p>
    <?php endif; ?>
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Classement Off.</th>
            <th>Points Off.</th>
            <th>Points mensuels</th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php
        $monclubtt_joueurs = new Joueurs();
        foreach($monclubtt_joueurs->getJoueurs('MF') as $monclubtt_joueur):?>
            <?php
                /** @var Joueur $monclubtt_joueur */
            ?>
        <tr class="<?php echo esc_attr($monclubtt_joueur->getSexe()); ?>">
            <td class="bold"><?php echo esc_html($monclubtt_joueur->getNom()); ?></td>
            <td class="bold"><?php echo esc_html($monclubtt_joueur->getPrenom()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getClassementOfficiel()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getPointsOfficiels()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getPointsMensuels()); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

</div>
