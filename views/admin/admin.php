<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Handle log clearing before any output
if (isset($_POST['monclubtt_clear_logs']) && check_admin_referer('monclubtt_clear_logs', 'monclubtt_clear_logs_nonce')) {
    AccesFFTTApi::clearApiLogs();
    wp_safe_redirect(add_query_arg(array('page' => 'monclubtt_parametres', 'logs_cleared' => '1'), admin_url('admin.php')));
    exit;
}

// Afficher un message de succès après la sauvegarde des paramètres
if (isset($_GET['settings-updated']) && filter_input(INPUT_GET, 'settings-updated', FILTER_SANITIZE_NUMBER_INT)) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    echo '<div class="notice notice-success is-dismissible"><p><strong>Paramètres enregistrés avec succès !</strong></p></div>';
}
?>

<div class="monclubtt-right-box" style="display: none">
    <p>N'hésitez pas à m'envoyer vos remarques et suggestions à contact@robin-aldasoro.com<br />
        Ceci est un plugin non-officiel et GRATUIT.
    </p>
</div>

<div class="wrap monclubtt-wrap">
    <p></p>
    <h2>Paramètres de l'API FFTT</h2>
    <?php $this->getForm(); ?>

    <h2>Synchronisation des données</h2>
    <div class="monclubtt-sync-section">
        <?php
        $lastSync = MonClubTT::getLastSyncTimestamp();
        if ($lastSync) {
            $syncDate = date_i18n(get_option('date_format') . ' à ' . get_option('time_format'), $lastSync);
            $timeDiff = human_time_diff($lastSync, current_time('timestamp'));
            echo '<p><strong>Dernière synchronisation :</strong><br>' . esc_html($syncDate) . '<br><small style="color: #666;">(il y a ' . esc_html($timeDiff) . ')</small></p>';
        } else {
            echo '<p><em>Aucune synchronisation manuelle effectuée</em></p>';
        }
        ?>
        <button id="monclubtt-sync-button" class="button button-primary" style="display:inline-flex; align-items:center; gap:6px; line-height:1;">
            <span class="monclubtt-icon monclubtt-sync-icon" aria-hidden="true">&#xf463;</span>
            <span class="monclubtt-sync-label">Synchroniser les données</span>
        </button>
        <div id="monclubtt-sync-message" style="margin-top: 10px;"></div>

        <details style="margin-top: 12px;">
            <summary style="cursor:pointer; color:#2271b1; font-size:13px;">En cas d'erreur lors de la synchronisation…</summary>
            <div style="margin-top:8px; padding:10px 14px; background:#f0f6fc; border-left:4px solid #72aee6; font-size:13px;">
                <ol style="margin:.5em 0 .5em 1.2em; padding:0;">
                    <li>Vérifiez que tous les paramètres ci-dessus sont correctement renseignés</li>
                    <li>Assurez-vous que le numéro de club est correct (format: 8 chiffres, ex: 10330011)</li>
                    <li>Vérifiez que vos identifiants API sont valides (obtenus auprès de la FFTT)</li>
                    <li><strong>Consultez les "Logs de l'API FFTT" ci-dessous pour voir les détails des appels API</strong></li>
                </ol>
                <p style="margin:.5em 0;"><strong>Erreurs fréquentes :</strong></p>
                <ul style="margin:.3em 0 0 1.2em; padding:0;">
                    <li><em>RÉPONSE VIDE de l'API FFTT</em> : <strong>Identifiants API invalides</strong> — Vérifiez votre ID et mot de passe</li>
                    <li><em>Aucune donnée récupérée</em> : Identifiants API invalides ou numéro de club incorrect</li>
                    <li><em>Erreur parsing XML</em> : L'API FFTT a retourné une réponse vide (vérifiez que le club existe)</li>
                    <li><em>Erreur cURL</em> : Problème de connexion réseau du serveur</li>
                </ul>
            </div>
        </details>
    </div>

    <h2>Logs de l'API FFTT</h2>
    <div style="background: #fff; border: 1px solid #ccd0d4; padding: 15px; margin-bottom: 20px;">
        <?php
        $logs = AccesFFTTApi::getApiLogs();
        if (empty($logs)) {
            echo '<p><em>Aucun log disponible. Lancez une synchronisation pour voir les logs.</em></p>';
        } else {
            echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            echo '<p style="margin: 0;"><strong>' . count($logs) . ' logs d\'API</strong></p>';
            echo '<form method="post" style="margin: 0;">';
            echo '<input type="hidden" name="monclubtt_clear_logs" value="1">';
            wp_nonce_field('monclubtt_clear_logs', 'monclubtt_clear_logs_nonce');
            echo '<button type="submit" class="button">Effacer les logs</button>';
            echo '</form>';
            echo '</div>';

            echo '<div style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';

            // Afficher les logs en ordre inverse (plus récent d'abord)
            foreach (array_reverse($logs) as $log) {
                $color = '#333';
                $icon = '●';
                if ($log['type'] === 'error') {
                    $color = '#dc3232';
                    $icon = '✖';
                } elseif ($log['type'] === 'success') {
                    $color = '#46b450';
                    $icon = '✓';
                }

                echo '<div style="margin-bottom: 5px; padding: 5px; border-left: 3px solid ' . esc_attr($color) . '; background: #fff;">';
                echo '<span style="color: #666;">[' . esc_html($log['time']) . ']</span> ';
                echo '<span style="color: ' . esc_attr($color) . '; font-weight: bold;">' . esc_html($icon) . '</span> ';
                echo '<span>' . esc_html($log['message']) . '</span>';
                echo '</div>';
            }

            echo '</div>';
        }

        if (isset($_GET['logs_cleared'])) {
            echo '<div class="notice notice-success" style="margin-top: 10px;"><p>Logs effacés avec succès.</p></div>';
        }
        ?>
    </div>

