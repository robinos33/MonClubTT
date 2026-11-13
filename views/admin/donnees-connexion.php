<?php
if ( ! defined( 'ABSPATH' ) ) exit;
?>

<div class="monclubtt-right-box" style="display: none">
    <p>N'hésitez pas à m'envoyer vos remarques et suggestions à contact@robin-aldasoro.com<br />
        Ceci est un plugin non-officiel et GRATUIT.
    </p>
</div>

<div class="monclubtt-wrap">
    <h2>Paramètres de l'API FFTT</h2>
    <?php $this->getForm('monclubtt_fftt_settings'); ?>

    <h2>Synchronisation des données</h2>
    <div class="monclubtt-sync-section">
        <?php
        $monclubtt_lastSync = MonClubTT_Plugin::getLastSyncTimestamp();
        if ($monclubtt_lastSync) {
            $monclubtt_syncDate = monclubtt_date_locale($monclubtt_lastSync);
            $monclubtt_timeDiff = human_time_diff($monclubtt_lastSync);
            echo '<p><strong>Dernière synchronisation :</strong><br>' . esc_html($monclubtt_syncDate) . '<br><small style="color: #666;">(il y a ' . esc_html($monclubtt_timeDiff) . ')</small></p>';
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
                    <li><strong>Activez les "Logs de l'API FFTT" ci-dessous, relancez la synchronisation et consultez le détail des appels</strong></li>
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
        <form action="options.php" method="POST" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:10px;">
            <?php settings_fields('monclubtt_logs_settings'); ?>
            <input type="hidden" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_API_LOGS_ACTIFS); ?>" value="0">
            <label>
                <input type="checkbox" name="<?php echo esc_attr(MonClubTT_Constantes::MONCLUBTT_API_LOGS_ACTIFS); ?>" value="1"<?php checked(MonClubTT_AccesFFTTApi::logsActifs()); ?>>
                Enregistrer les appels à l'API FFTT
            </label>
            <?php submit_button('Enregistrer', 'secondary', 'submit', false); ?>
        </form>
        <p class="description" style="margin-top:0;">
            À activer seulement pour diagnostiquer un problème de synchronisation, puis à désactiver :
            les 50 derniers appels lancés depuis l'admin ou par la tâche planifiée sont alors gardés. Désactiver efface le journal.
        </p>
        <?php
        $monclubtt_logs = MonClubTT_AccesFFTTApi::getApiLogs();
        if (empty($monclubtt_logs)) {
            if (MonClubTT_AccesFFTTApi::logsActifs()) {
                echo '<p><em>Aucun log pour l\'instant. Lancez une synchronisation pour voir les appels.</em></p>';
            }
        } else {
            echo '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">';
            echo '<p style="margin: 0;"><strong>' . count($monclubtt_logs) . ' logs d\'API</strong></p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin: 0;">';
            echo '<input type="hidden" name="action" value="monclubtt_clear_logs">';
            wp_nonce_field('monclubtt_clear_logs', 'monclubtt_clear_logs_nonce');
            echo '<button type="submit" class="button">Effacer les logs</button>';
            echo '</form>';
            echo '</div>';

            echo '<div style="max-height: 400px; overflow-y: auto; font-family: monospace; font-size: 12px; background: #f8f8f8; padding: 10px; border: 1px solid #ddd;">';

            // Afficher les logs en ordre inverse (plus récent d'abord)
            foreach (array_reverse($monclubtt_logs) as $monclubtt_log) {
                $monclubtt_color = '#333';
                $monclubtt_icon = '●';
                if ($monclubtt_log['type'] === 'error') {
                    $monclubtt_color = '#dc3232';
                    $monclubtt_icon = '✖';
                } elseif ($monclubtt_log['type'] === 'success') {
                    $monclubtt_color = '#46b450';
                    $monclubtt_icon = '✓';
                }

                echo '<div style="margin-bottom: 5px; padding: 5px; border-left: 3px solid ' . esc_attr($monclubtt_color) . '; background: #fff;">';
                echo '<span style="color: #666;">[' . esc_html($monclubtt_log['time']) . ']</span> ';
                echo '<span style="color: ' . esc_attr($monclubtt_color) . '; font-weight: bold;">' . esc_html($monclubtt_icon) . '</span> ';
                echo '<span>' . esc_html($monclubtt_log['message']) . '</span>';
                echo '</div>';
            }

            echo '</div>';
        }

        if (isset($_GET['logs_cleared'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
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


