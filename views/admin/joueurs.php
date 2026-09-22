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
    <?php
    $monclubtt_joueurs       = new MonClubTT_Joueurs();
    $monclubtt_nonRenouveles = $monclubtt_joueurs->getNonRenouveles();
    ?>
    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Classement Off.</th>
            <th>Points Off.</th>
            <th>Points mensuels</th>
            <th>Licence validée le</th>
        </tr>
        </thead>
        <tbody id="the-list">
        <?php
        foreach($monclubtt_joueurs->getJoueurs('MF') as $monclubtt_joueur):?>
            <?php
                /** @var MonClubTT_Joueur $monclubtt_joueur */
                $monclubtt_validation = $monclubtt_joueur->getDateValidation();
            ?>
        <tr class="<?php echo esc_attr($monclubtt_joueur->getSexe()); ?>">
            <td class="bold"><?php echo esc_html($monclubtt_joueur->getNom()); ?></td>
            <td class="bold"><?php echo esc_html($monclubtt_joueur->getPrenom()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getClassementOfficiel()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getPointsOfficiels()); ?></td>
            <td><?php echo esc_html($monclubtt_joueur->getClassement()->getPointsMensuels()); ?></td>
            <td>
                <?php
                echo null === $monclubtt_validation
                    ? '<em>non communiquée</em>'
                    : esc_html(gmdate('d/m/Y', $monclubtt_validation));
                ?>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if (!empty($monclubtt_nonRenouveles)): ?>
        <h2>Licences non renouvelées (<?php echo count($monclubtt_nonRenouveles); ?>)</h2>
        <p>
            Ces licenciés sont toujours rattachés au club par l'API FFTT, mais leur
            licence a été validée avant le 1<sup>er</sup> juillet
            <?php echo esc_html(monclubtt_saison_debut_annee()); ?>. Ils sont
            masqués du site public et des données exposées par le plugin.
        </p>
        <table class="wp-list-table widefat fixed striped posts">
            <thead>
            <tr>
                <th>Nom</th>
                <th>Prénom</th>
                <th>Points mensuels</th>
                <th>Dernière validation</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($monclubtt_nonRenouveles as $monclubtt_ancien): ?>
                <?php /** @var MonClubTT_Joueur $monclubtt_ancien */ ?>
                <tr>
                    <td class="bold"><?php echo esc_html($monclubtt_ancien->getNom()); ?></td>
                    <td class="bold"><?php echo esc_html($monclubtt_ancien->getPrenom()); ?></td>
                    <td><?php echo esc_html($monclubtt_ancien->getClassement()->getPointsMensuels()); ?></td>
                    <td><?php echo esc_html(gmdate('d/m/Y', $monclubtt_ancien->getDateValidation())); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
