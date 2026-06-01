<?php
if ( ! defined( 'ABSPATH' ) ) exit;
$equipes      = new Equipes();
$listeEquipes = $equipes->getEquipesSeniorChampionnat('MF');
$nonce        = wp_create_nonce('dataping_generate_pages_nonce');
?>
<div class="wrap">
    <h1 class="DataPing_title">Les équipes</h1>

    <p>Cochez les équipes pour lesquelles vous voulez une page WordPress, décochez celles
       dont la page doit être supprimée, puis cliquez sur <strong>Appliquer la sélection</strong>.</p>

    <div style="margin-bottom: 12px; display: flex; gap: 10px; align-items: center; flex-wrap: wrap;">
        <button type="button" id="dataping-select-all" class="button">Tout sélectionner</button>
        <button type="button" id="dataping-deselect-all" class="button">Tout désélectionner</button>
        <button type="button" id="dataping-generate-btn" class="button button-primary button-large" style="display:inline-flex; align-items:center; gap:6px; line-height:1;">
            <span aria-hidden="true" style="font-family:dashicons; display:inline-block; font-size:20px; line-height:1; width:20px; height:20px; flex-shrink:0; speak:none; -webkit-font-smoothing:antialiased;">&#xf147;</span>
            Appliquer la sélection
        </button>
        <span id="dataping-generate-spinner" class="spinner" style="float:none; margin:0;"></span>
    </div>

    <div id="dataping-generate-message" style="margin-bottom: 14px;"></div>

    <table class="wp-list-table widefat fixed striped posts">
        <thead>
        <tr>
            <td class="manage-column column-cb check-column">
                <input id="dataping-check-all" type="checkbox" title="Tout cocher/décocher" />
            </td>
            <th>Équipe</th>
            <th>Division</th>
            <th style="width:320px;">Shortcode</th>
        </tr>
        </thead>
        <tbody id="dataping-equipes-list">
        <?php foreach ( $listeEquipes as $equipe ): /* @var Equipe $equipe */ ?>
            <?php
            $iddiv     = $equipe->getIddiv()   ?? '';
            $idpoule   = $equipe->getIdpoule() ?? '';
            $hasIds    = !empty( $iddiv );
            $checkId   = 'dataping-cb-' . esc_attr( $iddiv ) . '-' . esc_attr( $idpoule );
            $shortcode = '[equipe iddiv="' . esc_attr( $iddiv ) . '" idpoule="' . esc_attr( $idpoule ) . '"]';
            ?>
            <tr class="<?php echo $hasIds ? '' : 'dataping-row-disabled'; ?>">
                <th scope="row" class="check-column">
                    <?php if ( $hasIds ): ?>
                        <input
                            type="checkbox"
                            id="<?php echo $checkId; ?>"
                            class="dataping-team-checkbox"
                            data-iddiv="<?php echo esc_attr( $iddiv ); ?>"
                            data-idpoule="<?php echo esc_attr( $idpoule ); ?>"
                            data-libequipe="<?php echo esc_attr( $equipe->getLibequipe() ); ?>"
                            checked
                        />
                    <?php endif; ?>
                </th>
                <td>
                    <?php if ( $hasIds ): ?>
                        <label for="<?php echo $checkId; ?>" style="cursor:pointer; font-weight:600;">
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
                            class="dataping-shortcode"
                            title="Cliquer pour copier"
                            style="cursor:pointer; background:#f0f0f1; padding:3px 6px; border-radius:3px;"
                        ><?php echo esc_html( $shortcode ); ?></code>
                        <span class="dataping-copy-confirm" style="display:none; color:green; font-size:11px; margin-left:6px;">✓ Copié</span>
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

<style>
.dataping-row-disabled td, .dataping-row-disabled th { opacity: 0.5; }
</style>

<script type="text/javascript">
jQuery(document).ready(function ($) {

    // Tout sélectionner / désélectionner via le header checkbox
    $('#dataping-check-all').on('change', function () {
        $('.dataping-team-checkbox').prop('checked', this.checked);
    });

    $('#dataping-select-all').on('click', function () {
        $('.dataping-team-checkbox').prop('checked', true);
        $('#dataping-check-all').prop('checked', true);
    });

    $('#dataping-deselect-all').on('click', function () {
        $('.dataping-team-checkbox').prop('checked', false);
        $('#dataping-check-all').prop('checked', false);
    });

    // Copie du shortcode au clic
    $(document).on('click', '.dataping-shortcode', function () {
        var text = $(this).text();
        if (navigator.clipboard) {
            navigator.clipboard.writeText(text);
        } else {
            var el = document.createElement('textarea');
            el.value = text;
            document.body.appendChild(el);
            el.select();
            document.execCommand('copy');
            document.body.removeChild(el);
        }
        $(this).next('.dataping-copy-confirm').stop(true).fadeIn(100).delay(1500).fadeOut(400);
    });

    // Appliquer la sélection : créer les cochés, supprimer les décochés
    $('#dataping-generate-btn').on('click', function () {
        var teamsCreate = [];
        var teamsDelete = [];

        $('.dataping-team-checkbox').each(function () {
            var data = {
                iddiv:     $(this).data('iddiv'),
                idpoule:   $(this).data('idpoule'),
                libequipe: $(this).data('libequipe')
            };
            if ($(this).is(':checked')) {
                teamsCreate.push(data);
            } else {
                teamsDelete.push(data);
            }
        });

        var $btn     = $(this);
        var $spinner = $('#dataping-generate-spinner');
        var $msg     = $('#dataping-generate-message');

        $btn.prop('disabled', true);
        $spinner.addClass('is-active');
        $msg.html('');

        $.ajax({
            url:  ajaxurl,
            type: 'POST',
            data: {
                action:        'dataping_generate_pages',
                nonce:         '<?php echo esc_js( $nonce ); ?>',
                teams_create:  teamsCreate,
                teams_delete:  teamsDelete
            },
            success: function (response) {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                if (response.success) {
                    var d    = response.data;
                    var link = d.parent_url
                        ? ' — <a href="' + d.parent_url + '" target="_blank">Voir la page parent →</a>'
                        : '';
                    $msg.html('<div class="notice notice-success inline"><p><strong>✓ ' +
                        d.message + '</strong>' + link + '</p></div>');
                } else {
                    $msg.html('<div class="notice notice-error inline"><p><strong>Erreur :</strong> ' +
                        response.data.message + '</p></div>');
                }
            },
            error: function () {
                $spinner.removeClass('is-active');
                $btn.prop('disabled', false);
                $msg.html('<div class="notice notice-error inline"><p>Erreur de communication avec le serveur.</p></div>');
            }
        });
    });
});
</script>
