/* ===================== VISUELS RÉSEAUX SOCIAUX (admin) =====================
 * Rendu côté navigateur dans un <canvas> : aucune dépendance à GD / Imagick,
 * donc fonctionne sur hébergement mutualisé. L'admin télécharge le PNG puis
 * le publie lui-même sur Facebook / Instagram.
 *
 * Palette : les couleurs du club (réglages du plugin : primaire, secondaire,
 * fond) ; les couleurs de texte et les teintes en sont déduites (contraste).
 */
(function () {
    'use strict';
    if (typeof MonClubTTSocial === 'undefined') return;

    var DATA = MonClubTTSocial;
    var W = 1080;
    var M = 72; // marge latérale

    var MEDAILLE = { 1: '#f0b429', 2: '#aeb9c4', 3: '#c8794a' };
    var FONT = 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    var MOIS = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

    /* Mise en page par format. Story : zones sûres Instagram (≈250 px) en haut et en bas. */
    var LAYOUTS = {
        carre: {
            h: 1080, headY: 56, ts: 1, footY: 988,
            podium: { baseY: 950, colW: 270, gap: 18, fig: 1.4, heights: { 1: 300, 2: 252, 3: 230 }, tk: 1 },
            perfs:  { rowH: 82, gap: 10, max: 5 }
        },
        story: {
            h: 1920, headY: 250, ts: 1.2, footY: 1660,
            podium: { baseY: 1610, colW: 300, gap: 20, fig: 2, heights: { 1: 520, 2: 440, 3: 390 }, tk: 1.2 },
            perfs:  { rowH: 100, gap: 12, max: 7 }
        }
    };

    var canvas    = document.getElementById('monclubtt-social-canvas');
    var form      = document.getElementById('monclubtt-social-form');
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
    var P = {};                  // palette courante (voir palette())

    /* ---------------------------------------------------------------- état */

    function etat() {
        var fd = new FormData(form);
        return {
            visuel: fd.get('visuel') || 'prog-mens',
            format: fd.get('format') || 'carre',
            sexe:   fd.get('sexe') || 'MF',
            club:   String(fd.get('club') || '').trim(),
            couleurs: DATA.couleurs // réglage du plugin (couleurs du club)
        };
    }

    function setStatus(msg) {
        statusEl.textContent = msg || '';
    }

    /* ------------------------------------------------------------ couleurs */

    function rgb(hex) {
        var n = parseInt(String(hex).slice(1), 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    function hex(c) {
        return '#' + c.map(function (v) {
            return ('0' + Math.round(Math.max(0, Math.min(255, v))).toString(16)).slice(-2);
        }).join('');
    }

    /* Mélange a → b (t = 0 : a, t = 1 : b). */
    function melange(a, b, t) {
        var x = rgb(a), y = rgb(b);
        return hex([0, 1, 2].map(function (i) { return x[i] + (y[i] - x[i]) * t; }));
    }

    function luminance(c) {
        var v = rgb(c).map(function (u) {
            u /= 255;
            return u <= 0.03928 ? u / 12.92 : Math.pow((u + 0.055) / 1.055, 2.4);
        });
        return 0.2126 * v[0] + 0.7152 * v[1] + 0.0722 * v[2];
    }

    /* Texte lisible sur une couleur : quasi noir ou blanc. */
    function surCouleur(c) {
        return luminance(c) > 0.4 ? '#141414' : '#ffffff';
    }

    function alpha(c, a) {
        var x = rgb(c);
        return 'rgba(' + x[0] + ',' + x[1] + ',' + x[2] + ',' + a + ')';
    }

    function palette(couleurs) {
        var texte = surCouleur(couleurs.fond);
        return {
            primaire:   couleurs.primaire,
            secondaire: couleurs.secondaire,
            fond:       couleurs.fond,
            texte:      texte,
            discret:    melange(texte, couleurs.fond, 0.45),
            surPrim:    surCouleur(couleurs.primaire),
            surSec:     surCouleur(couleurs.secondaire),
            teinte:     melange(couleurs.primaire, couleurs.fond, 0.86),  // cartes
            primFonce:  melange(couleurs.primaire, '#000000', 0.22),
            primClair:  melange(couleurs.primaire, '#ffffff', 0.3)
        };
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
        var svg = (sexe === 'F' ? MonClubTTAvatars.female : MonClubTTAvatars.male)(avecTete, { maillot: DATA.couleurs.maillot });
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

    /* Pastille pleine centrée (align='center') ou calée à gauche ; retourne sa largeur. */
    function pastille(texte, x, y, taille, fondC, texteC, align) {
        ctx.font = font(900, taille);
        espacement(1);
        var w = ctx.measureText(texte).width + taille * 1.3;
        var h = taille * 1.75;
        var left = align === 'left' ? x : x - w / 2;
        ctx.fillStyle = fondC;
        rectArrondi(left, y, w, h, h / 2);
        ctx.fill();
        ctx.fillStyle = texteC;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(texte, left + w / 2, y + h / 2 + 1);
        ctx.textBaseline = 'alphabetic';
        espacement(0);
        return w;
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
        ctx.shadowColor = 'rgba(15,20,26,0.35)';
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
        ctx.fillStyle = P.fond;
        ctx.fillRect(0, 0, W, L.h);

        // Balle de ping-pong en filigrane, très discrète, hors de la zone du titre
        ctx.fillStyle = alpha(P.texte, 0.035);
        ctx.beginPath(); ctx.arc(W * 0.02, L.h * 0.6, 150, 0, Math.PI * 2); ctx.fill();
    }

    /* Pied : filet en couleur secondaire, nom du club, adresse du site. */
    function pied(L, club) {
        var ts = L.ts, y = L.footY;
        ctx.fillStyle = P.secondaire;
        ctx.fillRect(M, y, W - 2 * M, 6 * ts);
        ctx.textAlign = 'center';
        if (club) {
            ctx.fillStyle = P.texte;
            espacement(2);
            ajuster(club.toUpperCase(), 800, 26 * ts, W - 2 * M);
            ctx.fillText(club.toUpperCase(), W / 2, y + 46 * ts);
        }
        if (DATA.siteHost) {
            ctx.fillStyle = P.discret;
            espacement(1);
            ctx.font = font(600, 20 * ts);
            ctx.fillText(DATA.siteHost, W / 2, y + 78 * ts);
        }
        espacement(0);
    }

    /* En-tête : logo + club, gros titre (+ accent à droite), pastille de date
       et filet. Retourne l'ordonnée du bas de l'en-tête. */
    function entete(L, club, titre, accent, tag, logo) {
        var y = L.headY, ts = L.ts;
        var logoT = 92 * ts;

        // Logo dans un rond cerclé, sinon pastille avec l'initiale du club
        var cx = M + logoT / 2, cy = y + logoT / 2;
        ctx.beginPath(); ctx.arc(cx, cy, logoT / 2, 0, Math.PI * 2);
        ctx.fillStyle = '#ffffff';
        ctx.fill();
        ctx.lineWidth = 3 * ts;
        ctx.strokeStyle = P.texte;
        ctx.stroke();
        if (logo) {
            ctx.save();
            ctx.beginPath(); ctx.arc(cx, cy, logoT / 2 - 6 * ts, 0, Math.PI * 2); ctx.clip();
            ctx.drawImage(logo, cx - logoT / 2 + 8 * ts, cy - logoT / 2 + 8 * ts, logoT - 16 * ts, logoT - 16 * ts);
            ctx.restore();
        } else if (club) {
            ctx.fillStyle = P.primaire;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = font(900, logoT * 0.45);
            ctx.fillText(club.charAt(0).toUpperCase(), cx, cy + 2);
            ctx.textBaseline = 'alphabetic';
        }

        var textX = M + logoT + 24 * ts;
        ctx.textAlign = 'left';
        if (club) {
            ctx.fillStyle = P.texte;
            espacement(1);
            ajuster(club.toUpperCase(), 800, 30 * ts, W - textX - M);
            ctx.fillText(club.toUpperCase(), textX, cy - 4 * ts);
        }
        ctx.fillStyle = P.discret;
        espacement(1);
        ctx.font = font(600, 22 * ts);
        ctx.fillText('TENNIS DE TABLE', textX, cy + 28 * ts);

        // Titre + accent (ex. « J1 ») en couleur secondaire
        var titreY = y + logoT + 150 * ts;
        espacement(-2);
        var accentW = 0, accentT = 0;
        if (accent) {
            accentT = 150 * ts;
            ctx.font = font(900, accentT);
            accentW = ctx.measureText(accent).width;
        }
        var titreT = ajuster(titre, 900, 150 * ts, W - 2 * M - (accent ? accentW + 50 * ts : 0));
        ctx.fillStyle = P.texte;
        ctx.fillText(titre, M, titreY);
        if (accent) {
            ctx.font = font(900, Math.min(accentT, titreT));
            ctx.fillStyle = P.secondaire;
            ctx.textAlign = 'right';
            ctx.fillText(accent, W - M, titreY);
            ctx.textAlign = 'left';
        }
        espacement(0);

        // Pastille de date pleine + filet jusqu'au bord
        var tagY = titreY + 30 * ts, tagH = 30 * ts * 1.75;
        var tagW = tag ? pastille(tag.toUpperCase(), M, tagY, 30 * ts, P.primaire, P.surPrim, 'left') : 0;
        ctx.fillStyle = P.texte;
        ctx.fillRect(M + tagW + (tag ? 30 * ts : 0), tagY + tagH / 2 - 3 * ts, W - M - (M + tagW + (tag ? 30 * ts : 0)), 6 * ts);

        return tagY + tagH;
    }

    /* Pastille détourée centrée (ex. « 12 PERFS • 8 JOUEURS »). */
    function resume(L, texte, y) {
        var ts = L.ts, taille = 28 * ts;
        ctx.font = font(900, taille);
        espacement(1);
        var w = ctx.measureText(texte).width + 56 * ts, h = taille * 2.1;
        rectArrondi(W / 2 - w / 2, y, w, h, h / 2);
        ctx.lineWidth = 3 * ts;
        ctx.strokeStyle = P.texte;
        ctx.stroke();
        ctx.fillStyle = P.texte;
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillText(texte, W / 2, y + h / 2 + 1);
        ctx.textBaseline = 'alphabetic';
        espacement(0);
        return y + h;
    }

    function messageVide(L, texte) {
        ctx.fillStyle = P.discret;
        ctx.textAlign = 'center';
        ajuster(texte, 700, 34 * L.ts, W - 2 * M);
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

    function bloc(Pd, rang, cx, top, gagnant) {
        var w = Pd.colW, x = cx - w / 2, base = Pd.baseY, tk = Pd.tk;
        var dx = 18 * tk, dy = -13 * tk;
        var face = rang === 1 ? P.primaire : P.primFonce;

        // Faces du dessus et de côté (perspective du widget)
        ctx.fillStyle = P.primClair;
        ctx.beginPath(); ctx.moveTo(x, top); ctx.lineTo(x + w, top); ctx.lineTo(x + w + dx, top + dy); ctx.lineTo(x + dx, top + dy); ctx.closePath(); ctx.fill();
        ctx.fillStyle = melange(P.primaire, '#000000', 0.45);
        ctx.beginPath(); ctx.moveTo(x + w, top); ctx.lineTo(x + w + dx, top + dy); ctx.lineTo(x + w + dx, base + dy); ctx.lineTo(x + w, base); ctx.closePath(); ctx.fill();

        ctx.fillStyle = face;
        ctx.fillRect(x, top, w, base - top);

        // Grand numéro en filigrane
        ctx.fillStyle = alpha(P.surPrim, 0.1);
        ctx.textAlign = 'center';
        ctx.font = font(900, 190 * tk);
        ctx.fillText(String(rang), cx, base - 18 * tk);

        // Médaille
        var my = top + 42 * tk, r = 26 * tk;
        ctx.fillStyle = MEDAILLE[rang];
        ctx.beginPath(); ctx.arc(cx, my, r, 0, Math.PI * 2); ctx.fill();
        ctx.strokeStyle = 'rgba(255,255,255,0.6)';
        ctx.lineWidth = 3;
        ctx.stroke();
        ctx.fillStyle = '#fff';
        ctx.font = font(900, 28 * tk);
        ctx.textBaseline = 'middle';
        ctx.fillText(String(rang), cx, my + 1);
        ctx.textBaseline = 'alphabetic';

        if (!gagnant) return;
        var p = gagnant.p;
        var surFace = surCouleur(face);
        ctx.fillStyle = surFace;
        ajuster(String(p.nom).toUpperCase(), 900, 34 * tk, w - 28);
        ctx.fillText(String(p.nom).toUpperCase(), cx, top + 114 * tk);
        ctx.fillStyle = alpha(surFace, 0.8);
        ajuster(String(p.prenom), 700, 28 * tk, w - 28);
        ctx.fillText(String(p.prenom), cx, top + 150 * tk);
        pastille(signe(gagnant.val) + ' PTS', cx, top + 170 * tk, 26 * tk, P.secondaire, P.surSec, 'center');
    }

    function figure(Pd, cx, top, g, i) {
        var k = Pd.fig;
        var fw = 128 * k, fh = 168 * k;
        var fx = cx + 9 * Pd.tk - fw / 2, fy = top - 4 - fh;
        ctx.save();
        ctx.shadowColor = alpha(P.texte, 0.2);
        ctx.shadowBlur = 12;
        ctx.shadowOffsetY = 6;
        if (g.corps) ctx.drawImage(g.corps, fx, fy, fw, fh);
        ctx.restore();
        if (g.photo) {
            photoSticker(g.photo, fx + 64 * k, fy + 40 * k, 72 * k, [4, -5, 3][i]);
        }
    }

    function dessinerPodium(L, gagnants) {
        var Pd = L.podium;
        if (!gagnants.length) {
            messageVide(L, 'Aucun joueur classé pour ce filtre.');
            return;
        }
        var pas = Pd.colW + Pd.gap;
        var cols = { 2: W / 2 - pas, 1: W / 2, 3: W / 2 + pas };
        ctx.fillStyle = alpha(P.texte, 0.1);
        ctx.beginPath();
        ctx.ellipse(W / 2, Pd.baseY + 4, (pas * 3) / 2 + 10, 14, 0, 0, Math.PI * 2);
        ctx.fill();
        [2, 1, 3].forEach(function (rang, i) {
            var top = Pd.baseY - Pd.heights[rang];
            bloc(Pd, rang, cols[rang], top, gagnants[rang - 1]);
            if (gagnants[rang - 1]) figure(Pd, cols[rang], top, gagnants[rang - 1], i);
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

    /* Version courte pour la pastille : « 19 & 20 septembre ». */
    function dateCourte(debut, fin) {
        var d1 = new Date(debut + 'T12:00:00'), d2 = new Date(fin + 'T12:00:00');
        if (debut === fin) return d2.getDate() + ' ' + MOIS[d2.getMonth()];
        if (d1.getMonth() === d2.getMonth()) return d1.getDate() + ' & ' + d2.getDate() + ' ' + MOIS[d2.getMonth()];
        return d1.getDate() + ' ' + MOIS[d1.getMonth()] + ' & ' + d2.getDate() + ' ' + MOIS[d2.getMonth()];
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

    function dessinerPerfs(L, lignes, top) {
        var R = L.perfs, ts = L.ts;
        if (!lignes.length) {
            messageVide(L, perfsErreur || 'Aucune perf ce week-end… la prochaine sera la bonne !');
            return;
        }
        var x = M, w = W - 2 * M, h = R.rowH, r = 16 * ts;
        lignes.forEach(function (l, i) {
            var p = l.p;
            var y = top + i * (h + R.gap);
            var cy = y + h / 2;

            // Carte teintée + carré de rang en couleur primaire
            ctx.fillStyle = P.teinte;
            rectArrondi(x, y, w, h, r);
            ctx.fill();
            ctx.save();
            rectArrondi(x, y, w, h, r);
            ctx.clip();
            ctx.fillStyle = P.primaire;
            ctx.fillRect(x, y, h, h);
            ctx.restore();
            ctx.fillStyle = P.surPrim;
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.font = font(900, 38 * ts);
            ctx.fillText(String(i + 1), x + h / 2, cy + 2);

            // Photo ronde (cadrée en haut : visage) si disponible
            var tx = x + h + 22 * ts;
            if (l.photo) {
                var ar = h / 2 - 10 * ts, ax = tx + ar;
                ctx.save();
                ctx.beginPath(); ctx.arc(ax, cy, ar, 0, Math.PI * 2); ctx.closePath();
                ctx.fillStyle = '#ffffff';
                ctx.fill();
                ctx.clip();
                var s = (ar * 2) / Math.min(l.photo.naturalWidth, l.photo.naturalHeight);
                ctx.drawImage(l.photo, ax - l.photo.naturalWidth * s / 2, cy - ar, l.photo.naturalWidth * s, l.photo.naturalHeight * s);
                ctx.restore();
                tx = ax + ar + 18 * ts;
            }

            // Colonnes : nom + équipe | gain | détail
            var colGain = x + w * 0.58, colDetail = x + w * 0.7;
            ctx.textBaseline = 'alphabetic';
            ctx.textAlign = 'left';
            ctx.fillStyle = P.texte;
            var nom = (p.prenom ? p.prenom + ' ' : '') + String(p.nom);
            ajuster(nom.toUpperCase(), 900, 28 * ts, colGain - 60 * ts - tx);
            ctx.fillText(nom.toUpperCase(), tx, cy - 4 * ts);

            ctx.font = font(800, 16 * ts);
            var eq = String(p.equipe).toUpperCase();
            var eqW = Math.min(ctx.measureText(eq).width + 20 * ts, colGain - 60 * ts - tx);
            ctx.fillStyle = P.primaire;
            rectArrondi(tx, cy + 6 * ts, eqW, 24 * ts, 12 * ts);
            ctx.fill();
            ctx.fillStyle = P.surPrim;
            ajuster(eq, 800, 16 * ts, eqW - 16 * ts);
            ctx.fillText(eq, tx + 10 * ts, cy + 24 * ts);

            ctx.textAlign = 'center';
            ctx.fillStyle = P.primaire;
            ctx.font = font(900, 40 * ts);
            ctx.fillText(signe(p.gain), colGain, cy + 8 * ts);
            ctx.font = font(800, 15 * ts);
            espacement(1);
            ctx.fillText('PTS', colGain, cy + 30 * ts);
            espacement(0);

            ctx.textAlign = 'left';
            ctx.fillStyle = P.texte;
            var titreDetail = p.nb_perfs > 1 ? p.nb_perfs + ' PERFS' : '1 PERF';
            ajuster(titreDetail, 800, 24 * ts, x + w - 20 * ts - colDetail);
            ctx.fillText(titreDetail, colDetail, cy - 4 * ts);
            ctx.fillStyle = P.primaire;
            var sous = (p.nb_perfs > 1 ? 'LA MEILLEURE À ' : 'À ') + p.adversaire_points + ' PTS';
            ajuster(sous, 800, 16 * ts, x + w - 20 * ts - colDetail);
            ctx.fillText(sous, colDetail, cy + 22 * ts);
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

            P = palette(st.couleurs);
            canvas.width = W;
            canvas.height = L.h;
            fond(L);

            if (estPerfs) {
                var tag = perfsData ? dateCourte(perfsData.date_debut, perfsData.date_fin) : '';
                var accent = perfsData && perfsData.tour ? 'J' + perfsData.tour : '';
                var bas = entete(L, st.club, 'TOP PERFS', accent, tag, logo);
                if (perfsData && donnees.length) {
                    bas = resume(L, perfsData.total_perfs + (perfsData.total_perfs > 1 ? ' PERFS' : ' PERF') + '  •  ' +
                        perfsData.total_joueurs + (perfsData.total_joueurs > 1 ? ' JOUEURS' : ' JOUEUR') + '  •  ' +
                        signe(perfsData.total_gain) + ' PTS', bas + 40 * L.ts);
                }
                dessinerPerfs(L, donnees, bas + 36 * L.ts);
                setStatus(perfsData ? perfsData.rencontres + ' rencontre(s) analysée(s) — victoires contre mieux classé, points au barème FFTT.' : perfsErreur);
                majLegende(donnees.length ? legendePerfs(donnees) : '');
            } else {
                var tagProg = st.visuel === 'prog-mens' ? DATA.moisLabel : DATA.saisonLabel;
                entete(L, st.club, 'TOP PROGRESSION', '', tagProg, logo);
                dessinerPodium(L, donnees);
                setStatus('');
                majLegende(donnees.length ? legendePodium(st, donnees) : '');
            }
            pied(L, st.club);
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
        return nom.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '') + '.png';
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
