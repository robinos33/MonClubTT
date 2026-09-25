<?php if ( ! defined( 'ABSPATH' ) ) exit; ?>
<div class="wrap monclubtt-social">
    <h1 class="monclubtt-title">Réseaux sociaux</h1>
    <p>Générez un visuel prêt à poster à partir des données du club, téléchargez-le puis publiez-le sur Facebook ou Instagram.</p>

    <?php if (monclubtt_mois_sans_competition()): ?>
        <div class="notice notice-warning inline">
            <p>Début de saison (juillet à septembre) : les progressions reposent encore sur les points de la saison précédente et sont peu représentatives.</p>
        </div>
    <?php endif; ?>

    <div class="monclubtt-social-layout">
        <form class="monclubtt-social-controls" id="monclubtt-social-form">
            <fieldset>
                <legend>Visuel</legend>
                <label><input type="radio" name="visuel" value="prog-mens" checked> Top Progression du mois</label>
                <label><input type="radio" name="visuel" value="prog-ann"> Top Progression de la saison</label>
                <label><input type="radio" name="visuel" value="perfs"> Top perfs du dernier week-end de championnat</label>
            </fieldset>

            <fieldset>
                <legend>Format</legend>
                <label><input type="radio" name="format" value="carre" checked> Carré 1080 × 1080 (publication)</label>
                <label><input type="radio" name="format" value="story"> Story 1080 × 1920</label>
            </fieldset>

            <fieldset data-visuel="prog">
                <legend>Joueurs</legend>
                <label><input type="radio" name="sexe" value="MF" checked> Tous</label>
                <label><input type="radio" name="sexe" value="M"> Hommes</label>
                <label><input type="radio" name="sexe" value="F"> Femmes</label>
            </fieldset>

            <fieldset class="monclubtt-social-couleurs">
                <legend>Couleurs</legend>
                <label><input type="color" name="primaire"> Primaire</label>
                <label><input type="color" name="secondaire"> Secondaire</label>
                <label><input type="color" name="fond"> Fond</label>
                <button type="button" class="button-link" id="monclubtt-social-couleurs-defaut">Couleurs par défaut</button>
            </fieldset>

            <fieldset>
                <legend><label for="monclubtt-social-club">Nom affiché</label></legend>
                <input type="text" id="monclubtt-social-club" name="club" class="regular-text" value="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </fieldset>

            <fieldset>
                <legend><label for="monclubtt-social-message">Texte de la publication</label></legend>
                <textarea id="monclubtt-social-message" name="message" rows="7" class="large-text"></textarea>
                <p class="description">Pré-rempli d'après le visuel et modifiable, à copier avec l'image dans Facebook ou Instagram.</p>
                <p><button type="button" class="button" id="monclubtt-social-copier">Copier le texte</button></p>
            </fieldset>

            <p>
                <button type="button" class="button button-primary button-large" id="monclubtt-social-download" disabled>Télécharger le PNG</button>
            </p>
            <p class="monclubtt-social-status" id="monclubtt-social-status" role="status" aria-live="polite"></p>
            <p class="description">
                Les photos affichées sont celles associées aux joueurs dans la page <a href="<?php echo esc_url(admin_url('admin.php?page=monclubtt_joueurs')); ?>">Joueurs</a>.
                Le logo est l'icône du site (Réglages › Général).
            </p>
        </form>

        <div class="monclubtt-social-preview">
            <canvas id="monclubtt-social-canvas" width="1080" height="1080" aria-label="Aperçu du visuel"></canvas>
        </div>
    </div>
</div>
