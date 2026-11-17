<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound

/** Valeurs disponibles sur le site, pour les attributs à slug. */
$monclubtt_slugs = function ($cle) {
    $taxonomies = array(
        'series'      => array('monclubtt_serie', 'Séries'),
        'categories'  => array('monclubtt_categorie', 'Catégories'),
        'commissions' => array('monclubtt_commission', 'Commissions'),
    );
    if (!isset($taxonomies[$cle]) || !taxonomy_exists($taxonomies[$cle][0])) {
        return null;
    }
    $termes = get_terms(array('taxonomy' => $taxonomies[$cle][0], 'hide_empty' => false));
    return array(
        'libelle'   => $taxonomies[$cle][1],
        'taxonomie' => $taxonomies[$cle][0],
        'termes'    => is_array($termes) ? $termes : array(),
    );
};
$monclubtt_html_autorise = array('code' => array());
?>
<div class="wrap monclubtt-shortcodes">
    <h1 class="monclubtt-title">Shortcodes</h1>
    <p>À coller dans une page ou un article (bloc <strong>Code court</strong> de l'éditeur, ou directement dans un paragraphe).
        Cliquez sur un exemple pour le copier.</p>

    <nav class="monclubtt-shortcodes__sommaire" aria-label="Sommaire des shortcodes">
        <?php foreach (MonClubTT_Shortcodes::catalogue() as $groupe) : ?>
            <p><strong><?php echo esc_html($groupe['titre']); ?></strong> :
                <?php
                $liens = array();
                foreach ($groupe['shortcodes'] as $sc) {
                    $liens[] = '<a href="#' . esc_attr($sc['tag']) . '">' . esc_html($sc['titre']) . '</a>';
                }
                echo implode(' · ', $liens); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé ci-dessus.
                ?>
            </p>
        <?php endforeach; ?>
    </nav>

    <?php foreach (MonClubTT_Shortcodes::catalogue() as $groupe) : ?>
        <h2 class="monclubtt-shortcodes__groupe"><?php echo esc_html($groupe['titre']); ?></h2>

        <?php foreach ($groupe['shortcodes'] as $sc) : ?>
            <section class="monclubtt-shortcodes__carte" id="<?php echo esc_attr($sc['tag']); ?>" aria-labelledby="<?php echo esc_attr($sc['tag'] . '-titre'); ?>">
                <h3 id="<?php echo esc_attr($sc['tag'] . '-titre'); ?>">
                    <?php echo esc_html($sc['titre']); ?>
                    <code>[<?php echo esc_html($sc['tag']); ?>]</code>
                </h3>
                <p><?php echo esc_html($sc['description']); ?>
                    <?php if (!empty($sc['admin'])) : ?>
                        Données : <a href="<?php echo esc_url(admin_url($sc['admin'][1])); ?>"><?php echo esc_html($sc['admin'][0]); ?></a>.
                    <?php endif; ?>
                </p>

                <ul class="monclubtt-shortcodes__exemples">
                    <?php foreach ($sc['exemples'] as $exemple => $explication) : ?>
                        <li>
                            <button type="button" class="monclubtt-shortcodes__copier" data-shortcode="<?php echo esc_attr($exemple); ?>">
                                <code><?php echo esc_html($exemple); ?></code>
                                <span class="monclubtt-shortcodes__etat" aria-live="polite">Copier</span>
                            </button>
                            <span class="description"><?php echo esc_html($explication); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($sc['attributs']) : ?>
                    <details>
                        <summary>Attributs (<?php echo (int) count($sc['attributs']); ?>)</summary>
                        <table class="widefat striped">
                            <thead><tr><th scope="col">Attribut</th><th scope="col">Valeurs</th><th scope="col">Défaut</th><th scope="col">Rôle</th></tr></thead>
                            <tbody>
                                <?php foreach ($sc['attributs'] as $attr) : ?>
                                    <tr>
                                        <td><code><?php echo esc_html($attr[0]); ?></code></td>
                                        <td><?php echo wp_kses($attr[1], $monclubtt_html_autorise); ?></td>
                                        <td><?php echo wp_kses($attr[2], $monclubtt_html_autorise); ?></td>
                                        <td><?php echo wp_kses($attr[3], $monclubtt_html_autorise); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </details>
                <?php endif; ?>

                <?php foreach ((array) ($sc['slugs'] ?? array()) as $cle) :
                    $valeurs = $monclubtt_slugs($cle);
                    if ($valeurs === null) {
                        continue;
                    } ?>
                    <p class="monclubtt-shortcodes__slugs">
                        <?php echo esc_html($valeurs['libelle']); ?> du site :
                        <?php if ($valeurs['termes']) : ?>
                            <?php
                            $codes = array();
                            foreach ($valeurs['termes'] as $terme) {
                                $codes[] = '<code title="' . esc_attr($terme->name) . '">' . esc_html($terme->slug) . '</code>';
                            }
                            echo implode(', ', $codes); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- échappé ci-dessus.
                            ?>
                        <?php else : ?>
                            <em>aucune pour l'instant</em>
                        <?php endif; ?>
                    </p>
                <?php endforeach; ?>
            </section>
        <?php endforeach; ?>
    <?php endforeach; ?>
</div>

<style>
    .monclubtt-shortcodes { max-width: 960px; }
    .monclubtt-shortcodes__sommaire { margin: 16px 0 8px; }
    .monclubtt-shortcodes__sommaire p { margin: 4px 0; }
    .monclubtt-shortcodes__groupe { margin-top: 32px; }
    .monclubtt-shortcodes__carte { background: #fff; border: 1px solid #c3c4c7; border-radius: 4px; padding: 4px 20px 16px; margin: 12px 0; scroll-margin-top: 48px; }
    .monclubtt-shortcodes__carte h3 { display: flex; flex-wrap: wrap; align-items: baseline; gap: 4px 12px; margin: 16px 0 8px; }
    .monclubtt-shortcodes__carte h3 code { font-size: 13px; font-weight: 400; }
    .monclubtt-shortcodes__exemples { margin: 12px 0; }
    .monclubtt-shortcodes__exemples li { display: flex; flex-wrap: wrap; align-items: center; gap: 4px 12px; margin: 0 0 8px; }
    .monclubtt-shortcodes__copier { display: inline-flex; align-items: center; gap: 10px; max-width: 100%; padding: 4px 4px 4px 0; border: 1px solid #c3c4c7; border-radius: 4px; background: #f6f7f7; cursor: pointer; text-align: left; }
    .monclubtt-shortcodes__copier:hover { border-color: #2271b1; }
    .monclubtt-shortcodes__copier:focus-visible { outline: 2px solid #2271b1; outline-offset: 1px; }
    .monclubtt-shortcodes__copier code { background: none; padding: 2px 8px; overflow-wrap: anywhere; }
    .monclubtt-shortcodes__etat { flex: none; padding: 2px 8px; border-radius: 3px; background: #fff; color: #2271b1; font-size: 12px; }
    .monclubtt-shortcodes__copier.is-copie .monclubtt-shortcodes__etat { background: #00a32a; color: #fff; }
    .monclubtt-shortcodes details { margin: 8px 0; }
    .monclubtt-shortcodes summary { cursor: pointer; color: #2271b1; margin-bottom: 8px; }
    .monclubtt-shortcodes__slugs { color: #50575e; }
</style>
<script>
    (function () {
        function copier(texte) {
            if (navigator.clipboard && window.isSecureContext) {
                return navigator.clipboard.writeText(texte);
            }
            // Repli (site en http) : sélection d'un champ temporaire.
            return new Promise(function (resoudre, rejeter) {
                var champ = document.createElement('textarea');
                champ.value = texte;
                champ.setAttribute('readonly', '');
                champ.style.position = 'fixed';
                champ.style.opacity = '0';
                document.body.appendChild(champ);
                champ.select();
                var ok = document.execCommand('copy');
                document.body.removeChild(champ);
                ok ? resoudre() : rejeter();
            });
        }
        document.querySelectorAll('.monclubtt-shortcodes__copier').forEach(function (bouton) {
            var etat = bouton.querySelector('.monclubtt-shortcodes__etat');
            bouton.addEventListener('click', function () {
                copier(bouton.dataset.shortcode).then(function () {
                    bouton.classList.add('is-copie');
                    etat.textContent = 'Copié';
                }, function () {
                    etat.textContent = 'Sélectionnez et copiez';
                });
                setTimeout(function () {
                    bouton.classList.remove('is-copie');
                    etat.textContent = 'Copier';
                }, 2000);
            });
        });
    })();
</script>
