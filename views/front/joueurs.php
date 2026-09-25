<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
require_once(__DIR__ . '/header.php'); ?><?php
$mois_fr    = ['janvier','février','mars','avril','mai','juin','juillet','août','septembre','octobre','novembre','décembre'];
$moisLabel  = ucfirst($mois_fr[(int)date_i18n('n') - 1]) . ' ' . date_i18n('Y');
$saisonDebut = monclubtt_debut_saison();
$saisonLabel = 'Saison ' . $saisonDebut . '–' . ($saisonDebut + 1);
$sansCompetition = monclubtt_mois_sans_competition();

$joueursList = [];
foreach ($joueurs->getJoueurs($atts['type']) as $joueur) {
    if (!is_null($joueur->getClassement()->getClassementOfficiel())) {
        $joueursList[] = $joueur;
    }
}
$playersData = $joueurs->getDonneesTopProgression($atts['type']);

// Tri par défaut : points officiels décroissants (les mieux classés en premier).
usort($joueursList, function ($a, $b) {
    return (float) $b->getClassement()->getPointsOfficiels() <=> (float) $a->getClassement()->getPointsOfficiels();
});

// Bande de stats club, calculée sur une liste de joueurs (triée par points décroissants).
$calculerStats = function (array $liste) use ($sansCompetition) {
    $nb        = count($liste);
    $somme     = 0;
    $enHausse  = 0;
    foreach ($liste as $joueur) {
        $cl     = $joueur->getClassement();
        $somme += (float) ($sansCompetition ? $cl->getProgressionAnnuelle() : $cl->getProgressionMensuelle());
        if ($cl->getProgressionMensuelle() > 0) {
            $enHausse++;
        }
    }
    $moyenne = $nb ? round($somme / $nb, 1) : 0;
    return [
        'nb'            => $nb,
        'meilleur'      => $nb ? $liste[0] : null,
        'enHausse'      => $enHausse,
        'moyenneLabel'  => ($moyenne > 0 ? '+' : '') . number_format_i18n($moyenne, 1),
        'moyenneClasse' => $moyenne > 0 ? 'up' : ($moyenne < 0 ? 'down' : 'neutral'),
    ];
};

$parSexe = ['M' => [], 'F' => []];
$avecPhotos = false;
foreach ($joueursList as $joueur) {
    if (isset($parSexe[$joueur->getSexe()])) {
        $parSexe[$joueur->getSexe()][] = $joueur;
    }
    if ($joueur->getPhotoUrl() !== '') {
        $avecPhotos = true;
    }
}
$nbJoueurs = count($joueursList);

// Filtre Tous / Hommes / Femmes : seulement sur la liste mixte, et s'il y a de quoi filtrer.
$avecFiltre = $atts['type'] === 'MF' && !empty($parSexe['M']) && !empty($parSexe['F']);
$groupesStats = ['MF' => $calculerStats($joueursList)];
if ($avecFiltre) {
    $groupesStats['M'] = $calculerStats($parSexe['M']);
    $groupesStats['F'] = $calculerStats($parSexe['F']);
}
?>
<?php if (!empty($playersData) && !$sansCompetition):
    wp_localize_script('monclubtt-js', 'MonClubTTTopProg', array(
        'players'     => $playersData,
        'moisLabel'   => $moisLabel,
        'saisonLabel' => $saisonLabel,
        'maillot'     => monclubtt_get_couleurs()['maillot'],
    ));
