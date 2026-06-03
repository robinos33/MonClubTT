<?php
if ( ! defined( 'ABSPATH' ) ) exit;
// phpcs:disable WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedVariableFound
$equipes      = new Equipes();
$listeEquipes = $equipes->getEquipesSeniorChampionnat('MF');
$nonce        = wp_create_nonce('monclubtt_generate_pages_nonce');
?>
<div class="wrap">
    <h1 class="monclubtt-title">Les équipes</h1>

    <p>Cochez les équipes pour lesquelles vous voulez une page WordPress, décochez celles
       dont la page doit être supprimée, puis cliquez sur <strong>Appliquer la sélection</strong>.</p>

    <div style="margin-bottom: 12px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" id="monclubtt-select-all" class="button">Tout sélectionner</button>
        <button type="button" id="monclubtt-deselect-all" class="button">Tout désélectionner</button>
        <button type="button" id="monclubtt-generate-btn" class="button button-primary button-large" style="display:inline-flex; align-items:center; gap:6px; line-height:1;">
            <span aria-hidden="true" style="font-family:dashicons; display:inline-block; font-size:20px; line-height:1; width:20px; height:20px; flex-shrink:0; speak:none; -webkit-font-smoothing:antialiased;">&#xf147;</span>
            Appliquer la sélection
        </button>
        <span id="monclubtt-generate-spinner" class="spinner" style="float:none; margin:0;"></span>
    </div>

    <div id="monclubtt-generate-message" style="margin-bottom: 14px;"></div>

    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <td class="manage-column column-cb check-column">
                <input id="monclubtt-check-all" type="checkbox" title="Tout cocher/décocher" />
            </td>
            <th>Équipe</th>
            <th>Division</th>
            <th style="width:320px;">Shortcode</th>
        </tr>
        </thead>
        <tbody id="monclubtt-equipes-list">
        <?php foreach ( $listeEquipes as $equipe ): /* @var Equipe $equipe */ ?>
            <?php
            $iddiv     = $equipe->getIddiv()   ?? '';
            $idpoule   = $equipe->getIdpoule() ?? '';
            $hasIds    = !empty( $iddiv );
            $checkId   = 'monclubtt-cb-' . esc_attr( $iddiv ) . '-' . esc_attr( $idpoule );
            $shortcode = '[equipe iddiv="' . esc_attr( $iddiv ) . '" idpoule="' . esc_attr( $idpoule ) . '"]';
            ?>
            <tr class="<?php echo $hasIds ? '' : 'monclubtt-row-disabled'; ?>">
                <th scope="row" class="check-column">
                    <?php if ( $hasIds ): ?>
                        <input
                            type="checkbox"
                            id="<?php echo esc_attr($checkId); ?>"
                            class="monclubtt-team-checkbox"
                            data-iddiv="<?php echo esc_attr( $iddiv ); ?>"
                            data-idpoule="<?php echo esc_attr( $idpoule ); ?>"
                            data-libequipe="<?php echo esc_attr( $equipe->getLibequipe() ); ?>"
                            checked
                        />
                    <?php endif; ?>
                </th>
                <td>
                    <?php if ( $hasIds ): ?>
                        <label for="<?php echo esc_attr($checkId); ?>" style="cursor:pointer; font-weight:600;">
                            <?php echo esc_html( $equipe->getLibequipe() ); ?>
                        </label>
                    <?php else: ?>
                        <?php echo esc_html( $equipe->getLibequipe() ); ?>
                        <em style="color:#999; font-size:11px;"> (poule indisponible)</em>
                    <?php endif; ?>
                </td>
                <td><?php echo esc_html( $equipe->getLibdivision() ); ?></td>
                <td>
                    <?php if ( $hasIds ): ?>
                        <code
                            class="monclubtt-shortcode"
                            title="Cliquer pour copier"
                            style="cursor:pointer; background:#f0f0f1; padding:3px 6px; border-radius:3px;"
                        ><?php echo esc_html( $shortcode ); ?></code>
                        <span class="monclubtt-copy-confirm" style="display:none; color:green; font-size:11px; margin-left:6px;">✓ Copié</span>
                    <?php else: ?>
                        <span style="color:#aaa;">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <p style="color:#666; font-size:12px; margin-top:10px;">
        Championnat sénior par équipes uniquement.
        <?php
        $total  = count( $equipes->getEquipes( 'MF' ) );
        $senior = count( $listeEquipes );
        echo esc_html( $senior . ' équipe(s) affichée(s) sur ' . $total . ' au total.' );
        ?>
    </p>
</div>

<?php
wp_add_inline_style('mon-club-tt-css', '.monclubtt-row-disabled td, .monclubtt-row-disabled th { opacity: 0.5; }');

wp_add_inline_script('monclubtt-js', 'jQuery(document).ready(function($) {
    $("#monclubtt-check-all").on("change", function() {
        $(".monclubtt-team-checkbox").prop("checked", this.checked);
    });
    $("#monclubtt-select-all").on("click", function() {
        $(".monclubtt-team-checkbox").prop("checked", true);
        $("#monclubtt-check-all").prop("checked", true);
    });
    $("#monclubtt-deselect-all").on("click", function() {
        $(".monclubtt-team-checkbox").prop("checked", false);
        $("#monclubtt-check-all").prop("checked", false);
    });
    $(document).on("click", ".monclubtt-shortcode", function() {
        var text = $(this).text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            var el = document.createElement("textarea");
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand("copy");
            document.body.removeChild(el);
        }
        $(this).next(".monclubtt-copy-confirm").stop(true).fadeIn(100).delay(1500).fadeOut(400);
    });
    $("#monclubtt-generate-btn").on("click", function() {
        var teamsCreate = [];
        var teamsDelete = [];
        $(".monclubtt-team-checkbox").each(function() {
            var data = { iddiv: $(this).data("iddiv"), idpoule: $(this).data("idpoule"), libequipe: $(this).data("libequipe") };
            if ($(this).is(":checked")) { teamsCreate.push(data); } else { teamsDelete.push(data); }
        });
        var $btn = $(this), $spinner = $("#monclubtt-generate-spinner"), $msg = $("#monclubtt-generate-message");
        $btn.prop("disabled", true);
        $spinner.addClass("is-active");
        $msg.html("");
        $.ajax({
            url: ajaxurl, type: "POST",
            data: { action: "monclubtt_generate_pages", nonce: ' . wp_json_encode($nonce) . ', teams_create: teamsCreate, teams_delete: teamsDelete },
            success: function(response) {
                $spinner.removeClass("is-active");
                $btn.prop("disabled", false);
                if (response.success) {
                    var d = response.data;
                    var link = d.parent_url ? " — <a href=\"" + d.parent_url + "\" target=\"_blank\">Voir la page parent →</a>" : "";
                    $msg.html("<div class=\"notice notice-success inline\"><p><strong>✓ " + d.message + "</strong>" + link + "</p></div>");
                } else {
                    $msg.html("<div class=\"notice notice-error inline\"><p><strong>Erreur :</strong> " + response.data.message + "</p></div>");
                }
            },
            error: function() {
                $spinner.removeClass("is-active");
                $btn.prop("disabled", false);
                $msg.html("<div class=\"notice notice-error inline\"><p>Erreur de communication avec le serveur.</p></div>");
            }
        });
    });
});');
?>
