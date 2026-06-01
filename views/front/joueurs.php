<?php
if ( ! defined( 'ABSPATH' ) ) exit;
require_once(__DIR__ . '/header.php'); ?><?php
$mois_fr    = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$moisLabel  = ucfirst($mois_fr[(int)date_i18n('n') - 1]) . ' ' . date_i18n('Y');
$annee      = (int) date_i18n('Y');
$saisonDebut = ((int)date_i18n('n') >= 9) ? $annee : $annee - 1;
$saisonLabel = 'Saison ' . $saisonDebut . '–' . ($saisonDebut + 1);

$joueursList = [];
$playersData = [];
foreach ($joueurs->getJoueurs($atts['type']) as $joueur) {
    if (!is_null($joueur->getClassement()->getClassementOfficiel())) {
        $joueursList[] = $joueur;
        $playersData[] = [
            'nom'    => $joueur->getNom(),
            'prenom' => $joueur->getPrenom(),
            'sex'    => $joueur->getSexe(),
            'cl'     => $joueur->getClassement()->getClassementOfficiel(),
            'pts'    => (float) $joueur->getClassement()->getPointsOfficiels(),
            'mens'   => (float) $joueur->getClassement()->getPointsMensuels(),
            'dm'     => (float) $joueur->getClassement()->getProgressionMensuelle(),
            'da'     => (float) $joueur->getClassement()->getProgressionAnnuelle(),
        ];
    }
}
?>
<?php if (!empty($playersData)): ?>
<script>
var DataPingTopProg = {
    players:     <?php echo wp_json_encode($playersData); ?>,
    moisLabel:   <?php echo wp_json_encode($moisLabel); ?>,
    saisonLabel: <?php echo wp_json_encode($saisonLabel); ?>
};
</script>
<?php endif; ?>
<div class="DataPing_div">

    <?php if ($updatedAt !== false): ?>
        <p class="dataping-updated-at">
            Dernière mise à jour : <?php echo esc_html(date_i18n('d/m/Y à H:i:s', $updatedAt)); ?>
        </p>
    <?php endif; ?>

    <?php if (!empty($playersData)): ?>
    <div class="dataping-top-prog">

        <div class="tp-head">
            <div>
                <h2 class="tp-h1">Top Progression</h2>
                <p class="tp-sub">Les joueurs qui grimpent le plus au classement officiel</p>
            </div>
            <div class="tp-toggle" id="dataping-tp-toggle">
                <button data-mode="mens" class="tp-on">📈 Progression mensuelle</button>
                <button data-mode="ann">🗓️ Progression annuelle</button>
            </div>
        </div>

        <div class="tp-podium-card">
            <div class="tp-pc-band"></div>
            <div class="tp-pc-title">
                <svg class="tp-icn" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M3 17l5-6 4 3 5-7 4 5"></path>
                </svg>
                <h3 id="dataping-tp-subtitle">Top 3 — gains sur le mois</h3>
                <span class="tp-tag" id="dataping-tp-tag"><?php echo esc_html($moisLabel); ?></span>
            </div>
            <div class="tp-stage-host" id="dataping-tp-stage-host">
                <div class="tp-scalable" id="dataping-tp-scalable">
                    <div class="tp-stage" id="dataping-tp-stage">
                        <svg class="tp-watermark" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="47" fill="#2b3a4f"/>
                            <g transform="rotate(-32 50 52)">
                                <ellipse cx="44" cy="42" rx="18" ry="20" fill="#2b3a4f"/>
                            </g>
                        </svg>
                        <svg class="tp-podium" id="dataping-tp-podium-svg" viewBox="0 0 720 498" aria-label="Podium"></svg>
                    </div>
                    <div class="tp-nameplates" id="dataping-tp-nameplates"></div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <table class="dataping-table listeJoueurs sortableTable">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Cl. Off.</th>
            <th>Pts Off.</th>
            <th>Pts Mens.</th>
            <th>↕ Mens.</th>
            <th>↕ Ann.</th>
        </tr>
        </thead>
        <tbody>
            <?php
            $i = 0;
            foreach ($joueursList as $joueur) {
                $i++;
                $class    = ($i % 2 == 0) ? 'odd' : 'even';
                $class   .= ' ' . $joueur->getSexe();
                $progMens = $joueur->getClassement()->getProgressionMensuelle();
                $progAnn  = $joueur->getClassement()->getProgressionAnnuelle();
                ?>
                <tr class="<?php echo esc_attr($class); ?>">
                    <td class="dataping-nom"><?php echo esc_html($joueur->getNom()); ?></td>
                    <td><?php echo esc_html($joueur->getPrenom()); ?></td>
                    <td class="center"><?php echo esc_html($joueur->getClassement()->getClassementOfficiel()); ?></td>
                    <td class="center"><?php echo esc_html($joueur->getClassement()->getPointsOfficiels()); ?></td>
                    <td class="center"><?php echo esc_html($joueur->getClassement()->getPointsMensuels()); ?></td>
                    <td class="center">
                        <?php if ($progMens > 0): ?>
                            <span class="dataping-badge dataping-badge--up">+<?php echo esc_html($progMens); ?></span>
                        <?php elseif ($progMens < 0): ?>
                            <span class="dataping-badge dataping-badge--down"><?php echo esc_html($progMens); ?></span>
                        <?php else: ?>
                            <span class="dataping-badge dataping-badge--neutral">—</span>
                        <?php endif; ?>
                    </td>
                    <td class="center">
                        <?php if ($progAnn > 0): ?>
                            <span class="dataping-badge dataping-badge--up">+<?php echo esc_html($progAnn); ?></span>
                        <?php elseif ($progAnn < 0): ?>
                            <span class="dataping-badge dataping-badge--down"><?php echo esc_html($progAnn); ?></span>
                        <?php else: ?>
                            <span class="dataping-badge dataping-badge--neutral">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>
