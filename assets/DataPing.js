jQuery(document).ready(function ($) {

    // ===== Tableau de joueurs triable =====
    jQuery('.sortableTable').tablesorter();

    // ===== Feuilles de match =====

    // Clic sur une ligne de rencontre expandable
    $(document).on('click', '.dataping-expandable', function () {
        var $row       = $(this);
        var $detailRow = $row.next('.dataping-feuille-row');
        var $icon      = $row.find('.dataping-expand-icon');
        var $content   = $detailRow.find('.dataping-feuille-content');

        if ($detailRow.is(':visible')) {
            // Réduire
            $detailRow.slideUp(200);
            $icon.text('▶');
            return;
        }

        // Développer
        $detailRow.slideDown(200);
        $icon.text('▼');

        // Déjà chargé ?
        if ($content.hasClass('dataping-loaded')) {
            return;
        }

        // Indiquer le chargement
        $content.html('<p class="dataping-feuille-loading">Chargement de la feuille de match…</p>');

        $.ajax({
            url:  DataPingAjax.ajaxurl,
            type: 'POST',
            data: {
                action:     'dataping_feuille_match',
                renc_id:    $row.data('renc-id'),
                is_retour:  $row.data('is-retour')
            },
            success: function (response) {
                if (response.success) {
                    $content.html(buildFeuilleHtml(response.data));
                    $content.addClass('dataping-loaded');
                } else {
                    $content.html('<p class="dataping-feuille-error">Feuille de match non disponible.</p>');
                }
            },
            error: function () {
                $content.html('<p class="dataping-feuille-error">Erreur de chargement.</p>');
            }
        });
    });

    /**
     * Construit le HTML de la feuille de match à partir des données AJAX.
     * @param {Object} data  { resultat, joueur, partie }
     * @returns {string}
     */
    function buildFeuilleHtml(data) {
        var html = '<div class="dataping-feuille">';

        // --- Score global ---
        if (data.resultat) {
            var r    = data.resultat;
            var resA = parseInt(r.resa, 10);
            var resB = parseInt(r.resb, 10);
            html += '<div class="dataping-feuille-resultat">';
            html += '<span class="dataping-feuille-equipe' + (resA > resB ? ' dataping-winner' : '') + '">' + esc(r.equa) + '</span>';
            html += '<span class="dataping-feuille-score"> ' + esc(r.resa) + ' – ' + esc(r.resb) + ' </span>';
            html += '<span class="dataping-feuille-equipe' + (resB > resA ? ' dataping-winner' : '') + '">' + esc(r.equb) + '</span>';
            html += '</div>';
        }

        // --- Composition ---
        if (data.joueur) {
            var joueurs = Array.isArray(data.joueur) ? data.joueur : [data.joueur];
            if (joueurs.length > 0) {
                html += '<h6 class="dataping-feuille-section">Composition</h6>';
                html += '<table class="dataping-table dataping-feuille-compo"><thead><tr>';
                html += '<th>Équipe A</th><th>Classement</th><th>Équipe B</th><th>Classement</th>';
                html += '</tr></thead><tbody>';
                joueurs.forEach(function (j) {
                    html += '<tr>';
                    html += '<td>' + esc(j.xja || '') + '</td>';
                    html += '<td class="center">' + esc(j.xca || '') + '</td>';
                    html += '<td>' + esc(j.xjb || '') + '</td>';
                    html += '<td class="center">' + esc(j.xcb || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
        }

        // --- Parties ---
        if (data.partie) {
            var parties = Array.isArray(data.partie) ? data.partie : [data.partie];
            if (parties.length > 0) {
                html += '<h6 class="dataping-feuille-section">Résultats des parties</h6>';
                html += '<table class="dataping-table dataping-feuille-parties"><thead><tr>';
                html += '<th class="left">Joueur A</th><th>Sc.</th><th></th><th>Sc.</th><th class="left">Joueur B</th><th>Détail</th>';
                html += '</tr></thead><tbody>';
                parties.forEach(function (p) {
                    var wonA = String(p.scorea) === '1';
                    var wonB = String(p.scoreb) === '1';
                    html += '<tr>';
                    html += '<td class="' + (wonA ? 'dataping-winner' : '') + '">' + esc(p.ja  || '') + '</td>';
                    html += '<td class="center dataping-score">' + esc(String(p.scorea)) + '</td>';
                    html += '<td class="center dataping-tiret">–</td>';
                    html += '<td class="center dataping-score">' + esc(String(p.scoreb)) + '</td>';
                    html += '<td class="' + (wonB ? 'dataping-winner' : '') + '">' + esc(p.jb  || '') + '</td>';
                    html += '<td class="dataping-sets">'  + esc(p.detail || '') + '</td>';
                    html += '</tr>';
                });
                html += '</tbody></table>';
            }
        }

        html += '</div>';
        return html;
    }

    /** Échappe les caractères HTML spéciaux. */
    function esc(str) {
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
    }

});

/* ===================== TOP PROGRESSION ===================== */
(function () {
    'use strict';
    if (typeof DataPingTopProg === 'undefined') return;

    var players     = DataPingTopProg.players;
    var moisLabel   = DataPingTopProg.moisLabel;
    var saisonLabel = DataPingTopProg.saisonLabel;

    /* ---- Géométrie du podium ---- */
    var COLS = {
        1: { cx: 360, top: 188, w: 200 },
        2: { cx: 148, top: 262, w: 200 },
        3: { cx: 572, top: 306, w: 200 }
    };
    var BASE_Y = 470, DX = 18, DY = -13;
    var MEDAL_COLOR = { 1: '#f0b429', 2: '#aeb9c4', 3: '#c8794a' };

    /* ---- Avatars SVG ---- */
    function avatarMale() {
        return '<svg viewBox="0 0 128 168" xmlns="http://www.w3.org/2000/svg">' +
            '<g>' +
              '<rect x="84" y="38" width="11" height="34" rx="5.5" fill="#e7b48f" transform="rotate(38 90 55)"/>' +
              '<rect x="84" y="36" width="11" height="14" rx="4" fill="#2b7cb5" transform="rotate(38 90 43)"/>' +
              '<g transform="translate(2 0)">' +
                '<ellipse cx="108" cy="22" rx="14" ry="16" fill="#d34328"/>' +
                '<ellipse cx="108" cy="22" rx="14" ry="16" fill="none" stroke="#fff" stroke-width="2"/>' +
                '<rect x="102" y="35" width="7" height="14" rx="3" fill="#e7c9a3" transform="rotate(18 105 42)"/>' +
              '</g>' +
            '</g>' +
            '<rect x="51" y="108" width="11" height="40" rx="5.5" fill="#e7b48f"/>' +
            '<rect x="66" y="108" width="11" height="40" rx="5.5" fill="#e7b48f"/>' +
            '<path d="M48 146 h15 v6 q0 4 -4 4 h-11 q-3 0 -3 -3 z" fill="#ffffff" stroke="#d9e0e6" stroke-width="1"/>' +
            '<path d="M65 146 h15 v7 q0 3 -3 3 h-12 v-10 z" fill="#ffffff" stroke="#d9e0e6" stroke-width="1"/>' +
            '<path d="M46 92 h36 v15 q0 4 -4 4 h-9 l-5 -10 -5 10 h-9 q-4 0 -4 -4 z" fill="#23344a"/>' +
            '<rect x="35" y="60" width="11" height="36" rx="5.5" fill="#e7b48f"/>' +
            '<rect x="35" y="58" width="11" height="13" rx="4" fill="#2b7cb5"/>' +
            '<path d="M44 60 q1 -6 7 -7 l26 0 q6 1 7 7 l0 30 q0 5 -6 5 l-28 0 q-6 0 -6 -5 z" fill="#2b7cb5"/>' +
            '<path d="M44 60 q1 -6 7 -7 l4 0 l0 42 l-9 0 q-2 0 -2 -3 z" fill="#1f5e8b"/>' +
            '<path d="M56 53 l8 8 l8 -8 q-8 -3 -16 0 z" fill="#ffffff"/>' +
            '<path d="M64 61 l-5 -6 l5 0 l5 0 z" fill="#d34328"/>' +
            '<rect x="58" y="46" width="12" height="10" rx="3" fill="#dba883"/>' +
            '<circle cx="64" cy="36" r="15" fill="#e7b48f"/>' +
            '<ellipse cx="58" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
            '<ellipse cx="70" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
            '<path d="M59 45 q5 3 10 0" stroke="#c98c63" stroke-width="1.6" fill="none" stroke-linecap="round"/>' +
            '<path d="M49 35 q1 -18 15 -18 q14 0 15 18 q-5 -7 -15 -7 q-10 0 -15 7 z" fill="#3a2f28"/>' +
        '</svg>';
    }

    function avatarFemale() {
        return '<svg viewBox="0 0 128 168" xmlns="http://www.w3.org/2000/svg">' +
            '<g>' +
              '<rect x="84" y="38" width="11" height="34" rx="5.5" fill="#ecbb98" transform="rotate(38 90 55)"/>' +
              '<rect x="84" y="36" width="11" height="14" rx="4" fill="#d34328" transform="rotate(38 90 43)"/>' +
              '<g transform="translate(2 0)">' +
                '<ellipse cx="108" cy="22" rx="14" ry="16" fill="#2b7cb5"/>' +
                '<ellipse cx="108" cy="22" rx="14" ry="16" fill="none" stroke="#fff" stroke-width="2"/>' +
                '<rect x="102" y="35" width="7" height="14" rx="3" fill="#e7c9a3" transform="rotate(18 105 42)"/>' +
              '</g>' +
            '</g>' +
            '<rect x="52" y="112" width="10" height="36" rx="5" fill="#ecbb98"/>' +
            '<rect x="66" y="112" width="10" height="36" rx="5" fill="#ecbb98"/>' +
            '<path d="M49 146 h14 v6 q0 4 -4 4 h-10 q-3 0 -3 -3 z" fill="#ffffff" stroke="#d9e0e6" stroke-width="1"/>' +
            '<path d="M65 146 h14 v7 q0 3 -3 3 h-11 v-10 z" fill="#ffffff" stroke="#d9e0e6" stroke-width="1"/>' +
            '<path d="M44 92 q20 -5 40 0 l8 26 l-8 -4 l-6 5 l-7 -5 l-7 5 l-7 -5 l-8 4 z" fill="#23344a"/>' +
            '<path d="M64 90 l0 28" stroke="#1b2839" stroke-width="1.4"/>' +
            '<path d="M54 91 l-3 25" stroke="#1b2839" stroke-width="1.2"/>' +
            '<path d="M74 91 l3 25" stroke="#1b2839" stroke-width="1.2"/>' +
            '<rect x="35" y="60" width="11" height="36" rx="5.5" fill="#ecbb98"/>' +
            '<rect x="35" y="58" width="11" height="13" rx="4" fill="#d34328"/>' +
            '<path d="M44 60 q1 -6 7 -7 l26 0 q6 1 7 7 l0 30 q0 5 -6 5 l-28 0 q-6 0 -6 -5 z" fill="#d34328"/>' +
            '<path d="M44 60 q1 -6 7 -7 l4 0 l0 42 l-9 0 q-2 0 -2 -3 z" fill="#b3331c"/>' +
            '<path d="M56 53 l8 9 l8 -9 q-8 -3 -16 0 z" fill="#ffffff"/>' +
            '<rect x="58" y="46" width="12" height="10" rx="3" fill="#e0a980"/>' +
            '<path d="M78 30 q14 4 12 22 q-1 8 -7 11 q5 -10 1 -19 q-3 -8 -10 -10 z" fill="#5a3b22"/>' +
            '<circle cx="64" cy="36" r="15" fill="#ecbb98"/>' +
            '<ellipse cx="58" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
            '<ellipse cx="70" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
            '<path d="M59 45 q5 3 10 0" stroke="#cf9269" stroke-width="1.6" fill="none" stroke-linecap="round"/>' +
            '<path d="M48 38 q-1 -21 16 -21 q17 0 16 21 q-2 -9 -8 -11 l-2 6 l-3 -7 q-9 1 -12 6 q-3 -1 -7 6 z" fill="#5a3b22"/>' +
        '</svg>';
    }

    /* ---- Bloc SVG du podium ---- */
    function blockSVG(rank) {
        var c    = COLS[rank];
        var x    = c.cx - c.w / 2;
        var w    = c.w;
        var top  = c.top;
        var bot  = BASE_Y;
        var front   = 'M' + x + ' ' + top + ' h' + w + ' v' + (bot - top) + ' h' + (-w) + ' z';
        var topFace = 'M' + x + ' ' + top + ' h' + w + ' l' + DX + ' ' + DY + ' h' + (-w) + ' z';
        var side    = 'M' + (x + w) + ' ' + top + ' l' + DX + ' ' + DY + ' v' + (bot - top) + ' l' + (-DX) + ' ' + (-DY) + ' z';
        var midY    = top + (bot - top) / 2 + 22;
        return '<path d="' + topFace + '" fill="#3d5573"/>' +
               '<path d="' + side   + '" fill="#1d2c3d"/>' +
               '<path d="' + front  + '" fill="url(#dtpg' + rank + ')"/>' +
               '<text x="' + c.cx + '" y="' + midY + '" text-anchor="middle"' +
               ' font-family="system-ui,sans-serif" font-weight="900" font-size="62" fill="#ffffff" opacity="0.92">' + rank + '</text>' +
               '<rect x="' + x + '" y="' + top + '" width="' + w + '" height="5" fill="#ffffff" opacity="0.25"/>';
    }

    function buildPodiumSvg() {
        var svg = document.getElementById('dataping-tp-podium-svg');
        if (!svg) return;
        svg.innerHTML =
            '<defs>' +
            '<linearGradient id="dtpg1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2f6fa0"/><stop offset="1" stop-color="#24557c"/></linearGradient>' +
            '<linearGradient id="dtpg2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b3a4f"/><stop offset="1" stop-color="#23303f"/></linearGradient>' +
            '<linearGradient id="dtpg3" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#2b3a4f"/><stop offset="1" stop-color="#23303f"/></linearGradient>' +
            '</defs>' +
            '<ellipse cx="360" cy="478" rx="330" ry="20" fill="#2b3a4f" opacity="0.06"/>' +
            blockSVG(2) + blockSVG(1) + blockSVG(3);
    }

    function medalSVG(rank) {
        var col = MEDAL_COLOR[rank];
        return '<svg class="tp-medal" viewBox="0 0 48 48" style="top:TOPpx">'
            .replace('TOP', COLS[rank].top - 2) +
            '<path d="M14 4 L20 4 L26 22 L18 22 Z" fill="#c0392b" opacity="0.85"/>' +
            '<path d="M34 4 L28 4 L22 22 L30 22 Z" fill="#2b7cb5" opacity="0.85"/>' +
            '<circle cx="24" cy="30" r="15" fill="' + col + '"/>' +
            '<circle cx="24" cy="30" r="15" fill="none" stroke="#ffffff" stroke-width="2" opacity="0.5"/>' +
            '<text x="24" y="36" text-anchor="middle" font-family="system-ui,sans-serif" font-weight="900" font-size="16" fill="#fff">' + rank + '</text>' +
            '</svg>';
    }

    function topThree(metric) {
        return players.slice().sort(function (a, b) {
            return b[metric] - a[metric];
        }).slice(0, 3);
    }

    function renderPodium(mode) {
        var metric  = mode === 'mens' ? 'dm' : 'da';
        var winners = topThree(metric);
        /* ordre visuel : 2e gauche, 1er centre, 3e droite */
        var order = [winners[1], winners[0], winners[2]];
        var ranks = [2, 1, 3];
        var stage = document.getElementById('dataping-tp-stage');
        if (!stage) return;

        var old = stage.querySelectorAll('.tp-figure,.tp-plate,.tp-medal');
        for (var k = 0; k < old.length; k++) { old[k].remove(); }

        order.forEach(function (p, i) {
            if (!p) return;
            var rank = ranks[i];
            var c    = COLS[rank];
            var val  = p[metric];
            var sign = val > 0 ? '+' : '';

            /* Avatar */
            var fig = document.createElement('div');
            fig.className = 'tp-figure tp-figure--anim';
            fig.style.cssText = 'left:' + (c.cx + DX / 2) + 'px;bottom:' + (498 - c.top - 4) + 'px;animation-delay:' + (i * 0.08) + 's';
            fig.innerHTML = p.sex === 'F' ? avatarFemale() : avatarMale();
            stage.appendChild(fig);
            (function (el) {
                setTimeout(function () { el.classList.remove('tp-figure--anim'); }, 700 + i * 80);
            }(fig));

            /* Médaille */
            var tmp = document.createElement('div');
            tmp.innerHTML = medalSVG(rank);
            var medalEl = tmp.firstChild;
            medalEl.style.left = (c.cx - 23) + 'px';
            stage.appendChild(medalEl);

            /* Plaque */
            var plate = document.createElement('div');
            plate.className = 'tp-plate';
            plate.style.cssText = 'left:' + (c.cx - 100) + 'px;top:' + (BASE_Y + 8) + 'px';
            plate.innerHTML =
                '<div class="tp-name tp-name--' + (p.sex === 'F' ? 'f' : 'm') + '">' + escTp(p.nom)    + '</div>' +
                '<div class="tp-first">'                                                 + escTp(p.prenom) + '</div>' +
                '<div class="tp-pill">' +
                  '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">' +
                    '<path d="M4 16l6-6 4 4 6-8"/><path d="M20 6v5M20 6h-5"/>' +
                  '</svg>' +
                  sign + val +
                '</div>';
            stage.appendChild(plate);
        });
    }

    function setMode(mode) {
        var toggle = document.getElementById('dataping-tp-toggle');
        if (toggle) {
            var btns = toggle.querySelectorAll('button');
            for (var b = 0; b < btns.length; b++) {
                btns[b].classList.toggle('tp-on', btns[b].dataset.mode === mode);
            }
        }
        var subtitle = document.getElementById('dataping-tp-subtitle');
        var tag      = document.getElementById('dataping-tp-tag');
        if (subtitle) subtitle.textContent = mode === 'mens' ? 'Top 3 — gains sur le mois' : 'Top 3 — gains sur la saison';
        if (tag)      tag.textContent      = mode === 'mens' ? moisLabel : saisonLabel;
        renderPodium(mode);
    }

    function scalePodium() {
        var host  = document.getElementById('dataping-tp-stage-host');
        var stage = document.getElementById('dataping-tp-stage');
        if (!host || !stage) return;
        var scale = Math.min(1, host.offsetWidth / 720);
        stage.style.transform = 'scale(' + scale + ')';
        host.style.height     = Math.round(498 * scale) + 'px';
    }

    function escTp(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function init() {
        var toggle = document.getElementById('dataping-tp-toggle');
        if (!toggle) return;
        buildPodiumSvg();
        setMode('mens');
        toggle.addEventListener('click', function (e) {
            var btn = e.target.closest && e.target.closest('button');
            if (!btn) return;
            setMode(btn.dataset.mode);
        });
        scalePodium();
        window.addEventListener('resize', scalePodium);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
