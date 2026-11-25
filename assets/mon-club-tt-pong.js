/* ===================== PONG DU CLUB (shortcode [monclubtt_pong]) =====================
 * Jeu de ping vu du dessus, rendu dans un <canvas> : le joueur choisit sa tête
 * parmi les licenciés du club, puis affronte un « top 10 mondial ». Les
 * raquettes sont des joueurs dessinés en position de match (maillot du club,
 * ou de sa sélection pour le top 10, et photo détourée). Score sur un marqueur à fiches papier, règles du ping : manches
 * en 11 points, 2 points d'écart, service alterné tous les 2 points (à chaque
 * point à partir de 10-10).
 *
 * Données : JSON posé dans le conteneur par le shortcode (joueurs, adversaire,
 * couleurs du club, nombre de manches). Dépend de MonClubTTAvatars (mon-club-tt.js).
 */
(function () {
    'use strict';
    if (typeof MonClubTTAvatars === 'undefined') return;

    /* ---- Géométrie (unités logiques, mises à l'échelle du conteneur) ---- */
    var W = 360, H = 580;
    var TABLE = { x: 34, y: 88, w: 292, h: 412 };
    var R = 6;                                   // rayon de la balle
    var HIT_J = 500, HIT_A = 50;                 // lignes de frappe
    /* Joueur dessiné (MonClubTTAvatars.joueur, viewBox -128..128 × 0..200) ;
     * fig.x désigne l'axe du corps, centre de la zone de frappe (± demi).
     * Plateau de la raquette en attente en (raquette, yRaquette), tête photo
     * centrée en yTete, haute de tete unités. */
    var SVG = { l: 256, h: 200, raquette: 52, yRaquette: 91, pied: 190, yTete: 46, tete: 94, demi: 82 };
    var MARGE_FRAPPE = 4;                        // tolérance au-delà du dessin
    var FIG_J = { h: 116, hit: HIT_J };          // personnage du joueur (bas)
    var FIG_A = { h: 96, hit: HIT_A };           // adversaire (haut)
    var POSES = ['attente', 'cd', 'rv'];
    var DUREE_COUP = 0.25;                       // pose de coup droit / revers après une frappe (s)
    var BORD_GRILLE = 6;                         // liseré blanc (px) des têtes de la sélection
    var TETE_GRILLE = 88;                        // hauteur (px CSS) des têtes de la sélection

    /* Accélération à chaque frappe d'un même échange (repart de v0 au service) :
     * plafond vMax atteint en ~8 frappes. */
    var ACCELERATION = 1.10;
    /* Effet : la vitesse latérale de la raquette à l'impact donne à la balle
     * une courbe (accélération latérale, px/s²) qui s'atténue en vol. */
    var EFFET_MAX = 270, EFFET_VITESSE = 450, EFFET_AMORTI = 0.8;
    var EFFET_MAX_JOUEUR = 360; // effet du visiteur plus marqué que celui de l'adversaire
    /* Lifté / coupé : un geste vers l'avant à l'impact (ou flèche haut) lifte,
     * vers l'arrière (ou flèche bas) coupe. La balle accélère ou freine
     * après son rebond sur la table. */
    var ROT_VITESSE = 700;  // vitesse du geste (px/s) pour un effet maximal
    var ROT_GAIN = 0.3;     // ±30 % de vitesse après le rebond
    /* Service : la 1re touche lance la balle, qui part seule en fin de lancer.
     * Une 2e touche dans la toute fin du lancer donne un service rapide ;
     * trop tôt, le service est mou. */
    var LANCER = 0.6, FENETRE = 0.1, HAUTEUR_LANCER = 34;
    var SERVICE_RAPIDE = 1.7, SERVICE_MOU = 0.85;

    /* Vue légèrement inclinée, sans fuite : la physique reste en
     * coordonnées de table vue de dessus (x, y) avec une hauteur z, le rendu
     * ne fait que tasser la profondeur pour libérer en haut la place des
     * séparations. Transformation affine : x inchangé (les rebonds latéraux
     * tombent sur les bords de l'écran), trajectoires droites, vitesse
     * apparente constante. La ligne de frappe du joueur garde ses
     * coordonnées d'écran, celle de l'adversaire descend en VUE.yAdversaire.
     * VUE.relief : part de la hauteur z visible à l'écran. */
    var VUE = { yAdversaire: 96, relief: 0.7 };
    var FILET = 14; // hauteur du filet
    /* Séparations : rangée de panneaux au fond, derrière l'adversaire, sur
     * toute la largeur de l'écran, avec les logos des partenaires. Bleu
     * marine du marqueur, un peu plus clair. */
    var SEPA = {
        y: -4, h: 42, panneaux: 4, marge: 0.14,
        haut: '#3b5274', bas: '#2e4260', arete: 'rgba(255,255,255,0.28)', joint: '#1f2e42'
    };

    /* special : probabilité que l'adversaire lâche son coup spécial quand
     * sa jauge est pleine, à chacune de ses frappes (mode Arcade). */
    var NIVEAUX = {
        normal:  { libelle: 'Normal',         vIa: 270, erreur: 42, reaction: 0.65, lecture: 0.5,  v0: 300, vMax: 640, rot: 0.3, rapide: 0.12, special: 0.5 },
        mondial: { libelle: 'Expert',         vIa: 350, erreur: 34, reaction: 0.85, lecture: 0.7,  v0: 330, vMax: 720, rot: 0.5, rapide: 0.2,  special: 0.8 }
    };

    /* ---- Mode Arcade ----
     * Le joueur choisit son coup spécial avant le match ; l'adversaire a un
     * coup attitré (coup signature) tiré de son nom, le même à chaque match. La jauge se remplit au fil du jeu ; pleine, une touche (ou un
     * appui sur l'écran) arme le coup, qui part à la frappe suivante.
     * Tous les coups restent renvoyables. */
    var COUPS = [
        { cle: 'feu', nom: 'Balle de feu', couleur: '255,110,30', desc: "Très rapide, sonne celui qui la renvoie" },
        { cle: 'zigzag', nom: 'Zigzag', couleur: '255,214,0', desc: "Zigzague jusqu'au rebond" },
        { cle: 'fantome', nom: 'Balle fantôme', couleur: '190,200,255', desc: "Disparaît au milieu du trajet" },
        { cle: 'boomerang', nom: 'Boomerang', couleur: '90,230,140', desc: "Part sur le côté et revient" },
        { cle: 'glace', nom: 'Glace', couleur: '150,220,255', desc: "Fige l'adversaire dans un glaçon" }
    ];
    /* Picto de chaque coup (viewBox 24×24) : des tracés, pleins (plein) ou
     * en trait, partagés entre le HTML (SVG) et le cut-in (Path2D). */
    var PICTOS_COUPS = {
        feu: [{ plein: true, d: 'M12 2.5c.6 3.2 2.6 4.9 4 6.9 1.2 1.7 2 3.4 2 5.3a6 6 0 0 1-12 0c0-2.4 1.1-4.3 2.6-5.6.1 1.6.8 2.8 2 3.3-.4-3.4.1-6.4 1.4-9.9z' }],
        zigzag: [{ d: 'M2.5 19l2-4.5 3 2.5 2-5 3 2.5 2-5' }, { plein: true, d: 'M18.5 3.5a3 3 0 1 1 0 6 3 3 0 1 1 0-6z' }],
        fantome: [{ d: 'M6 20.5V11a6 6 0 0 1 12 0v9.5l-2-1.6-2 1.6-2-1.6-2 1.6-2-1.6z' },
            { plein: true, d: 'M10 9.6a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 1 1 0-2.8zM14 9.6a1.4 1.4 0 1 1 0 2.8 1.4 1.4 0 1 1 0-2.8z' }],
        boomerang: [{ plein: true, d: 'M20.5 7C15 3.5 8 3 4.5 4.5 3 8 3.5 15 7 20.5c1.5 1 3 0 2.8-1.5-.8-4-.8-7.5 0-9.2 1.7-.8 5.2-.8 9.2 0 1.5.2 2.5-1.3 1.5-2.8z' }],
        glace: [{ d: 'M12 3v18M4.2 7.5l15.6 9M4.2 16.5l15.6-9M12 5.8l-1.7-2M12 5.8l1.7-2M6.6 8.9l-2.5.5M6.6 8.9l-.9-2.4M6.6 15.1l-.9 2.4M6.6 15.1l-2.5-.5M12 18.2l1.7 2M12 18.2l-1.7 2M17.4 15.1l2.5-.5M17.4 15.1l.9 2.4M17.4 8.9l.9-2.4M17.4 8.9l2.5.5' }]
    };
    var TRAIT_PICTO = 2.2;

    function pictoCoup(coup, taille) {
        var contenu = (PICTOS_COUPS[coup.cle] || []).map(function (t) {
            return t.plein
                ? '<path d="' + t.d + '" fill="currentColor"/>'
                : '<path d="' + t.d + '" fill="none" stroke="currentColor" stroke-width="' + TRAIT_PICTO + '" stroke-linecap="round" stroke-linejoin="round"/>';
        }).join('');
        return '<svg viewBox="0 0 24 24" width="' + taille + '" height="' + taille + '" aria-hidden="true" focusable="false">' + contenu + '</svg>';
    }

    /* Même picto sur le canvas, centré en (x, y), de côté `taille`. */
    function dessinerPictoCoup(ctx, coup, x, y, taille) {
        if (typeof Path2D === 'undefined') return;
        var k = taille / 24;
        ctx.save();
        ctx.translate(x - taille / 2, y - taille / 2);
        ctx.scale(k, k);
        ctx.lineWidth = TRAIT_PICTO;
        ctx.lineCap = ctx.lineJoin = 'round';
        (PICTOS_COUPS[coup.cle] || []).forEach(function (t) {
            var chemin = new Path2D(t.d);
            if (t.plein) ctx.fill(chemin); else ctx.stroke(chemin);
        });
        ctx.restore();
    }
    /* Remplissage de la jauge : frappe d'un échange long (à partir de la
     * 4e), point gagné, et surtout les effets : lifté ou coupé marqué, balle
     * courbée (effet latéral à plus de la moitié du maximum). Cumulables :
     * quatre frappes liftées suffisent. */
    var JAUGE = { echange: 0.08, apresFrappes: 4, effet: 0.25, courbe: 0.2, point: 0.2 };
    var GEL_CUTIN = 0.65;  // arrêt sur image du déclenchement (s)
    var SONNE = 0.7;       // durée où le receveur d'une balle de feu titube (s)
    var GLACE = 0.5;       // durée où la Glace fige l'adversaire dans un glaçon (s)
    var FEU = { gain: 1.45, plafond: 1.3 }; // vitesse ×1,45, au plus 1,3 × vMax
    var ZIGZAG = { amplitude: 22, pulsation: 15 };
    var FANTOME = { de: 0.22, a: 0.68 }; // part du trajet où la balle disparaît
    /* Boomerang : vitesse latérale et vers l'avant au départ (× vitesse de
     * la balle). 1,1 / 0,8 : départ à 54°, écart maximal ~130 px, sans
     * toucher les bords ; la durée du trajet ne change pas. */
    var BOOMERANG = { lateral: 1.1, avant: 0.8 };

    function coupParCle(cle) {
        return COUPS.filter(function (c) { return c.cle === cle; })[0] || null;
    }

    function coupSignature(p) {
        var t = String((p && p.prenom) || '') + String((p && p.nom) || ''), h = 0;
        for (var i = 0; i < t.length; i++) h = (h * 31 + t.charCodeAt(i)) >>> 0;
        return COUPS[h % COUPS.length];
    }

    /* ---------------------------------------------------------- utilitaires */

    function el(tag, classe, texte) {
        var e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texte !== undefined) e.textContent = texte;
        return e;
    }

    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

    /* Point de la salle (x, y au sol, hauteur z) projeté à l'écran ; s =
     * échelle des objets posés à cette profondeur (1 : les tailles des
     * joueurs portent déjà l'effet de profondeur). */
    function proj(x, y, z) {
        var k = (HIT_J - VUE.yAdversaire) / (HIT_J - HIT_A);
        return { x: x, y: HIT_J - (HIT_J - y) * k - (z || 0) * VUE.relief, s: 1 };
    }

    /* Rangée des séparations à l'écran : pied à la profondeur SEPA.y,
     * hauteur à l'échelle de cette profondeur, toute la largeur. */
    function rangeeSepa() {
        var pied = proj(W / 2, SEPA.y, 0);
        var h = SEPA.h * pied.s;
        return { y: pied.y - h, h: h, l: W / SEPA.panneaux, pied: pied.y };
    }

    /* Logo recoloré en blanc (silhouette) : les pixels opaques deviennent
     * blancs, la transparence est gardée. Un logo sans transparence devient
     * donc un rectangle blanc. Taille en pixels réels. */
    function logoBlanc(img, largeur, hauteur) {
        var sw = img.naturalWidth || img.width, sh = img.naturalHeight || img.height;
        var r = Math.min(largeur / sw, hauteur / sh);
        var c = document.createElement('canvas');
        c.width = Math.max(1, Math.round(sw * r));
        c.height = Math.max(1, Math.round(sh * r));
        var o = c.getContext('2d');
        o.drawImage(img, 0, 0, c.width, c.height);
        o.globalCompositeOperation = 'source-in';
        o.fillStyle = '#fff';
        o.fillRect(0, 0, c.width, c.height);
        return { canvas: c, largeur: c.width, hauteur: c.height };
    }

    /* Indices des panneaux qui portent un partenaire quand ils sont moins
     * nombreux que les panneaux : répartis sur la rangée. */
    function placesPartenaires(nbPartenaires, nbPanneaux) {
        var places = [];
        for (var k = 0; k < nbPartenaires; k++) places.push(Math.min(nbPanneaux - 1, Math.floor((k + 0.5) * nbPanneaux / nbPartenaires)));
        return places;
    }

    function rgb(hex) {
        var n = parseInt(String(hex).slice(1), 16);
        return [(n >> 16) & 255, (n >> 8) & 255, n & 255];
    }

    function teinte(hex, t) { // t < 0 : assombrit, t > 0 : éclaircit
        return 'rgb(' + rgb(hex).map(function (v) {
            return Math.round(t < 0 ? v * (1 + t) : v + (255 - v) * t);
        }).join(',') + ')';
    }

    function alpha(hex, a) {
        return 'rgba(' + rgb(hex).join(',') + ',' + a + ')';
    }

    function nomAffiche(p) {
        if (p.tel_quel) return String(p.nom); // adversaires : écrits comme sur la WTT
        return [p.prenom, String(p.nom || '').toUpperCase()].filter(Boolean).join(' ');
    }

    /* Inclinaison aléatoire (-20° à 20°) par photo, comme sur le podium. */
    var inclinaisons = {};
    function inclinaison(cle) {
        if (!(cle in inclinaisons)) inclinaisons[cle] = Math.random() * 40 - 20;
        return inclinaisons[cle];
    }

    var cacheImages = {};
    function chargerImage(url) {
        if (!url) return Promise.resolve(null);
        if (!cacheImages[url]) {
            cacheImages[url] = new Promise(function (resolve) {
                var img = new Image();
                img.decoding = 'async';
                img.onload = function () { resolve(img.naturalWidth ? img : null); };
                img.onerror = function () { resolve(null); };
                img.src = url;
            });
        }
        return cacheImages[url];
    }

    /* Avatar SVG en image ; largeur/hauteur explicites pour que tous les
     * navigateurs lui donnent une taille naturelle. */
    function urlAvatar(sexe, avecTete, maillot) {
        var svg = (sexe === 'F' ? MonClubTTAvatars.female : MonClubTTAvatars.male)(avecTete, { maillot: maillot });
        svg = svg.replace('<svg ', '<svg width="256" height="336" ');
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    /* Joueur du jeu dans une pose ; tenue = couleur du club ou code pays. */
    function urlJoueur(sexe, tenue, pose, avecTete) {
        var svg = MonClubTTAvatars.joueur(sexe, { tenue: tenue, pose: pose, tete: avecTete })
            .replace('<svg ', '<svg width="' + SVG.l * 2 + '" height="' + SVG.h * 2 + '" ');
        return 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(svg);
    }

    /* Photo détourée + liseré blanc suivant la silhouette : la silhouette est
     * tamponnée en blanc tout autour (plusieurs rayons pour un trait plein),
     * puis la photo est posée par-dessus. Renvoie un canvas avec la marge. */
    function sticker(img, hauteur, bord) {
        var sw = img.naturalWidth || img.width, sh = img.naturalHeight || img.height;
        var h = Math.max(1, Math.round(hauteur));
        var w = Math.max(1, Math.round(h * sw / sh));
        var b = Math.ceil(bord);
        var sil = document.createElement('canvas');
        sil.width = w; sil.height = h;
        var s = sil.getContext('2d');
        s.drawImage(img, 0, 0, w, h);
        s.globalCompositeOperation = 'source-in';
        s.fillStyle = '#fff';
        s.fillRect(0, 0, w, h);

        var c = document.createElement('canvas');
        c.width = w + 2 * b; c.height = h + 2 * b;
        var o = c.getContext('2d');
        [bord / 3, bord * 2 / 3, bord].forEach(function (r) {
            for (var a = 0; a < 24; a++) {
                var t = a * Math.PI / 12;
                o.drawImage(sil, b + Math.cos(t) * r, b + Math.sin(t) * r);
            }
        });
        o.drawImage(img, b, b, w, h);
        return c;
    }

    /* Tête de l'avatar dessiné (viewBox 128×168, image ×2), isolée par un
     * disque autour du visage et des cheveux pour écarter bras et raquette. */
    function teteAvatar(img) {
        var c = document.createElement('canvas');
        c.width = c.height = 80;
        var o = c.getContext('2d');
        o.beginPath();
        o.arc(40, 40, 38, 0, Math.PI * 2);
        o.clip();
        o.drawImage(img, 88, 32, 80, 80, 0, 0, 80, 80);
        return c;
    }

    /* Tête d'un joueur pour la sélection : photo, sinon tête de l'avatar. */
    function teteJoueur(p, maillot) {
        if (p.photo) {
            return chargerImage(p.photo).then(function (img) {
                return img ? { img: img, photo: true } : avatarTete();
            });
        }
        return avatarTete();
        function avatarTete() {
            return chargerImage(urlAvatar(p.sex, true, maillot)).then(function (img) {
                return img ? { img: teteAvatar(img), photo: false } : null;
            });
        }
    }

    /* Pictogrammes des réseaux (dessins simplifiés, blancs, viewBox 24×24). */
    var PICTOS = (function () {
        function svg(contenu) {
            return '<svg viewBox="0 0 24 24" width="24" height="24" aria-hidden="true" focusable="false">' + contenu + '</svg>';
        }
        return {
            Facebook: svg('<path fill="#fff" d="M13.6 21v-7.6h2.6l.4-3h-3V8.5c0-.9.3-1.5 1.5-1.5h1.6V4.3c-.3 0-1.2-.1-2.3-.1-2.3 0-3.9 1.4-3.9 4v2.2H7.9v3h2.6V21z"/>'),
            Instagram: svg('<rect x="3.5" y="3.5" width="17" height="17" rx="5" fill="none" stroke="#fff" stroke-width="2"/>' +
                '<circle cx="12" cy="12" r="4" fill="none" stroke="#fff" stroke-width="2"/><circle cx="17.2" cy="6.8" r="1.25" fill="#fff"/>'),
            X: svg('<path fill="#fff" d="M4 4h4.4L20 20h-4.4z"/><path d="M19.6 4l-6.3 7.1M10.7 12.9L4.4 20" stroke="#fff" stroke-width="1.9" stroke-linecap="round"/>'),
            WhatsApp: svg('<path d="M12 3.2a8.8 8.8 0 0 0-7.6 13.2L3.3 20.7l4.4-1.1A8.8 8.8 0 1 0 12 3.2z" fill="none" stroke="#fff" stroke-width="1.8" stroke-linejoin="round"/>' +
                '<path fill="#fff" d="M9.1 7.9c.3-.3.7-.3.9 0l1 1.5c.2.3.2.7-.1.9l-.6.5c.5 1.1 1.3 1.9 2.4 2.4l.5-.6c.3-.3.6-.3.9-.1l1.5 1c.3.2.3.6 0 .9l-.6.7c-.6.6-1.5.8-2.3.5-2.2-.8-3.9-2.5-4.7-4.7-.3-.8-.1-1.7.5-2.3z"/>')
        };
    }());

    /* ---------------------------------------------------------- son */

    var audio = null;
    function tock(freq, son) {
        if (!son) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            var t = audio.currentTime;
            var osc = audio.createOscillator(), gain = audio.createGain();
            osc.type = 'triangle';
            osc.frequency.setValueAtTime(freq, t);
            osc.frequency.exponentialRampToValueAtTime(freq * 0.6, t + 0.07);
            gain.gain.setValueAtTime(0.22, t);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.08);
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start(t);
            osc.stop(t + 0.09);
        } catch (e) { /* pas d'audio : jeu muet */ }
    }

    /* Petite fanfare quand le joueur marque : trois notes montantes. */
    function fanfare(son) {
        if (!son) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            [523, 659, 988].forEach(function (freq, i) {
                var t = audio.currentTime + i * 0.085;
                var osc = audio.createOscillator(), gain = audio.createGain();
                osc.type = 'square';
                osc.frequency.setValueAtTime(freq, t);
                gain.gain.setValueAtTime(0.0001, t);
                gain.gain.exponentialRampToValueAtTime(0.09, t + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.001, t + (i === 2 ? 0.28 : 0.1));
                osc.connect(gain);
                gain.connect(audio.destination);
                osc.start(t);
                osc.stop(t + 0.3);
            });
        } catch (e) { /* pas d'audio : jeu muet */ }
    }

    /* Coup spécial : montée rapide. */
    function sonSpecial(son) {
        if (!son) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            var t = audio.currentTime;
            var osc = audio.createOscillator(), gain = audio.createGain();
            osc.type = 'sawtooth';
            osc.frequency.setValueAtTime(220, t);
            osc.frequency.exponentialRampToValueAtTime(1320, t + 0.35);
            gain.gain.setValueAtTime(0.0001, t);
            gain.gain.exponentialRampToValueAtTime(0.1, t + 0.03);
            gain.gain.exponentialRampToValueAtTime(0.001, t + 0.45);
            osc.connect(gain);
            gain.connect(audio.destination);
            osc.start(t);
            osc.stop(t + 0.5);
        } catch (e) { /* pas d'audio : jeu muet */ }
    }

    /* ---------------------------------------------------------- marqueur */

    /* Fiche papier : changer la valeur fait basculer la page de devant
     * par-dessus les anneaux, la nouvelle apparaît dessous. */
    function Fiche(classe) {
        this.el = el('div', 'pong-fiche ' + classe);
        this.valeur = 0;
        this.page = el('div', 'pong-page', '0');
        this.el.appendChild(this.page);
    }
    Fiche.prototype.set = function (v) {
        if (v === this.valeur) return;
        this.valeur = v;
        var ancienne = this.page;
        this.page = el('div', 'pong-page', String(v));
        this.el.insertBefore(this.page, ancienne);
        if (window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
            ancienne.remove();
            return;
        }
        ancienne.classList.add('pong-page--tourne');
        ancienne.addEventListener('animationend', function () { ancienne.remove(); });
        setTimeout(function () { if (ancienne.parentNode) ancienne.remove(); }, 900);
    };

    function Marqueur() {
        this.el = el('div', 'pong-marqueur');
        this.camps = {};
        var self = this;
        ['joueur', 'adversaire'].forEach(function (cote) {
            var camp = el('div', 'pong-camp pong-camp--' + cote);
            var nom = el('div', 'pong-nom');
            var service = el('span', 'pong-service');
            service.setAttribute('aria-hidden', 'true');
            var libelle = el('span', 'pong-nom-texte');
            nom.appendChild(service);
            nom.appendChild(libelle);
            var fiches = el('div', 'pong-fiches');
            var manches = new Fiche('pong-fiche--manches');
            var points = new Fiche('pong-fiche--points');
            if (cote === 'joueur') {
                fiches.appendChild(manches.el);
                fiches.appendChild(points.el);
            } else {
                fiches.appendChild(points.el);
                fiches.appendChild(manches.el);
            }
            camp.appendChild(nom);
            camp.appendChild(fiches);
            // Jauge du mode Arcade et nom du coup signature (masquées sinon).
            var jauge = el('div', 'pong-jauge');
            var niveau = el('span', 'pong-jauge-niveau');
            jauge.appendChild(niveau);
            var coup = el('span', 'pong-jauge-coup');
            camp.appendChild(jauge);
            camp.appendChild(coup);
            self.el.appendChild(camp);
            self.camps[cote] = { el: camp, libelle: libelle, manches: manches, points: points, jauge: jauge, niveau: niveau, coup: coup };
        });
        this.annonce = el('div', 'pong-sr');
        this.annonce.setAttribute('aria-live', 'polite');
        this.el.appendChild(this.annonce);
    }
    Marqueur.prototype.noms = function (j, a) {
        this.camps.joueur.libelle.textContent = j;
        this.camps.adversaire.libelle.textContent = a;
    };
    /* Jauges (0 à 1) et coup armé, par côté. */
    Marqueur.prototype.jauges = function (niveaux, armes) {
        var self = this;
        ['joueur', 'adversaire'].forEach(function (cote) {
            var c = self.camps[cote];
            c.niveau.style.width = Math.round(niveaux[cote] * 100) + '%';
            c.jauge.classList.toggle('pong-jauge--pleine', niveaux[cote] >= 1);
            c.jauge.classList.toggle('pong-jauge--arme', !!armes[cote]);
        });
    };
    Marqueur.prototype.coups = function (j, a) {
        [[this.camps.joueur.coup, j], [this.camps.adversaire.coup, a]].forEach(function (x) {
            x[0].innerHTML = pictoCoup(x[1], 12);
            x[0].appendChild(document.createTextNode(x[1].nom));
            x[0].style.setProperty('--pong-coup', 'rgb(' + x[1].couleur + ')');
        });
    };
    Marqueur.prototype.maj = function (m) {
        this.camps.joueur.points.set(m.points.joueur);
        this.camps.adversaire.points.set(m.points.adversaire);
        this.camps.joueur.manches.set(m.manches.joueur);
        this.camps.adversaire.manches.set(m.manches.adversaire);
        var serveur = m.serveur();
        this.camps.joueur.el.classList.toggle('pong-camp--sert', serveur === 'joueur');
        this.camps.adversaire.el.classList.toggle('pong-camp--sert', serveur === 'adversaire');
        this.annonce.textContent = m.points.joueur + ' à ' + m.points.adversaire +
            (m.gagnantes > 1 ? ', manches ' + m.manches.joueur + ' à ' + m.manches.adversaire : '');
    };

    /* ---------------------------------------------------------- règles */

    function Match(nbManches) {
        this.gagnantes = Math.ceil(nbManches / 2);
        this.manches = { joueur: 0, adversaire: 0 };
        this.points = { joueur: 0, adversaire: 0 };
        this.premierServeur = 'joueur';
    }
    function autre(cote) { return cote === 'joueur' ? 'adversaire' : 'joueur'; }
    Match.prototype.serveur = function () {
        var total = this.points.joueur + this.points.adversaire;
        var n = total < 20 ? Math.floor(total / 2) : 10 + (total - 20);
        return n % 2 === 0 ? this.premierServeur : autre(this.premierServeur);
    };
    /* Renvoie 'point', 'manche' ou 'match'. */
    Match.prototype.marquer = function (cote) {
        this.points[cote]++;
        var p = this.points[cote], q = this.points[autre(cote)];
        if (p >= 11 && p - q >= 2) {
            this.manches[cote]++;
            return this.manches[cote] >= this.gagnantes ? 'match' : 'manche';
        }
        return 'point';
    };
    /* Le prochain point peut-il finir le match (d'un côté ou de l'autre) ? */
    Match.prototype.balleDeMatch = function () {
        var self = this;
        return ['joueur', 'adversaire'].some(function (c) {
            var p = self.points[c] + 1, q = self.points[autre(c)];
            return p >= 11 && p - q >= 2 && self.manches[c] + 1 >= self.gagnantes;
        });
    };
    Match.prototype.mancheSuivante = function () {
        this.points = { joueur: 0, adversaire: 0 };
        this.premierServeur = autre(this.premierServeur);
    };

    /* ---------------------------------------------------------- jeu */

    function init(root) {
        var source = root.querySelector('.monclubtt-pong-config');
        if (!source) return;
        var cfg;
        try { cfg = JSON.parse(source.textContent); } catch (e) { return; }
        var couleurs = cfg.couleurs || {};
        var joueurs = cfg.joueurs || [];
        /* Adversaires : top 10 mondial des réglages (ou adversaire imposé par
         * le shortcode) ; les joueurs du club peuvent aussi être choisis. */
        var adversaires = cfg.adversaires || [];
        // Adversaire imposé par le shortcode : joué dès le choix du joueur.
        var impose = cfg.impose || null;
        var adv = null;
        var nbManches = [1, 3, 5].indexOf(cfg.manches) >= 0 ? cfg.manches : 1;
        var tactile = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

        /* Préférences du visiteur (son, musique), gardées sur son appareil. */
        function lirePref(cle, defaut) {
            try { var v = localStorage.getItem('monclubtt-pong-' + cle); return v === null ? defaut : v === '1'; } catch (e) { return defaut; }
        }
        function ecrirePref(cle, v) {
            try { localStorage.setItem('monclubtt-pong-' + cle, v ? '1' : '0'); } catch (e) { /* stockage indisponible */ }
        }

        var etat = {
            niveau: 'normal',
            mode: lirePref('arcade', false) ? 'arcade' : 'classique',
            son: lirePref('son', true),
            musique: lirePref('musique', true),
            joueur: null
        };

        var dpr = Math.min(window.devicePixelRatio || 1, 3);

        function poserTete(conteneur, t, cle) {
            if (!t) return;
            var c = sticker(t.img, TETE_GRILLE * (t.photo ? 1 : 0.8) * dpr, BORD_GRILLE * dpr);
            c.style.width = (c.width / dpr) + 'px';
            c.style.height = (c.height / dpr) + 'px';
            c.style.transform = 'rotate(' + inclinaison(cle).toFixed(1) + 'deg)';
            conteneur.appendChild(c);
        }

        function sansAccents(t) {
            return String(t).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '').trim();
        }

        /* Grille de cartes (tête détourée, nom, points ou rang), avec une
         * recherche au-delà de 12 joueurs ; surChoix(joueur) au clic. */
        function grilleCartes(liste, maillot, surChoix) {
            var bloc = el('div', 'pong-bloc');
            var grille = el('div', 'pong-grille');
            if (liste.length > 12) {
                var recherche = el('input', 'pong-recherche');
                recherche.type = 'search';
                recherche.placeholder = 'Rechercher un joueur…';
                recherche.setAttribute('aria-label', 'Rechercher un joueur');
                recherche.addEventListener('input', function () {
                    var q = sansAccents(recherche.value);
                    grille.querySelectorAll('.pong-carte').forEach(function (c) {
                        c.hidden = q !== '' && c.dataset.recherche.indexOf(q) < 0;
                    });
                });
                bloc.appendChild(recherche);
            }
            if (!liste.length) grille.appendChild(el('p', 'pong-vide', 'Aucun joueur à afficher pour le moment.'));
            liste.forEach(function (p, i) {
                var carte = el('button', 'pong-carte');
                carte.type = 'button';
                carte.dataset.index = i;
                carte.dataset.recherche = sansAccents(p.nom + ' ' + (p.prenom || ''));
                var tete = el('span', 'pong-carte-tete');
                carte.appendChild(tete);
                if (p.tel_quel) {
                    carte.appendChild(el('span', 'pong-carte-nom', nomAffiche(p)));
                } else {
                    carte.appendChild(el('span', 'pong-carte-nom', String(p.nom).toUpperCase()));
                    carte.appendChild(el('span', 'pong-carte-prenom', p.prenom));
                }
                var info = p.titre || (p.pts ? Math.round(p.pts) + ' pts' : '');
                if (info) carte.appendChild(el('span', 'pong-carte-pts', info));
                grille.appendChild(carte);
                teteJoueur(p, maillot).then(function (t) { poserTete(tete, t, p.photo || 'avatar-' + p.nom + p.prenom); });
            });
            grille.addEventListener('click', function (e) {
                var carte = e.target.closest('.pong-carte');
                if (carte) surChoix(liste[+carte.dataset.index]);
            });
            bloc.appendChild(grille);
            /* Masque la carte du joueur déjà choisi (pas de match contre soi-même). */
            bloc.masquer = function (joueur) {
                grille.querySelectorAll('.pong-carte').forEach(function (c) {
                    c.classList.toggle('pong-carte--moi', liste[+c.dataset.index] === joueur);
                });
            };
            return bloc;
        }

        function lienRetour(texte, action) {
            var b = el('button', 'pong-lien pong-retour', texte);
            b.type = 'button';
            b.addEventListener('click', action);
            return b;
        }

        /* ---- Écran 1 : choix du joueur ---- */
        var ecranSel = el('div', 'pong-ecran pong-selection');

        /* Arrivée par un lien partagé : bandeau « Défi » pour rejouer le même
         * match (même adversaire, même niveau). */
        var defi = cfg.defi || null;
        if (defi) {
            defi.adv = defi.adv_type === 'monde' ? adversaires.filter(function (a) { return a.nom === defi.adv_nom; })[0]
                : defi.adv_type === 'club' ? joueurs.filter(function (j) { return j.nom === defi.adv_nom && j.prenom === defi.adv_prenom; })[0]
                : defi.adv_type === 'invite' && impose && impose.nom === defi.adv_nom ? impose : null;
            var bandeau = el('div', 'pong-defi');
            var vainqueur = defi.victoire ? defi.joueur : defi.adversaire;
            var vaincu = defi.victoire ? defi.adversaire : defi.joueur;
            var scoreDefi = Math.max(defi.pj, defi.pa) + '–' + Math.min(defi.pj, defi.pa);
            bandeau.appendChild(el('span', 'pong-defi-label', 'Défi'));
            bandeau.appendChild(el('strong', 'pong-defi-titre', vainqueur + ' a battu ' + vaincu + ' ' + scoreDefi));
            bandeau.appendChild(el('span', 'pong-defi-texte', defi.victoire
                ? 'À toi de battre ' + defi.adversaire + ', et de faire mieux !'
                : 'Venge ' + defi.joueur + ' : bats ' + defi.adversaire + ' !'));
            if (defi.adv) {
                var relever = el('button', 'pong-bouton pong-defi-bouton', 'Relever le défi');
                relever.type = 'button';
                relever.addEventListener('click', function () {
                    etat.defiActif = true;
                    if (NIVEAUX[defi.niveau]) {
                        etat.niveau = defi.niveau;
                        niveaux.querySelectorAll('.pong-niveau').forEach(function (x) {
                            x.setAttribute('aria-pressed', String(x.dataset.niveau === defi.niveau));
                        });
                    }
                    if (defi.mode === 'arcade' || defi.mode === 'classique') {
                        etat.mode = defi.mode;
                        majModes();
                    }
                    relever.textContent = 'Choisis ton joueur ↓';
                    relever.disabled = true;
                    ecranSel.querySelector('.pong-grille').scrollIntoView({ block: 'start', behavior: 'smooth' });
                });
                bandeau.appendChild(relever);
            }
            ecranSel.appendChild(bandeau);
        }

        /* ---- Défi du jour ----
         * Même adversaire pour tous aujourd'hui (tiré par le serveur), en une
         * manche, niveau Normal, force neutre. Une seule tentative comptée :
         * elle est consommée au lancement du match (stockage local), les
         * suivantes se jouent hors classement. */
        var defiJour = null; // données du serveur + adversaire retrouvé (advObj)
        var grilleJoueurs = null; // grille du choix du joueur, posée plus bas
        function lireLocal(cle) {
            try { return localStorage.getItem('monclubtt-pong-' + cle); } catch (e) { return null; }
        }
        function ecrireLocal(cle, v) {
            try { localStorage.setItem('monclubtt-pong-' + cle, v); } catch (e) { /* stockage indisponible */ }
        }
        function defiJoue() { return !!defiJour && lireLocal('defi-jour') === defiJour.date; }
        function resultatJour() {
            try {
                var r = JSON.parse(lireLocal('defi-jour-resultat') || 'null');
                return r && defiJour && r.date === defiJour.date ? r : null;
            } catch (e) { return null; }
        }
        function texteDuree(s) {
            s = Math.round(s);
            return s < 60 ? s + ' s' : Math.floor(s / 60) + ' min ' + ('0' + s % 60).slice(-2);
        }
        function rangTexte(n) { return n === 1 ? '1er' : n + 'e'; }
        function adversaireJour(d) {
            if (!d || !d.adv) return null;
            var liste = d.adv.type === 'monde' ? adversaires : joueurs;
            return liste.filter(function (a) {
                return a.nom === d.adv.nom && (d.adv.type === 'monde' || a.prenom === d.adv.prenom);
            })[0] || null;
        }

        var bandeauJour = el('div', 'pong-defi pong-defi--jour');
        bandeauJour.hidden = true;
        ecranSel.appendChild(bandeauJour);

        var blocJour = el('section', 'pong-scores pong-scores--jour');
        blocJour.hidden = true;

        function majDefiJour(d) {
            var advObj = adversaireJour(d);
            defiJour = advObj ? d : null;
            if (defiJour) defiJour.advObj = advObj;
            bandeauJour.hidden = blocJour.hidden = !defiJour;
            if (!defiJour) return;
            var joue = defiJoue(), r = resultatJour();
            bandeauJour.innerHTML = '';
            bandeauJour.appendChild(el('span', 'pong-defi-label', 'Défi du jour'));
            bandeauJour.appendChild(el('strong', 'pong-defi-titre', 'Bats ' + nomAffiche(advObj)));
            bandeauJour.appendChild(el('span', 'pong-defi-texte', joue
                ? (r ? 'Ton résultat : ' + r.pj + '–' + r.pa + (r.rang ? ', ' + rangTexte(r.rang) + ' sur ' + r.total + ' à ce moment-là' : '') + '.'
                    : 'Ta tentative du jour est jouée.') + ' Tu peux rejouer, hors classement.'
                : 'Une manche, niveau Normal. Une seule tentative comptée, dès le premier service.'));
            var bouton = el('button', 'pong-bouton pong-defi-bouton', joue ? 'Rejouer (hors classement)' : 'Relever le défi');
            bouton.type = 'button';
            bouton.addEventListener('click', releverDefiJour);
            bandeauJour.appendChild(bouton);
            afficherClassementJour(d.classement, d.total, d.hier);
        }

        function afficherClassementJour(liste, total, hier) {
            blocJour.innerHTML = '';
            blocJour.appendChild(el('h4', 'pong-scores-titre', 'Défi du jour : ' + nomAffiche(defiJour.advObj)));
            if (hier) {
                blocJour.appendChild(el('p', 'pong-scores-hier', 'Vainqueur d\'hier : ' + hier.joueur + ', ' + hier.pj + '–' + hier.pa + ' contre ' + hier.adversaire));
            }
            var ol = el('ol', 'pong-scores-liste');
            if (!liste || !liste.length) {
                ol.appendChild(el('li', 'pong-scores-vide', 'Personne n\'a encore joué aujourd\'hui. À toi d\'ouvrir le classement !'));
            }
            (liste || []).forEach(function (e) {
                var li = el('li', 'pong-score' + (e.pj > e.pa ? '' : ' pong-score--defaite'));
                var nom = el('span', 'pong-score-joueur', e.joueur);
                if (e.visiteur) nom.appendChild(el('span', 'pong-score-visiteur', 'hors club'));
                li.appendChild(nom);
                li.appendChild(el('span', 'pong-score-detail', (e.pj > e.pa ? 'gagné' : 'perdu') + ' en ' + texteDuree(e.duree)));
                li.appendChild(el('span', 'pong-score-points', e.pj + '–' + e.pa));
                ol.appendChild(li);
            });
            blocJour.appendChild(ol);
            if (total > (liste || []).length) {
                blocJour.appendChild(el('p', 'pong-scores-suite', '… et ' + (total - liste.length) + ' autre' + (total - liste.length > 1 ? 's' : '')));
            }
        }

        /* Relever le défi : on choisit son joueur, puis le match part contre
         * l'adversaire du jour (masqué de la grille : pas de match contre soi). */
        function releverDefiJour() {
            if (!defiJour) return;
            fermerAccueilJour();
            etat.defiActif = false;
            etat.defiJour = defiJoue() ? 'libre' : 'compte';
            etat.niveau = 'normal';
            niveaux.querySelectorAll('.pong-niveau').forEach(function (x) {
                x.setAttribute('aria-pressed', String(x.dataset.niveau === 'normal'));
            });
            if (grilleJoueurs) grilleJoueurs.masquer(defiJour.advObj);
            var bouton = bandeauJour.querySelector('.pong-defi-bouton');
            bouton.textContent = 'Choisis ton joueur ↓';
            bouton.disabled = true;
            var grille = ecranSel.querySelector('.pong-grille');
            if (grille) grille.scrollIntoView({ block: 'start', behavior: 'smooth' });
        }

        /* Écran d'accueil du défi, une fois par jour tant qu'il n'est pas
         * joué ; pas sur un lien de défi partagé (son propre bandeau prime). */
        var accueilJour = null;
        function montrerAccueilJour() {
            if (!defiJour || defiJoue() || cfg.defi || ecranSel.hidden || lireLocal('defi-jour-vu') === defiJour.date) return;
            ecrireLocal('defi-jour-vu', defiJour.date);
            var a = defiJour.advObj;
            accueilJour = el('div', 'pong-defi-accueil');
            accueilJour.setAttribute('role', 'dialog');
            accueilJour.setAttribute('aria-label', 'Défi du jour');
            var carte = el('div', 'pong-defi-carte');
            carte.appendChild(el('span', 'pong-defi-label', 'Défi du jour'));
            var tete = el('span', 'pong-defi-tete');
            carte.appendChild(tete);
            teteJoueur(a, couleurs.secondaire).then(function (t) { poserTete(tete, t, a.photo || 'avatar-' + a.nom + a.prenom); });
            carte.appendChild(el('strong', 'pong-defi-nom', nomAffiche(a)));
            var info = a.titre || (a.pts ? Math.round(a.pts) + ' pts' : '');
            if (info) carte.appendChild(el('span', 'pong-defi-info', info));
            var meneur = defiJour.classement && defiJour.classement[0];
            carte.appendChild(el('span', 'pong-defi-texte', meneur
                ? 'En tête : ' + meneur.joueur + ', ' + meneur.pj + '–' + meneur.pa + ' en ' + texteDuree(meneur.duree)
                : 'Personne ne l\'a encore affronté aujourd\'hui.'));
            carte.appendChild(el('span', 'pong-defi-regle', 'Une manche, niveau Normal. Une seule tentative comptée, dès le premier service : classement à l\'écart de points, puis au temps.'));
            var boutons = el('div', 'pong-message-boutons');
            var go = el('button', 'pong-bouton', 'Relever le défi');
            go.type = 'button';
            go.addEventListener('click', releverDefiJour);
            var plusTard = el('button', 'pong-bouton pong-bouton--secondaire', 'Plus tard');
            plusTard.type = 'button';
            plusTard.addEventListener('click', fermerAccueilJour);
            boutons.appendChild(go);
            boutons.appendChild(plusTard);
            carte.appendChild(boutons);
            accueilJour.appendChild(carte);
            accueilJour.addEventListener('click', function (e) { if (e.target === accueilJour) fermerAccueilJour(); });
            ecranSel.appendChild(accueilJour);
            setTimeout(function () { go.focus({ preventScroll: true }); }, 50);
        }
        function fermerAccueilJour() {
            if (accueilJour) accueilJour.remove();
            accueilJour = null;
        }

        function choisirJoueur(p) {
            etat.joueur = p;
            clubAdv.masquer(p);
            sousTitreAdv.textContent = 'Tu joues avec ' + nomAffiche(p);
            majCoups();
            if (etat.defiJour && defiJour && defiJour.advObj !== p) jouerContre(defiJour.advObj);
            else if (etat.defiActif && defi.adv && defi.adv !== p) jouerContre(defi.adv);
            else if (impose) jouerContre(impose);
            else montrer(ecranAdv);
        }

        ecranSel.appendChild(el('h3', 'pong-titre', 'Choisis ton joueur'));
        ecranSel.appendChild(formulaireVisiteur());
        grilleJoueurs = grilleCartes(joueurs, couleurs.maillot, choisirJoueur);
        ecranSel.appendChild(grilleJoueurs);

        /* Visiteur hors club : joue sous son prénom, avec un avatar dessiné
         * (joueur ou joueuse). Prénom et choix gardés sur son appareil.
         * Bouton discret sous le titre, qui déplie le formulaire. */
        function formulaireVisiteur() {
            var bloc = el('div', 'pong-visiteur');
            var form = el('form', 'pong-visiteur-form');
            var ligne = el('div', 'pong-visiteur-ligne');
            var champ = el('input', 'pong-recherche pong-visiteur-nom');
            champ.type = 'text';
            champ.maxLength = 20;
            champ.required = true;
            champ.autocomplete = 'given-name';
            champ.placeholder = 'Ton prénom';
            champ.setAttribute('aria-label', 'Ton prénom');
            try { champ.value = localStorage.getItem('monclubtt-pong-visiteur') || ''; } catch (e) { /* stockage indisponible */ }
            ligne.appendChild(champ);
            var sexe = 'M';
            try { sexe = localStorage.getItem('monclubtt-pong-visiteur-sexe') === 'F' ? 'F' : 'M'; } catch (e) { /* stockage indisponible */ }
            var genre = el('div', 'pong-niveaux pong-visiteur-genre');
            genre.setAttribute('role', 'group');
            genre.setAttribute('aria-label', 'Avatar');
            [['M', 'Joueur'], ['F', 'Joueuse']].forEach(function (g) {
                var b = el('button', 'pong-niveau', g[1]);
                b.type = 'button';
                b.dataset.sexe = g[0];
                b.setAttribute('aria-pressed', String(g[0] === sexe));
                b.addEventListener('click', function () {
                    sexe = g[0];
                    genre.querySelectorAll('.pong-niveau').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); });
                });
                genre.appendChild(b);
            });
            ligne.appendChild(genre);
            var jouer = el('button', 'pong-bouton', 'Jouer');
            jouer.type = 'submit';
            ligne.appendChild(jouer);
            form.appendChild(ligne);
            var connu = champ.value.trim();
            var ouvrir = el('button', 'pong-lien pong-visiteur-ouvrir',
                connu ? 'Pas du club ? Rejoue en tant que ' + connu : 'Pas du club ? Joue sous ton prénom');
            ouvrir.type = 'button';
            ouvrir.setAttribute('aria-expanded', 'false');
            ouvrir.addEventListener('click', function () {
                var ouvert = form.hidden;
                form.hidden = !ouvert;
                ouvrir.setAttribute('aria-expanded', String(ouvert));
                if (ouvert) champ.focus();
            });
            // Sans joueur du club à choisir, le formulaire est ouvert d'office.
            form.hidden = joueurs.length > 0;
            if (joueurs.length) bloc.appendChild(ouvrir);
            bloc.appendChild(form);
            form.addEventListener('submit', function (e) {
                e.preventDefault();
                var nom = champ.value.replace(/\s+/g, ' ').trim().slice(0, 20);
                if (!nom) { champ.focus(); return; }
                try {
                    localStorage.setItem('monclubtt-pong-visiteur', nom);
                    localStorage.setItem('monclubtt-pong-visiteur-sexe', sexe);
                } catch (err) { /* stockage indisponible */ }
                choisirJoueur({ nom: nom, prenom: '', tel_quel: true, sex: sexe, photo: '', visiteur: true });
            });
            return bloc;
        }

        /* Verdict du défi en fin de match, comparé au match partagé. */
        function verdictDefi() {
            if (!etat.defiActif || !defi || adv !== defi.adv) return '';
            var gagne = match.points.joueur > match.points.adversaire;
            var score = defi.pj + '–' + defi.pa;
            if (!defi.victoire) {
                return gagne ? 'Vengeance accomplie pour ' + defi.joueur + ' !' : defi.adversaire + ' reste invaincu…';
            }
            if (!gagne) return 'Défi raté : ' + defi.adversaire + ' a eu le dernier mot.';
            var moi = match.points.joueur - match.points.adversaire, lui = defi.pj - defi.pa;
            return moi > lui ? 'Défi relevé : tu fais mieux que ' + defi.joueur + ' (' + score + ') !'
                : moi === lui ? 'Égalité parfaite avec ' + defi.joueur + ' (' + score + ').'
                : 'Gagné, mais ' + defi.joueur + ' avait fait mieux (' + score + ').';
        }

        /* ---- Tableau des meilleurs scores (commun à tous les visiteurs) ---- */
        var blocScores = el('section', 'pong-scores');
        blocScores.appendChild(el('h4', 'pong-scores-titre', 'Meilleurs scores'));
        var listeScores = el('ol', 'pong-scores-liste');
        blocScores.appendChild(listeScores);
        ecranSel.appendChild(blocJour);
        ecranSel.appendChild(blocScores);

        function afficherScores(liste) {
            listeScores.innerHTML = '';
            if (!liste || !liste.length) {
                listeScores.appendChild(el('li', 'pong-scores-vide', 'Aucune victoire pour l\'instant. À toi d\'ouvrir le tableau !'));
                return;
            }
            liste.forEach(function (e) {
                var li = el('li', 'pong-score');
                var nom = el('span', 'pong-score-joueur', e.joueur);
                if (e.visiteur) nom.appendChild(el('span', 'pong-score-visiteur', 'hors club'));
                li.appendChild(nom);
                li.appendChild(el('span', 'pong-score-detail', 'bat ' + e.adversaire));
                li.appendChild(el('span', 'pong-score-points', e.pj + '–' + e.pa));
                if (e.niveau === 'mondial') li.appendChild(el('span', 'pong-score-niveau', NIVEAUX.mondial.libelle));
                listeScores.appendChild(li);
            });
        }
        afficherScores(cfg.scores);

        function appelAjax(action, donnees) {
            if (!cfg.ajax) return Promise.reject(new Error('hors ligne'));
            var corps = new URLSearchParams(donnees || {});
            corps.set('action', action);
            return fetch(cfg.ajax, { method: 'POST', credentials: 'same-origin', body: corps })
                .then(function (r) { return r.json(); })
                .then(function (r) {
                    if (!r || !r.success) throw new Error(r && r.data && r.data.message || 'erreur');
                    return r.data;
                });
        }
        // La page peut venir d'un cache : le tableau est relu à l'ouverture.
        appelAjax('monclubtt_pong_scores').then(function (d) { afficherScores(d.classement); }, function () {});
        // Défi du jour : relu de même (la page peut dater d'hier), puis l'écran d'accueil.
        majDefiJour(cfg.defiJour || null);
        appelAjax('monclubtt_pong_defi').then(function (d) { majDefiJour(d); }, function () {}).then(montrerAccueilJour);

        /* Type d'adversaire envoyé au serveur (seuls « monde » et « club » sont classés). */
        function typeAdversaire(a) {
            if (adversaires.indexOf(a) >= 0) return 'monde';
            if (joueurs.indexOf(a) >= 0) return 'club';
            return 'invite';
        }

        /* Fin de match : le match est enregistré pour le partage ; une victoire
         * contre le top 10 ou le club est en plus proposée au tableau. */
        function envoyerFin(ligne, zonePartage, ligneJour) {
            var victoire = match.points.joueur > match.points.adversaire;
            var type = typeAdversaire(adv);
            // Tentative comptée au défi du jour : la première, contre l'adversaire du jour.
            var compteJour = etat.defiJour === 'compte' && defiJour && adv === defiJour.advObj;
            if (compteJour) ligneJour.textContent = 'Défi du jour : enregistrement…';
            else if (etat.defiJour === 'libre') ligneJour.textContent = 'Hors classement : ta tentative du jour est déjà jouée.';
            var classe = type !== 'invite' && !modeArcade();
            if (victoire && classe) ligne.textContent = 'Enregistrement au tableau…';
            appelAjax('monclubtt_pong_fin', {
                nonce: cfg.nonce || '', page: cfg.page || 0,
                joueur_type: etat.joueur.visiteur ? 'visiteur' : 'club',
                joueur_nom: etat.joueur.nom, joueur_prenom: etat.joueur.prenom || '',
                adv_type: type, adv_nom: adv.nom, adv_prenom: adv.prenom || '',
                niveau: etat.niveau, pj: match.points.joueur, pa: match.points.adversaire,
                mode: modeArcade() ? 'arcade' : 'classique', speciaux: arcade.speciaux,
                defi_jour: compteJour ? '1' : '', defi_date: compteJour ? defiJour.date : '', duree: match.duree.toFixed(1)
            }).then(function (d) {
                if (d.defi) {
                    var dj = d.defi;
                    if (dj.message) ligneJour.textContent = dj.message;
                    else if (dj.deja) ligneJour.textContent = 'Défi du jour : ta tentative est déjà classée aujourd\'hui.';
                    else if (dj.rang) ligneJour.textContent = 'Défi du jour : ' + rangTexte(dj.rang) + ' sur ' + dj.total + ' !';
                    if (dj.rang) ecrireLocal('defi-jour-resultat', JSON.stringify({ date: defiJour.date, pj: match.points.joueur, pa: match.points.adversaire, rang: dj.rang, total: dj.total }));
                    if (dj.classement) {
                        defiJour.classement = dj.classement;
                        defiJour.total = dj.total;
                    }
                }
                if (d.classement) {
                    afficherScores(d.classement);
                    ligne.textContent = d.rang
                        ? 'Tu entres ' + (d.rang === 1 ? '1er' : d.rang + 'e') + ' au tableau des meilleurs scores !'
                        : 'Pas assez pour entrer au tableau des meilleurs scores, cette fois.';
                }
                if (d.partage) afficherPartage(zonePartage, d.partage);
            }, function (err) {
                // Raison donnée par le serveur (prénom refusé, trop de parties…), défaites comprises.
                ligne.textContent = (victoire && classe ? 'Score non enregistré : ' : 'Match non enregistré : ') + err.message;
                if (compteJour) ligneJour.textContent = 'Défi du jour : partie non classée.';
            });
        }

        /* Partage du match : aperçu de l'image (og:image de la page partagée),
         * Facebook / X / WhatsApp par leurs liens de partage, Instagram par le
         * partage natif du téléphone (image en fichier), sinon l'image à enregistrer. */
        function afficherPartage(zone, p) {
            zone.innerHTML = '';
            zone.appendChild(el('span', 'pong-partage-titre', 'Défie tes amis'));
            var apercu = el('img', 'pong-partage-apercu');
            apercu.src = p.image;
            apercu.alt = 'Image de partage du match';
            apercu.width = 1200;
            apercu.height = 630;
            zone.appendChild(apercu);
            var liens = el('div', 'pong-partage-liens');
            /* Bouton rond à pictogramme ; le nom du réseau reste lisible par les
             * lecteurs d'écran (aria-label) et en info-bulle. */
            function bouton(tag, reseau, classe) {
                var b = el(tag, 'pong-partage-lien ' + classe);
                b.innerHTML = PICTOS[reseau];
                b.setAttribute('aria-label', 'Partager sur ' + reseau);
                b.title = reseau;
                liens.appendChild(b);
                return b;
            }
            function lien(reseau, url, classe) {
                var a = bouton('a', reseau, classe);
                a.href = url;
                a.target = '_blank';
                a.rel = 'noopener noreferrer';
            }
            var u = encodeURIComponent(p.url), t = encodeURIComponent(p.texte);
            lien('Facebook', 'https://www.facebook.com/sharer/sharer.php?u=' + u, 'pong-partage--facebook');
            var insta = bouton('button', 'Instagram', 'pong-partage--instagram');
            insta.type = 'button';
            lien('X', 'https://twitter.com/intent/tweet?text=' + t + '&url=' + u, 'pong-partage--x');
            lien('WhatsApp', 'https://wa.me/?text=' + encodeURIComponent(p.texte + ' ' + p.url), 'pong-partage--whatsapp');
            zone.appendChild(liens);
            var aide = el('span', 'pong-partage-aide');
            zone.appendChild(aide);

            // Image préchargée en fichier : le partage natif doit partir du clic même.
            var fichier = null;
            fetch(p.image).then(function (r) { return r.blob(); }).then(function (b) {
                fichier = new File([b], 'pong-du-club.png', { type: b.type || 'image/png' });
            }, function () {});
            /* Instagram ne garde que l'image : texte et lien vont dans le
             * presse-papier, à coller en légende ou dans un sticker « Lien ». */
            function copier(texte) {
                if (navigator.clipboard && window.isSecureContext) {
                    return navigator.clipboard.writeText(texte).then(function () { return true; }, function () { return false; });
                }
                var zoneTexte = el('textarea');
                zoneTexte.value = texte;
                zoneTexte.setAttribute('readonly', '');
                zoneTexte.style.cssText = 'position:fixed;top:0;left:0;opacity:0';
                document.body.appendChild(zoneTexte);
                zoneTexte.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (err) { ok = false; }
                document.body.removeChild(zoneTexte);
                return Promise.resolve(ok);
            }
            insta.addEventListener('click', function () {
                // Copie lancée avant le partage : les deux demandent le geste du visiteur.
                var copie = copier(p.texte + ' ' + p.url);
                var partageNatif = fichier && navigator.canShare && navigator.canShare({ files: [fichier] });
                if (partageNatif) {
                    navigator.share({ files: [fichier], text: p.texte + ' ' + p.url }).catch(function () {});
                } else {
                    window.open(p.image, '_blank', 'noopener');
                }
                copie.then(function (ok) {
                    aide.textContent = (partageNatif ? '' : 'Enregistre l\'image, puis publie-la dans Instagram. ') + (ok
                        ? 'Texte et lien copiés : colle-les dans ta légende, ou dans un sticker « Lien » en story.'
                        : 'Ajoute le lien dans un sticker « Lien » en story : ' + p.url);
                });
            });
        }

        /* ---- Écran 2 : choix de l'adversaire ---- */
        var ecranAdv = el('div', 'pong-ecran pong-selection pong-choix-adv');
        ecranAdv.hidden = true;
        ecranAdv.appendChild(lienRetour('← Changer de joueur', function () { montrer(ecranSel); }));
        ecranAdv.appendChild(el('h3', 'pong-titre', 'Choisis ton adversaire'));
        var sousTitreAdv = el('p', 'pong-sous-titre');
        ecranAdv.appendChild(sousTitreAdv);

        var onglets = el('div', 'pong-onglets');
        onglets.setAttribute('role', 'tablist');
        var mondeAdv = grilleCartes(adversaires, couleurs.secondaire, jouerContre);
        var clubAdv = grilleCartes(joueurs, couleurs.secondaire, jouerContre);
        var vues = [
            { libelle: 'Top 10 mondial', bloc: mondeAdv, liste: adversaires },
            { libelle: 'Joueurs du club', bloc: clubAdv, liste: joueurs }
        ];
        if (impose) vues.unshift({ libelle: 'Invité', bloc: grilleCartes([impose], couleurs.secondaire, jouerContre), liste: [impose] });
        vues = vues.filter(function (v) { return v.liste.length; });
        var vueActive = vues[0];
        vues.forEach(function (v, i) {
            var b = el('button', 'pong-onglet', v.libelle);
            b.type = 'button';
            b.setAttribute('role', 'tab');
            b.setAttribute('aria-selected', String(i === 0));
            b.addEventListener('click', function () {
                vueActive = v;
                onglets.querySelectorAll('.pong-onglet').forEach(function (x) { x.setAttribute('aria-selected', String(x === b)); });
                vues.forEach(function (w) { w.bloc.hidden = w !== v; });
            });
            onglets.appendChild(b);
            v.bloc.hidden = i !== 0;
        });
        // Un seul onglet (top 10 désactivé, sans invité) : pas de barre d'onglets.
        onglets.hidden = vues.length < 2;
        ecranAdv.appendChild(onglets);

        var niveaux = el('div', 'pong-niveaux');
        niveaux.setAttribute('role', 'group');
        niveaux.setAttribute('aria-label', 'Niveau de l\'adversaire');
        Object.keys(NIVEAUX).forEach(function (cle) {
            var b = el('button', 'pong-niveau', NIVEAUX[cle].libelle);
            b.type = 'button';
            b.dataset.niveau = cle;
            b.setAttribute('aria-pressed', String(cle === etat.niveau));
            niveaux.appendChild(b);
        });
        niveaux.addEventListener('click', function (e) {
            var b = e.target.closest('.pong-niveau');
            if (!b) return;
            etat.niveau = b.dataset.niveau;
            niveaux.querySelectorAll('.pong-niveau').forEach(function (x) {
                x.setAttribute('aria-pressed', String(x === b));
            });
        });
        ecranAdv.appendChild(niveaux);

        /* Mode : Classique (règles du ping, tableau des scores) ou Arcade
         * (coups spéciaux, hors tableau). Gardé sur l'appareil. */
        var modes = el('div', 'pong-modes');
        modes.setAttribute('role', 'group');
        modes.setAttribute('aria-label', 'Mode de jeu');
        [['classique', 'Classique', 'Les règles du ping'], ['arcade', 'Arcade', 'Coups spéciaux']].forEach(function (m) {
            var b = el('button', 'pong-mode pong-mode--' + m[0]);
            b.type = 'button';
            b.dataset.mode = m[0];
            if (m[0] === 'arcade') {
                var eclair = el('span', 'pong-mode-eclair');
                eclair.innerHTML = '<svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false"><path d="M13.5 2 4.5 13.5H11L9.5 22l10-12.5H13z" fill="currentColor"/></svg>';
                b.appendChild(eclair);
            }
            b.appendChild(el('strong', 'pong-mode-nom', m[1]));
            b.appendChild(el('span', 'pong-mode-sous', m[2]));
            modes.appendChild(b);
        });
        var aideMode = el('p', 'pong-mode-aide');
        function majModes() {
            modes.querySelectorAll('.pong-mode').forEach(function (x) {
                x.setAttribute('aria-pressed', String(x.dataset.mode === etat.mode));
            });
            aideMode.textContent = etat.mode === 'arcade'
                ? 'Coups spéciaux : remplis ta jauge (les effets la remplissent vite), puis ' + (tactile ? 'touche l\'écran' : 'appuie sur Espace') + ' pour lâcher ton coup. Hors tableau des scores.'
                : 'Les règles du ping, et le tableau des meilleurs scores.';
        }
        modes.addEventListener('click', function (e) {
            var b = e.target.closest('.pong-mode');
            if (!b) return;
            etat.mode = b.dataset.mode;
            ecrirePref('arcade', etat.mode === 'arcade');
            majModes();
            majCoups();
        });
        majModes();
        ecranAdv.appendChild(modes);
        ecranAdv.appendChild(aideMode);

        /* Choix du coup spécial (mode Arcade), gardé sur l'appareil ; à
         * défaut, le coup signature du joueur choisi. */
        etat.coup = coupParCle(lireLocal('coup')) ? lireLocal('coup') : null;
        function coupJoueur() { return coupParCle(etat.coup) || coupSignature(etat.joueur); }
        var blocCoups = el('div', 'pong-coups-bloc');
        blocCoups.appendChild(el('p', 'pong-coups-titre', 'Ton coup spécial'));
        var choixCoups = el('div', 'pong-coups');
        choixCoups.setAttribute('role', 'group');
        choixCoups.setAttribute('aria-label', 'Ton coup spécial');
        COUPS.forEach(function (c) {
            var b = el('button', 'pong-coup');
            b.type = 'button';
            b.dataset.coup = c.cle;
            b.style.setProperty('--pong-coup', 'rgb(' + c.couleur + ')');
            var picto = el('span', 'pong-coup-picto');
            picto.innerHTML = pictoCoup(c, 22);
            b.appendChild(picto);
            b.appendChild(el('strong', 'pong-coup-nom', c.nom));
            b.appendChild(el('span', 'pong-coup-desc', c.desc));
            choixCoups.appendChild(b);
        });
        choixCoups.addEventListener('click', function (e) {
            var b = e.target.closest('.pong-coup');
            if (!b) return;
            etat.coup = b.dataset.coup;
            ecrireLocal('coup', etat.coup);
            majCoups();
        });
        blocCoups.appendChild(choixCoups);
        ecranAdv.appendChild(blocCoups);
        function majCoups() {
            blocCoups.hidden = etat.mode !== 'arcade';
            var choisi = etat.joueur ? coupJoueur().cle : etat.coup;
            choixCoups.querySelectorAll('.pong-coup').forEach(function (x) {
                x.setAttribute('aria-pressed', String(x.dataset.coup === choisi));
            });
        }
        majCoups();

        var hasard = el('button', 'pong-bouton pong-hasard', 'Au hasard');
        hasard.type = 'button';
        hasard.addEventListener('click', function () {
            var choix = vueActive.liste.filter(function (a) { return a !== etat.joueur; });
            if (choix.length) jouerContre(choix[Math.floor(Math.random() * choix.length)]);
        });
        ecranAdv.appendChild(hasard);
        vues.forEach(function (v) { ecranAdv.appendChild(v.bloc); });

        function jouerContre(a) {
            adv = a;
            demarrer(etat.joueur);
        }

        function montrer(ecran) {
            [ecranSel, ecranAdv, ecranJeu].forEach(function (e) { e.hidden = e !== ecran; });
            ecran.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        }

        /* ---- Écran de jeu ---- */
        var ecranJeu = el('div', 'pong-ecran pong-jeu');
        ecranJeu.hidden = true;
        var marqueur = new Marqueur();
        // Match en une manche : pas de fiches de manches sur le marqueur.
        if (nbManches === 1) marqueur.el.classList.add('pong-marqueur--une-manche');
        ecranJeu.appendChild(marqueur.el);
        var scene = el('div', 'pong-scene');
        var canvas = el('canvas', 'pong-canvas');
        canvas.setAttribute('role', 'img');
        canvas.setAttribute('aria-label', 'Table de ping : déplacez votre joueur pour renvoyer la balle');
        scene.appendChild(canvas);
        var message = el('div', 'pong-message');
        message.hidden = true;
        scene.appendChild(message);
        ecranJeu.appendChild(scene);

        /* Roue crantée au milieu du marqueur, entre les deux scores (hors de
         * la table, qui reste dégagée) : ouvre le panneau « Pause » (le match
         * est figé) avec les réglages et le retour au choix de l'adversaire.
         * Touche P ou Échap aussi. */
        var btnMenu = el('button', 'pong-menu-bouton');
        btnMenu.type = 'button';
        btnMenu.innerHTML = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">' +
            // Roue à 6 dents, centre évidé (fill-rule evenodd).
            '<path fill-rule="evenodd" fill="currentColor" d="M9.55 4.60L9.69 1.66A10.6 10.6 0 0 1 14.31 1.66L14.45 4.60A7.8 7.8 0 0 1 17.19 6.17L19.80 4.82A10.6 10.6 0 0 1 22.12 8.83L19.64 10.42A7.8 7.8 0 0 1 19.64 13.58L22.12 15.17A10.6 10.6 0 0 1 19.80 19.18L17.19 17.83A7.8 7.8 0 0 1 14.45 19.40L14.31 22.34A10.6 10.6 0 0 1 9.69 22.34L9.55 19.40A7.8 7.8 0 0 1 6.81 17.83L4.20 19.18A10.6 10.6 0 0 1 1.88 15.17L4.36 13.58A7.8 7.8 0 0 1 4.36 10.42L1.88 8.83A10.6 10.6 0 0 1 4.20 4.82L6.81 6.17A7.8 7.8 0 0 1 9.55 4.60ZM8.70 12.00a3.3 3.3 0 1 0 6.6 0a3.3 3.3 0 1 0 -6.6 0Z"/></svg>';
        btnMenu.setAttribute('aria-label', 'Pause et réglages');
        btnMenu.setAttribute('aria-haspopup', 'dialog');
        btnMenu.title = 'Pause et réglages (P)';
        marqueur.el.insertBefore(btnMenu, marqueur.camps.adversaire.el);

        var menu = el('div', 'pong-menu');
        menu.hidden = true;
        menu.setAttribute('role', 'dialog');
        menu.setAttribute('aria-modal', 'true');
        menu.setAttribute('aria-label', 'Pause');
        var panneau = el('div', 'pong-menu-panneau');
        panneau.appendChild(el('strong', 'pong-message-titre', 'Pause'));
        var btnReprendre = el('button', 'pong-bouton', 'Reprendre');
        btnReprendre.type = 'button';
        function option() {
            var b = el('button', 'pong-menu-option');
            b.type = 'button';
            return b;
        }
        var btnSon = option();
        var btnMusique = option();
        var btnPlein = option();
        btnPlein.setAttribute('aria-pressed', 'false');
        var btnChanger = el('button', 'pong-bouton pong-bouton--secondaire', 'Changer d\'adversaire');
        btnChanger.type = 'button';
        var options = el('div', 'pong-menu-options');
        options.appendChild(btnSon);
        if (cfg.musique) options.appendChild(btnMusique);
        options.appendChild(btnPlein);
        panneau.appendChild(btnReprendre);
        panneau.appendChild(options);
        panneau.appendChild(btnChanger);
        menu.appendChild(panneau);
        scene.appendChild(menu);

        var enPause = false;
        function ouvrirMenu() {
            if (ecranJeu.hidden || enPause) return;
            enPause = true;
            touches.g = touches.d = touches.h = touches.b = false;
            cible = null;
            oublierGeste();
            menu.hidden = false;
            btnMenu.setAttribute('aria-expanded', 'true');
            setTimeout(function () { btnReprendre.focus({ preventScroll: true }); }, 30);
        }
        function fermerMenu() {
            if (!enPause) return;
            enPause = false;
            menu.hidden = true;
            btnMenu.setAttribute('aria-expanded', 'false');
            root.focus({ preventScroll: true });
        }
        btnMenu.setAttribute('aria-expanded', 'false');
        btnMenu.addEventListener('click', ouvrirMenu);
        btnReprendre.addEventListener('click', fermerMenu);
        // Clic à côté du panneau : reprise.
        menu.addEventListener('click', function (e) { if (e.target === menu) fermerMenu(); });

        // Écrans avant les crédits photo posés par le shortcode.
        var credits = root.querySelector('.pong-credits');
        root.insertBefore(ecranSel, credits);
        root.insertBefore(ecranAdv, credits);
        root.insertBefore(ecranJeu, credits);
        root.classList.add('pong-pret');
        if (!root.hasAttribute('tabindex')) root.tabIndex = -1;

        /* ---- Effets sonores et musique de fond ---- */
        var PICTO_SON = '<path d="M4 9.5h3.5L12 5.5v13l-4.5-4H4z" fill="currentColor"/>';
        var PICTO_NOTE = '<path d="M9.5 17.5V6.5l9-2v11" fill="none" stroke="currentColor" stroke-width="2" stroke-linejoin="round"/><circle cx="7" cy="17.5" r="2.6" fill="currentColor"/><circle cx="16" cy="15.5" r="2.6" fill="currentColor"/>';
        var ONDES = '<path d="M15 9.2a4 4 0 0 1 0 5.6M17.6 6.8a7.5 7.5 0 0 1 0 10.4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>';
        var BARRE = '<path d="M3.5 3.5l17 17" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>';
        function picto(contenu) {
            return '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false">' + contenu + '</svg>';
        }
        /* Ligne du panneau : pictogramme, libellé, état (Oui / Non). */
        function contenuOption(svg, libelle, actif) {
            return svg + '<span class="pong-menu-libelle">' + libelle + '</span><span class="pong-menu-etat">' + (actif ? 'Oui' : 'Non') + '</span>';
        }
        function majBoutonsSon() {
            btnSon.innerHTML = contenuOption(picto(PICTO_SON + (etat.son ? ONDES : BARRE)), 'Effets sonores', etat.son);
            btnSon.setAttribute('aria-pressed', String(etat.son));
            btnSon.classList.toggle('pong-coupe', !etat.son);
            btnMusique.innerHTML = contenuOption(picto(PICTO_NOTE + (etat.musique ? '' : BARRE)), 'Musique', etat.musique);
            btnMusique.setAttribute('aria-pressed', String(etat.musique));
            btnMusique.classList.toggle('pong-coupe', !etat.musique);
        }
        majBoutonsSon();
        btnSon.addEventListener('click', function () {
            etat.son = !etat.son;
            ecrirePref('son', etat.son);
            majBoutonsSon();
        });

        /* Musique en boucle : lancée au premier geste du visiteur (règle des
         * navigateurs), coupée quand l'onglet est caché. */
        var musique = null, musiqueLancee = false;
        function jouerMusique() {
            if (!cfg.musique || !etat.musique || document.hidden) return;
            if (!musique) {
                musique = new Audio(cfg.musique);
                musique.loop = true;
                musique.preload = 'auto';
                musique.volume = 0.35;
            }
            var lecture = musique.play();
            musiqueLancee = true;
            if (lecture && lecture.catch) lecture.catch(function () { musiqueLancee = false; });
        }
        function couperMusique() { if (musique) musique.pause(); }
        if (cfg.musique) {
            root.addEventListener('pointerdown', function () { if (!musiqueLancee) jouerMusique(); }, true);
            root.addEventListener('keydown', function () { if (!musiqueLancee) jouerMusique(); }, true);
            btnMusique.addEventListener('click', function () {
                etat.musique = !etat.musique;
                ecrirePref('musique', etat.musique);
                majBoutonsSon();
                if (etat.musique) jouerMusique(); else couperMusique();
            });
            document.addEventListener('visibilitychange', function () {
                if (document.hidden) couperMusique();
                else if (musiqueLancee) jouerMusique();
            });
        }
        btnChanger.addEventListener('click', function () { fermerMenu(); retourSelection(); });

        /* ---- Plein écran ----
         * API Fullscreen quand le navigateur la permet ; sinon (iPhone, cadre
         * qui l'interdit) le jeu couvre la fenêtre en position fixe. */
        var pleinEcran = false, vraiPleinEcran = false;
        var PICTO_PLEIN = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M4 9V4h5M15 4h5v5M20 15v5h-5M9 20H4v-5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        var PICTO_REDUIRE = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path d="M9 4v5H4M20 9h-5V4M15 20v-5h5M4 15h5v5" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"/></svg>';
        function majBoutonPlein() {
            btnPlein.innerHTML = contenuOption(pleinEcran ? PICTO_REDUIRE : PICTO_PLEIN, 'Plein écran', pleinEcran);
            btnPlein.setAttribute('aria-pressed', String(pleinEcran));
        }
        majBoutonPlein();
        function elementPleinEcran() { return document.fullscreenElement || document.webkitFullscreenElement || null; }
        function basculerPleinEcran(actif) {
            if (actif === pleinEcran) return;
            pleinEcran = actif;
            root.classList.toggle('pong-plein-ecran', actif);
            document.documentElement.classList.toggle('monclubtt-pong-verrou', actif);
            majBoutonPlein();
            if (actif) {
                var demande = root.requestFullscreen || root.webkitRequestFullscreen;
                if (demande) {
                    vraiPleinEcran = true;
                    try {
                        var promesse = demande.call(root);
                        if (promesse && promesse.catch) promesse.catch(function () { vraiPleinEcran = false; });
                    } catch (e) { vraiPleinEcran = false; }
                }
            } else if (vraiPleinEcran) {
                vraiPleinEcran = false;
                var sortir = document.exitFullscreen || document.webkitExitFullscreen;
                if (elementPleinEcran() === root && sortir) sortir.call(document);
            }
            requestAnimationFrame(dimensionner);
            if (!enPause) root.focus({ preventScroll: true });
        }
        btnPlein.addEventListener('click', function () {
            // Plein écran quitté à la main : plus imposé pendant la visite.
            if (pleinEcran) refusPleinEcran = true;
            basculerPleinEcran(!pleinEcran);
        });

        /* Mobile (écran tactile étroit) : plein écran au lancement du match.
         * Les navigateurs l'exigent pendant un geste du visiteur, d'où l'appel
         * depuis le toucher sur l'adversaire, et non au chargement de la page. */
        var mobile = tactile && window.matchMedia('(max-width: 820px)').matches;
        var pleinEcranAuto = false, refusPleinEcran = false;
        function pleinEcranMobile() {
            if (!mobile || pleinEcran || refusPleinEcran) return;
            pleinEcranAuto = true;
            basculerPleinEcran(true);
        }
        // Sortie par la touche Échap ou le geste du navigateur.
        ['fullscreenchange', 'webkitfullscreenchange'].forEach(function (evt) {
            document.addEventListener(evt, function () {
                if (vraiPleinEcran && elementPleinEcran() !== root) {
                    // Sortie par le visiteur (geste retour, Échap) : plus imposé.
                    refusPleinEcran = true;
                    pleinEcranAuto = false;
                    vraiPleinEcran = false;
                    basculerPleinEcran(false);
                }
                requestAnimationFrame(dimensionner);
            });
        });

        var ctx = canvas.getContext('2d');
        var echelle = 1;
        var sprites = { joueur: null, adversaire: null };
        var figJ = { x: W / 2, vx: 0, coup: '', tCoup: 0, demi: SVG.demi * FIG_J.h / SVG.h + MARGE_FRAPPE };
        var figA = { x: W / 2, vx: 0, coup: '', tCoup: 0, demi: SVG.demi * FIG_A.h / SVG.h + MARGE_FRAPPE };
        var balle = { x: W / 2, y: H / 2, vx: 0, vy: 0, v: 0, ax: 0, rot: 0, y0: 0, rebond: false, visible: false, trace: [] };
        var match = null;
        var phase = 'arret'; // arret | service | jeu | pause | message
        var minuterie = 0;
        var cible = null, touches = { g: false, d: false, h: false, b: false };
        var lancer = null; // durée écoulée du lancer de balle au service, null hors lancer
        var geste = { y: null, yAvant: null, vy: 0 }; // mouvement vertical du pointeur
        var ia = { cible: W / 2, erreur: 0, vise: 0 };
        var raf = 0, dernier = 0;
        var toast = { texte: '', t: 0 };

        /* ---- Effets visuels (tous modes) ----
         * Étincelles et onde à chaque frappe (selon la vitesse), secousse sur
         * un smash ou un service rapide gagnant, ralenti quand la balle de
         * match passe la raquette. Ni secousse ni ralenti si l'appareil
         * demande de réduire les animations. */
        var mouvementReduit = !!(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches);
        var particules = [], secousse = { t: 0, duree: 1, force: 0 };
        function impact() {
            var ratio = clamp(balle.v / NIVEAUX[etat.niveau].vMax, 0, 1.4);
            var p = proj(balle.x, balle.y, hauteurBalle());
            particules.push({ onde: true, x: p.x, y: p.y, t: 0, vie: 0.2, r: 6 + 14 * ratio });
            var nb = mouvementReduit ? 3 : Math.round(4 + 8 * ratio);
            for (var i = 0; i < nb; i++) {
                var a = Math.random() * Math.PI * 2, v = 60 + Math.random() * 140 * (0.5 + ratio);
                particules.push({
                    x: p.x, y: p.y, vx: Math.cos(a) * v, vy: Math.sin(a) * v, t: 0, vie: 0.22 + Math.random() * 0.15,
                    couleur: i % 3 ? '255,255,255' : '242,162,74'
                });
            }
        }
        function secouer(duree, force) {
            if (!mouvementReduit) secousse = { t: duree, duree: duree, force: force };
        }
        function animerEffets(dt) {
            particules = particules.filter(function (q) {
                q.t += dt;
                if (!q.onde) {
                    q.x += q.vx * dt;
                    q.y += q.vy * dt;
                    q.vx *= 1 - 4 * dt;
                    q.vy *= 1 - 4 * dt;
                }
                return q.t < q.vie;
            });
            if (secousse.t > 0) secousse.t = Math.max(0, secousse.t - dt);
        }
        function dessinerEffets() {
            particules.forEach(function (q) {
                var k = 1 - q.t / q.vie;
                if (q.onde) {
                    ctx.strokeStyle = 'rgba(255,255,255,' + (0.7 * k).toFixed(3) + ')';
                    ctx.lineWidth = 2.5 * k + 0.5;
                    ctx.beginPath();
                    ctx.arc(q.x, q.y, q.r * (1 - k * 0.7), 0, Math.PI * 2);
                    ctx.stroke();
                } else {
                    ctx.fillStyle = 'rgba(' + q.couleur + ',' + k.toFixed(3) + ')';
                    ctx.beginPath();
                    ctx.arc(q.x, q.y, 1.2 + 1.6 * k, 0, Math.PI * 2);
                    ctx.fill();
                }
            });
        }
        /* Facteur de temps : ralenti quand la balle de match a passé la
         * raquette qui devait la renvoyer (le point se joue). */
        function vitesseTemps() {
            if (mouvementReduit || phase !== 'jeu' || !match || !match.balleDeMatch()) return 1;
            var manquee = balle.vy > 0 ? balle.y - R > HIT_J : balle.y + R < HIT_A;
            return manquee ? 0.35 : 1;
        }

        /* ---- Mode Arcade : jauges, coups spéciaux, cut-in ---- */
        var arcade = { jauge: { joueur: 0, adversaire: 0 }, arme: { joueur: false, adversaire: false }, speciaux: 0, gel: 0, cutin: null };
        // Jamais au défi du jour : ses règles sont fixes.
        function modeArcade() { return etat.mode === 'arcade' && !etat.defiJour; }
        function majJauges() { marqueur.jauges(arcade.jauge, arcade.arme); }
        function remplirJauge(cote, q) {
            if (!modeArcade() || arcade.arme[cote]) return;
            var avant = arcade.jauge[cote];
            arcade.jauge[cote] = Math.min(1, avant + q);
            if (cote === 'joueur' && avant < 1 && arcade.jauge.joueur >= 1) {
                montrerToast(tactile ? 'Jauge pleine : touche l\'écran !' : 'Jauge pleine : Espace !', 1.4);
            }
            majJauges();
        }
        /* Arme le coup du joueur (jauge pleine, pendant l'échange). */
        function armerSpecial() {
            if (!modeArcade() || phase !== 'jeu' || arcade.jauge.joueur < 1 || arcade.arme.joueur) return false;
            arcade.arme.joueur = true;
            montrerToast('Coup spécial prêt : ' + coupJoueur().nom, 0.9);
            tock(980, etat.son);
            majJauges();
            return true;
        }
        /* Coup spécial à la frappe de fig : la balle change de comportement,
         * le jeu se fige le temps du cut-in. */
        function lancerSpecial(fig, sens, cote) {
            var n = NIVEAUX[etat.niveau];
            var coup = cote === 'joueur' ? coupJoueur() : coupSignature(adv);
            arcade.jauge[cote] = 0;
            arcade.arme[cote] = false;
            if (cote === 'joueur') arcade.speciaux++;
            balle.special = { cle: coup.cle, couleur: coup.couleur, t: 0 };
            if (coup.cle === 'feu') {
                var v = Math.min(n.vMax * FEU.plafond, balle.v * FEU.gain), k = v / balle.v;
                balle.v = v;
                balle.vx *= k;
                balle.vy *= k;
                balle.ax = 0;
            } else if (coup.cle === 'glace') {
                // L'adversaire est figé dès la reprise du jeu, après le cut-in.
                (cote === 'joueur' ? figA : figJ).gele = GLACE;
            } else if (coup.cle === 'boomerang') {
                // Part vers le bord opposé et revient en courbe au point de départ.
                var c = balle.x < W / 2 ? 1 : -1;
                balle.vx = c * BOOMERANG.lateral * balle.v;
                balle.vy = sens * BOOMERANG.avant * balle.v;
                var trajet = Math.abs(HIT_J - HIT_A) / Math.abs(balle.vy);
                // Courbure telle que l'écart soit nul à l'arrivée, amortissement de
                // l'effet compris : x(T) = vx·T + ax/k · (T − (1 − e^(−kT))/k) = 0.
                balle.ax = -balle.vx * trajet * EFFET_AMORTI / (trajet - (1 - Math.exp(-EFFET_AMORTI * trajet)) / EFFET_AMORTI);
            }
            arcade.gel = mouvementReduit ? 0.4 : GEL_CUTIN;
            arcade.cutin = { cote: cote, coup: coup, t: 0 };
            sonSpecial(etat.son);
            majJauges();
        }
        /* Balle fantôme : invisible sur le milieu de son trajet. */
        function balleCachee() {
            if (!balle.special || balle.special.cle !== 'fantome' || phase !== 'jeu') return false;
            var u = Math.abs(balle.y - balle.y0) / Math.abs(HIT_J - HIT_A);
            return u > FANTOME.de && u < FANTOME.a;
        }

        /* Personnage : corps dessiné dans ses trois poses (sans tête si photo)
         * + tête détourée. */
        function preparerSprite(p, tenue, hauteur) {
            return chargerImage(p.photo).then(function (photo) {
                return Promise.all(POSES.map(function (pose) {
                    return chargerImage(urlJoueur(p.sex, tenue, pose, !photo));
                })).then(function (images) {
                    var corps = {};
                    POSES.forEach(function (pose, i) { corps[pose] = images[i]; });
                    return { p: p, corps: corps, photo: photo, hauteur: hauteur, tete: null, cle: p.photo || 'avatar' };
                });
            });
        }

        /* ---- Séparations : logos des partenaires, sinon du club ----
         * Images chargées une fois, recolorées en blanc à la taille d'affichage
         * à chaque redimensionnement. Plus de partenaires que de panneaux : la
         * rangée défile dans l'ordre, d'une rangée entière à chaque match, à
         * partir d'un partenaire tiré au hasard à chaque visite (sinon les
         * mêmes sortiraient toujours au premier match, le plus joué). */
        var partenaires = (cfg.partenaires || []).map(function (p) { return { url: p.logo, nom: p.nom || '' }; });
        var club = { url: cfg.club && cfg.club.logo || '', nom: cfg.club && cfg.club.nom || '' };
        var panneaux = [], tourSepa = 0;
        var departSepa = Math.floor(Math.random() * Math.max(1, partenaires.length));
        partenaires.concat([club]).forEach(function (p) {
            chargerImage(p.url).then(function (img) {
                p.img = img;
                construireSeparations();
            });
        });
        function construireSeparations() {
            var n = SEPA.panneaux, r = rangeeSepa();
            var bw = r.l * (1 - 2 * SEPA.marge) * echelle * dpr, bh = r.h * (1 - 2 * SEPA.marge) * echelle * dpr;
            function contenu(p) {
                if (!p) return null;
                if (p.img && (!p.blanc || p.taille !== bw + 'x' + bh)) {
                    p.blanc = logoBlanc(p.img, bw, bh);
                    p.taille = bw + 'x' + bh;
                }
                return p.img ? p : { nom: p.nom };
            }
            panneaux = [];
            var places = partenaires.length < n ? placesPartenaires(partenaires.length, n) : null;
            for (var i = 0; i < n; i++) {
                var p;
                if (!places) p = partenaires[(departSepa + tourSepa * n + i) % partenaires.length];
                else p = places.indexOf(i) >= 0 ? partenaires[places.indexOf(i)] : club;
                panneaux.push(contenu(p));
            }
        }

        function construireTetes() {
            ['joueur', 'adversaire'].forEach(function (cote) {
                var s = sprites[cote];
                if (!s || !s.photo) return;
                var k = s.hauteur * proj(0, cote === 'joueur' ? FIG_J.hit : FIG_A.hit, 0).s / SVG.h;
                var hTete = SVG.tete * k * echelle * dpr;
                s.tete = sticker(s.photo, hTete, 6 * hTete / TETE_GRILLE);
            });
        }

        /* L'écran de jeu entier (marqueur, table, boutons) tient dans la hauteur
         * visible de la fenêtre (100vh, barres du navigateur mobile déduites). */
        function dimensionner(passe) {
            if (ecranJeu.hidden) return;
            var avant = canvas.style.width;
            var hauteurFenetre = window.visualViewport ? window.visualViewport.height : window.innerHeight;
            var style = getComputedStyle(root);
            var marges = pleinEcran ? parseFloat(style.paddingTop) + parseFloat(style.paddingBottom) : 0;
            var ecarts = parseFloat(getComputedStyle(ecranJeu).rowGap) || 0;
            var largeurDispo = ecranJeu.clientWidth;
            var hauteurDispo = hauteurFenetre - marges - marqueur.el.offsetHeight - ecarts - 4;
            var largeur = Math.max(120, Math.min(largeurDispo, pleinEcran ? Infinity : 520, hauteurDispo * W / H));
            canvas.style.width = largeur + 'px';
            canvas.style.height = (largeur * H / W) + 'px';
            scene.style.width = largeur + 'px';
            canvas.width = Math.round(largeur * dpr);
            canvas.height = Math.round(largeur * H / W * dpr);
            echelle = largeur / W;
            construireTetes();
            construireSeparations();
            // Une passe de plus si la mise en page autour a changé (retour à la ligne…).
            if (!passe && canvas.style.width !== avant) dimensionner(true);
        }

        /* Force de l'adversaire selon l'écart de points avec le joueur choisi
         * (pour un visiteur, la médiane du club) : 5 % par tranche de
         * 150 points, ±20 % au plus. Top 10 et invité, sans points : niveau de base. */
        var ptsClub = joueurs.map(function (j) { return +j.pts || 0; }).filter(Boolean).sort(function (a, b) { return a - b; });
        var medianeClub = ptsClub.length ? ptsClub[Math.floor(ptsClub.length / 2)] : 0;
        function forceAdversaire(p, a) {
            var ref = +p.pts || medianeClub;
            if (!(+a.pts) || !ref) return 1;
            return clamp(1 + 0.05 * (a.pts - ref) / 150, 0.8, 1.2);
        }

        function demarrer(p) {
            pleinEcranMobile();
            etat.joueur = p;
            // Défi du jour : même adversaire pour tous, sans ajustement à son joueur.
            etat.force = etat.defiJour ? 1 : forceAdversaire(p, adv);
            montrer(ecranJeu);
            marqueur.noms(nomAffiche(p), nomAffiche(adv));
            Promise.all([
                preparerSprite(p, couleurs.maillot, FIG_J.h),
                preparerSprite(adv, adv.pays || couleurs.secondaire, FIG_A.h)
            ]).then(function (s) {
                sprites.joueur = s[0];
                sprites.adversaire = s[1];
                dimensionner();
                nouveauMatch();
                root.focus({ preventScroll: true });
            });
        }

        function retourSelection() {
            fermerMenu();
            // Retour au choix de l'adversaire : la page reprend sa place.
            if (pleinEcranAuto) {
                pleinEcranAuto = false;
                basculerPleinEcran(false);
            }
            cancelAnimationFrame(raf);
            raf = 0;
            phase = 'arret';
            // Fin du défi du jour : retour au jeu libre.
            if (etat.defiJour) {
                etat.defiJour = null;
                if (grilleJoueurs) grilleJoueurs.masquer(null);
                majDefiJour(defiJour);
            }
            montrer(ecranAdv);
        }

        function nouveauMatch() {
            // Compteur des parties jouées (réglages du plugin) ; sans réponse attendue.
            appelAjax('monclubtt_pong_debut', { nonce: cfg.nonce || '' }).catch(function () {});
            // Défi du jour : une manche ; la tentative comptée est consommée ici.
            if (etat.defiJour === 'compte' && defiJoue()) etat.defiJour = 'libre';
            if (etat.defiJour === 'compte') ecrireLocal('defi-jour', defiJour.date);
            var manches = etat.defiJour ? 1 : nbManches;
            match = new Match(manches);
            match.duree = 0; // temps de jeu (s), pauses exclues
            arcade = { jauge: { joueur: 0, adversaire: 0 }, arme: { joueur: false, adversaire: false }, speciaux: 0, gel: 0, cutin: null };
            figJ.sonne = figA.sonne = 0;
            figJ.gele = figA.gele = 0;
            var hauteurMarqueur = marqueur.el.offsetHeight;
            marqueur.el.classList.toggle('pong-marqueur--arcade', modeArcade());
            marqueur.coups(coupJoueur(), coupSignature(adv));
            majJauges();
            // Jauges affichées ou retirées : la table se recale sur la hauteur restante.
            if (marqueur.el.offsetHeight !== hauteurMarqueur) dimensionner();
            marqueur.el.classList.toggle('pong-marqueur--une-manche', manches === 1);
            tourSepa++;
            construireSeparations();
            marqueur.maj(match);
            figJ.x = W / 2; figA.x = W / 2;
            figJ.tCoup = figA.tCoup = 0;
            masquerMessage();
            preparerService();
            if (!raf) { dernier = performance.now(); raf = requestAnimationFrame(boucle); }
        }

        function preparerService() {
            phase = 'service';
            balle.visible = true;
            balle.vx = balle.vy = 0;
            lancer = null;
            minuterie = match.serveur() === 'adversaire' ? 0.9 : 0;
            if (match.serveur() === 'joueur') {
                montrerToast(tactile ? 'Touchez pour servir, 2 fois à temps : rapide' : 'Espace pour servir, 2 fois à temps : rapide', 2.6);
            }
        }

        /* Touche de service du joueur : lance la balle, puis frappe pendant le lancer. */
        function appuiService() {
            if (armerSpecial()) return;
            if (phase !== 'service' || match.serveur() !== 'joueur') return;
            if (lancer === null) { lancer = 0; tock(420, etat.son); return; }
            var aTemps = LANCER - lancer <= FENETRE;
            servir('joueur', aTemps ? SERVICE_RAPIDE : SERVICE_MOU);
            montrerToast(aTemps ? 'Service rapide !' : 'Trop tôt…', 0.7);
        }

        function servir(cote, force) {
            var n = NIVEAUX[etat.niveau];
            var angle = (Math.random() * 0.7 - 0.35);
            lancer = null;
            balle.v = Math.min(n.vMax, n.v0 * (force || 1));
            balle.vx = Math.sin(angle) * balle.v;
            balle.vy = (cote === 'joueur' ? -1 : 1) * Math.cos(angle) * balle.v;
            balle.ax = 0;
            balle.rot = 0;
            balle.trace = [];
            balle.y0 = balle.y;
            balle.rebond = false;
            balle.frappes = 0;
            balle.special = null;
            balle.serveur = cote;
            balle.serviceRapide = force > 1;
            phase = 'jeu';
            toast.t = 0;
            tock(force > 1 ? 760 : 620, etat.son);
            if (cote === 'joueur') viserIa();
        }

        function frapper(fig, sens) {
            var n = NIVEAUX[etat.niveau];
            var cote = fig === figJ ? 'joueur' : 'adversaire';
            if (balle.special && balle.special.cle === 'feu') fig.sonne = SONNE;
            balle.special = null;
            var rel = clamp((balle.x - fig.x) / fig.demi, -1, 1);
            balle.v = Math.min(n.vMax, balle.v * ACCELERATION);
            // Coup droit si la balle arrive du côté de la raquette, revers sinon.
            fig.coup = balle.x >= fig.x ? 'cd' : 'rv';
            fig.tCoup = DUREE_COUP;
            var angle = rel * 0.85;
            balle.vx = Math.sin(angle) * balle.v;
            balle.vy = sens * Math.cos(angle) * balle.v;
            balle.ax = clamp((fig.vx || 0) / EFFET_VITESSE, -1, 1) * (fig === figJ ? EFFET_MAX_JOUEUR : EFFET_MAX);
            balle.rot = fig === figJ ? rotJoueur() : rotIa(n);
            if (fig === figJ && Math.abs(balle.rot) >= 0.5) montrerToast(balle.rot > 0 ? 'Lifté !' : 'Coupé !', 0.6);
            balle.y0 = balle.y;
            balle.rebond = false;
            balle.frappes++;
            impact();
            if (modeArcade()) {
                if (balle.frappes >= JAUGE.apresFrappes) remplirJauge(cote, JAUGE.echange);
                if (Math.abs(balle.rot) >= 0.5) remplirJauge(cote, JAUGE.effet);
                if (Math.abs(balle.ax) >= EFFET_MAX * 0.5) remplirJauge(cote, JAUGE.courbe);
                var iaTire = cote === 'adversaire' && arcade.jauge.adversaire >= 1 && Math.random() < n.special;
                if (arcade.arme[cote] || iaTire) lancerSpecial(fig, sens, cote);
            }
            // Smash : frappe du joueur à pleine vitesse.
            if (fig === figJ && balle.v >= n.vMax * 0.95) secouer(0.18, 3.5);
            tock(sens < 0 ? 560 : 700, etat.son);
        }

        /* Lifté (+1) à coupé (-1) : touches haut / bas, sinon geste vertical. */
        function rotJoueur() {
            if (touches.h !== touches.b) return touches.h ? 1 : -1;
            var r = clamp(-geste.vy / ROT_VITESSE, -1, 1);
            return Math.abs(r) < 0.2 ? 0 : r;
        }

        function rotIa(n) {
            if (Math.random() >= n.rot) return 0;
            return (Math.random() < 0.5 ? -1 : 1) * (0.5 + Math.random() * 0.5);
        }

        /* Où la balle croisera la ligne yCible, rebonds sur les côtés compris. */
        /* lecture (0 à 1) : part de l'effet que l'adversaire anticipe. */
        function predire(yCible, lecture) {
            if (!balle.vy) return balle.x;
            var x = balle.x, y = balle.y, vx = balle.vx, ax = balle.ax * lecture, pas = 1 / 120;
            for (var i = 0; i < 600 && (balle.vy > 0 ? y < yCible : y > yCible); i++) {
                vx += ax * pas;
                ax *= 1 - EFFET_AMORTI * pas;
                x += vx * pas;
                y += balle.vy * pas;
                if (x < R) { x = 2 * R - x; vx = Math.abs(vx); }
                if (x > W - R) { x = 2 * (W - R) - x; vx = -Math.abs(vx); }
            }
            return x;
        }

        function viserIa() {
            var n = NIVEAUX[etat.niveau];
            ia.erreur = (Math.random() * 2 - 1) * n.erreur / (etat.force || 1);
            ia.vise = (Math.random() * 2 - 1) * figA.demi * 0.7;
        }

        function marquer(cote) {
            if (balle.serviceRapide && balle.frappes === 0 && balle.serveur === cote) secouer(0.25, 5);
            balle.visible = false;
            balle.special = null;
            remplirJauge(cote, JAUGE.point);
            var resultat = match.marquer(cote);
            marqueur.maj(match);
            if (cote === 'joueur') fanfare(etat.son);
            else tock(300, etat.son);
            if (resultat === 'point') {
                montrerToast(cote === 'joueur' ? 'Point !' : 'Point pour ' + (adv.prenom || adv.nom), 0.9);
                phase = 'pause';
                minuterie = 0.9;
            } else if (resultat === 'manche') {
                phase = 'message';
                var score = match.points.joueur + '–' + match.points.adversaire;
                afficherMessage(cote === 'joueur' ? 'Manche gagnée !' : 'Manche perdue',
                    score + ' · manches ' + match.manches.joueur + '–' + match.manches.adversaire,
                    [{ texte: 'Manche suivante', action: function () {
                        match.mancheSuivante();
                        marqueur.maj(match);
                        masquerMessage();
                        preparerService();
                    } }]);
            } else {
                phase = 'message';
                var victoire = cote === 'joueur';
                // En une manche, le score de la manche ; sinon le décompte des manches.
                var bilan = match.gagnantes === 1
                    ? match.points.joueur + '–' + match.points.adversaire
                    : 'manches ' + match.manches.joueur + '–' + match.manches.adversaire;
                afficherMessage(victoire ? 'Victoire !' : 'Défaite…',
                    (victoire ? 'Bravo ' + (etat.joueur.prenom || etat.joueur.nom) + ', ' : '') + bilan,
                    [
                        { texte: 'Rejouer', action: nouveauMatch },
                        { texte: 'Changer d\'adversaire', action: retourSelection, secondaire: true }
                    ]);
                if (modeArcade()) {
                    message.insertBefore(el('span', 'pong-message-classement', 'Mode Arcade : ' + arcade.speciaux + ' coup' + (arcade.speciaux > 1 ? 's' : '') + ' spécia' + (arcade.speciaux > 1 ? 'ux' : 'l') + ' placé' + (arcade.speciaux > 1 ? 's' : '') + ', hors tableau des scores.'), message.querySelector('.pong-message-boutons'));
                }
                var verdict = verdictDefi();
                if (verdict) message.insertBefore(el('strong', 'pong-message-defi', verdict), message.querySelector('.pong-message-boutons'));
                var ligneJour = el('strong', 'pong-message-defi');
                var ligneScore = el('span', 'pong-message-classement');
                var zonePartage = el('div', 'pong-partage');
                message.insertBefore(ligneJour, message.querySelector('.pong-message-boutons'));
                message.insertBefore(ligneScore, message.querySelector('.pong-message-boutons'));
                message.insertBefore(zonePartage, message.querySelector('.pong-message-boutons'));
                envoyerFin(ligneScore, zonePartage, ligneJour);
            }
        }

        function afficherMessage(titreTxt, sous, boutons) {
            message.innerHTML = '';
            message.appendChild(el('strong', 'pong-message-titre', titreTxt));
            message.appendChild(el('span', 'pong-message-sous', sous));
            var zone = el('div', 'pong-message-boutons');
            boutons.forEach(function (b, i) {
                var bt = el('button', 'pong-bouton' + (b.secondaire ? ' pong-bouton--secondaire' : ''), b.texte);
                bt.type = 'button';
                bt.addEventListener('click', function (e) { e.stopPropagation(); b.action(); });
                zone.appendChild(bt);
                if (i === 0) setTimeout(function () { bt.focus({ preventScroll: true }); }, 50);
            });
            message.appendChild(zone);
            message.hidden = false;
        }

        function masquerMessage() { message.hidden = true; }

        function montrerToast(texte, duree) { toast.texte = texte; toast.t = duree; }

        /* ---- Commandes ---- */
        function versLogique(e) {
            var r = canvas.getBoundingClientRect();
            return (e.clientX - r.left) * W / r.width;
        }
        function versLogiqueY(e) {
            var r = canvas.getBoundingClientRect();
            return (e.clientY - r.top) * H / r.height;
        }
        function oublierGeste() { geste.y = geste.yAvant = null; geste.vy = 0; }
        var appui = null;
        scene.addEventListener('pointerdown', function (e) {
            if (e.target.closest('.pong-message, .pong-menu, .pong-menu-bouton')) return;
            appui = { x: e.clientX, y: e.clientY, t: performance.now() };
            if (e.pointerType !== 'mouse') { cible = versLogique(e); oublierGeste(); geste.y = versLogiqueY(e); }
            try { scene.setPointerCapture(e.pointerId); } catch (err) { /* ignoré */ }
        });
        scene.addEventListener('pointermove', function (e) {
            if (e.pointerType === 'mouse' || appui) { cible = versLogique(e); geste.y = versLogiqueY(e); }
        });
        scene.addEventListener('pointerup', function (e) {
            if (!appui) return;
            var court = performance.now() - appui.t < 350 &&
                Math.abs(e.clientX - appui.x) + Math.abs(e.clientY - appui.y) < 24;
            appui = null;
            if (e.pointerType !== 'mouse') oublierGeste();
            if (court) appuiService();
        });
        scene.addEventListener('pointercancel', function () { appui = null; oublierGeste(); });
        scene.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') { cible = null; oublierGeste(); } });

        root.addEventListener('keydown', function (e) {
            if (enPause) {
                if (e.key === 'Escape' || e.key === 'p' || e.key === 'P') { e.preventDefault(); fermerMenu(); }
                return;
            }
            if (!ecranJeu.hidden && (e.key === 'p' || e.key === 'P' || (e.key === 'Escape' && !pleinEcran))) {
                e.preventDefault();
                ouvrirMenu();
                return;
            }
            if (e.key === 'Escape' && pleinEcran) { basculerPleinEcran(false); return; }
            if (ecranJeu.hidden || e.target.closest('.pong-message') || e.target.tagName === 'BUTTON') return;
            var k = e.key;
            if (k === 'ArrowLeft' || k === 'a' || k === 'q') { touches.g = true; cible = null; e.preventDefault(); }
            else if (k === 'ArrowRight' || k === 'd') { touches.d = true; cible = null; e.preventDefault(); }
            else if (k === 'ArrowUp' || k === 'z' || k === 'w') { touches.h = true; e.preventDefault(); }
            else if (k === 'ArrowDown' || k === 's') { touches.b = true; e.preventDefault(); }
            else if (k === ' ' || k === 'Enter') {
                e.preventDefault();
                if (!e.repeat) appuiService();
            }
        });
        root.addEventListener('keyup', function (e) {
            if (e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'q') touches.g = false;
            if (e.key === 'ArrowRight' || e.key === 'd') touches.d = false;
            if (e.key === 'ArrowUp' || e.key === 'z' || e.key === 'w') touches.h = false;
            if (e.key === 'ArrowDown' || e.key === 's') touches.b = false;
        });

        var redim = 0;
        function surRedim() {
            clearTimeout(redim);
            redim = setTimeout(dimensionner, 120);
        }
        window.addEventListener('resize', surRedim);
        if (window.visualViewport) window.visualViewport.addEventListener('resize', surRedim);

        /* ---- Simulation ---- */
        function deplacerJoueur(dt) {
            if (figJ.gele > 0) { // figé dans un glaçon
                figJ.vx = 0;
                geste.yAvant = geste.y;
                return;
            }
            var avant = figJ.x;
            if (touches.g || touches.d) {
                figJ.x += ((touches.d ? 1 : 0) - (touches.g ? 1 : 0)) * 420 * (figJ.sonne > 0 ? 0.5 : 1) * dt;
            } else if (cible !== null) {
                figJ.x += (cible - figJ.x) * Math.min(1, dt * (figJ.sonne > 0 ? 6 : 20));
            }
            figJ.x = clamp(figJ.x, 20, W - 20);
            figJ.vx = (figJ.x - avant) / Math.max(dt, 0.001);
            // Vitesse verticale du geste, lissée (négative = vers l'adversaire).
            var vy = geste.y !== null && geste.yAvant !== null ? (geste.y - geste.yAvant) / Math.max(dt, 0.001) : 0;
            geste.vy += (vy - geste.vy) * Math.min(1, dt * 15);
            geste.yAvant = geste.y;
        }

        function deplacerIa(dt) {
            if (figA.gele > 0) { // figé dans un glaçon
                figA.vx = 0;
                return;
            }
            var n = NIVEAUX[etat.niveau];
            var but = W / 2;
            if (phase === 'jeu' && balle.vy < 0 && balle.y < HIT_J - (HIT_J - HIT_A) * (1 - n.reaction) - 1) {
                but = predire(HIT_A + R, n.lecture) - ia.vise + ia.erreur;
            } else if (phase === 'jeu' && balle.vy < 0) {
                but = figA.x; // pas encore réagi
            } else if (phase === 'service' && match.serveur() === 'adversaire') {
                but = figA.x;
            }
            if (balleCachee()) but = figA.x; // balle fantôme : il ne la lit plus
            var pas = n.vIa * (etat.force || 1) * (figA.sonne > 0 ? 0.5 : 1) * dt, avant = figA.x;
            figA.x += clamp(but - figA.x, -pas, pas);
            figA.x = clamp(figA.x, 20, W - 20);
            figA.vx = (figA.x - avant) / Math.max(dt, 0.001);
        }

        function step(dt) {
            animerEffets(dt);
            if (match && (phase === 'service' || phase === 'jeu' || phase === 'pause')) match.duree += dt;
            deplacerJoueur(dt);
            deplacerIa(dt);
            figJ.tCoup = Math.max(0, figJ.tCoup - dt);
            figJ.sonne = Math.max(0, (figJ.sonne || 0) - dt);
            figA.sonne = Math.max(0, (figA.sonne || 0) - dt);
            figJ.gele = Math.max(0, (figJ.gele || 0) - dt);
            figA.gele = Math.max(0, (figA.gele || 0) - dt);
            figA.tCoup = Math.max(0, figA.tCoup - dt);
            if (toast.t > 0) toast.t -= dt;

            if (phase === 'service') {
                var s = match.serveur();
                if (s === 'joueur') {
                    balle.x = figJ.x + SVG.raquette * FIG_J.h / SVG.h;
                    balle.y = HIT_J - R - 4;
                    if (lancer !== null) {
                        lancer += dt;
                        if (lancer >= LANCER) servir('joueur');
                    }
                } else {
                    balle.x = figA.x + SVG.raquette * FIG_A.h / SVG.h;
                    balle.y = HIT_A + R + 4;
                    minuterie -= dt;
                    if (minuterie <= 0) servir('adversaire', Math.random() < NIVEAUX[etat.niveau].rapide ? SERVICE_RAPIDE : 1);
                }
                return;
            }
            if (phase === 'pause') {
                minuterie -= dt;
                if (minuterie <= 0) preparerService();
                return;
            }
            if (phase !== 'jeu') return;

            var py = balle.y;
            balle.vx += balle.ax * dt;
            balle.ax *= 1 - EFFET_AMORTI * dt;
            balle.trace.unshift({ x: balle.x, y: balle.y, z: hauteurBalle() });
            if (balle.trace.length > 7) balle.trace.pop();
            balle.x += balle.vx * dt;
            balle.y += balle.vy * dt;
            // Zigzag : oscillation latérale jusqu'au rebond sur la table.
            if (balle.special && balle.special.cle === 'zigzag' && !balle.rebond) {
                balle.special.t += dt;
                balle.x += ZIGZAG.amplitude * ZIGZAG.pulsation * Math.cos(ZIGZAG.pulsation * balle.special.t) * dt;
            }
            if (balle.x < R) { balle.x = 2 * R - balle.x; balle.vx = Math.abs(balle.vx); }
            if (balle.x > W - R) { balle.x = 2 * (W - R) - balle.x; balle.vx = -Math.abs(balle.vx); }

            // Rebond (décoratif) sur la table, côté receveur.
            var yr = balle.y0 + (balle.vy > 0 ? 1 : -1) * Math.abs(HIT_J - HIT_A) * 0.72;
            if (!balle.rebond && (balle.vy > 0 ? balle.y >= yr : balle.y <= yr)) {
                balle.rebond = true;
                balle.vy *= 1 + ROT_GAIN * balle.rot;
                tock(1100 + 250 * balle.rot, etat.son);
            }

            if (balle.vy > 0 && py + R <= HIT_J && balle.y + R >= HIT_J) {
                if (Math.abs(balle.x - figJ.x) <= figJ.demi + R) {
                    balle.y = HIT_J - R;
                    frapper(figJ, -1);
                    viserIa();
                }
            } else if (balle.vy < 0 && py - R >= HIT_A && balle.y - R <= HIT_A) {
                if (Math.abs(balle.x - figA.x) <= figA.demi + R) {
                    balle.y = HIT_A + R;
                    frapper(figA, 1);
                }
            }
            if (balle.y - R > H) marquer('adversaire');
            else if (balle.y + R < 0) marquer('joueur');
        }

        /* ---- Rendu ----
         * Tout ce qui est posé dans la salle passe par proj() (perspective) ;
         * le toast reste en coordonnées d'écran. */
        function chemin(points) {
            ctx.beginPath();
            points.forEach(function (p, i) { if (i) ctx.lineTo(p.x, p.y); else ctx.moveTo(p.x, p.y); });
            ctx.closePath();
        }

        function dessinerFond() {
            var fond = couleurs.fond || '#f4f1ea';
            ctx.fillStyle = fond;
            ctx.fillRect(0, 0, W, H);
            // Mur du fond, au-dessus des séparations.
            ctx.fillStyle = teinte(fond, -0.1);
            ctx.fillRect(0, 0, W, rangeeSepa().pied);
        }

        /* Séparations bleu marine : n panneaux de face, au fond, de bord à bord. */
        function dessinerSeparations() {
            var r = rangeeSepa();
            ctx.fillStyle = 'rgba(15,20,26,0.18)'; // ombre au sol
            ctx.fillRect(0, r.pied, W, 5 * proj(W / 2, SEPA.y, 0).s);
            var g = ctx.createLinearGradient(0, r.y, 0, r.pied);
            g.addColorStop(0, SEPA.haut);
            g.addColorStop(1, SEPA.bas);
            for (var i = 0; i < SEPA.panneaux; i++) {
                var x0 = i * r.l;
                ctx.fillStyle = g;
                ctx.fillRect(x0, r.y, r.l, r.h);
                ctx.fillStyle = SEPA.arete;
                ctx.fillRect(x0, r.y, r.l, 2);
                ctx.fillStyle = SEPA.joint;
                if (i) ctx.fillRect(x0, r.y, 1, r.h);
                dessinerPanneau(panneaux[i], x0, r.y, r.l, r.h);
            }
        }

        /* Contenu d'un panneau : logo blanc centré, sinon le nom en blanc. */
        function dessinerPanneau(contenu, x, y, l, h) {
            if (!contenu) return;
            var mx = l * SEPA.marge, my = h * SEPA.marge;
            var bw = l - 2 * mx, bh = h - 2 * my;
            if (contenu.blanc) {
                var c = contenu.blanc, r = Math.min(bw / c.largeur, bh / c.hauteur);
                var w = c.largeur * r, hh = c.hauteur * r;
                ctx.drawImage(c.canvas, x + (l - w) / 2, y + (h - hh) / 2, w, hh);
            } else if (contenu.nom) {
                ctx.save();
                ctx.fillStyle = '#fff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                var taille = bh * 0.62;
                ctx.font = '800 ' + taille.toFixed(1) + 'px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
                var mesure = ctx.measureText(contenu.nom).width;
                if (mesure > bw) {
                    taille *= bw / mesure;
                    ctx.font = '800 ' + taille.toFixed(1) + 'px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
                }
                ctx.fillText(contenu.nom, x + l / 2, y + h / 2 + 1);
                ctx.restore();
            }
        }

        function dessinerTable() {
            var prim = couleurs.primaire || '#2b7cb5';
            var x1 = TABLE.x, x2 = TABLE.x + TABLE.w, y1 = TABLE.y, y2 = TABLE.y + TABLE.h;
            var hg = proj(x1, y1, 0), hd = proj(x2, y1, 0), bd = proj(x2, y2, 0), bg = proj(x1, y2, 0);
            var ep = 9 * bg.s; // épaisseur du plateau, vue sur le bord avant
            // ombre portée de la table
            ctx.fillStyle = 'rgba(15,20,26,0.16)';
            chemin([{ x: hg.x + 5, y: hg.y + 6 }, { x: hd.x + 5, y: hd.y + 6 }, { x: bd.x + 7, y: bd.y + ep + 8 }, { x: bg.x + 7, y: bg.y + ep + 8 }]);
            ctx.fill();
            // bord avant
            ctx.fillStyle = teinte(prim, -0.38);
            chemin([bg, bd, { x: bd.x, y: bd.y + ep }, { x: bg.x, y: bg.y + ep }]);
            ctx.fill();
            var g = ctx.createLinearGradient(0, hg.y, 0, bg.y);
            g.addColorStop(0, teinte(prim, -0.12));
            g.addColorStop(0.5, prim);
            g.addColorStop(1, teinte(prim, -0.12));
            ctx.fillStyle = g;
            chemin([hg, hd, bd, bg]);
            ctx.fill();
            // lignes blanches
            var i = 2;
            ctx.strokeStyle = '#fff';
            ctx.lineJoin = 'round';
            ctx.lineWidth = 3.4;
            chemin([proj(x1 + i, y1 + i, 0), proj(x2 - i, y1 + i, 0), proj(x2 - i, y2 - i, 0), proj(x1 + i, y2 - i, 0)]);
            ctx.stroke();
            ctx.lineWidth = 1.5;
            var c1 = proj(W / 2, y1 + 4, 0), c2 = proj(W / 2, y2 - 4, 0);
            ctx.beginPath();
            ctx.moveTo(c1.x, c1.y);
            ctx.lineTo(c2.x, c2.y);
            ctx.stroke();
        }

        /* Filet : toile tendue entre deux poteaux, au milieu de la table. */
        function dessinerFilet() {
            var yf = TABLE.y + TABLE.h / 2, xa = TABLE.x - 10, xb = TABLE.x + TABLE.w + 10;
            var a = proj(xa, yf, 0), b = proj(xb, yf, 0), ah = proj(xa, yf, FILET), bh = proj(xb, yf, FILET);
            ctx.fillStyle = 'rgba(15,20,26,0.18)';
            ctx.fillRect(a.x, a.y, b.x - a.x, 5 * a.s);
            ctx.fillStyle = 'rgba(35,52,74,0.55)';
            ctx.fillRect(ah.x, ah.y, bh.x - ah.x, a.y - ah.y);
            ctx.fillStyle = '#fff';
            ctx.fillRect(ah.x, ah.y - 1, bh.x - ah.x, 2.2 * a.s);
            ctx.fillStyle = '#23344a';
            [[xa - 4, ah], [xb + 4, bh]].forEach(function (p) {
                var pied = proj(p[0], yf, 0), tete = proj(p[0], yf, FILET + 2);
                ctx.fillRect(pied.x - 3 * pied.s, tete.y, 6 * pied.s, pied.y - tete.y + 2);
            });
        }

        /* Joueur dans sa pose (coup droit ou revers juste après une frappe),
         * ombre au sol, penché dans le sens du déplacement (pivot aux pieds),
         * tête détourée posée par-dessus. Raquette sur la ligne de frappe,
         * taille selon la profondeur. */
        function dessinerFigure(s, fig, cadre) {
            if (!s) return;
            var p = proj(fig.x, cadre.hit, 0);
            var k = s.hauteur * p.s / SVG.h;
            var fw = SVG.l * k, fh = s.hauteur * p.s;
            var top = p.y - SVG.yRaquette * k;
            var fx = p.x - SVG.l / 2 * k;
            var pied = top + SVG.pied * k;
            var corps = s.corps[fig.tCoup > 0 && fig.coup ? fig.coup : 'attente'];
            ctx.save();
            ctx.fillStyle = 'rgba(15,20,26,0.16)';
            ctx.beginPath();
            ctx.ellipse(p.x, pied + 2 * k, 58 * k, 8 * k, 0, 0, Math.PI * 2);
            ctx.fill();
            var cote = fig === figJ ? 'joueur' : 'adversaire';
            if (modeArcade() && arcade.jauge[cote] >= 1) {
                // Halo pulsant : coup spécial disponible.
                var pouls = 0.5 + 0.5 * Math.sin(performance.now() / 140);
                ctx.fillStyle = 'rgba(255,214,0,' + (0.25 + 0.3 * pouls).toFixed(3) + ')';
                ctx.beginPath();
                ctx.ellipse(p.x, pied, (62 + 8 * pouls) * k, (13 + 3 * pouls) * k, 0, 0, Math.PI * 2);
                ctx.fill();
            }
            ctx.translate(p.x, pied);
            var titube = fig.sonne > 0 ? Math.sin(fig.sonne * 40) * 12 * fig.sonne / SONNE : 0;
            ctx.rotate((clamp((fig.vx || 0) / 700, -1, 1) * 10 + titube) * Math.PI / 180);
            ctx.translate(-p.x, -pied);
            ctx.save();
            ctx.shadowColor = 'rgba(15,20,26,0.22)';
            ctx.shadowBlur = 8 * echelle * dpr;
            ctx.shadowOffsetY = 3 * echelle * dpr;
            if (corps) ctx.drawImage(corps, fx, top, fw, fh);
            ctx.restore();
            if (s.tete) {
                var tw = s.tete.width / (echelle * dpr), th = s.tete.height / (echelle * dpr);
                ctx.save();
                ctx.translate(p.x, top + SVG.yTete * k);
                ctx.rotate(inclinaison(s.cle) * Math.PI / 180);
                ctx.shadowColor = 'rgba(15,20,26,0.35)';
                ctx.shadowBlur = 4 * echelle * dpr;
                ctx.shadowOffsetY = 2 * echelle * dpr;
                ctx.drawImage(s.tete, -tw / 2, -th / 2, tw, th);
                ctx.restore();
            }
            if (fig.gele > 0) dessinerGlacon(p.x, top + 4 * k, 128 * k, pied - top, Math.min(1, fig.gele / 0.12));
            ctx.restore();
        }

        /* Glaçon autour d'un joueur figé : bloc translucide, arêtes et reflets
         * blancs ; opacite fond le bloc dans les derniers instants. */
        function dessinerGlacon(cx, top, l, h, opacite) {
            var x = cx - l / 2;
            ctx.save();
            ctx.globalAlpha = opacite;
            ctx.fillStyle = 'rgba(170,225,255,0.5)';
            ctx.strokeStyle = 'rgba(255,255,255,0.9)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(x, top, l, h, 8);
            else ctx.rect(x, top, l, h);
            ctx.fill();
            ctx.stroke();
            ctx.strokeStyle = 'rgba(255,255,255,0.7)';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(x + l * 0.18, top + h * 0.12);
            ctx.lineTo(x + l * 0.08, top + h * 0.42);
            ctx.moveTo(x + l * 0.3, top + h * 0.1);
            ctx.lineTo(x + l * 0.24, top + h * 0.26);
            ctx.moveTo(x + l * 0.86, top + h * 0.62);
            ctx.lineTo(x + l * 0.78, top + h * 0.88);
            ctx.stroke();
            ctx.restore();
        }

        function hauteurBalle() {
            if (phase === 'service' && lancer !== null) {
                var l = clamp(lancer / LANCER, 0, 1);
                return 10 + HAUTEUR_LANCER * 4 * l * (1 - l);
            }
            if (phase !== 'jeu') return 10;
            var total = Math.abs(HIT_J - HIT_A);
            var u = clamp(Math.abs(balle.y - balle.y0) / total, 0, 1.2);
            var ub = 0.72;
            return u < ub ? 4 * (u / ub) * (1 - u / ub) * 26 : ((u - ub) / (1 - ub)) * 16;
        }

        function dessinerBalle() {
            if (!balle.visible) return;
            var z = hauteurBalle();
            // Sillage visible seulement quand la balle a de l'effet.
            // Blanc pour l'effet latéral, orangé pour le lifté, bleuté pour le coupé.
            // Traînée aussi à grande vitesse (au-delà de 75 % de la vitesse maximale).
            var vitesse = clamp((balle.v / NIVEAUX[etat.niveau].vMax - 0.75) / 0.25, 0, 1);
            var effet = Math.max(Math.abs(balle.ax) / EFFET_MAX, Math.abs(balle.rot), vitesse * 0.8);
            var teinteSillage = balle.rot > 0.15 ? '255,120,40' : balle.rot < -0.15 ? '90,170,255' : '255,255,255';
            if (balle.special) {
                teinteSillage = balle.special.couleur;
                effet = 1;
            }
            var cachee = balleCachee();
            if (phase === 'jeu' && effet > 0.15 && !cachee) {
                balle.trace.forEach(function (t, i) {
                    var q = proj(t.x, t.y, t.z);
                    ctx.fillStyle = 'rgba(' + teinteSillage + ',' + (Math.min(1, effet) * 0.35 * (1 - i / balle.trace.length)).toFixed(3) + ')';
                    ctx.beginPath();
                    ctx.arc(q.x, q.y, R * q.s * (1 - i / 10), 0, Math.PI * 2);
                    ctx.fill();
                });
            }
            var sol = proj(balle.x, balle.y, 0), p = proj(balle.x, balle.y, z);
            ctx.fillStyle = 'rgba(15,20,26,' + (0.28 - z / 200).toFixed(3) + ')';
            ctx.beginPath();
            ctx.ellipse(sol.x + z * 0.15 * sol.s, sol.y + z * 0.05 * sol.s, R * 0.95 * sol.s, R * 0.6 * sol.s, 0, 0, Math.PI * 2);
            ctx.fill();
            if (cachee) {
                // Seule l'ombre trahit la balle fantôme.
                ctx.fillStyle = 'rgba(255,255,255,0.1)';
                ctx.beginPath();
                ctx.arc(p.x, p.y, R * p.s, 0, Math.PI * 2);
                ctx.fill();
                return;
            }
            var r = R * p.s * (1 + z / 90);
            var g = ctx.createRadialGradient(p.x - r * 0.35, p.y - r * 0.35, r * 0.1, p.x, p.y, r);
            g.addColorStop(0, '#ffffff');
            g.addColorStop(1, '#f2a24a');
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(p.x, p.y, r, 0, Math.PI * 2);
            ctx.fill();
        }

        /* Cut-in du coup spécial : bandeau en biais aux couleurs du club (ou
         * de l'adversaire), joueur en coup droit, nom du coup en grand. */
        function dessinerCutIn() {
            var c = arcade.cutin;
            if (!c || arcade.gel <= 0) return;
            var entree = mouvementReduit ? 1 : clamp(c.t / 0.14, 0, 1);
            var couleur = c.cote === 'joueur' ? couleurs.primaire || '#2b7cb5' : couleurs.secondaire || '#d34328';
            ctx.save();
            ctx.fillStyle = 'rgba(15,20,26,' + (0.35 * entree).toFixed(3) + ')';
            ctx.fillRect(-20, -20, W + 40, H + 40);
            if (!mouvementReduit && c.t < 0.08) {
                ctx.fillStyle = 'rgba(255,255,255,' + (0.8 * (1 - c.t / 0.08)).toFixed(3) + ')';
                ctx.fillRect(-20, -20, W + 40, H + 40);
            }
            ctx.translate(W / 2 + (1 - entree) * W * (c.cote === 'joueur' ? -1 : 1), H / 2);
            ctx.rotate(-0.18);
            ctx.fillStyle = couleur;
            ctx.fillRect(-W, -62, W * 2, 124);
            ctx.fillStyle = 'rgba(255,255,255,0.9)';
            ctx.fillRect(-W, -62, W * 2, 5);
            ctx.fillRect(-W, 57, W * 2, 5);
            // Picto du coup en filigrane, en bout de bandeau.
            ctx.fillStyle = ctx.strokeStyle = 'rgba(255,255,255,0.28)';
            dessinerPictoCoup(ctx, c.coup, 140, 0, 96);
            var s = sprites[c.cote];
            if (s && s.corps.cd) {
                var h = 150, k = h / SVG.h, w = SVG.l * k, x = -100, top = -88;
                ctx.drawImage(s.corps.cd, x - w / 2, top, w, h);
                if (s.photo) {
                    var hTete = SVG.tete * k;
                    if (!s.teteCutin || s.teteCutinEchelle !== echelle) {
                        s.teteCutin = sticker(s.photo, hTete * echelle * dpr, 6 * echelle * dpr);
                        s.teteCutinEchelle = echelle;
                    }
                    var tw = s.teteCutin.width / (echelle * dpr), th = s.teteCutin.height / (echelle * dpr);
                    ctx.drawImage(s.teteCutin, x - tw / 2, top + SVG.yTete * k - th / 2, tw, th);
                }
            }
            ctx.fillStyle = '#fff';
            ctx.strokeStyle = 'rgba(15,20,26,0.35)';
            ctx.lineJoin = 'round';
            ctx.textBaseline = 'middle';
            ctx.font = '800 12px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
            ctx.fillText(nomAffiche(c.cote === 'joueur' ? etat.joueur : adv).toUpperCase(), -8, -24);
            var texte = c.coup.nom.toUpperCase() + ' !', taille = 30;
            ctx.font = 'italic 900 ' + taille + 'px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
            var largeur = ctx.measureText(texte).width;
            if (largeur > 185) {
                taille *= 185 / largeur;
                ctx.font = 'italic 900 ' + taille.toFixed(1) + 'px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
            }
            ctx.lineWidth = 6;
            ctx.strokeText(texte, -8, 8);
            ctx.fillText(texte, -8, 8);
            ctx.restore();
        }

        function dessinerToast() {
            if (toast.t <= 0 || !toast.texte) return;
            ctx.save();
            ctx.globalAlpha = clamp(toast.t * 2, 0, 1);
            ctx.font = '800 17px system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
            var w = ctx.measureText(toast.texte).width + 28;
            var y = TABLE.y + TABLE.h * 0.68;
            ctx.fillStyle = alpha('#0f141a', 0.72);
            ctx.beginPath();
            if (ctx.roundRect) ctx.roundRect(W / 2 - w / 2, y - 17, w, 34, 17);
            else ctx.rect(W / 2 - w / 2, y - 17, w, 34);
            ctx.fill();
            ctx.fillStyle = '#fff';
            ctx.textAlign = 'center';
            ctx.textBaseline = 'middle';
            ctx.fillText(toast.texte, W / 2, y + 1);
            ctx.restore();
        }

        /* Ordre du peintre, du fond vers la caméra. La balle passe toujours
         * devant les joueurs : on doit la voir arriver sur la raquette. */
        function rendu() {
            var s = echelle * dpr;
            ctx.setTransform(s, 0, 0, s, 0, 0);
            if (secousse.t > 0) {
                var k = secousse.force * secousse.t / secousse.duree;
                ctx.translate((Math.random() * 2 - 1) * k, (Math.random() * 2 - 1) * k);
            }
            var filet = TABLE.y + TABLE.h / 2;
            dessinerFond();
            dessinerSeparations();
            dessinerFigure(sprites.adversaire, figA, FIG_A);
            dessinerTable();
            if (balle.y < filet) dessinerBalle();
            dessinerFilet();
            dessinerFigure(sprites.joueur, figJ, FIG_J);
            if (balle.y >= filet) dessinerBalle();
            dessinerEffets();
            dessinerToast();
            dessinerCutIn();
        }

        function boucle(t) {
            var dt = Math.min((t - dernier) / 1000, 1 / 30);
            dernier = t;
            if (arcade.gel > 0 && !enPause) {
                arcade.gel -= dt;
                arcade.cutin.t += dt;
            } else if (!document.hidden && !enPause) step(dt * vitesseTemps());
            rendu();
            raf = phase === 'arret' || !root.isConnected ? 0 : requestAnimationFrame(boucle);
        }
    }

    function demarrerTout() {
        document.querySelectorAll('.monclubtt-pong').forEach(init);
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', demarrerTout);
    } else {
        demarrerTout();
    }
}());
