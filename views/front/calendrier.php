<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
/**
 * Shortcode [monclubtt_calendrier].
 *
 * @var string $id      Identifiant unique du calendrier dans la page.
 * @var array  $donnees Événements et réglages lus par assets/mon-club-tt-calendrier.js.
 * @var int    $saison
 */
?>
<div class="monclubtt-div monclubtt-calendrier" id="<?php echo esc_attr($id); ?>" aria-label="<?php echo esc_attr('Calendrier ' . MonClubTT_Calendrier::libelleSaison($saison)); ?>">
    <script type="application/json" class="monclubtt-calendrier__donnees"><?php echo wp_json_encode($donnees, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?></script>

    <div class="mct-entete">
        <span class="mct-saison"><?php echo esc_html(MonClubTT_Calendrier::libelleSaison($saison)); ?></span>
        <button type="button" data-mct="export-ouvrir">Ajouter à mon agenda</button>
    </div>

    <div class="mct-barre">
        <div class="mct-mois-nav">
            <button type="button" data-mct="precedent" aria-label="Mois précédent">←</button>
            <select data-mct="mois" aria-label="Mois affiché"></select>
            <button type="button" data-mct="suivant" aria-label="Mois suivant">→</button>
        </div>
        <div class="mct-vues">
            <button type="button" data-mct="vue-mois" aria-pressed="true">Mois</button>
            <button type="button" data-mct="vue-liste" aria-pressed="false">Liste</button>
        </div>
    </div>

    <form class="mct-periode" data-mct="periode" aria-label="Choisir une période">
        <label for="<?php echo esc_attr($id); ?>-du">Du <input type="date" id="<?php echo esc_attr($id); ?>-du" data-mct="du" required></label>
        <label for="<?php echo esc_attr($id); ?>-au">au <input type="date" id="<?php echo esc_attr($id); ?>-au" data-mct="au" required></label>
        <button type="submit">Afficher</button>
    </form>

    <div class="mct-filtres" data-mct="filtres" role="group" aria-label="Type de rendez-vous"></div>
    <p class="mct-compte" data-mct="compte" aria-live="polite"></p>
    <div class="mct-grille" data-mct="grille" aria-label="Calendrier mensuel"></div>
    <div class="mct-agenda" data-mct="agenda"></div>
    <section class="mct-detail" data-mct="detail" hidden aria-live="polite"></section>

    <dialog class="mct-dialogue" data-mct="export" aria-labelledby="<?php echo esc_attr($id); ?>-export-titre">
        <button type="button" class="mct-fermer" data-mct="export-fermer">Fermer</button>
        <h2 id="<?php echo esc_attr($id); ?>-export-titre">Ajouter à mon agenda</h2>

        <h3>S'abonner</h3>
        <p>Votre agenda se met à jour tout seul quand le club ajoute ou déplace une date.</p>
        <div class="mct-abonnement">
            <a class="mct-principal" data-mct="lien-webcal" href="#">Apple Calendrier / Outlook</a>
            <a class="mct-principal" data-mct="lien-google" href="#" target="_blank" rel="noopener">Google Agenda →</a>
        </div>
        <p class="mct-note">Ou copiez l'adresse : <input type="text" readonly data-mct="adresse-flux" aria-label="Adresse d'abonnement"></p>

        <h3>Télécharger des dates</h3>
        <fieldset class="mct-export-options">
            <legend>Dates à télécharger</legend>
            <label><input type="radio" name="<?php echo esc_attr($id); ?>-portee" value="saison"> <span>Toute la saison<small>Toutes les catégories, tous les mois</small></span></label>
            <label><input type="radio" name="<?php echo esc_attr($id); ?>-portee" value="categorie"> <span>Ma catégorie, sur toute la saison<small data-mct="export-categorie"></small></span></label>
            <label><input type="radio" name="<?php echo esc_attr($id); ?>-portee" value="selection"> <span>La sélection affichée<small data-mct="export-selection"></small></span></label>
        </fieldset>
        <a class="mct-principal" data-mct="telecharger" href="#">Télécharger (.ics)</a>
        <p class="mct-note">Import ponctuel : les changements du club ne suivront pas. Préférez l'abonnement.</p>
    </dialog>

    <noscript><p><a href="<?php echo esc_url($donnees['ics']['saison']); ?>">Télécharger le calendrier de la saison (.ics)</a></p></noscript>
</div>
