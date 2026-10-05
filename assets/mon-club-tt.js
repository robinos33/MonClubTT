jQuery(document).ready(function ($) {

    // ===== Tableau de joueurs triable =====
    // Tri par défaut : colonne « Pts Off. » (indice 3), décroissant.
    jQuery('.sortableTable').tablesorter({ sortList: [[3, 1]] });

    // Zébrage recalculé sur les seules lignes visibles (après tri ou filtre).
    function rezebrer($table) {
        $table.find('tbody tr:visible').each(function (i) {
            $(this).removeClass('odd even').addClass(i % 2 ? 'odd' : 'even');
        });
    }
    $('.sortableTable').on('sortEnd', function () { rezebrer($(this)); });

    // ===== Filtre Tous / Hommes / Femmes =====
    $(document).on('click', '.monclubtt-filtre', function () {
        var filtre = $(this).data('filtre');
        var $div   = $(this).closest('.monclubtt-div');
        var $table = $div.find('.listeJoueurs');

        $div.find('.monclubtt-filtre').attr('aria-pressed', 'false');
        $(this).attr('aria-pressed', 'true');

        $table.find('tbody tr').each(function () {
            $(this).toggle(filtre === 'MF' || $(this).hasClass(filtre));
        });
        rezebrer($table);

        $div.find('.monclubtt-stats').each(function () {
            this.hidden = $(this).data('filtre') !== filtre;
        });

        document.dispatchEvent(new CustomEvent('monclubtt:filtre', { detail: { sexe: filtre } }));
    });

    // ===== Feuilles de match =====

    // Clic sur une ligne de rencontre expandable
    $(document).on('click', '.monclubtt-expandable', function () {
        var $row       = $(this);
        var $detailRow = $row.next('.monclubtt-feuille-row');
        var $icon      = $row.find('.monclubtt-expand-icon');
        var $content   = $detailRow.find('.monclubtt-feuille-content');

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
        if ($content.hasClass('monclubtt-loaded')) {
            return;
        }

        // Indiquer le chargement
        $content.html('<p class="monclubtt-feuille-loading">Chargement de la feuille de match…</p>');

        $.ajax({
            url:  MonClubTTAjax.ajaxurl,
            type: 'POST',
            data: {
                action:     'monclubtt_feuille_match',
                renc_id:    $row.data('renc-id'),
                is_retour:  $row.data('is-retour')
            },
            success: function (response) {
                if (response.success) {
                    $content.html(buildFeuilleHtml(response.data));
                    $content.addClass('monclubtt-loaded');
                } else {
                    $content.html('<p class="monclubtt-feuille-error">Feuille de match non disponible.</p>');
                }
            },
            error: function () {
                $content.html('<p class="monclubtt-feuille-error">Erreur de chargement.</p>');
            }
        });
    });

    /**
     * Construit le HTML de la feuille de match à partir des données AJAX.
     * @param {Object} data  { resultat, joueur, partie }
     * @returns {string}
     */
    function buildFeuilleHtml(data) {
        var html = '<div class="monclubtt-feuille">';

        // --- Score global ---
        if (data.resultat) {
            var r    = data.resultat;
            var resA = parseInt(r.resa, 10);
            var resB = parseInt(r.resb, 10);
            html += '<div class="monclubtt-feuille-resultat">';
            html += '<span class="monclubtt-feuille-equipe' + (resA > resB ? ' monclubtt-winner' : '') + '">' + esc(r.equa) + '</span>';
            html += '<span class="monclubtt-feuille-score"> ' + esc(r.resa) + ' – ' + esc(r.resb) + ' </span>';
            html += '<span class="monclubtt-feuille-equipe' + (resB > resA ? ' monclubtt-winner' : '') + '">' + esc(r.equb) + '</span>';
            html += '</div>';
        }

        // --- Composition ---
        if (data.joueur) {
            var joueurs = Array.isArray(data.joueur) ? data.joueur : [data.joueur];
            if (joueurs.length > 0) {
                html += '<h6 class="monclubtt-feuille-section">Composition</h6>';
                html += '<table class="monclubtt-table monclubtt-feuille-compo"><thead><tr>';
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
                html += '<h6 class="monclubtt-feuille-section">Résultats des parties</h6>';
                html += '<table class="monclubtt-table monclubtt-feuille-parties"><thead><tr>';
                html += '<th class="left">Joueur A</th><th>Sc.</th><th></th><th>Sc.</th><th class="left">Joueur B</th><th>Détail</th>';
                html += '</tr></thead><tbody>';
                parties.forEach(function (p) {
                    var wonA = String(p.scorea) === '1';
                    var wonB = String(p.scoreb) === '1';
                    html += '<tr>';
                    html += '<td class="' + (wonA ? 'monclubtt-winner' : '') + '">' + esc(p.ja  || '') + '</td>';
                    html += '<td class="center monclubtt-score">' + esc(String(p.scorea)) + '</td>';
                    html += '<td class="center monclubtt-tiret">–</td>';
                    html += '<td class="center monclubtt-score">' + esc(String(p.scoreb)) + '</td>';
                    html += '<td class="' + (wonB ? 'monclubtt-winner' : '') + '">' + esc(p.jb  || '') + '</td>';
                    html += '<td class="monclubtt-sets">'  + esc(p.detail || '') + '</td>';
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

/* ===================== AVATARS DESSINÉS ===================== */
/* Partagés entre le widget Top Progression et les visuels réseaux sociaux.
   showHead=false : corps sans tête, pour poser une photo détourée par-dessus.
   opts.maillot : couleur du maillot (#rrggbb). */
var MonClubTTAvatars = (function () {
    'use strict';

    function assombrir(hex, t) {
        var n = parseInt(String(hex).slice(1), 16);
        return '#' + [16, 8, 0].map(function (d) {
            return ('0' + Math.round(((n >> d) & 255) * (1 - t)).toString(16)).slice(-2);
        }).join('');
    }

    function valide(hex) {
        return /^#[0-9a-f]{6}$/i.test(String(hex || ''));
    }

    function avatarMale(showHead, opts) {
        var maillot = opts && valide(opts.maillot) ? opts.maillot : '#2b7cb5';
        var ombre   = assombrir(maillot, 0.28);
        return '<svg viewBox="0 0 128 168" xmlns="http://www.w3.org/2000/svg">' +
            '<g>' +
              '<rect x="84" y="38" width="11" height="34" rx="5.5" fill="#e7b48f" transform="rotate(38 90 55)"/>' +
              '<rect x="84" y="36" width="11" height="14" rx="4" fill="' + maillot + '" transform="rotate(38 90 43)"/>' +
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
            '<rect x="35" y="58" width="11" height="13" rx="4" fill="' + maillot + '"/>' +
            '<path d="M44 60 q1 -6 7 -7 l26 0 q6 1 7 7 l0 30 q0 5 -6 5 l-28 0 q-6 0 -6 -5 z" fill="' + maillot + '"/>' +
            '<path d="M44 60 q1 -6 7 -7 l4 0 l0 42 l-9 0 q-2 0 -2 -3 z" fill="' + ombre + '"/>' +
            '<path d="M56 53 l8 8 l8 -8 q-8 -3 -16 0 z" fill="#ffffff"/>' +
            '<path d="M64 61 l-5 -6 l5 0 l5 0 z" fill="#d34328"/>' +
            '<rect x="58" y="46" width="12" height="10" rx="3" fill="#dba883"/>' +
            (showHead === false ? '' :
                '<circle cx="64" cy="36" r="15" fill="#e7b48f"/>' +
                '<ellipse cx="58" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
                '<ellipse cx="70" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
                '<path d="M59 45 q5 3 10 0" stroke="#c98c63" stroke-width="1.6" fill="none" stroke-linecap="round"/>' +
                '<path d="M49 35 q1 -18 15 -18 q14 0 15 18 q-5 -7 -15 -7 q-10 0 -15 7 z" fill="#3a2f28"/>'
            ) +
        '</svg>';
    }

    function avatarFemale(showHead, opts) {
        var maillot = opts && valide(opts.maillot) ? opts.maillot : '#d34328';
        var ombre   = assombrir(maillot, 0.2);
        return '<svg viewBox="0 0 128 168" xmlns="http://www.w3.org/2000/svg">' +
            '<g>' +
              '<rect x="84" y="38" width="11" height="34" rx="5.5" fill="#ecbb98" transform="rotate(38 90 55)"/>' +
              '<rect x="84" y="36" width="11" height="14" rx="4" fill="' + maillot + '" transform="rotate(38 90 43)"/>' +
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
            '<rect x="35" y="58" width="11" height="13" rx="4" fill="' + maillot + '"/>' +
            '<path d="M44 60 q1 -6 7 -7 l26 0 q6 1 7 7 l0 30 q0 5 -6 5 l-28 0 q-6 0 -6 -5 z" fill="' + maillot + '"/>' +
            '<path d="M44 60 q1 -6 7 -7 l4 0 l0 42 l-9 0 q-2 0 -2 -3 z" fill="' + ombre + '"/>' +
            '<path d="M56 53 l8 9 l8 -9 q-8 -3 -16 0 z" fill="#ffffff"/>' +
            '<rect x="58" y="46" width="12" height="10" rx="3" fill="#e0a980"/>' +
            (showHead === false ? '' :
                '<path d="M78 30 q14 4 12 22 q-1 8 -7 11 q5 -10 1 -19 q-3 -8 -10 -10 z" fill="#5a3b22"/>' +
                '<circle cx="64" cy="36" r="15" fill="#ecbb98"/>' +
                '<ellipse cx="58" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
                '<ellipse cx="70" cy="40" rx="2.2" ry="2.6" fill="#3a2f28"/>' +
                '<path d="M59 45 q5 3 10 0" stroke="#cf9269" stroke-width="1.6" fill="none" stroke-linecap="round"/>' +
                '<path d="M48 38 q-1 -21 16 -21 q17 0 16 21 q-2 -9 -8 -11 l-2 6 l-3 -7 q-9 1 -12 6 q-3 -1 -7 6 z" fill="#5a3b22"/>'
            ) +
        '</svg>';
    }

    /* ---------- Joueur du jeu de pong ----------
       Position de match, repère -128..128 × 0..200 (axe du corps en x = 0,
       pieds en y ≈ 195). Membres en « capsules » effilées, contour foncé,
       ombrage en aplat côté droit (lumière en haut à gauche). La raquette est
       tenue dans le poing : manche évasé, plateau à bande de chant, rouge en
       coup droit et noir en revers.
       opts.pose : 'attente' (défaut), 'cd' ou 'rv' ;
       opts.tenue : couleur du maillot (club) ou code pays de SELECTIONS ;
       opts.tete : false pour poser une photo détourée à la place. */

    /* Tenues des sélections, inspirées des drapeaux (pas des maillots officiels). */
    var SELECTIONS = {
        CHN: { maillot: '#d71f2a', panneau: '#a3141d', liseret: '#ffd23f', short: '#7e1017', bande: '#ffd23f', drapeau: { fond: '#de2910', etoile: '#ffde00' } },
        JPN: { maillot: '#1f3577', panneau: '#152555', liseret: '#e0283a', col: '#ffffff', short: '#121c40', bande: '#e0283a', drapeau: { fond: '#ffffff', disque: '#bc002d' } },
        KOR: { maillot: '#1b3c8c', panneau: '#132b66', liseret: '#cd2e3a', col: '#ffffff', short: '#13285e', bande: '#cd2e3a', drapeau: { fond: '#ffffff', disque: '#cd2e3a', disque2: '#0047a0' } },
        TPE: { maillot: '#2a56c6', panneau: '#1f429c', liseret: '#ffffff', short: '#18306f', bande: '#ffffff' },
        HKG: { maillot: '#c8102e', panneau: '#9b0c23', liseret: '#ffffff', short: '#7d0a1d', bande: '#ffffff' },
        MAC: { maillot: '#1b8a5a', panneau: '#126543', liseret: '#ffffff', short: '#0e4f33', bande: '#ffffff' },
        IND: { maillot: '#1f4fa0', panneau: '#173c7a', liseret: '#ff9933', short: '#0f2a5c', bande: '#138808', drapeau: { sens: 'h', bandes: ['#ff9933', '#ffffff', '#138808'] } },
        FRA: { maillot: '#1f4fa8', panneau: '#163b80', liseret: '#ffffff', short: '#142a5c', bande: '#e1252f', drapeau: { bandes: ['#0055a4', '#ffffff', '#ef4135'] } },
        GER: { maillot: '#262626', panneau: '#0f0f0f', liseret: '#dd1f26', col: '#ffce00', short: '#1a1a1a', bande: '#ffce00', drapeau: { sens: 'h', bandes: ['#000000', '#dd0000', '#ffce00'] } },
        SWE: { maillot: '#f6c915', panneau: '#d6a800', liseret: '#1a4f9c', short: '#1a4f9c', bande: '#f6c915', drapeau: { fond: '#006aa7', croix: '#fecc00' } },
        POR: { maillot: '#c8102e', panneau: '#9b0c23', liseret: '#046a38', col: '#ffffff', short: '#046a38', bande: '#ffffff', drapeau: { bandes: ['#046a38', '#da291c', '#da291c'] } },
        ROU: { maillot: '#002b7f', panneau: '#001f5c', liseret: '#fcd116', short: '#001a4d', bande: '#ce1126', drapeau: { bandes: ['#002b7f', '#fcd116', '#ce1126'] } },
        SLO: { maillot: '#0b4ea2', panneau: '#083a7a', liseret: '#ffffff', short: '#08326b', bande: '#ed1c24', drapeau: { sens: 'h', bandes: ['#ffffff', '#0b4ea2', '#ed1c24'] } },
        EGY: { maillot: '#ce1126', panneau: '#a00d1d', liseret: '#ffffff', short: '#1a1a1a', bande: '#ffffff', drapeau: { sens: 'h', bandes: ['#ce1126', '#ffffff', '#000000'] } },
        BRA: { maillot: '#13994a', panneau: '#0c7638', liseret: '#f7d117', short: '#1d3e8a', bande: '#f7d117', drapeau: { fond: '#009c3b', losange: '#ffdf00' } },
        USA: { maillot: '#1f3a7a', panneau: '#152a5a', liseret: '#bf0a30', col: '#ffffff', short: '#14254f', bande: '#bf0a30', drapeau: { sens: 'h', bandes: ['#bf0a30', '#ffffff', '#bf0a30', '#ffffff', '#bf0a30'] } }
    };

    var joueurPong = (function () {
    var TRAIT = '#22303d', EP = 1.7;
    function eclaircir(hex, t) {
        var n = parseInt(String(hex).slice(1), 16);
        return '#' + [16, 8, 0].map(function (d) { var v = (n >> d) & 255; return ('0' + Math.round(v + (255 - v) * t).toString(16)).slice(-2); }).join('');
    }
    function f(n) { return Math.round(n * 100) / 100; }
    function lerp(a, b, t) { return [a[0] + (b[0] - a[0]) * t, a[1] + (b[1] - a[1]) * t]; }

    /* Capsule effilée de a (rayon ra) à b (rayon rb). */
    function capsule(a, ra, b, rb, attrs) {
        var dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy) || 1;
        var nx = -dy / L, ny = dx / L;
        var d = 'M' + f(a[0] + nx * ra) + ' ' + f(a[1] + ny * ra) +
            ' L' + f(b[0] + nx * rb) + ' ' + f(b[1] + ny * rb) +
            ' A' + rb + ' ' + rb + ' 0 0 0 ' + f(b[0] - nx * rb) + ' ' + f(b[1] - ny * rb) +
            ' L' + f(a[0] - nx * ra) + ' ' + f(a[1] - ny * ra) +
            ' A' + ra + ' ' + ra + ' 0 0 0 ' + f(a[0] + nx * ra) + ' ' + f(a[1] + ny * ra) + 'Z';
        return '<path d="' + d + '" ' + attrs + '/>';
    }
    /* Ombre propre d'un membre : capsule plus fine, collée au bord droit. */
    function ombreMembre(a, ra, b, rb, couleur) {
        var dx = b[0] - a[0], dy = b[1] - a[1], L = Math.hypot(dx, dy) || 1;
        var nx = -dy / L, ny = dx / L;
        if (nx < 0 || (nx === 0 && ny < 0)) { nx = -nx; ny = -ny; }
        var o = 0.42, k = 0.55;
        return capsule([a[0] + nx * ra * o, a[1] + ny * ra * o], ra * k, [b[0] + nx * rb * o, b[1] + ny * rb * o], rb * k, 'fill="' + couleur + '"');
    }
    function membre(a, ra, b, rb, couleur, ombre) {
        return capsule(a, ra, b, rb, 'fill="' + couleur + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"') +
            ombreMembre(a, ra, b, rb, ombre);
    }

    var POSES = {
        attente: { libre: [-42, 104, -30, 121], coude: [45, 104], main: [36, 121], angle: 28 },
        cd:      { libre: [-44, 99, -57, 114], coude: [50, 95], main: [69, 99], angle: 58 },
        rv:      { libre: [-47, 103, -55, 119], coude: [9, 107], main: [-18, 103], angle: -58 }
    };

    function raquette(x, y, angle, revers) {
        var face = revers ? '#232323' : '#c8202f', reflet = revers ? '#555' : '#ec6a72';
        return '<g transform="translate(' + x + ' ' + y + ') rotate(' + angle + ')">' +
            // manche évasé, bois en lamelles, bouchon foncé
            '<path d="M-5 10 q5 2 10 0 l1.4 -23 q-6.4 -3.4 -12.8 0 z" fill="#d2a46c" stroke="' + TRAIT + '" stroke-width="1.2" stroke-linejoin="round"/>' +
            '<path d="M-2 9.5 l-0.6 -21 M1.6 9.5 l0.5 -21" stroke="#a87a45" stroke-width="0.9"/>' +
            '<path d="M-5 10 q5 2 10 0 l-0.2 -2.4 q-4.8 1.6 -9.6 0 z" fill="#6b4a2b"/>' +
            // plateau : bande de chant, revêtement, ombre interne, logo, reflet
            '<ellipse cx="0" cy="-31" rx="18.4" ry="19.4" fill="#151515" stroke="' + TRAIT + '" stroke-width="1"/>' +
            '<ellipse cx="0" cy="-31" rx="16" ry="17" fill="' + face + '"/>' +
            '<path d="M-4.6 -16.4 h9.2 l-1.4 3.4 h-6.4 z" fill="#f4f4f4" opacity="0.85"/>' +
            '<path d="M-10 -41 q7 -6.5 15.5 -3.4" stroke="' + reflet + '" stroke-width="2.6" fill="none" stroke-linecap="round" opacity="0.9"/>' +
        '</g>';
    }

    function chaussure(x, y, sens, accent) {
        // sens : -1 pied gauche (pointe vers l'extérieur gauche), 1 pied droit
        return '<g transform="translate(' + x + ' ' + y + ') scale(' + sens + ' 1)">' +
            '<path d="M-9 1 q-1 -8 7 -9 h4 q8 1 9 8 l0 1 z" fill="#fbfcfd" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
            '<path d="M-10.4 1 h22 q1 3.4 -2.4 4 h-17.6 q-3 -0.6 -2 -4 z" fill="#cfd6dd" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
            '<path d="M-5 -1.6 q5 -4.4 12 -2.6" stroke="' + accent + '" stroke-width="2.4" fill="none" stroke-linecap="round"/>' +
            '<path d="M-2.4 -6.6 l2.6 2.6 M0.4 -7.4 l2.6 2.6" stroke="#9aa6b1" stroke-width="0.9" stroke-linecap="round"/>' +
        '</g>';
    }

    function tete(femme, peau, maillot) {
        var ombrePeau = assombrir(peau, 0.13), cheveux = femme ? '#5a3b22' : '#3a2a20';
        return '<g>' +
            (femme ? // queue de cheval derrière la tête
                '<path d="M14 28 q22 2 22 26 q0 14 -9 20 q4 -12 -1 -24 q-4 -10 -14 -12 z" fill="' + cheveux + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
                '<path d="M22 34 q8 6 8 18" stroke="#3d2716" stroke-width="1" fill="none"/>' +
                '<rect x="15" y="29" width="7" height="6" rx="2" fill="' + maillot + '" stroke="' + TRAIT + '" stroke-width="1" transform="rotate(20 18 32)"/>' : '') +
            // oreilles
            '<ellipse cx="-22.5" cy="49" rx="4.6" ry="6.4" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="' + EP + '"/>' +
            '<ellipse cx="22.5" cy="49" rx="4.6" ry="6.4" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="' + EP + '"/>' +
            '<path d="M-23 46 q2 3 0 6 M23 46 q-2 3 0 6" stroke="' + ombrePeau + '" stroke-width="1.2" fill="none"/>' +
            // visage
            '<path d="M-21 44 q0 -21 21 -21 q21 0 21 21 q0 14 -7 21 q-6 6 -14 6 q-8 0 -14 -6 q-7 -7 -7 -21 z" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="' + EP + '"/>' +
            '<path d="M12 64 q-6 6 -12 6 q8 -4 12 -12 q4 -8 4 -14 l5 0 q0 13 -9 20 z" fill="' + ombrePeau + '" opacity="0.8"/>' +
            // sourcils, yeux, nez, bouche, joues
            '<path d="M-13 41 q5 -3 9 -1 M4 40 q5 -2 9 1" stroke="' + cheveux + '" stroke-width="2.2" fill="none" stroke-linecap="round"/>' +
            '<ellipse cx="-8" cy="48" rx="3.6" ry="4.2" fill="#fff"/><ellipse cx="8" cy="48" rx="3.6" ry="4.2" fill="#fff"/>' +
            '<circle cx="-7.2" cy="48.6" r="2.4" fill="#4a3426"/><circle cx="8.8" cy="48.6" r="2.4" fill="#4a3426"/>' +
            '<circle cx="-6.5" cy="47.7" r="0.8" fill="#fff"/><circle cx="9.5" cy="47.7" r="0.8" fill="#fff"/>' +
            '<path d="M1 52 q2.4 4.4 -1.4 5.4" stroke="' + assombrir(peau, 0.25) + '" stroke-width="1.4" fill="none" stroke-linecap="round"/>' +
            '<path d="M-5.5 61 q5.5 4 11 0" stroke="#9c5a44" stroke-width="1.8" fill="none" stroke-linecap="round"/>' +
            '<ellipse cx="-13" cy="57" rx="3.6" ry="2.2" fill="#e8846a" opacity="0.32"/><ellipse cx="13" cy="57" rx="3.6" ry="2.2" fill="#e8846a" opacity="0.32"/>' +
            // cheveux
            (femme
                ? '<path d="M-22 46 q-4 -27 22 -27 q25 0 23 27 q-3 -10 -9 -13 q-8 6 -22 6 q-7 0 -10 -3 q-2 6 -4 10 z" fill="' + cheveux + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
                    '<path d="M-6 25 q-6 6 -8 12 M6 24 q4 5 8 9" stroke="#7a5232" stroke-width="1.2" fill="none" stroke-linecap="round"/>'
                : '<path d="M-22 44 q-3 -24 20 -25 q24 -1 24 24 q-3 -6 -7 -8 l-2 4 l-4 -6 q-8 4 -16 3 l-3 5 l-3 -6 q-5 3 -9 9 z" fill="' + cheveux + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
                    '<path d="M-8 23 q-4 4 -6 9 M4 22 q5 3 8 8" stroke="#5a4334" stroke-width="1.2" fill="none" stroke-linecap="round"/>') +
        '</g>';
    }

    /* Tenue : une couleur (maillot du club) ou un objet de sélection
          { maillot, panneau, liseret, col, short, bande, drapeau }. */
    function tenueDe(t) {
        if (typeof t === 'string') t = { maillot: t };
        return {
            maillot: t.maillot, panneau: t.panneau || assombrir(t.maillot, 0.24),
            liseret: t.liseret || '#fff', col: t.col || t.liseret || '#fff',
            short: t.short || '#22334a', bande: t.bande || t.maillot, drapeau: t.drapeau || null
        };
    }

    /* Écusson de poitrine : petit drapeau stylisé, sinon pastille du club. */
    function ecusson(t) {
        var x = -16.5, y = 88.5, w = 10, h = 7, d = t.drapeau, cadre = '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" rx="1" fill="none" stroke="' + TRAIT + '" stroke-width="0.9"/>';
        if (!d) return '<circle cx="-12" cy="92" r="4.6" fill="#fff" stroke="' + TRAIT + '" stroke-width="1"/><circle cx="-12" cy="92" r="2.6" fill="' + t.maillot + '"/>';
        var c = '';
        if (d.bandes) { // bandes verticales (v) ou horizontales (h)
            d.bandes.forEach(function (col, i, a) {
                c += d.sens === 'h' ? '<rect x="' + x + '" y="' + (y + i * h / a.length) + '" width="' + w + '" height="' + (h / a.length + 0.05) + '" fill="' + col + '"/>'
                                                        : '<rect x="' + (x + i * w / a.length) + '" y="' + y + '" width="' + (w / a.length + 0.05) + '" height="' + h + '" fill="' + col + '"/>';
            });
        } else {
            c += '<rect x="' + x + '" y="' + y + '" width="' + w + '" height="' + h + '" fill="' + d.fond + '"/>';
            if (d.croix) c += '<rect x="' + (x + 3) + '" y="' + y + '" width="1.6" height="' + h + '" fill="' + d.croix + '"/><rect x="' + x + '" y="' + (y + 2.7) + '" width="' + w + '" height="1.6" fill="' + d.croix + '"/>';
            if (d.disque) c += '<circle cx="' + (x + w / 2) + '" cy="' + (y + h / 2) + '" r="2.1" fill="' + d.disque + '"/>';
            if (d.disque2) c += '<path d="M' + (x + w / 2 - 2.1) + ' ' + (y + h / 2) + ' a2.1 2.1 0 0 0 4.2 0 z" fill="' + d.disque2 + '"/>';
            if (d.losange) c += '<path d="M' + (x + w / 2) + ' ' + (y + 0.9) + ' l4 2.6 l-4 2.6 l-4 -2.6 z" fill="' + d.losange + '"/><circle cx="' + (x + w / 2) + '" cy="' + (y + h / 2) + '" r="1.4" fill="#1d3e8a"/>';
            if (d.etoile) c += '<circle cx="' + (x + 2.4) + '" cy="' + (y + 2.2) + '" r="1.3" fill="' + d.etoile + '"/>';
        }
        return c + cadre;
    }

    function corps(sexe, pose, tenue, teteDessinee) {
        var femme = sexe === 'F', p = POSES[pose];
        var t = tenueDe(tenue), maillot = t.maillot;
        var peau = femme ? '#ecbb98' : '#e3ad86', ombrePeau = assombrir(peau, 0.14);
        var mOmbre = t.panneau, mClair = eclaircir(maillot, 0.18);
        var short = t.short, shortOmbre = assombrir(short, 0.32);
        var s = '';

        // ---- jambes : cuisse, mollet galbé, chaussette, chaussure
        [-1, 1].forEach(function (c) {
            var hanche = [13 * c, 124], genou = [28 * c, 155], cheville = [36 * c, 181];
            s += membre(lerp(genou, cheville, 0.12), 6.2, lerp(genou, cheville, 0.45), 6.6, peau, ombrePeau);
            s += membre(genou, 6.4, cheville, 4.2, peau, ombrePeau);
            s += capsule(lerp(genou, cheville, 0.72), 5.2, cheville, 4.8, 'fill="#fff" stroke="' + TRAIT + '" stroke-width="' + EP + '"');
            var bande = lerp(genou, cheville, 0.78);
            s += '<path d="M' + f(bande[0] - 4.6) + ' ' + f(bande[1]) + ' h9.2" stroke="' + t.bande + '" stroke-width="1.8"/>';
            s += membre(hanche, 9, genou, 6.6, peau, ombrePeau);
            s += '<path d="M' + f(genou[0] - 3) + ' ' + f(genou[1] - 1) + ' q3 2 6 0" stroke="' + assombrir(peau, 0.2) + '" stroke-width="1" fill="none"/>';
            s += chaussure(cheville[0] + 3 * c, cheville[1] + 7, c, t.bande);
        });

        // ---- short (ou jupette) avec bande latérale à la couleur du club
        if (femme) {
            s += '<path d="M-26 109 q26 -6 52 0 l10 30 q-36 9 -72 0 z" fill="' + short + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
                '<path d="M0 108 v33 M-12 110 l-5 30 M12 110 l5 30" stroke="' + shortOmbre + '" stroke-width="1.4"/>' +
                '<path d="M18 110 l9 29 q-6 1 -12 1.6 z" fill="' + shortOmbre + '"/>' +
                '<path d="M-27 112 l-8.6 26" stroke="' + t.bande + '" stroke-width="2.4"/><path d="M27 112 l8.6 26" stroke="' + t.bande + '" stroke-width="2.4"/>';
        } else {
            s += '<path d="M-25 111 h50 l6 27 q-10 3 -21 1 l-10 -13 l-10 13 q-11 2 -21 -1 z" fill="' + short + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
                '<path d="M14 112 h11 l6 26 q-5 1.4 -10 1.4 z" fill="' + shortOmbre + '"/>' +
                '<path d="M-25.4 113 l-5.8 24.6" stroke="' + t.bande + '" stroke-width="2.6"/><path d="M25.4 113 l5.8 24.6" stroke="' + t.bande + '" stroke-width="2.6"/>' +
                '<path d="M-6 120 l-3 8 M7 121 l2 7" stroke="' + shortOmbre + '" stroke-width="1.2" stroke-linecap="round"/>';
        }

        // ---- cou
        s += '<path d="M-7.5 58 h15 l1.2 20 h-17.4 z" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
            '<path d="M-7.5 58 h15 l0.4 6 q-7.8 4 -15.6 0 z" fill="' + ombrePeau + '"/>';

        // ---- dessous des manches (seul leur contour extérieur dépassera du buste)
        var l = p.libre;
        var EPAULES = [[-21, 84], [21, 84]];
        function manche(epaule, coude) { return { a: epaule, b: lerp(epaule, coude, 0.42) }; }
        var manches = [manche(EPAULES[0], [l[0], l[1]]), manche(EPAULES[1], p.coude)];
        manches.forEach(function (m) {
            s += capsule(m.a, 7.6, m.b, 7.2, 'fill="' + maillot + '" stroke="' + TRAIT + '" stroke-width="' + (2 * EP) + '"');
        });

        // ---- maillot : buste, panneau latéral foncé, col polo, écusson, plis, ourlet
        s += '<path d="M-28 86 q0 -10 10 -12 l9 -2 q9 6 18 0 l9 2 q10 2 10 12 l-4 30 q-1 5 -6 5 h-34 q-5 0 -6 -5 z" fill="' + maillot + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
            '<path d="M17 74 l2 0 q9 2 9 12 l-4 30 q-1 5 -6 5 h-4 q4 -20 3 -47 z" fill="' + mOmbre + '"/>' +
            '<path d="M-18 76 q-6 4 -7 12" stroke="' + mClair + '" stroke-width="2.4" fill="none" stroke-linecap="round" opacity="0.8"/>' +
            '<path d="M-9 72 l9 11 l9 -11 l-2.6 -2.4 l-6.4 8 l-6.4 -8 z" fill="' + t.col + '" stroke="' + TRAIT + '" stroke-width="1.2" stroke-linejoin="round"/>' +
            '<path d="M0 83 v6" stroke="' + mOmbre + '" stroke-width="1.2"/><circle cx="0" cy="86" r="0.9" fill="#fff"/>' +
            ecusson(t) +
            '<path d="M-14 104 q6 3 11 1 M5 108 q5 -2 9 1 M-20 112 q4 -1 7 1" stroke="' + mOmbre + '" stroke-width="1.2" fill="none" stroke-linecap="round"/>' +
            '<path d="M-23.6 117 q23.6 2 47 0" stroke="' + t.liseret + '" stroke-width="1.6" fill="none" opacity="0.7"/>';

        // ---- bras : manche à liseré, bras galbé, avant-bras, poignet
        function bras(epaule, coude, main, m, avecRaquette) {
            var poignet = lerp(main, coude, 0.2);
            var out = membre(lerp(coude, poignet, 0.08), 5.6, lerp(coude, poignet, 0.4), 6, peau, ombrePeau) +
                membre(coude, 5.4, poignet, 4.2, peau, ombrePeau) +
                membre(epaule, 6.8, coude, 5.4, peau, ombrePeau);
            var finManche = m.b;
            out += capsule(m.a, 7.6, m.b, 7.2, 'fill="' + maillot + '"');
            var dx = coude[0] - epaule[0], dy = coude[1] - epaule[1], L = Math.hypot(dx, dy), nx = -dy / L * 6.8, ny = dx / L * 6.8;
            out += '<path d="M' + f(finManche[0] + nx) + ' ' + f(finManche[1] + ny) + ' L' + f(finManche[0] - nx) + ' ' + f(finManche[1] - ny) + '" stroke="' + t.liseret + '" stroke-width="2" stroke-linecap="round"/>';
            if (avecRaquette) {
                var a = lerp(poignet, coude, 0.08), b = lerp(poignet, coude, 0.3);
                out += capsule(a, 5, b, 5.4, 'fill="#fff" stroke="' + TRAIT + '" stroke-width="1.3"');
                var m2 = lerp(a, b, 0.5);
                out += '<circle cx="' + f(m2[0]) + '" cy="' + f(m2[1]) + '" r="1.1" fill="' + maillot + '"/>';
            }
            return out;
        }
        s += bras(EPAULES[0], [l[0], l[1]], [l[2], l[3]], manches[0], false);
        s += '<ellipse cx="' + l[2] + '" cy="' + l[3] + '" rx="5.6" ry="6" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="' + EP + '"/>' +
            '<path d="M' + (l[2] - 2.4) + ' ' + (l[3] + 1) + ' q2.4 2 4.8 0" stroke="' + ombrePeau + '" stroke-width="1" fill="none"/>';
        s += bras(EPAULES[1], p.coude, p.main, manches[1], true);
        s += raquette(p.main[0], p.main[1], p.angle, pose === 'rv');
        // poing par-dessus le manche, doigts repliés, pouce sur le plateau
        var rv = pose === 'rv';
        s += '<g transform="translate(' + p.main[0] + ' ' + p.main[1] + ') rotate(' + p.angle + ')">' +
            '<path d="M-7.6 -4 q0 -4 4 -4 h7.6 q4 0 4 4 v8 q0 4.6 -4.6 4.6 h-6.4 q-4.6 0 -4.6 -4.6 z" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="' + EP + '" stroke-linejoin="round"/>' +
            '<path d="' + (rv ? 'M8 -3.4 h-9 M8 0.2 h-9 M8 3.8 h-8' : 'M-8 -3.4 h9 M-8 0.2 h9 M-8 3.8 h8') + '" stroke="' + assombrir(peau, 0.24) + '" stroke-width="1" stroke-linecap="round"/>' +
            '<path d="' + (rv ? 'M8 -7.6 v15.6 h-3 q3 -8 0 -15.6 z' : 'M-8 -7.6 v15.6 h3 q-3 -8 0 -15.6 z') + '" fill="' + ombrePeau + '" opacity="0.6"/>' +
            '<ellipse cx="' + (rv ? -3.4 : 3.4) + '" cy="-9" rx="2.6" ry="5.4" fill="' + peau + '" stroke="' + TRAIT + '" stroke-width="1.1"/>' +
        '</g>';

        if (teteDessinee) s += tete(femme, peau, maillot);
        return s;
    }

        return function (sexe, opts) {
            opts = opts || {};
            var pose = POSES[opts.pose] ? opts.pose : 'attente';
            var tenue = SELECTIONS[opts.tenue] || (valide(opts.tenue) ? opts.tenue : (sexe === 'F' ? '#d34328' : '#2b7cb5'));
            return '<svg viewBox="-128 0 256 200" xmlns="http://www.w3.org/2000/svg">' +
                corps(sexe === 'F' ? 'F' : 'M', pose, tenue, opts.tete !== false) + '</svg>';
        };
    }());
    return { male: avatarMale, female: avatarFemale, joueur: joueurPong, SELECTIONS: SELECTIONS };
}());

/* ===================== TOP PROGRESSION ===================== */
(function () {
    'use strict';
    if (typeof MonClubTTTopProg === 'undefined') return;

    var players     = MonClubTTTopProg.players;
    var moisLabel   = MonClubTTTopProg.moisLabel;
    var saisonLabel = MonClubTTTopProg.saisonLabel;
    var modeCourant  = 'mens';
    var filtreSexe   = 'MF';

    /* ---- Géométrie du podium ---- */
    var COLS = {
        1: { cx: 360, top: 188, w: 200 },
        2: { cx: 148, top: 262, w: 200 },
        3: { cx: 572, top: 306, w: 200 }
    };
    var BASE_Y = 470, DX = 18, DY = -13;
    var MEDAL_COLOR = { 1: '#f0b429', 2: '#aeb9c4', 3: '#c8794a' };

    var avatarMale   = MonClubTTAvatars.male;
    var avatarFemale = MonClubTTAvatars.female;

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
        // Couleurs du club via les variables CSS du conteneur (style="fill:var(…)").
        return '<path d="' + topFace + '" style="fill:var(--dtp-prim-clair)"/>' +
               '<path d="' + side   + '" style="fill:var(--dtp-prim-ombre)"/>' +
               '<path d="' + front  + '" fill="url(#dtpg' + rank + ')"/>' +
               '<text x="' + c.cx + '" y="' + midY + '" text-anchor="middle"' +
               ' font-family="system-ui,sans-serif" font-weight="900" font-size="62" fill="#ffffff" opacity="0.92">' + rank + '</text>' +
               '<rect x="' + x + '" y="' + top + '" width="' + w + '" height="5" fill="#ffffff" opacity="0.25"/>';
    }

    function buildPodiumSvg() {
        var svg = document.getElementById('monclubtt-tp-podium-svg');
        if (!svg) return;
        svg.innerHTML =
            '<defs>' +
            '<linearGradient id="dtpg1" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--dtp-primaire)"/><stop offset="1" style="stop-color:var(--dtp-prim-fonce)"/></linearGradient>' +
            '<linearGradient id="dtpg2" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--dtp-prim-fonce)"/><stop offset="1" style="stop-color:var(--dtp-prim-ombre)"/></linearGradient>' +
            '<linearGradient id="dtpg3" x1="0" y1="0" x2="0" y2="1"><stop offset="0" style="stop-color:var(--dtp-prim-fonce)"/><stop offset="1" style="stop-color:var(--dtp-prim-ombre)"/></linearGradient>' +
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
        return players.filter(function (p) {
            return filtreSexe === 'MF' || p.sex === filtreSexe;
        }).sort(function (a, b) {
            return b[metric] - a[metric];
        }).slice(0, 3);
    }

    /* Inclinaison aléatoire (-20° à 20°) par photo, gardée d'un mode à l'autre. */
    var inclinaisons = {};
    function inclinaison(photo) {
        if (!(photo in inclinaisons)) inclinaisons[photo] = Math.round(Math.random() * 40 - 20);
        return inclinaisons[photo];
    }

    function renderPodium(mode) {
        var metric  = mode === 'mens' ? 'dm' : 'da';
        var winners = topThree(metric);
        /* ordre visuel : 2e gauche, 1er centre, 3e droite */
        var order = [winners[1], winners[0], winners[2]];
        var ranks = [2, 1, 3];
        var stage      = document.getElementById('monclubtt-tp-stage');
        var nameplates = document.getElementById('monclubtt-tp-nameplates');
        if (!stage || !nameplates) return;

        /* Nettoyer figures + médailles dans le stage */
        var old = stage.querySelectorAll('.tp-figure,.tp-medal');
        for (var k = 0; k < old.length; k++) { old[k].remove(); }
        /* Nettoyer les plaques hors du stage */
        nameplates.innerHTML = '';

        order.forEach(function (p, i) {
            var rank = ranks[i];
            var c    = COLS[rank];

            if (p) {
                var val  = p[metric];
                var sign = val > 0 ? '+' : '';

                /* Avatar : photo détourée si disponible, sinon avatar dessiné */
                var hasPhoto = p.photo && p.photo.length;
                var fig = document.createElement('div');
                fig.className = 'tp-figure tp-figure--anim';
                fig.style.cssText = 'left:' + (c.cx + DX / 2) + 'px;bottom:' + (498 - c.top - 4) + 'px;animation-delay:' + (i * 0.08) + 's';
                var tenue = { maillot: MonClubTTTopProg.maillot };
                fig.innerHTML = p.sex === 'F' ? avatarFemale(!hasPhoto, tenue) : avatarMale(!hasPhoto, tenue);
                if (hasPhoto) {
                    var tilt = inclinaison(p.photo);
                    var photoWrap = document.createElement('div');
                    photoWrap.className = 'tp-photo';
                    photoWrap.style.setProperty('--rot', tilt + 'deg');
                    var photoImg = document.createElement('img');
                    photoImg.src = p.photo;
                    photoImg.alt = '';
                    photoWrap.appendChild(photoImg);
                    fig.appendChild(photoWrap);
                }
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

                /* Plaque — dans le flux normal, hors du stage */
                var plate = document.createElement('div');
                plate.className = 'tp-plate';
                plate.innerHTML =
                    '<div class="tp-name tp-name--' + (p.sex === 'F' ? 'f' : 'm') + '">' + escTp(p.nom)    + '</div>' +
                    '<div class="tp-first">'                                                 + escTp(p.prenom) + '</div>' +
                    '<div class="tp-pill">' +
                      '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round">' +
                        '<path d="M4 16l6-6 4 4 6-8"/><path d="M20 6v5M20 6h-5"/>' +
                      '</svg>' +
                      sign + val +
                    '</div>';
                nameplates.appendChild(plate);
            } else {
                /* Colonne vide pour maintenir l'alignement flex */
                nameplates.appendChild(document.createElement('div'));
            }
        });

        scalePodium();
    }

    function setMode(mode) {
        modeCourant = mode;
        var toggle = document.getElementById('monclubtt-tp-toggle');
        if (toggle) {
            var btns = toggle.querySelectorAll('button');
            for (var b = 0; b < btns.length; b++) {
                btns[b].classList.toggle('tp-on', btns[b].dataset.mode === mode);
            }
        }
        var subtitle = document.getElementById('monclubtt-tp-subtitle');
        var tag      = document.getElementById('monclubtt-tp-tag');
        if (subtitle) subtitle.textContent = mode === 'mens' ? 'Top 3 — gains sur le mois' : 'Top 3 — gains sur la saison';
        if (tag)      tag.textContent      = mode === 'mens' ? moisLabel : saisonLabel;
        renderPodium(mode);
    }

    function scalePodium() {
        var host     = document.getElementById('monclubtt-tp-stage-host');
        var scalable = document.getElementById('monclubtt-tp-scalable');
        if (!host || !scalable) return;
        var scale = Math.min(1, host.offsetWidth / 720);
        scalable.style.transform = 'scale(' + scale + ')';
        host.style.height = Math.round(scalable.offsetHeight * scale) + 'px';
    }

    function escTp(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function init() {
        var toggle = document.getElementById('monclubtt-tp-toggle');
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
        document.addEventListener('monclubtt:filtre', function (e) {
            filtreSexe = e.detail.sexe;
            renderPodium(modeCourant);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
