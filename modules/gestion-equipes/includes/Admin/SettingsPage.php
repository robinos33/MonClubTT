<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Admin;

use MonClubTT\GestionEquipes\Repository\PlayerRepository;

class SettingsPage
{
    /** Slug de la page d'admin « Gestion des équipes ». */
    public const SLUG = 'monclubtt_gestion_equipes';

    /** Onglets de la page : clé => libellé. */
    private const TABS = [
        'general' => 'Général',
        'equipes' => 'Équipes & journées',
        'joueurs' => 'Joueurs & rencontres',
        'regles'  => 'Règles',
        'donnees' => 'Import / export',
    ];

    public static function url(string $tab = '', array $args = []): string
    {
        $args = ['page' => self::SLUG] + ($tab !== '' ? ['tab' => $tab] : []) + $args;
        return add_query_arg($args, admin_url('admin.php'));
    }

    // =========================================================================
    // Enregistrement
    // =========================================================================

    /**
     * Un groupe de réglages par formulaire : options.php remet à vide toutes
     * les options du groupe envoyé.
     */
    public function registerSettings(): void
    {
        add_action('admin_post_monclubtt_ge_create_front_page', [$this, 'handleCreateFrontPage']);
        (new DataExporter())->register();
        (new DataImporter())->register();

        register_setting('monclubtt_ge_general', 'monclubtt_ge_pwa_enabled',               ['sanitize_callback' => [$this, 'sanitizeCheckbox']]);
        register_setting('monclubtt_ge_general', 'monclubtt_ge_sms_template_availability', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('monclubtt_ge_general', 'monclubtt_ge_sms_template_confirmation', ['sanitize_callback' => 'sanitize_textarea_field']);
        register_setting('monclubtt_ge_equipes', 'monclubtt_ge_teams',                     ['sanitize_callback' => [$this, 'sanitizeTeams']]);
        register_setting('monclubtt_ge_equipes', 'monclubtt_ge_journee_dates_p1',          ['sanitize_callback' => [$this, 'sanitizeDates']]);
        register_setting('monclubtt_ge_equipes', 'monclubtt_ge_journee_dates_p2',          ['sanitize_callback' => [$this, 'sanitizeDates']]);
    }

    public function sanitizeCheckbox(mixed $raw): string
    {
        return $raw === '1' ? '1' : '0';
    }

    public function sanitizeTeams(mixed $raw): array
    {
        if (! is_array($raw)) {
            return [];
        }
        $result = [];
        foreach ($raw as $team) {
            if (! is_array($team)) {
                continue;
            }
            $code = sanitize_text_field($team['code'] ?? '');
            if ($code === '') {
                continue; // Ignore les lignes sans code
            }
            $result[] = [
                'code'  => $code,
                'name'  => sanitize_text_field($team['name']  ?? ''),
                'level' => sanitize_text_field($team['level'] ?? ''),
                'color' => sanitize_hex_color($team['color']  ?? '#2563eb') ?? '#2563eb',
            ];
        }
        return $result;
    }

    /** Valide un tableau de 7 dates ISO (YYYY-MM-DD), ignore les invalides. */
    public function sanitizeDates(mixed $raw): array
    {
        if (! is_array($raw)) {
            return array_fill(0, 7, '');
        }
        $result = [];
        for ($i = 0; $i < 7; $i++) {
            $val = trim($raw[$i] ?? '');
            // Accepte YYYY-MM-DD ou vide
            $result[] = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $val) && strtotime($val) !== false)
                ? $val
                : '';
        }
        return $result;
    }

    // =========================================================================
    // Handler admin-post
    // =========================================================================

    /** Cree une page WP publiee contenant le shortcode [tt_team_planner]. */
    public function handleCreateFrontPage(): void
    {
        check_admin_referer('monclubtt_ge_create_front_page');

        if (! current_user_can('publish_pages')) {
            wp_die(esc_html__('Permission insuffisante.', 'mon-club-tt'));
        }

        $pageId = wp_insert_post([
            'post_title'   => __('Gestion des équipes', 'mon-club-tt'),
            'post_content' => '[tt_team_planner]',
            'post_status'  => 'publish',
            'post_type'    => 'page',
        ], true);

        if (is_wp_error($pageId)) {
            wp_safe_redirect(self::url('general', ['monclubtt_ge_notice' => 'create_error']));
        } else {
            wp_safe_redirect(self::url('general', ['monclubtt_ge_notice' => 'created', 'monclubtt_ge_page_id' => $pageId]));
        }
        exit;
    }

    // =========================================================================
    // Rendu principal
    // =========================================================================

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Accès refusé.', 'mon-club-tt'));
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- simple choix d'onglet
        $tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'general';
        if (! isset(self::TABS[$tab])) {
            $tab = 'general';
        }

        echo '<div class="wrap">';
        echo '<h1 class="wp-heading-inline monclubtt-title">' . esc_html__('Gestion des équipes', 'mon-club-tt') . '</h1> ';
        $this->renderFrontLink();
        echo '<hr class="wp-header-end">';

        $this->renderRedirectNotice();
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- retour d'options.php
        if (isset($_GET['settings-updated'])) {
            echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__('Paramètres enregistrés.', 'mon-club-tt') . '</strong></p></div>';
        }

        echo '<nav class="nav-tab-wrapper wp-clearfix" aria-label="' . esc_attr__('Onglets de la gestion des équipes', 'mon-club-tt') . '">';
        foreach (self::TABS as $key => $label) {
            $active = $key === $tab;
            echo '<a href="' . esc_url(self::url($key)) . '" class="nav-tab' . ($active ? ' nav-tab-active' : '') . '"' . ($active ? ' aria-current="page"' : '') . '>' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        switch ($tab) {
            case 'equipes':
                echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
                settings_fields('monclubtt_ge_equipes');
                $this->renderTeamsSection();
                $this->renderDatesForm();
                submit_button(__('Enregistrer', 'mon-club-tt'));
                echo '</form>';
                $this->renderSeasonReset();
                break;

            case 'joueurs':
                $this->renderSyncSection(get_option('monclubtt_ge_last_sync'), new PlayerRepository());
                echo '<hr>';
                $this->renderImportSection();
                break;

            case 'regles':
                $this->renderBurnageRulesSection();
                break;

            case 'donnees':
                (new DataImporter())->render();
                echo '<hr>';
                $this->renderExportSection();
                break;

            default:
                $this->renderGeneralForm();
        }

        echo '</div>';
    }

    private function renderGeneralForm(): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('options.php')) . '">';
        settings_fields('monclubtt_ge_general');

        echo '<table class="form-table" role="presentation">';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Club', 'mon-club-tt') . '</th>';
        echo '<td><strong>' . esc_html(\monclubtt_nom_club()) . '</strong> ';
        echo '<span class="description"><a href="' . esc_url(admin_url('admin.php?page=monclubtt_parametres')) . '">' . esc_html__('Modifier dans Réglages', 'mon-club-tt') . '</a></span></td>';
        echo '</tr>';

        // Saison détectée automatiquement — lecture seule
        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Saison', 'mon-club-tt') . '</th>';
        echo '<td><code>' . esc_html(\MonClubTT\GestionEquipes\Front\Assets::computeSeason()) . '</code>';
        echo ' <span class="description">' . esc_html__('Calculée depuis la date du jour.', 'mon-club-tt') . '</span></td>';
        echo '</tr>';

        echo '<tr>';
        echo '<th scope="row">' . esc_html__('Installation sur mobile', 'mon-club-tt') . '</th>';
        echo '<td>';
        // Champ caché : une case décochée n'est pas envoyée, l'option ne serait jamais remise à 0.
        echo '<input type="hidden" name="monclubtt_ge_pwa_enabled" value="0">';
        echo '<label for="monclubtt_ge_pwa_enabled">';
        echo '<input type="checkbox" id="monclubtt_ge_pwa_enabled" name="monclubtt_ge_pwa_enabled" value="1" ' . checked(\MonClubTT\GestionEquipes\Front\Assets::pwaEnabled(), true, false) . '> ';
        echo esc_html__('Permettre l\'installation de l\'application sur l\'écran d\'accueil (PWA)', 'mon-club-tt');
        echo '</label>';
        echo '<p class="description">' . esc_html__('Le staff pourra installer l\'application de gestion des équipes : icône sur l\'écran d\'accueil, plein écran, et consultation hors ligne des dernières données chargées.', 'mon-club-tt') . '</p>';
        echo '</td>';
        echo '</tr>';
        echo '</table>';

        echo '<h2>' . esc_html__('SMS pré-rédigés', 'mon-club-tt') . '</h2>';
        echo '<p class="description">' . esc_html__('Variables : {prenom}, {nom}, {journee}, {date}, {equipe}', 'mon-club-tt') . '</p>';
        echo '<table class="form-table" role="presentation">';
        echo '<tr>';
        echo '<th scope="row"><label for="monclubtt_ge_sms_avail">' . esc_html__('Demande de disponibilité', 'mon-club-tt') . '</label></th>';
        echo '<td><textarea id="monclubtt_ge_sms_avail" name="monclubtt_ge_sms_template_availability" rows="3" class="large-text">' . esc_textarea((string) get_option('monclubtt_ge_sms_template_availability', '')) . '</textarea></td>';
        echo '</tr>';
        echo '<tr>';
        echo '<th scope="row"><label for="monclubtt_ge_sms_confirm">' . esc_html__('Confirmation de composition', 'mon-club-tt') . '</label></th>';
        echo '<td><textarea id="monclubtt_ge_sms_confirm" name="monclubtt_ge_sms_template_confirmation" rows="3" class="large-text">' . esc_textarea((string) get_option('monclubtt_ge_sms_template_confirmation', '')) . '</textarea></td>';
        echo '</tr>';
        echo '</table>';

        submit_button(__('Enregistrer', 'mon-club-tt'));
        echo '</form>';
    }

    // =========================================================================
    // Sections privees
    // =========================================================================

    private function renderTeamsSection(): void
    {
        $teams = (array) get_option('monclubtt_ge_teams', []);

        echo '<h2>' . esc_html__('Équipes', 'mon-club-tt') . '</h2>';
        echo '<p class="description">' . esc_html__('Définissez les équipes du club. Le code est utilisé pour grouper les joueurs par équipe habituelle.', 'mon-club-tt') . '</p>';

        echo '<datalist id="ttp-levels-list">';
        foreach ([
            'Pro A', 'Pro B',
            'Nationale 1', 'Nationale 2', 'Nationale 3',
            'Pré-Nationale',
            'Régionale 1', 'Régionale 2', 'Régionale 3',
            'Pré-Régionale',
            'Départementale 1', 'Départementale 2', 'Départementale 3', 'Départementale 4', 'Départementale 5',
        ] as $lvl) {
            echo '<option value="' . esc_attr($lvl) . '">';
        }
        echo '</datalist>';

        echo '<table id="ttp-teams-table" class="widefat" style="max-width:700px;margin-bottom:12px">';
        echo '<thead><tr>';
        echo '<th style="width:80px">' . esc_html__('Code', 'mon-club-tt') . '</th>';
        echo '<th>' . esc_html__('Nom', 'mon-club-tt') . '</th>';
        echo '<th style="width:140px">' . esc_html__('Niveau', 'mon-club-tt') . '</th>';
        echo '<th style="width:60px">' . esc_html__('Couleur', 'mon-club-tt') . '</th>';
        echo '<th style="width:36px"></th>';
        echo '</tr></thead>';
        echo '<tbody id="ttp-teams-body">';

        foreach ($teams as $i => $team) {
            $this->renderTeamRow((int) $i, $team);
        }

        echo '</tbody></table>';

        echo '<button type="button" id="ttp-add-team" class="button">';
        echo '+ ' . esc_html__('Ajouter une équipe', 'mon-club-tt');
        echo '</button>';

        // Template de ligne (caché) cloné par le JS
        echo '<template id="ttp-team-row-tpl">';
        echo '<tr class="ttp-team-row">';
        echo '<td><input type="text" name="monclubtt_ge_teams[__i__][code]" value="" placeholder="auto" class="small-text ttp-team-code" style="color:#6b7280;font-style:italic"></td>';
        echo '<td><input type="text" name="monclubtt_ge_teams[__i__][name]" value="" placeholder="Équipe 1" class="regular-text ttp-team-name" required></td>';
        echo '<td><input type="text" name="monclubtt_ge_teams[__i__][level]" value="" placeholder="Régionale 1" list="ttp-levels-list" class="regular-text ttp-team-level"></td>';
        echo '<td><input type="color" name="monclubtt_ge_teams[__i__][color]" value="#2563eb" style="width:44px;height:30px;padding:2px;cursor:pointer"></td>';
        echo '<td><button type="button" class="button ttp-remove-team" title="' . esc_attr__('Supprimer', 'mon-club-tt') . '">✕</button></td>';
        echo '</tr>';
        echo '</template>';

        echo '<script>
        (function () {
            var tbody  = document.getElementById("ttp-teams-body");
            var addBtn = document.getElementById("ttp-add-team");
            var tpl    = document.getElementById("ttp-team-row-tpl");

            function autoCode(name) {
                return (name.normalize("NFD").replace(/[̀-ͯ]/g, "")
                    .match(/[A-Za-z]+|\d+/g) || [])
                    .map(function (t) { return /^\d+$/.test(t) ? t : t[0].toUpperCase(); })
                    .join("");
            }

            function reIndex() {
                tbody.querySelectorAll("tr.ttp-team-row").forEach(function (row, i) {
                    row.querySelectorAll("[name]").forEach(function (el) {
                        el.name = el.name.replace(/\[\d+\]/, "[" + i + "]");
                    });
                });
            }

            function bindRow(row) {
                var nameInput = row.querySelector(".ttp-team-name");
                var codeInput = row.querySelector(".ttp-team-code");

                // Auto-fill code from name unless manually overridden
                nameInput.addEventListener("input", function () {
                    if (codeInput.dataset.manual !== "1") {
                        codeInput.value = autoCode(nameInput.value);
                    }
                });
                codeInput.addEventListener("input", function () {
                    codeInput.dataset.manual = codeInput.value ? "1" : "0";
                });
                codeInput.addEventListener("blur", function () {
                    // Sync back if user cleared the code field
                    if (!codeInput.value) {
                        codeInput.dataset.manual = "0";
                        codeInput.value = autoCode(nameInput.value);
                    }
                });

                row.querySelector(".ttp-remove-team").addEventListener("click", function () {
                    row.remove();
                    reIndex();
                });
            }

            tbody.querySelectorAll("tr.ttp-team-row").forEach(function (row) {
                // Existing rows: code already set, mark as manual
                var codeInput = row.querySelector(".ttp-team-code");
                if (codeInput.value) codeInput.dataset.manual = "1";
                bindRow(row);
            });

            addBtn.addEventListener("click", function () {
                var count = tbody.querySelectorAll("tr.ttp-team-row").length;
                var frag  = tpl.content.cloneNode(true);
                var row   = frag.querySelector("tr");
                row.querySelectorAll("[name]").forEach(function (el) {
                    el.name = el.name.replace("__i__", count);
                });
                tbody.appendChild(frag);
                row = tbody.querySelector("tr.ttp-team-row:last-child");
                bindRow(row);
                row.querySelector(".ttp-team-name").focus();
            });
        })();
        </script>';
    }

    private function renderTeamRow(int $i, array $team): void
    {
        $code  = esc_attr($team['code']  ?? '');
        $name  = esc_attr($team['name']  ?? '');
        $level = esc_attr($team['level'] ?? '');
        $color = esc_attr($team['color'] ?? '#2563eb');

        $idx = absint($i); // entier garanti, sans user input
        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $idx est absint(), $code/$name/$level/$color sont esc_attr()
        echo '<tr class="ttp-team-row">';
        echo '<td><input type="text" name="monclubtt_ge_teams[' . $idx . '][code]" value="' . $code . '" placeholder="auto" class="small-text ttp-team-code"></td>';
        echo '<td><input type="text" name="monclubtt_ge_teams[' . $idx . '][name]" value="' . $name . '" placeholder="' . esc_attr__('Équipe 1', 'mon-club-tt') . '" class="regular-text ttp-team-name" required></td>';
        echo '<td><input type="text" name="monclubtt_ge_teams[' . $idx . '][level]" value="' . $level . '" placeholder="' . esc_attr__('Régionale 1', 'mon-club-tt') . '" list="ttp-levels-list" class="regular-text ttp-team-level"></td>';
        echo '<td><input type="color" name="monclubtt_ge_teams[' . $idx . '][color]" value="' . $color . '" style="width:44px;height:30px;padding:2px;cursor:pointer"></td>';
        echo '<td><button type="button" class="button ttp-remove-team" title="' . esc_attr__('Supprimer', 'mon-club-tt') . '">✕</button></td>';
        echo '</tr>';
        // phpcs:enable
    }

    private function renderDatesForm(): void
    {

        echo '<h2>' . esc_html__('Dates des journees', 'mon-club-tt') . '</h2>';
        echo '<p class="description">' . esc_html__('Saisissez les 7 dates de chaque phase. Ces dates sont affichees dans l\'application et envoyees par SMS.', 'mon-club-tt') . '</p>';

        echo '<div style="display:flex;gap:48px;flex-wrap:wrap">';
        foreach ([1 => 'monclubtt_ge_journee_dates_p1', 2 => 'monclubtt_ge_journee_dates_p2'] as $num => $key) {
            $dates = (array) get_option($key, array_fill(0, 7, ''));
            if (count($dates) < 7) {
                $dates = array_pad($dates, 7, '');
            }

            echo '<div>';
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- $num est absint() dans le foreach
            echo '<h3 style="margin-bottom:8px">Phase ' . absint($num) . '</h3>';
            echo '<table role="presentation">';
            for ($j = 1; $j <= 7; $j++) {
                $val  = esc_attr($dates[$j - 1] ?? '');
                $id   = esc_attr('monclubtt_ge_date_p' . absint($num) . '_j' . absint($j));
                $name = esc_attr($key . '[' . ( absint($j) - 1 ) . ']');
                // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- $id/$name/$val sont esc_attr(), $j est absint()
                echo '<tr style="margin-bottom:4px">';
                echo '<th scope="row" style="width:36px;font-weight:600;padding:4px 8px 4px 0"><label for="' . $id . '">J' . absint($j) . '</label></th>';
                echo '<td><input type="date" id="' . $id . '" name="' . $name . '" value="' . $val . '"></td>';
                echo '</tr>';
                // phpcs:enable
            }
            echo '</table>';
            echo '</div>';
        }
        echo '</div>';

    }

    /** Réinitialisation de la saison, hors du formulaire des réglages. */
    private function renderSeasonReset(): void
    {
        $season = \MonClubTT\GestionEquipes\Front\Assets::computeSeason();
        echo '<hr><h2>' . esc_html__('Nouvelle saison', 'mon-club-tt') . '</h2>';
        $confirmMsg = esc_js(__('ATTENTION : supprime toutes les compositions et disponibilites des deux phases. Tapez RESET pour confirmer.', 'mon-club-tt'));
        $resetUrl   = esc_url(rest_url('monclubtt-equipes/v1/season/reset'));
        $nonce      = esc_js(wp_create_nonce('wp_rest'));

        // phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped -- variables déjà échappées via esc_js()/esc_url()
        echo "<div style='margin-top:20px'>";
        echo "<button type='button' class='button' style='color:#b91c1c;border-color:#b91c1c'"
           . " onclick=\"(function(){"
           . "if(prompt('{$confirmMsg}')!=='RESET')return;"
           . "var url='{$resetUrl}',h={'Content-Type':'application/json','X-WP-Nonce':'{$nonce}'};"
           . "Promise.all([1,2].map(function(p){return fetch(url,{method:'POST',headers:h,body:JSON.stringify({phase:p})}).then(function(r){return r.json();});}))"
           . ".then(function(r){alert(r.map(function(d){return d.message;}).join('\n'));location.reload();})"
           . ".catch(function(){alert('" . esc_js(__('Erreur lors de la reinitialisation.', 'mon-club-tt')) . "');});"
           . "})()\">"
           . "&#x1F5D1; " . esc_html__('Reinitialiser la saison', 'mon-club-tt')
           . " " . esc_html($season)
           . "</button>";
        echo "</div>";
        // phpcs:enable
    }

    /**
     * Récapitulatif en lecture seule des règles de qualification (brûlage) :
     * ce que l'application contrôle, ce qu'elle se contente de signaler et ce
     * qui reste à la charge du capitaine. Sources : règlements sportifs FFTT
     * (juillet 2025) et mémo du championnat régional LNATT 2025-2026.
     */
    private function renderBurnageRulesSection(): void
    {
        $statuses = [
            'auto'   => ['#15803d', __('Contrôlé', 'mon-club-tt')],
            'signal' => ['#b45309', __('Signalé', 'mon-club-tt')],
            'none'   => ['#b91c1c', __('Non contrôlé', 'mon-club-tt')],
        ];

        $rules = [
            [
                'signal',
                __('Une seule rencontre par journée', 'mon-club-tt'),
                'FFTT II.112.1',
                __('Un joueur ne peut disputer qu\'une rencontre, dans une seule équipe, au titre d\'une même journée. L\'application signale un joueur déjà placé dans une autre équipe pour la journée, sans l\'empêcher.', 'mon-club-tt'),
            ],
            [
                'auto',
                __('Brûlage après 2 rencontres', 'mon-club-tt'),
                'FFTT II.112.1',
                __('Un joueur ayant disputé 2 rencontres d\'une même phase, consécutives ou non, dans une équipe de numéro N (ou dans des équipes différentes) ne peut plus jouer dans une équipe de numéro supérieur à N.', 'mon-club-tt'),
            ],
            [
                'auto',
                __('Limite de la 2e journée', 'mon-club-tt'),
                'FFTT II.112.1',
                __('À la 2e journée d\'une phase, une équipe de 3 ou 4 joueurs ne peut comporter plus d\'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.', 'mon-club-tt'),
            ],
            [
                'auto',
                __('Joueurs étrangers', 'mon-club-tt'),
                'FFTT II.609',
                __('Au plus 1 joueur étranger (hors UE, EEE et Suisse) par équipe de 4 joueurs. Un deuxième étranger est signalé « 2e étranger ». Les étrangers de moins de 18 ans nés en France ne comptent pas, mais l\'application ne sait pas les distinguer : à vérifier à la main.', 'mon-club-tt'),
            ],
            [
                'signal',
                __('Joueurs mutés', 'mon-club-tt'),
                'FFTT II.610',
                __('Nombre de mutés non limité, quelle que soit la phase ou le type de mutation. Le badge « Mut. » est purement informatif.', 'mon-club-tt'),
            ],
            [
                'auto',
                __('Points minimum (Nouvelle-Aquitaine)', 'mon-club-tt'),
                'LNATT 4.1 / FFTT II.112.4',
                __('Pré-Nationale : 1200 points minimum ; Régionale 1 : 1000 points minimum, atteints lors de l\'un des deux classements officiels de la saison. Pas de minimum en R2 et R3. Exception : si l\'équipe monte à l\'issue de la phase 1, les joueurs ayant disputé au moins 3 rencontres avec elle en phase 1 restent qualifiés. L\'application compare au meilleur classement officiel relevé à chaque synchronisation des données FFTT (resynchronisez après la parution de chaque classement) et se fonde sur le niveau saisi pour chaque équipe.', 'mon-club-tt'),
            ],
            [
                'auto',
                __('Forfait, exempt et forfait adverse', 'mon-club-tt'),
                'FFTT II.112.2',
                __('Forfait de l\'équipe à la 1re journée : à la 2e journée, elle ne peut aligner que des joueurs n\'ayant pas joué la 1re journée dans une autre équipe. Forfait à une autre journée : les joueurs ayant disputé la journée précédente dans l\'équipe forfait ne peuvent pas jouer, ce jour-là, dans une équipe de numéro supérieur. Le forfait se déclare depuis la journée de l\'équipe dans l\'application. Équipe exempte ou bénéficiant d\'un forfait : les joueurs inscrits sur la feuille sont réputés avoir joué ; ces feuilles n\'étant pas publiées par la FFTT, validez la composition à la main.', 'mon-club-tt'),
            ],
        ];

        echo '<h2>' . esc_html__('Règles de brûlage et de qualification', 'mon-club-tt') . '</h2>';
        echo '<p class="description">' . esc_html__('Règles appliquées au championnat régional par équipes de la ligue Nouvelle-Aquitaine (saison 2025-2026). Le numéro d\'équipe est déduit du code (ex. « T2 » → 2). Les équipes départementales suivent en plus le règlement de leur comité, que l\'application ne vérifie pas. Les rencontres jouées sont importées des feuilles de match FFTT (onglet Joueurs & rencontres).', 'mon-club-tt') . '</p>';

        echo '<table class="widefat striped" style="max-width:960px;margin-top:12px">';
        echo '<thead><tr>';
        echo '<th style="width:220px">' . esc_html__('Règle', 'mon-club-tt') . '</th>';
        echo '<th>' . esc_html__('Détail', 'mon-club-tt') . '</th>';
        echo '<th style="width:150px">' . esc_html__('Référence', 'mon-club-tt') . '</th>';
        echo '<th style="width:110px">' . esc_html__('Application', 'mon-club-tt') . '</th>';
        echo '</tr></thead><tbody>';

        foreach ($rules as [$status, $title, $ref, $detail]) {
            [$color, $label] = $statuses[$status];
            echo '<tr>';
            echo '<td><strong>' . esc_html($title) . '</strong></td>';
            echo '<td>' . esc_html($detail) . '</td>';
            echo '<td><code>' . esc_html($ref) . '</code></td>';
            echo '<td><span style="color:' . esc_attr($color) . ';font-weight:600">' . esc_html($label) . '</span></td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

        echo '<p class="description" style="margin-top:8px">';
        echo esc_html__('Contrôlé : l\'application détecte l\'infraction dans les compositions. Signalé : l\'information est affichée, la vérification reste manuelle. Non contrôlé : à vérifier par le capitaine.', 'mon-club-tt') . ' ';
        echo '<a href="' . esc_url('https://www.lnatt.fr/competitions/29-championnat-par-equipes') . '" target="_blank" rel="noopener">' . esc_html__('Règlements de la ligue', 'mon-club-tt') . ' &rarr;</a>';
        echo '</p>';
    }

    private function renderExportSection(): void
    {
        echo '<h2>' . esc_html__('Exporter', 'mon-club-tt') . '</h2>';
        echo '<p class="description" style="max-width:960px">' . esc_html__('Télécharge toutes les données de la gestion des équipes dans un fichier JSON : réglages, joueurs, disponibilités, compositions, effectifs de phase, rencontres jouées, journées validées et forfaits. Sert de sauvegarde ou pour transférer les données vers un autre site, avec l\'import ci-dessus. Les liens magiques ne sont pas exportés.', 'mon-club-tt') . '</p>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="' . esc_attr(DataExporter::ACTION) . '">';
        wp_nonce_field(DataExporter::ACTION);
        submit_button(__('Exporter en JSON', 'mon-club-tt'), 'secondary', 'submit', false);
        echo '</form>';
    }

    private function renderRedirectNotice(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended
        $notice = isset($_GET['monclubtt_ge_notice']) ? sanitize_key($_GET['monclubtt_ge_notice']) : '';
        $pageId = isset($_GET['monclubtt_ge_page_id']) ? (int) $_GET['monclubtt_ge_page_id'] : 0;
        // phpcs:enable

        if ($notice === 'created' && $pageId > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html__('Page creee avec succes.', 'mon-club-tt') . ' ';
            echo '<a href="' . esc_url((string) get_permalink($pageId)) . '" target="_blank" rel="noopener"><strong>' . esc_html__("Voir l'application", 'mon-club-tt') . ' &rarr;</strong></a>';
            echo ' &mdash; <a href="' . esc_url((string) get_edit_post_link($pageId)) . '">' . esc_html__('Modifier la page', 'mon-club-tt') . '</a>';
            echo '</p></div>';
        } elseif ($notice === 'create_error') {
            echo '<div class="notice notice-error is-dismissible"><p>';
            echo esc_html__('Erreur lors de la creation de la page.', 'mon-club-tt');
            echo '</p></div>';
        }
    }

    private function renderFrontLink(): void
    {
        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- lecture unique à l'affichage, pas de valeur à mettre en cache
        $page = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts}
                 WHERE post_status  = 'publish'
                   AND post_type    = 'page'
                   AND post_content LIKE %s
                 LIMIT 1",
                '%[tt_team_planner]%'
            )
        );

        if ($page) {
            echo '<a href="' . esc_url((string) get_permalink((int) $page->ID)) . '" class="page-title-action" target="_blank" rel="noopener">';
            echo '&#x1F3D3; ' . esc_html__("Ouvrir l'application", 'mon-club-tt') . ' &rarr;';
            echo '</a>';
        } else {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;margin-left:6px">';
            echo '<input type="hidden" name="action" value="monclubtt_ge_create_front_page">';
            wp_nonce_field('monclubtt_ge_create_front_page');
            echo '<button type="submit" class="page-title-action">+ ' . esc_html__('Creer la page front', 'mon-club-tt') . '</button>';
            echo '</form>';
        }
    }

    private function renderSyncSection(mixed $lastSync, PlayerRepository $repo): void
    {
        echo '<h2>' . esc_html__('Joueurs', 'mon-club-tt') . '</h2>';
        echo '<p>';
        echo esc_html__('Les joueurs sont repris des données FFTT et mis à jour à chaque synchronisation (Données FFTT › Connexion). Le bouton ci-dessous relance seulement cette mise à jour.', 'mon-club-tt');
        echo '<br><em>' . esc_html__('Les données saisies dans l\'application (téléphone, e-mail, équipe habituelle, capitaine, notes) sont conservées.', 'mon-club-tt') . '</em>';
        echo '</p>';

        echo '<table class="form-table" role="presentation" style="margin-bottom:12px">';
        echo '<tr><th>' . esc_html__('Dernière mise à jour', 'mon-club-tt') . '</th>';
        echo '<td>' . esc_html($lastSync ? (string) $lastSync : __('Jamais', 'mon-club-tt')) . '</td></tr>';
        echo '<tr><th>' . esc_html__('Joueurs en base', 'mon-club-tt') . '</th>';
        echo '<td id="ttp-player-count">' . esc_html((string) $repo->count()) . '</td></tr>';
        echo '</table>';

        echo '<button id="ttp-sync-btn" class="button button-primary">';
        echo esc_html__('Mettre à jour les joueurs', 'mon-club-tt');
        echo '</button>';
        echo '<div id="ttp-sync-result" style="margin-top:12px;display:none"></div>';

        $syncUrl   = esc_url(rest_url('monclubtt-equipes/v1/players/sync'));
        $nonce     = esc_js(wp_create_nonce('wp_rest'));
        $lblWait   = esc_js(__('Synchronisation en cours...', 'mon-club-tt'));
        $lblSync   = esc_js(__('Mettre à jour les joueurs', 'mon-club-tt'));
        $lblNet    = esc_js(__('Erreur réseau.', 'mon-club-tt'));

        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- variables inline déjà échappées via esc_js()/esc_url()
        echo "<script>
        document.getElementById('ttp-sync-btn')?.addEventListener('click', async function () {
            var btn    = this;
            var result = document.getElementById('ttp-sync-result');
            btn.disabled    = true;
            btn.textContent = '{$lblWait}';
            result.style.display = 'none';

            var res, text;
            try {
                res  = await fetch('{$syncUrl}', { method: 'POST', headers: { 'X-WP-Nonce': '{$nonce}' } });
                text = await res.text();
            } catch (e) {
                result.style.display = 'block';
                result.className     = 'notice notice-error';
                result.innerHTML     = '<p>Erreur réseau : ' + e.message + '</p>';
                btn.disabled = false; btn.textContent = '{$lblSync}';
                return;
            }

            try {
                var data = JSON.parse(text);
                result.style.display = 'block';
                result.className     = 'notice ' + (res.ok ? 'notice-success' : 'notice-error');
                result.innerHTML     = '<p>' + (data.message || JSON.stringify(data)) + '</p>';
                if (res.ok && data.synced !== undefined) {
                    var c = document.getElementById('ttp-player-count');
                    if (c) c.textContent = data.synced;
                }
            } catch (e) {
                // La reponse n'est pas du JSON (ex: erreur PHP fatale) — on l'affiche brut
                result.style.display = 'block';
                result.className     = 'notice notice-error';
                result.innerHTML     = '<p>HTTP ' + res.status + ' — reponse non-JSON :</p>'
                    + '<pre style=\"max-height:200px;overflow:auto;font-size:11px\">' + text.substring(0, 2000) + '</pre>';
            }

            btn.disabled    = false;
            btn.textContent = '{$lblSync}';
        });
        </script>";
    }

    private function renderImportSection(): void
    {
        $available  = \MonClubTT\GestionEquipes\Sync\AppearanceImporter::isAvailable();
        $lastImport = get_option('monclubtt_ge_last_appearance_import');

        echo '<h2>' . esc_html__('Rencontres jouées', 'mon-club-tt') . '</h2>';
        echo '<p>';
        echo esc_html__('Les rencontres jouées sont importées des feuilles de match FFTT, deux fois par jour : chaque équipe du club est rattachée à l\'équipe de même numéro (équipe FFTT n° 3 ↔ code « E3 ») et les joueurs sont reconnus par leur nom. La feuille officielle remplace une validation manuelle de la même journée.', 'mon-club-tt');
        echo '</p>';

        if (! $available) {
            echo '<div class="notice notice-warning inline"><p>' . esc_html__('Import indisponible. En attendant, validez les compositions à la main dans l\'application.', 'mon-club-tt') . '</p></div>';
            return;
        }

        echo '<table class="form-table" role="presentation" style="margin-bottom:12px">';
        echo '<tr><th>' . esc_html__('Dernier import', 'mon-club-tt') . '</th>';
        echo '<td>' . esc_html($lastImport ? (string) $lastImport : __('Jamais', 'mon-club-tt')) . '</td></tr>';
        echo '</table>';

        echo '<button id="ttp-import-btn" class="button">' . esc_html__('Importer maintenant', 'mon-club-tt') . '</button>';
        echo '<div id="ttp-import-result" style="margin-top:12px;display:none"></div>';

        $importUrl = esc_url(rest_url('monclubtt-equipes/v1/appearances/import'));
        $nonce     = esc_js(wp_create_nonce('wp_rest'));
        $labels    = wp_json_encode([
            'wait'      => __('Import en cours...', 'mon-club-tt'),
            'idle'      => __('Importer maintenant', 'mon-club-tt'),
            'exempt'    => __('Journées exemptes (feuille à valider à la main)', 'mon-club-tt'),
            'empty'     => __('Rencontres jouées sans joueurs du club sur la feuille (forfait à déclarer ?)', 'mon-club-tt'),
            'unmatched' => __('Joueurs de la feuille introuvables (resynchronisez les joueurs)', 'mon-club-tt'),
            'unmapped'  => __('Équipes FFTT sans équipe correspondante dans les réglages', 'mon-club-tt'),
            'network'   => __('Erreur réseau.', 'mon-club-tt'),
        ]);

        // Rapport construit en DOM (textContent) : les noms viennent de la FFTT.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- URL et nonce échappés, libellés encodés par wp_json_encode()
        echo "<script>
        (function () {
            var L = {$labels};
            var btn = document.getElementById('ttp-import-btn');
            var out = document.getElementById('ttp-import-result');
            function add(tag, text, parent) { var el = document.createElement(tag); el.textContent = text; (parent || out).appendChild(el); return el; }
            btn.addEventListener('click', async function () {
                btn.disabled = true; btn.textContent = L.wait;
                out.style.display = 'none'; out.textContent = '';
                try {
                    var res  = await fetch('{$importUrl}', { method: 'POST', headers: { 'X-WP-Nonce': '{$nonce}' } });
                    var data = await res.json();
                    out.className = 'notice ' + (res.ok ? 'notice-success' : 'notice-error');
                    add('p', data.message || '');
                    ['exempt', 'empty', 'unmatched', 'unmapped'].forEach(function (k) {
                        if (!data[k] || !data[k].length) return;
                        add('p', L[k] + ' :');
                        var ul = add('ul', '');
                        ul.style.cssText = 'list-style:disc;margin-left:20px';
                        data[k].forEach(function (item) { add('li', item, ul); });
                    });
                } catch (e) {
                    out.className = 'notice notice-error';
                    add('p', L.network + ' ' + e.message);
                }
                out.style.display = 'block';
                btn.disabled = false; btn.textContent = L.idle;
            });
        })();
        </script>";
    }
}
