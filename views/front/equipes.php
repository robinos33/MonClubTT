<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once(__DIR__ . '/header.php'); ?>
<div class="monclubtt-div">
    <?php
    foreach ($listeEquipes as $equipe) {
        if ($atts['iddiv'] === $equipe['iddiv'] && $atts['idpoule'] === $equipe['idpoule']) {
            $classementPoule = $api->getPouleClassement($equipe['iddiv'], $equipe['idpoule']);
            ?>
            <h4 class="monclubtt-equipe-titre">
                <?php echo esc_html($equipe['libdivision']); ?>
                <span class="monclubtt-equipe-sous-titre">— <?php echo esc_html($equipe['libequipe']); ?></span>
            </h4>

            <h5 class="monclubtt-section-titre">Classement</h5>
            <table class="monclubtt-table">
                <thead>
                <tr>
                    <th class="classement">Class.</th>
                    <th class="equipe">Équipe</th>
                    <th class="joues">Joués</th>
                    <th class="points">Pts</th>
                </tr>
                </thead>
                <tbody>
                <?php
                $i = 0;
                foreach ($classementPoule as $classement) {
                    $classEquipe = '';
                    if (preg_match('/' . preg_quote($classement['equipe'], '/') . '/', $equipe['libequipe'])) {
                        $classEquipe = 'equipe_club';
                    }
                    $i++;
                    $class = ($i % 2 == 0) ? 'odd' : 'even';
                    ?>
                    <tr class="<?php echo esc_attr(trim($class . ' ' . $classEquipe)); ?>">
                        <td class="center"><?php echo esc_html($classement['clt']); ?></td>
                        <td><?php echo esc_html($classement['equipe']); ?></td>
                        <td class="center"><?php echo esc_html($classement['joue']); ?></td>
                        <td class="center"><?php echo esc_html($classement['pts']); ?></td>
                    </tr>
                    <?php
                }
                ?>
                </tbody>
            </table>

            <?php $rencontresPoule = $api->getPouleRencontres($equipe['iddiv'], $equipe['idpoule']); ?>
            <h5 class="monclubtt-section-titre">Résultats par journée</h5>
            <?php
            $numJournee = 0;
            $journee    = '';
            foreach ($rencontresPoule as $rencontre) {
                // Parsing du lien pour extraire renc_id et is_retour
                $rencId   = '';
                $isRetour = 0;
                if (isset($rencontre['lien']) && is_string($rencontre['lien']) && !empty($rencontre['lien'])) {
                    $lienParams = array();
                    parse_str($rencontre['lien'], $lienParams);
                    $rencId   = $lienParams['renc_id'] ?? '';
                    $isRetour = (int) ($lienParams['is_retour'] ?? 0);
                }

                $scoreA    = !is_array($rencontre['scorea']) ? $rencontre['scorea'] : '';
                $scoreB    = !is_array($rencontre['scoreb']) ? $rencontre['scoreb'] : '';
                $hasScore  = $scoreA !== '' && $scoreB !== '';
                $hasDetail = $hasScore && !empty($rencId);

                if ($journee !== $rencontre['libelle']) {
                    if ($numJournee !== 0) {
                        echo '</tbody></table>';
                    }
                    $journee = $rencontre['libelle'];
                    $numJournee++;
                    echo '<table class="monclubtt-table monclubtt-rencontres" id="journee' . esc_attr($numJournee) . '">';
                    echo '<caption>' . esc_html($journee) . '</caption>';
                    echo '<tbody>';
                }
                ?>
                <tr class="monclubtt-rencontre-row<?php echo $hasDetail ? ' monclubtt-expandable' : ''; ?>"
                    <?php if ($hasDetail): ?>
                        data-renc-id="<?php echo esc_attr($rencId); ?>"
                        data-is-retour="<?php echo esc_attr($isRetour); ?>"
                        title="Cliquer pour voir la feuille de match"
                    <?php endif; ?>>
                    <td class="equipes left">
                        <?php if ($hasDetail): ?>
                            <span class="monclubtt-expand-icon" aria-hidden="true">▶</span>
                        <?php endif; ?>
                        <?php echo esc_html($rencontre['equa']); ?>
                    </td>
                    <td class="score center monclubtt-score"><?php echo esc_html($scoreA); ?></td>
                    <td class="tiret center monclubtt-tiret"> - </td>
                    <td class="score center monclubtt-score"><?php echo esc_html($scoreB); ?></td>
                    <td class="equipes right"><?php echo esc_html($rencontre['equb']); ?></td>
                </tr>
                <?php if ($hasDetail): ?>
                <tr class="monclubtt-feuille-row">
                    <td colspan="5">
                        <div class="monclubtt-feuille-content"></div>
                    </td>
                </tr>
                <?php endif; ?>
                <?php
            }
            if ($numJournee > 0) {
                echo '</tbody></table>';
            }

            // Dernière mise à jour
            if (method_exists($api, 'getCacheUpdatedAt')) {
                $u1      = $api->getCacheUpdatedAt('poule_classement', array('D1' => $equipe['iddiv'], 'cx_poule' => $equipe['idpoule']));
                $u2      = $api->getCacheUpdatedAt('poule_rencontres', array('D1' => $equipe['iddiv'], 'cx_poule' => $equipe['idpoule']));
                $updated = 0;
                if ($u1 !== false) { $updated = (int) max($updated, $u1); }
                if ($u2 !== false) { $updated = (int) max($updated, $u2); }
                if ($updated > 0) {
                    $formatted = date_i18n('d/m/Y H:i', $updated, false);
                    echo '<p class="monclubtt-updated-at">Dernière mise à jour : ' . esc_html($formatted) . '</p>';
                }
            }
        }
    }
    ?>
</div>