</div>

<?php
wp_add_inline_script('monclubtt-js', 'jQuery(document).ready(function($) {
    $("#monclubtt-sync-button").on("click", function() {
        var $button = $(this);
        var $message = $("#monclubtt-sync-message");

        $button.prop("disabled", true).addClass("is-loading");
        $button.find(".monclubtt-sync-label").text("Synchronisation en cours…");
        $message.html("");

        $.ajax({
            url: ajaxurl,
            type: "POST",
            data: {
                action: "monclubtt_sync",
                nonce: ' . wp_json_encode(wp_create_nonce('monclubtt_sync_nonce')) . '
            },
            success: function(response) {
                $button.prop("disabled", false).removeClass("is-loading");
                $button.find(".monclubtt-sync-label").text("Synchroniser les données");

                if (response.success) {
                    var details = "";
                    if (response.data.results) {
                        details = "<br><small>Joueurs: " + (response.data.results.joueurs || 0) +
                                 " | Équipes: " + (response.data.results.equipes || 0) + "</small>";
                    }
                    $message.html("<div class=\"notice notice-success is-dismissible\"><p><strong>Succès !</strong> " + response.data.message + details + "</p></div>");
                    setTimeout(function() { location.reload(); }, 1500);
                } else {
                    var errorHtml = "<div class=\"notice notice-error is-dismissible\"><p><strong>Erreur :</strong> " + response.data.message + "</p></div>";
                    $message.html(errorHtml);
                }
            },
            error: function() {
                $button.prop("disabled", false).removeClass("is-loading");
                $button.find(".monclubtt-sync-label").text("Synchroniser les données");
                $message.html("<div class=\"notice notice-error is-dismissible\"><p><strong>Erreur :</strong> Erreur de communication avec le serveur</p></div>");
            }
        });
    });

    $(".notice.is-dismissible").each(function() {
        var $notice = $(this);
        setTimeout(function() { $notice.fadeOut(); }, 5000);
    });
});');
?>


