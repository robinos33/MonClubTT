/* ===================== VISUELS RÉSEAUX SOCIAUX (admin) =====================
 * Rendu côté navigateur dans un <canvas> : aucune dépendance à GD / Imagick,
 * donc fonctionne sur hébergement mutualisé. L'admin télécharge le PNG puis
 * le publie lui-même sur Facebook / Instagram.
 */
(function () {
    'use strict';
    if (typeof MonClubTTSocial === 'undefined') return;

    var DATA = MonClubTTSocial;
    var W = 1080;

    var C = {
        navy:    '#2b3a4f',
        navyDk:  '#1b2839',
        slate:   '#3d5573',
        slateDk: '#34465e',
        blue:    '#2b7cb5',
        blueDk:  '#24557c',
        red:     '#d34328',
        green:   '#2e9e54',
        sky:     '#9cc3e4',
        medal:   { 1: '#f0b429', 2: '#aeb9c4', 3: '#c8794a' }
    };
    var FONT = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    var MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /* Mise en page par format. Story : zones sûres Instagram (≈250 px) en haut et en bas. */
    var LAYOUTS = {
        carre: {
            h: 1080, headY: 70, ts: 1, footY: 1040,
            podium: { baseY: 975, colW: 270, gap: 18, fig: 1.55, heights: { 1: 340, 2: 290, 3: 260 }, tk: 1 },
            perfs:  { top: 368, rowH: 110, gap: 10, max: 5 }
        },
        story: {
            h: 1920, headY: 300, ts: 1.2, footY: 1700,
            podium: { baseY: 1600, colW: 310, gap: 20, fig: 2.3, heights: { 1: 600, 2: 510, 3: 450 }, tk: 1.25 },
            perfs:  { top: 700, rowH: 126, gap: 12, max: 7 }
        }
    };

    var canvas   = document.getElementById('monclubtt-social-canvas');
    var form     = document.getElementById('monclubtt-social-form');
    var btn       = document.getElementById('monclubtt-social-download');
    var legendeEl = document.getElementById('monclubtt-social-message');
    var statusEl  = document.getElementById('monclubtt-social-status');
    if (!canvas || !form || !canvas.getContext) return;
    var ctx = canvas.getContext('2d');

    var perfsData    = null;   // réponse AJAX mise en cache pour la session
    var perfsErreur  = '';
    var renderToken  = 0;
    var imageCache   = {};
    var legendeModifiee = false; // texte retouché à la main : ne plus l'écraser

    /* ---------------------------------------------------------------- état */

    function etat() {
        var fd = new FormData(form);
        return {
            visuel: fd.get('visuel') || 'prog-mens',
            format: fd.get('format') || 'carre',
            sexe:   fd.get('sexe') || 'MF',
            club:   String(fd.get('club') || '').trim()
        };
    }

    function setStatus(msg) {
        statusEl.textContent = msg || '';
    }

    /* ------------------------------------------------------------- images */

    /* Résout avec l'image chargée, ou null en cas d'échec (repli dessiné).
       crossOrigin : sans lui une image d'un autre domaine (CDN) « souillerait »
       le canvas et bloquerait l'export PNG. */
    function chargerImage(url) {
        if (!url) return Promise.resolve(null);
        if (imageCache[url]) return imageCache[url];
        imageCache[url] = new Promise(function (resolve) {
            var img = new Image();
            if (url.indexOf('data:') !== 0) img.crossOrigin = 'anonymous';
            img.onload  = function () { resolve(img); };
            img.onerror = function () { resolve(null); };
            img.src = url;
        });
        return imageCache[url];
    }

    function avatarUrl(sexe, avecTete) {
        var svg = (sexe === 'F' ? MonClubTTAvatars.female : MonClubTTAvatars.male)(avecTete);
        svg = svg.replace('<svg ', '<svg width="128" height="168" ');
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    /* ------------------------------------------------------------ helpers */

    function font(poids, taille) {
        return poids + ' ' + Math.round(taille) + 'px ' + FONT;
    }

    /* Plus grande taille ≤ max qui fait tenir le texte dans la largeur. */
    function ajuster(texte, poids, max, largeur) {
        var taille = max;
        ctx.font = font(poids, taille);
        while (taille > 12 && ctx.measureText(texte).width > largeur) {
            taille -= 2;
            ctx.font = font(poids, taille);
        }
        return taille;
    }

    function espacement(px) {
        if ('letterSpacing' in ctx) ctx.letterSpacing = px + 'px';
    }

    function rectArrondi(x, y, w, h, r) {
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.arcTo(x + w, y, x + w, y + h, r);
        ctx.arcTo(x + w, y + h, x, y + h, r);
        ctx.arcTo(x, y + h, x, y, r);
        ctx.arcTo(x, y, x + w, y, r);
        ctx.closePath();
    }

    /* Pastille verte « +12 pts » centrée (align='center') ou calée à droite. */
    function pastille(texte, x, y, taille, align) {
        ctx.font = font(900, taille);
        espacement(0);
        var w = ctx.measureText(texte).width + taille * 1.2;
        var h = taille * 1.7;
        var left = align === 'right' ? x - w : x - w / 2;
        ctx.fillStyle = C.green;
        rectArrondi(left, y, w, h, h / 2);
        ctx.fill();
        ctx.fillStyle = '#fff';
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(texte, left + w / 2, y + h / 2 + 1);
        ctx.textBaseline = 'alphabetic';
    }

    function signe(v) {
        var n = Math.round(v * 10) / 10;
        return (n > 0 ? '+' : '') + String(n).replace('.', ',');
    }

    /* Photo détourée avec liseré blanc suivant la silhouette (comme le widget). */
    function photoSticker(img, cx, cy, hauteur, angle) {
        var w = hauteur * img.naturalWidth / img.naturalHeight;
        var bord = Math.max(2, hauteur * 0.03);
        var off = document.createElement('canvas');
        off.width = Math.ceil(w);
        off.height = Math.ceil(hauteur);
        var o = off.getContext('2d');
        o.drawImage(img, 0, 0, w, hauteur);
        o.globalCompositeOperation = 'source-in';
        o.fillStyle = '#fff';
        o.fillRect(0, 0, off.width, off.height);

        ctx.save();
        ctx.translate(cx, cy);
        ctx.rotate(angle * Math.PI / 180);
        ctx.shadowColor = 'rgba(15,20,26,0.45)';
        ctx.shadowBlur = hauteur * 0.06;
        ctx.shadowOffsetY = hauteur * 0.04;
        for (var a = 0; a < 16; a++) {
            var t = a * Math.PI / 8;
            ctx.drawImage(off, -w / 2 + Math.cos(t) * bord, -hauteur / 2 + Math.sin(t) * bord);
            ctx.shadowColor = 'transparent';
        }
        ctx.drawImage(img, -w / 2, -hauteur / 2, w, hauteur);
        ctx.restore();
    }

    /* ------------------------------------------------------ fond / cadre */

    function fond(L) {
        var h = L.h;
        var g = ctx.createLinearGradient(0, 0, 0, h);
        g.addColorStop(0, C.navy);
        g.addColorStop(1, C.navyDk);
        ctx.fillStyle = g;
        ctx.fillRect(0, 0, W, h);

        // Balles de ping-pong en filigrane
        ctx.fillStyle = 'rgba(255,255,255,0.045)';
        ctx.beginPath(); ctx.arc(W * 0.88, h * 0.1, 280, 0, Math.PI * 2); ctx.fill();
        ctx.beginPath(); ctx.arc(W * 0.06, h * 0.72, 170, 0, Math.PI * 2); ctx.fill();

        var band = ctx.createLinearGradient(0, 0, W, 0);
        band.addColorStop(0, C.blue);
        band.addColorStop(1, C.red);
        ctx.fillStyle = band;
        ctx.fillRect(0, 0, W, 14);
        ctx.fillRect(0, h - 14, W, 14);

        if (DATA.siteHost) {
            ctx.font = font(700, 26 * L.ts);
            espacement(2);
            ctx.fillStyle = 'rgba(255,255,255,0.55)';
            ctx.textAlign = 'center';
            ctx.fillText(DATA.siteHost, W / 2, L.footY);
        }
    }

    function entete(L, club, titre, tag, logo) {
        var x = 72, y = L.headY, ts = L.ts;
        var logoTaille = 76 * ts;
        var textX = x;
        if (logo) {
            ctx.save();
            ctx.beginPath();
            ctx.arc(x + logoTaille / 2, y + logoTaille / 2 - 8, logoTaille / 2, 0, Math.PI * 2);
            ctx.fillStyle = '#fff';
            ctx.fill();
            ctx.clip();
            ctx.drawImage(logo, x + 6, y - 2, logoTaille - 12, logoTaille - 12);
            ctx.restore();
            textX = x + logoTaille + 20;
        }
        if (club) {
            ctx.textAlign = 'left';
            espacement(4);
            ajuster(club.toUpperCase(), 800, 30 * ts, W - textX - 72);
            ctx.fillStyle = C.sky;
            ctx.fillText(club.toUpperCase(), textX, y + logoTaille / 2 + 2);
        }

        var titreY = y + logoTaille + 108 * ts;
        espacement(0);
        ctx.textAlign = 'left';
        ctx.fillStyle = '#fff';
        ajuster(titre, 900, 112 * ts, W - 144);
        ctx.fillText(titre, x, titreY);

        if (tag) {
            espacement(3);
            var taille = ajuster(tag.toUpperCase(), 800, 28 * ts, W - 144 - 48);
            var w = ctx.measureText(tag.toUpperCase()).width + 48;
            var h = taille * 1.9;
            ctx.fillStyle = C.blue;
            rectArrondi(x, titreY + 30 * ts, w, h, h / 2);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.textBaseline = 'middle';
            ctx.fillText(tag.toUpperCase(), x + 24, titreY + 30 * ts + h / 2 + 1);
            ctx.textBaseline = 'alphabetic';
            espacement(0);
        }
    }

    function messageVide(L, texte) {
        ctx.fillStyle = 'rgba(255,255,255,0.75)';
        ctx.textAlign = 'center';
        ajuster(texte, 700, 36 * L.ts, W - 160);
        ctx.fillText(texte, W / 2, L.h / 2 + 60);
    }

    /* ------------------------------------------------ Top Progression */

    function topTrois(metrique, sexe) {
        return (DATA.players || []).filter(function (p) {
            return sexe === 'MF' || p.sex === sexe;
        }).sort(function (a, b) {
            return b[metrique] - a[metrique];
        }).slice(0, 3);
    }

    function preparerPodium(st) {
        var metrique = st.visuel === 'prog-mens' ? 'dm' : 'da';
        var gagnants = topTrois(metrique, st.sexe);
        return Promise.all(gagnants.map(function (p) {
            var aPhoto = !!(p.photo && p.photo.length);
            return Promise.all([chargerImage(aPhoto ? p.photo : ''), chargerImage(avatarUrl(p.sex, true)), chargerImage(avatarUrl(p.sex, false))])
                .then(function (imgs) {
                    // Photo inutilisable (CORS, 404) : avatar complet dessiné.
                    return { p: p, val: p[metrique], photo: imgs[0], corps: imgs[0] ? imgs[2] : imgs[1] };
                });
        }));
    }

    function bloc(P, rang, cx, top, gagnant) {
        var w = P.colW, x = cx - w / 2, base = P.baseY, tk = P.tk;
        var dx = 18 * tk, dy = -13 * tk;

        // Faces du dessus et de côté (perspective du widget)
        ctx.fillStyle = rang === 1 ? '#4c7fae' : '#556f90';
        ctx.beginPath(); ctx.moveTo(x, top); ctx.lineTo(x + w, top); ctx.lineTo(x + w + dx, top + dy); ctx.lineTo(x + dx, top + dy); ctx.closePath(); ctx.fill();
        ctx.fillStyle = '#16212e';
        ctx.beginPath(); ctx.moveTo(x + w, top); ctx.lineTo(x + w + dx, top + dy); ctx.lineTo(x + w + dx, base + dy); ctx.lineTo(x + w, base); ctx.closePath(); ctx.fill();

        var g = ctx.createLinearGradient(0, top, 0, base);
        g.addColorStop(0, rang === 1 ? '#2f6fa0' : C.slate);
        g.addColorStop(1, rang === 1 ? C.blueDk : C.slateDk);
        ctx.fillStyle = g;
        ctx.fillRect(x, top, w, base - top);
        ctx.fillStyle = 'rgba(255,255,255,0.22)';
        ctx.fillRect(x, top, w, 5);

        // Grand numéro en filigrane
        ctx.fillStyle = 'rgba(255,255,255,0.08)';
        ctx.textAlign = 'center';
        ctx.font = font(900, 200 * tk);
        ctx.fillText(String(rang), cx, base - 20 * tk);

        // Médaille
        var my = top + 42 * tk, r = 26 * tk;
        ctx.fillStyle = C.medal[rang];
        ctx.beginPath(); ctx.arc(cx, my, r, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.55)';
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.fillStyle = '#fff';
        ctx.font = font(900, 28 * tk);
        ctx.textBaseline = 'middle';
        ctx.fillText(String(rang), cx, my + 1);
        ctx.textBaseline = 'alphabetic';

        if (!gagnant) return;
        var p = gagnant.p;
        ctx.fillStyle = '#fff';
        ajuster(String(p.nom).toUpperCase(), 900, 34 * tk, w - 28);
        ctx.fillText(String(p.nom).toUpperCase(), cx, top + 116 * tk);
        ctx.fillStyle = C.sky;
        ajuster(String(p.prenom), 700, 28 * tk, w - 28);
        ctx.fillText(String(p.prenom), cx, top + 152 * tk);
        pastille(signe(gagnant.val) + ' pts', cx, top + 172 * tk, 28 * tk, 'center');
    }

    function figure(P, cx, top, g, i) {
        var k = P.fig;
        var fw = 128 * k, fh = 168 * k;
        var fx = cx + 9 * P.tk - fw / 2, fy = top - 4 - fh;
        ctx.save();
        ctx.shadowColor = 'rgba(0,0,0,0.35)';
        ctx.shadowBlur = 14;
        ctx.shadowOffsetY = 8;
        if (g.corps) ctx.drawImage(g.corps, fx, fy, fw, fh);
        ctx.restore();
        if (g.photo) {
            photoSticker(g.photo, fx + 64 * k, fy + 40 * k, 72 * k, [4, -5, 3][i]);
        }
    }

    function dessinerPodium(st, L, gagnants) {
        var P = L.podium;
        if (!gagnants.length) {
            messageVide(L, 'Aucun joueur classé pour ce filtre.');
            return;
        }
        var pas = P.colW + P.gap;
        var cols = { 2: W / 2 - pas, 1: W / 2, 3: W / 2 + pas };
        var ordre = [2, 1, 3];
        ctx.fillStyle = 'rgba(0,0,0,0.25)';
        ctx.beginPath();
        ctx.ellipse(W / 2, P.baseY + 6, (pas * 3) / 2 + 10, 18, 0, 0, Math.PI * 2);
        ctx.fill();
        ordre.forEach(function (rang, i) {
            var top = P.baseY - P.heights[rang];
            bloc(P, rang, cols[rang], top, gagnants[rang - 1]);
            if (gagnants[rang - 1]) figure(P, cols[rang], top, gagnants[rang - 1], i);
        });
    }

    /* ---------------------------------------------------- Top perfs */

    function libelleWeekend(debut, fin) {
        var d1 = new Date(debut + 'T12:00:00'), d2 = new Date(fin + 'T12:00:00');
        var m2 = MOIS[d2.getMonth()] + ' ' + d2.getFullYear();
        if (debut === fin) return 'Journée du ' + d2.getDate() + ' ' + m2;
        if (d1.getMonth() === d2.getMonth()) return 'Week-end du ' + d1.getDate() + '–' + d2.getDate() + ' ' + m2;
        return 'Week-end du ' + d1.getDate() + ' ' + MOIS[d1.getMonth()] + ' au ' + d2.getDate() + ' ' + m2;
    }

    function chargerPerfs() {
        if (perfsData || perfsErreur) return Promise.resolve();
        setStatus('Lecture des feuilles de match du dernier week-end…');
        var body = new URLSearchParams({ action: 'monclubtt_top_perfs', nonce: DATA.nonce });
        return fetch(DATA.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body })
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res.success) {
                    perfsData = res.data;
                } else {
                    perfsErreur = (res && res.data && res.data.message) || 'Impossible de calculer les perfs.';
                }
            })
            .catch(function () {
                perfsErreur = 'Erreur de communication avec le serveur.';
            });
    }

    function preparerPerfs(L) {
        return chargerPerfs().then(function () {
            if (!perfsData) return [];
            var liste = perfsData.perfs.slice(0, L.perfs.max);
            return Promise.all(liste.map(function (p) {
                return chargerImage(p.photo).then(function (img) { return { p: p, photo: img }; });
            }));
        });
    }

    function initiales(p) {
        return (String(p.prenom).charAt(0) + String(p.nom).charAt(0)).toUpperCase();
    }

    function dessinerPerfs(L, lignes) {
        var R = L.perfs, ts = L.ts;
        if (!lignes.length) {
            messageVide(L, perfsErreur || 'Aucune perf ce week-end… la prochaine sera la bonne !');
            return;
        }
        var x = 60, w = W - 120;
        lignes.forEach(function (l, i) {
            var p = l.p;
            var y = R.top + i * (R.rowH + R.gap);
            var h = R.rowH;
            var cy = y + h / 2;

            ctx.fillStyle = i === 0 ? 'rgba(43,124,181,0.35)' : 'rgba(255,255,255,0.07)';
            rectArrondi(x, y, w, h, 22);
            ctx.fill();

            // Rang
            var rx = x + 48;
            ctx.fillStyle = C.medal[i + 1] || 'rgba(255,255,255,0.14)';
            ctx.beginPath(); ctx.arc(rx, cy, 26 * ts, 0, Math.PI * 2); ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = font(900, 26 * ts);
            ctx.fillText(String(i + 1), rx, cy + 1);

            // Photo ronde (cadrée en haut : visage) ou initiales
            var ar = h / 2 - 12, ax = rx + 26 * ts + 22 + ar;
            ctx.save();
            ctx.beginPath(); ctx.arc(ax, cy, ar, 0, Math.PI * 2); ctx.closePath();
            ctx.fillStyle = l.photo ? '#e8f1f8' : (p.sex === 'F' ? C.red : C.blue);
            ctx.fill();
            ctx.clip();
            if (l.photo) {
                var s = (ar * 2) / Math.min(l.photo.naturalWidth, l.photo.naturalHeight);
                var iw = l.photo.naturalWidth * s, ih = l.photo.naturalHeight * s;
                ctx.drawImage(l.photo, ax - iw / 2, cy - ar, iw, ih);
            } else {
                ctx.fillStyle = '#fff';
                ctx.font = font(900, ar * 0.8);
                ctx.fillText(initiales(p), ax, cy + 2);
            }
            ctx.restore();
            ctx.textBaseline = 'alphabetic';

            // Gain à droite
            pastille(signe(p.gain) + ' pts', x + w - 26, cy - 22 * ts, 26 * ts, 'right');
            ctx.font = font(900, 26 * ts);
            var pillW = ctx.measureText(signe(p.gain) + ' pts').width + 26 * ts * 1.2;

            // Textes
            var tx = ax + ar + 24, tw = x + w - 26 - pillW - 24 - tx;
            var nom = (p.prenom ? p.prenom + ' ' : '') + String(p.nom).toUpperCase();
            ctx.textAlign = 'left';
            ctx.fillStyle = '#fff';
            ajuster(nom, 800, 36 * ts, tw);
            ctx.fillText(nom, tx, cy - 4);

            var detail = (p.nb_perfs > 1 ? p.nb_perfs + ' perfs, la meilleure à ' : 'Perf à ') + p.adversaire_points + ' pts · ' + p.equipe;
            ctx.fillStyle = C.sky;
            ajuster(detail, 600, 24 * ts, tw);
            ctx.fillText(detail, tx, cy + 34 * ts);
        });
    }

    /* ---------------------------------------------------------- légende */

    var MEDAILLES = ['🥇', '🥈', '🥉'];

    function nomComplet(p) {
        return (p.prenom ? p.prenom + ' ' : '') + String(p.nom).toUpperCase();
    }

    function legendePodium(st, gagnants) {
        var periode = st.visuel === 'prog-mens' ? DATA.moisLabel : DATA.saisonLabel;
        var lignes = ['🏓 Top Progression — ' + periode, ''];
        gagnants.forEach(function (g, i) {
            lignes.push(MEDAILLES[i] + ' ' + nomComplet(g.p) + ' : ' + signe(g.val) + ' pts');
        });
        lignes.push('', 'Bravo à eux ! 👏');
        return lignes.join('\n');
    }

    function legendePerfs(lignesPerfs) {
        var lignes = ['🏓 Top perfs — ' + libelleWeekend(perfsData.date_debut, perfsData.date_fin).toLowerCase(), ''];
        lignesPerfs.forEach(function (l, i) {
            var p = l.p;
            lignes.push((MEDAILLES[i] || (i + 1) + '.') + ' ' + nomComplet(p) + ' : ' + signe(p.gain) + ' pts' +
                (p.nb_perfs > 1 ? ' (' + p.nb_perfs + ' perfs, la meilleure à ' + p.adversaire_points + ' pts)' : ' (perf à ' + p.adversaire_points + ' pts)'));
        });
        lignes.push('', 'Bravo à tous ! 👏');
        return lignes.join('\n');
    }

    function majLegende(texte) {
        if (legendeEl && !legendeModifiee) legendeEl.value = texte;
    }

    /* ------------------------------------------------------------ rendu */

    function render() {
        var token = ++renderToken;
        var st = etat();
        var L = LAYOUTS[st.format] || LAYOUTS.carre;
        var estPerfs = st.visuel === 'perfs';

        form.querySelectorAll('[data-visuel="prog"]').forEach(function (el) { el.hidden = estPerfs; });
        canvas.classList.toggle('is-story', st.format === 'story');
        btn.disabled = true;

        var contenu = estPerfs ? preparerPerfs(L) : preparerPodium(st);
        Promise.all([contenu, chargerImage(DATA.logo)]).then(function (res) {
            if (token !== renderToken) return; // un rendu plus récent a été demandé
            var donnees = res[0], logo = res[1];

            canvas.width = W;
            canvas.height = L.h;
            fond(L);

            if (estPerfs) {
                var tag = perfsData ? libelleWeekend(perfsData.date_debut, perfsData.date_fin) : '';
                entete(L, st.club, 'TOP PERFS', tag, logo);
                dessinerPerfs(L, donnees);
                setStatus(perfsData ? perfsData.rencontres + ' rencontre(s) analysée(s) — victoires contre mieux classé, points au barème FFTT.' : perfsErreur);
                majLegende(donnees.length ? legendePerfs(donnees) : '');
            } else {
                var tagProg = st.visuel === 'prog-mens' ? DATA.moisLabel : DATA.saisonLabel;
                entete(L, st.club, 'TOP PROGRESSION', tagProg, logo);
                dessinerPodium(st, L, donnees);
                setStatus('');
                majLegende(donnees.length ? legendePodium(st, donnees) : '');
            }
            btn.disabled = !donnees.length;
        });
    }

    function nomFichier() {
        var st = etat();
        var nom = {
            'prog-mens': 'top-progression-' + DATA.moisLabel,
            'prog-ann':  'top-progression-' + DATA.saisonLabel,
            'perfs':     'top-perfs-' + (perfsData ? perfsData.date_fin : '')
        }[st.visuel] + '-' + st.format;
        return nom.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.png';
    }

    function telecharger() {
        var nom = nomFichier();
        try {
            canvas.toBlob(function (blob) {
                if (!blob) {
                    setStatus('Export impossible dans ce navigateur.');
                    return;
                }
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = nom;
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(a.href); }, 1000);
            }, 'image/png');
        } catch (e) {
            // Canvas « souillé » par une image d'un autre domaine sans en-têtes CORS.
            setStatus('Export bloqué : une photo est servie depuis un autre domaine sans autorisation CORS.');
        }
    }

    function copierLegende() {
        if (!legendeEl || !legendeEl.value) return;
        var fini = function () { setStatus('Texte copié.'); };
        // Repli (hors HTTPS ou presse-papiers refusé) : sélection + execCommand.
        var repli = function () {
            legendeEl.select();
            var ok = false;
            try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
            setStatus(ok ? 'Texte copié.' : 'Copie impossible : le texte est sélectionné, copiez-le avec Ctrl/Cmd + C.');
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(legendeEl.value).then(fini, repli);
        } else {
            repli();
        }
    }

    form.addEventListener('change', function (e) {
        if (e.target === legendeEl) return;
        // Nouveau visuel ou filtre : le texte suit à nouveau le visuel.
        if (e.target.name === 'visuel' || e.target.name === 'sexe') legendeModifiee = false;
        render();
    });
    if (legendeEl) {
        legendeEl.addEventListener('input', function () { legendeModifiee = true; });
    }
    var btnCopier = document.getElementById('monclubtt-social-copier');
    if (btnCopier) btnCopier.addEventListener('click', copierLegende);
    form.addEventListener('submit', function (e) { e.preventDefault(); });
    var champClub = document.getElementById('monclubtt-social-club');
    var attente;
    champClub.addEventListener('input', function () {
        clearTimeout(attente);
        attente = setTimeout(render, 250);
    });
    btn.addEventListener('click', telecharger);

    render();
}());