endif; ?>
<div class="monclubtt-div">

    <?php if ($updatedAt !== false): ?>
        <p class="monclubtt-updated-at">
            Dernière mise à jour : <?php echo esc_html(monclubtt_date_locale($updatedAt, 'd/m/Y à H:i:s')); ?>
        </p>
    <?php endif; ?>

    <?php if ($avecFiltre): ?>
    <div class="monclubtt-filtres" role="group" aria-label="Filtrer les joueurs">
        <button type="button" class="monclubtt-filtre" data-filtre="MF" aria-pressed="true">Tous <span class="monclubtt-filtre-nb"><?php echo esc_html(number_format_i18n($nbJoueurs)); ?></span></button>
        <button type="button" class="monclubtt-filtre" data-filtre="M" aria-pressed="false">Hommes <span class="monclubtt-filtre-nb"><?php echo esc_html(number_format_i18n(count($parSexe['M']))); ?></span></button>
        <button type="button" class="monclubtt-filtre" data-filtre="F" aria-pressed="false">Femmes <span class="monclubtt-filtre-nb"><?php echo esc_html(number_format_i18n(count($parSexe['F']))); ?></span></button>
    </div>
    <?php endif; ?>

    <?php if (!empty($playersData) && !$sansCompetition): ?>
    <?php $couleurs = monclubtt_get_couleurs(); // couleurs du club (réglages), le fond ne s'applique qu'aux visuels ?>
    <div class="monclubtt-top-prog" style="<?php echo esc_attr('--dtp-primaire: ' . $couleurs['primaire'] . '; --dtp-secondaire: ' . $couleurs['secondaire']); ?>">

        <div class="tp-head">
            <div>
                <h2 class="tp-h1">Top Progression</h2>
                <p class="tp-sub">Les joueurs qui grimpent le plus au classement officiel</p>
            </div>
            <div class="tp-toggle" id="monclubtt-tp-toggle">
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
                <h3 id="monclubtt-tp-subtitle">Top 3 — gains sur le mois</h3>
                <span class="tp-tag" id="monclubtt-tp-tag"><?php echo esc_html($moisLabel); ?></span>
            </div>
            <div class="tp-stage-host" id="monclubtt-tp-stage-host">
                <div class="tp-scalable" id="monclubtt-tp-scalable">
                    <div class="tp-stage" id="monclubtt-tp-stage">
                        <svg class="tp-watermark" viewBox="0 0 100 100">
                            <circle cx="50" cy="50" r="47" fill="#2b3a4f"/>
                            <g transform="rotate(-32 50 52)">
                                <ellipse cx="44" cy="42" rx="18" ry="20" fill="#2b3a4f"/>
                            </g>
                        </svg>
                        <svg class="tp-podium" id="monclubtt-tp-podium-svg" viewBox="0 0 720 498" aria-label="Podium"></svg>
                    </div>
                    <div class="tp-nameplates" id="monclubtt-tp-nameplates"></div>
                </div>
            </div>
        </div>

    </div>
    <?php endif; ?>

    <?php if ($nbJoueurs > 0):
        foreach ($groupesStats as $groupe => $stats): ?>
    <ul class="monclubtt-stats" data-filtre="<?php echo esc_attr($groupe); ?>"<?php echo $groupe !== 'MF' ? ' hidden' : ''; ?>>
        <li class="monclubtt-stat">
            <span class="monclubtt-stat-val"><?php echo esc_html(number_format_i18n($stats['nb'])); ?></span>
            <span class="monclubtt-stat-lbl"><?php echo esc_html($groupe === 'F' ? 'Joueuses classées' : 'Joueurs classés'); ?></span>
        </li>
        <li class="monclubtt-stat">
            <span class="monclubtt-stat-val"><?php echo esc_html($stats['meilleur']->getClassement()->getClassementOfficiel()); ?></span>
            <span class="monclubtt-stat-lbl">Meilleur classement</span>
            <span class="monclubtt-stat-sub"><?php echo esc_html($stats['meilleur']->getPrenom() . ' ' . $stats['meilleur']->getNom()); ?></span>
        </li>
        <li class="monclubtt-stat">
            <span class="monclubtt-stat-val monclubtt-stat-val--<?php echo esc_attr($stats['moyenneClasse']); ?>"><?php echo esc_html($stats['moyenneLabel']); ?></span>
            <span class="monclubtt-stat-lbl"><?php echo esc_html($sansCompetition ? 'Progression moyenne sur la saison' : 'Progression moyenne ce mois'); ?></span>
            <span class="monclubtt-stat-sub"><?php echo esc_html($groupe === 'F' ? 'pts par joueuse' : 'pts par joueur'); ?></span>
        </li>
        <?php if (!$sansCompetition): ?>
        <li class="monclubtt-stat">
            <span class="monclubtt-stat-val"><?php echo esc_html(number_format_i18n($stats['enHausse'])); ?></span>
            <span class="monclubtt-stat-lbl">En hausse ce mois</span>
            <span class="monclubtt-stat-sub"><?php echo esc_html($moisLabel); ?></span>
        </li>
        <?php endif; ?>
    </ul>
    <?php endforeach;
    endif; ?>

    <table class="monclubtt-table listeJoueurs sortableTable">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Cl. Off.</th>
            <th>Pts Off.</th>
            <?php if (!$sansCompetition): ?>
            <th>Pts Mens.</th>
            <th>↕ Mens.</th>
            <?php endif; ?>
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
                    <td class="monclubtt-nom">
                        <?php if ($avecPhotos): ?>
                            <?php if ($joueur->getPhotoUrl() !== ''): ?>
                                <span class="monclubtt-vignette"><img src="<?php echo esc_url($joueur->getPhotoUrl()); ?>" alt="" loading="lazy" decoding="async"></span>
                            <?php else: ?>
                                <?php // Initiales via attr() CSS : aucun texte ajouté, le tri (textContent) reste sur le nom. ?>
                                <span class="monclubtt-vignette monclubtt-vignette--vide" data-initiales="<?php echo esc_attr(mb_substr((string) $joueur->getPrenom(), 0, 1) . mb_substr((string) $joueur->getNom(), 0, 1)); ?>" aria-hidden="true"></span>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php echo esc_html($joueur->getNom()); ?>
                    </td>
                    <td><?php echo esc_html($joueur->getPrenom()); ?></td>
                    <td class="center"><?php echo esc_html($joueur->getClassement()->getClassementOfficiel()); ?></td>
                    <td class="center"><?php echo esc_html($joueur->getClassement()->getPointsOfficiels()); ?></td>
                    <?php if (!$sansCompetition): ?>
                    <td class="center"><?php echo esc_html($joueur->getClassement()->getPointsMensuels()); ?></td>
                    <td class="center">
                        <?php if ($progMens > 0): ?>
                            <span class="monclubtt-badge monclubtt-badge--up">+<?php echo esc_html($progMens); ?></span>
                        <?php elseif ($progMens < 0): ?>
                            <span class="monclubtt-badge monclubtt-badge--down"><?php echo esc_html($progMens); ?></span>
                        <?php else: ?>
                            <span class="monclubtt-badge monclubtt-badge--neutral">—</span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="center">
                        <?php if ($progAnn > 0): ?>
                            <span class="monclubtt-badge monclubtt-badge--up">+<?php echo esc_html($progAnn); ?></span>
                        <?php elseif ($progAnn < 0): ?>
                            <span class="monclubtt-badge monclubtt-badge--down"><?php echo esc_html($progAnn); ?></span>
                        <?php else: ?>
                            <span class="monclubtt-badge monclubtt-badge--neutral">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php
            }
            ?>
        </tbody>
    </table>
</div>
