/* ===================== PONG DU CLUB (shortcode [monclubtt_pong]) =====================
 * Jeu de ping vu du dessus, rendu dans un <canvas> : le joueur choisit sa tête
 * parmi les licenciés du club, puis affronte un « top 10 mondial ». La raquette
 * est le personnage du podium (corps dessiné au maillot du club + photo
 * détourée). Score sur un marqueur à fiches papier, règles du ping : manches
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
    var W = 360, H = 600;
    var TABLE = { x: 34, y: 92, w: 292, h: 412 };
    var R = 6;                                   // rayon de la balle
    var FIG_J = { h: 104, top: 490 };            // personnage du joueur (bas)
    var FIG_A = { h: 88, top: 4 };               // adversaire (haut)
    var HIT_J = 500, HIT_A = 96;                 // lignes de frappe
    var DEMI_RAQ = 38, DECALAGE_RAQ = 8;         // zone de frappe autour du corps
    var BORD_GRILLE = 6;                         // liseré blanc (px) des têtes de la sélection
    var TETE_GRILLE = 88;                        // hauteur (px CSS) des têtes de la sélection

    var NIVEAUX = {
        facile:  { libelle: 'Facile',         vIa: 190, erreur: 46, reaction: 0.5,  v0: 230, vMax: 520 },
        normal:  { libelle: 'Normal',         vIa: 280, erreur: 24, reaction: 0.66, v0: 260, vMax: 600 },
        mondial: { libelle: 'Top 10 mondial', vIa: 380, erreur: 10, reaction: 1,    v0: 290, vMax: 680 }
    };

    /* ---------------------------------------------------------- utilitaires */

    function el(tag, classe, texte) {
        var e = document.createElement(tag);
        if (classe) e.className = classe;
        if (texte !== undefined) e.textContent = texte;
        return e;
    }

    function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }

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
            self.el.appendChild(camp);
            self.camps[cote] = { el: camp, libelle: libelle, manches: manches, points: points };
        });
        this.annonce = el('div', 'pong-sr');
        this.annonce.setAttribute('aria-live', 'polite');
        this.el.appendChild(this.annonce);
    }
    Marqueur.prototype.noms = function (j, a) {
        this.camps.joueur.libelle.textContent = j;
        this.camps.adversaire.libelle.textContent = a;
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
            ', manches ' + m.manches.joueur + ' à ' + m.manches.adversaire;
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
        var adv = cfg.adversaire || { nom: 'Top 10 mondial', prenom: '', sex: 'M', photo: '' };
        var nbManches = [1, 3, 5].indexOf(cfg.manches) >= 0 ? cfg.manches : 3;
        var tactile = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;

        var etat = {
            niveau: 'normal',
            son: true,
            joueur: null
        };

        /* ---- Écran de sélection ---- */
        var ecranSel = el('div', 'pong-ecran pong-selection');
        var titre = el('h3', 'pong-titre', 'Choisis ton joueur');
        ecranSel.appendChild(titre);

        var versus = el('div', 'pong-versus');
        var versusTete = el('div', 'pong-versus-tete');
        var versusTexte = el('div', 'pong-versus-texte');
        versusTexte.appendChild(el('span', 'pong-versus-label', 'Ton adversaire'));
        versusTexte.appendChild(el('strong', 'pong-versus-nom', nomAffiche(adv)));
        if (adv.titre) versusTexte.appendChild(el('span', 'pong-versus-titre', adv.titre));
        versus.appendChild(versusTete);
        versus.appendChild(versusTexte);
        ecranSel.appendChild(versus);

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
        ecranSel.appendChild(niveaux);

        var recherche = null;
        if (joueurs.length > 12) {
            recherche = el('input', 'pong-recherche');
            recherche.type = 'search';
            recherche.placeholder = 'Rechercher un joueur…';
            recherche.setAttribute('aria-label', 'Rechercher un joueur');
            ecranSel.appendChild(recherche);
        }

        var grille = el('div', 'pong-grille');
        ecranSel.appendChild(grille);
        if (!joueurs.length) {
            grille.appendChild(el('p', 'pong-vide', 'Aucun joueur à afficher pour le moment.'));
        }

        var dpr = Math.min(window.devicePixelRatio || 1, 3);
        joueurs.forEach(function (p, i) {
            var carte = el('button', 'pong-carte');
            carte.type = 'button';
            carte.dataset.index = i;
            carte.dataset.recherche = (p.nom + ' ' + p.prenom).toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '');
            var tete = el('span', 'pong-carte-tete');
            carte.appendChild(tete);
            carte.appendChild(el('span', 'pong-carte-nom', String(p.nom).toUpperCase()));
            carte.appendChild(el('span', 'pong-carte-prenom', p.prenom));
            if (p.pts) carte.appendChild(el('span', 'pong-carte-pts', Math.round(p.pts) + ' pts'));
            grille.appendChild(carte);
            teteJoueur(p, couleurs.maillot).then(function (t) { poserTete(tete, t, p.photo || 'avatar-' + i); });
        });

        function poserTete(conteneur, t, cle) {
            if (!t) return;
            var c = sticker(t.img, TETE_GRILLE * (t.photo ? 1 : 0.8) * dpr, BORD_GRILLE * dpr);
            c.style.width = (c.width / dpr) + 'px';
            c.style.height = (c.height / dpr) + 'px';
            c.style.transform = 'rotate(' + inclinaison(cle).toFixed(1) + 'deg)';
            conteneur.appendChild(c);
        }

        teteJoueur(adv, couleurs.secondaire).then(function (t) { poserTete(versusTete, t, adv.photo || 'avatar-adv'); });

        if (recherche) {
            recherche.addEventListener('input', function () {
                var q = recherche.value.toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
                grille.querySelectorAll('.pong-carte').forEach(function (c) {
                    c.hidden = q !== '' && c.dataset.recherche.indexOf(q) < 0;
                });
            });
        }

        grille.addEventListener('click', function (e) {
            var carte = e.target.closest('.pong-carte');
            if (!carte) return;
            demarrer(joueurs[+carte.dataset.index]);
        });

        /* ---- Écran de jeu ---- */
        var ecranJeu = el('div', 'pong-ecran pong-jeu');
        ecranJeu.hidden = true;
        var marqueur = new Marqueur();
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

        var actions = el('div', 'pong-actions');
        var btnChanger = el('button', 'pong-lien', '← Changer de joueur');
        btnChanger.type = 'button';
        var btnSon = el('button', 'pong-lien', 'Son : oui');
        btnSon.type = 'button';
        btnSon.setAttribute('aria-pressed', 'true');
        actions.appendChild(btnChanger);
        actions.appendChild(btnSon);
        ecranJeu.appendChild(actions);

        root.appendChild(ecranSel);
        root.appendChild(ecranJeu);
        root.classList.add('pong-pret');
        if (!root.hasAttribute('tabindex')) root.tabIndex = -1;

        btnSon.addEventListener('click', function () {
            etat.son = !etat.son;
            btnSon.textContent = 'Son : ' + (etat.son ? 'oui' : 'non');
            btnSon.setAttribute('aria-pressed', String(etat.son));
        });
        btnChanger.addEventListener('click', retourSelection);

        var ctx = canvas.getContext('2d');
        var echelle = 1;
        var sprites = { joueur: null, adversaire: null };
        var figJ = { x: W / 2, vx: 0 }, figA = { x: W / 2 };
        var balle = { x: W / 2, y: H / 2, vx: 0, vy: 0, v: 0, y0: 0, rebond: false, visible: false };
        var match = null;
        var phase = 'arret'; // arret | service | jeu | pause | message
        var minuterie = 0;
        var cible = null, touches = { g: false, d: false };
        var ia = { cible: W / 2, erreur: 0, vise: 0 };
        var raf = 0, dernier = 0;
        var toast = { texte: '', t: 0 };

        /* Personnage : corps de l'avatar (sans tête si photo) + tête détourée. */
        function preparerSprite(p, maillot, hauteur) {
            return chargerImage(p.photo).then(function (photo) {
                return chargerImage(urlAvatar(p.sex, !photo, maillot)).then(function (corps) {
                    return { p: p, corps: corps, photo: photo, hauteur: hauteur, tete: null, cle: p.photo || 'avatar' };
                });
            });
        }

        function construireTetes() {
            ['joueur', 'adversaire'].forEach(function (cote) {
                var s = sprites[cote];
                if (!s || !s.photo) return;
                var k = s.hauteur / 168;
                var hTete = 72 * k * echelle * dpr;
                s.tete = sticker(s.photo, hTete, 6 * hTete / TETE_GRILLE);
            });
        }

        function dimensionner() {
            if (ecranJeu.hidden) return;
            var largeurDispo = root.clientWidth;
            var hauteurDispo = window.innerHeight - marqueur.el.offsetHeight - actions.offsetHeight - 32;
            var largeur = Math.max(220, Math.min(largeurDispo, 520, hauteurDispo * W / H));
            canvas.style.width = largeur + 'px';
            canvas.style.height = (largeur * H / W) + 'px';
            scene.style.width = largeur + 'px';
            canvas.width = Math.round(largeur * dpr);
            canvas.height = Math.round(largeur * H / W * dpr);
            echelle = largeur / W;
            construireTetes();
        }

        function demarrer(p) {
            etat.joueur = p;
            ecranSel.hidden = true;
            ecranJeu.hidden = false;
            marqueur.noms(nomAffiche(p), nomAffiche(adv));
            Promise.all([
                preparerSprite(p, couleurs.maillot, FIG_J.h),
                preparerSprite(adv, couleurs.secondaire, FIG_A.h)
            ]).then(function (s) {
                sprites.joueur = s[0];
                sprites.adversaire = s[1];
                dimensionner();
                nouveauMatch();
                ecranJeu.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
                root.focus({ preventScroll: true });
            });
        }

        function retourSelection() {
            cancelAnimationFrame(raf);
            raf = 0;
            phase = 'arret';
            ecranJeu.hidden = true;
            ecranSel.hidden = false;
        }

        function nouveauMatch() {
            match = new Match(nbManches);
            marqueur.maj(match);
            figJ.x = W / 2; figA.x = W / 2;
            masquerMessage();
            preparerService();
            if (!raf) { dernier = performance.now(); raf = requestAnimationFrame(boucle); }
        }

        function preparerService() {
            phase = 'service';
            balle.visible = true;
            balle.vx = balle.vy = 0;
            minuterie = match.serveur() === 'adversaire' ? 0.9 : 0;
            if (match.serveur() === 'joueur') {
                montrerToast(tactile ? 'Touchez pour servir' : 'Espace ou clic pour servir', 2.2);
            }
        }

        function servir(cote) {
            var n = NIVEAUX[etat.niveau];
            var angle = (Math.random() * 0.7 - 0.35);
            balle.v = n.v0;
            balle.vx = Math.sin(angle) * balle.v;
            balle.vy = (cote === 'joueur' ? -1 : 1) * Math.cos(angle) * balle.v;
            balle.y0 = balle.y;
            balle.rebond = false;
            phase = 'jeu';
            toast.t = 0;
            tock(620, etat.son);
            if (cote === 'joueur') viserIa();
        }

        function frapper(fig, sens) {
            var n = NIVEAUX[etat.niveau];
            var rel = clamp((balle.x - (fig.x + DECALAGE_RAQ)) / DEMI_RAQ, -1, 1);
            balle.v = Math.min(n.vMax, balle.v * 1.045);
            var angle = rel * 1.0;
            balle.vx = Math.sin(angle) * balle.v + (fig.vx || 0) * 0.15;
            balle.vy = sens * Math.cos(angle) * balle.v;
            balle.y0 = balle.y;
            balle.rebond = false;
            tock(sens < 0 ? 560 : 700, etat.son);
        }

        /* Où la balle croisera la ligne yCible, rebonds sur les côtés compris. */
        function predire(yCible) {
            if (!balle.vy) return balle.x;
            var t = (yCible - balle.y) / balle.vy;
            var x = balle.x + balle.vx * t;
            var L = W - 2 * R;
            var u = (((x - R) % (2 * L)) + 2 * L) % (2 * L);
            return R + (u > L ? 2 * L - u : u);
        }

        function viserIa() {
            var n = NIVEAUX[etat.niveau];
            ia.erreur = (Math.random() * 2 - 1) * n.erreur;
            ia.vise = (Math.random() * 2 - 1) * DEMI_RAQ * 0.7;
        }

        function marquer(cote) {
            balle.visible = false;
            var resultat = match.marquer(cote);
            marqueur.maj(match);
            tock(cote === 'joueur' ? 880 : 300, etat.son);
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
                afficherMessage(victoire ? 'Victoire !' : 'Défaite…',
                    (victoire ? 'Bravo ' + (etat.joueur.prenom || etat.joueur.nom) + ', ' : '') +
                    'manches ' + match.manches.joueur + '–' + match.manches.adversaire,
                    [
                        { texte: 'Rejouer', action: nouveauMatch },
                        { texte: 'Changer de joueur', action: retourSelection, secondaire: true }
                    ]);
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
        var appui = null;
        scene.addEventListener('pointerdown', function (e) {
            if (e.target.closest('.pong-message')) return;
            appui = { x: e.clientX, y: e.clientY, t: performance.now() };
            if (e.pointerType !== 'mouse') cible = versLogique(e);
            try { scene.setPointerCapture(e.pointerId); } catch (err) { /* ignoré */ }
        });
        scene.addEventListener('pointermove', function (e) {
            if (e.pointerType === 'mouse' || appui) cible = versLogique(e);
        });
        scene.addEventListener('pointerup', function (e) {
            if (!appui) return;
            var court = performance.now() - appui.t < 350 &&
                Math.abs(e.clientX - appui.x) + Math.abs(e.clientY - appui.y) < 24;
            appui = null;
            if (court && phase === 'service' && match.serveur() === 'joueur') servir('joueur');
        });
        scene.addEventListener('pointercancel', function () { appui = null; });
        scene.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') cible = null; });

        root.addEventListener('keydown', function (e) {
            if (ecranJeu.hidden || e.target.closest('.pong-message') || e.target.tagName === 'BUTTON') return;
            var k = e.key;
            if (k === 'ArrowLeft' || k === 'a' || k === 'q') { touches.g = true; cible = null; e.preventDefault(); }
            else if (k === 'ArrowRight' || k === 'd') { touches.d = true; cible = null; e.preventDefault(); }
            else if (k === ' ' || k === 'Enter') {
                e.preventDefault();
                if (phase === 'service' && match.serveur() === 'joueur') servir('joueur');
            }
        });
        root.addEventListener('keyup', function (e) {
            if (e.key === 'ArrowLeft' || e.key === 'a' || e.key === 'q') touches.g = false;
            if (e.key === 'ArrowRight' || e.key === 'd') touches.d = false;
        });

        var redim = 0;
        window.addEventListener('resize', function () {
            clearTimeout(redim);
            redim = setTimeout(dimensionner, 120);
        });

        /* ---- Simulation ---- */
        function deplacerJoueur(dt) {
            var avant = figJ.x;
            if (touches.g || touches.d) {
                figJ.x += ((touches.d ? 1 : 0) - (touches.g ? 1 : 0)) * 420 * dt;
            } else if (cible !== null) {
                figJ.x += (cible - DECALAGE_RAQ - figJ.x) * Math.min(1, dt * 20);
            }
            figJ.x = clamp(figJ.x, 20, W - 20);
            figJ.vx = (figJ.x - avant) / Math.max(dt, 0.001);
        }

        function deplacerIa(dt) {
            var n = NIVEAUX[etat.niveau];
            var but = W / 2;
            if (phase === 'jeu' && balle.vy < 0 && balle.y < HIT_J - (HIT_J - HIT_A) * (1 - n.reaction) - 1) {
                but = predire(HIT_A + R) - DECALAGE_RAQ - ia.vise + ia.erreur;
            } else if (phase === 'jeu' && balle.vy < 0) {
                but = figA.x; // pas encore réagi
            } else if (phase === 'service' && match.serveur() === 'adversaire') {
                but = figA.x;
            }
            var pas = n.vIa * dt;
            figA.x += clamp(but - figA.x, -pas, pas);
            figA.x = clamp(figA.x, 20, W - 20);
        }

        function step(dt) {
            deplacerJoueur(dt);
            deplacerIa(dt);
            if (toast.t > 0) toast.t -= dt;

            if (phase === 'service') {
                var s = match.serveur();
                if (s === 'joueur') {
                    balle.x = figJ.x + DECALAGE_RAQ;
                    balle.y = HIT_J - R - 4;
                } else {
                    balle.x = figA.x + DECALAGE_RAQ;
                    balle.y = HIT_A + R + 4;
                    minuterie -= dt;
                    if (minuterie <= 0) servir('adversaire');
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
            balle.x += balle.vx * dt;
            balle.y += balle.vy * dt;
            if (balle.x < R) { balle.x = 2 * R - balle.x; balle.vx = Math.abs(balle.vx); }
            if (balle.x > W - R) { balle.x = 2 * (W - R) - balle.x; balle.vx = -Math.abs(balle.vx); }

            // Rebond (décoratif) sur la table, côté receveur.
            var yr = balle.y0 + (balle.vy > 0 ? 1 : -1) * Math.abs(HIT_J - HIT_A) * 0.72;
            if (!balle.rebond && (balle.vy > 0 ? balle.y >= yr : balle.y <= yr)) {
                balle.rebond = true;
                tock(1100, etat.son);
            }

            if (balle.vy > 0 && py + R <= HIT_J && balle.y + R >= HIT_J) {
                if (Math.abs(balle.x - (figJ.x + DECALAGE_RAQ)) <= DEMI_RAQ + R) {
                    balle.y = HIT_J - R;
                    frapper(figJ, -1);
                    viserIa();
                }
            } else if (balle.vy < 0 && py - R >= HIT_A && balle.y - R <= HIT_A) {
                if (Math.abs(balle.x - (figA.x + DECALAGE_RAQ)) <= DEMI_RAQ + R) {
                    balle.y = HIT_A + R;
                    frapper(figA, 1);
                }
            }
            if (balle.y - R > H) marquer('adversaire');
            else if (balle.y + R < 0) marquer('joueur');
        }

        /* ---- Rendu ---- */
        function dessinerTable() {
            var prim = couleurs.primaire || '#2b7cb5';
            ctx.fillStyle = couleurs.fond || '#f4f1ea';
            ctx.fillRect(0, 0, W, H);
            // ombre portée de la table
            ctx.fillStyle = 'rgba(15,20,26,0.16)';
            ctx.fillRect(TABLE.x + 6, TABLE.y + 8, TABLE.w, TABLE.h);
            var g = ctx.createLinearGradient(0, TABLE.y, 0, TABLE.y + TABLE.h);
            g.addColorStop(0, teinte(prim, -0.12));
            g.addColorStop(0.5, prim);
            g.addColorStop(1, teinte(prim, -0.12));
            ctx.fillStyle = g;
            ctx.fillRect(TABLE.x, TABLE.y, TABLE.w, TABLE.h);
            ctx.strokeStyle = '#fff';
            ctx.lineWidth = 4;
            ctx.strokeRect(TABLE.x + 2, TABLE.y + 2, TABLE.w - 4, TABLE.h - 4);
            ctx.lineWidth = 1.5;
            ctx.beginPath();
            ctx.moveTo(W / 2, TABLE.y + 4);
            ctx.lineTo(W / 2, TABLE.y + TABLE.h - 4);
            ctx.stroke();
            // filet
            var yf = TABLE.y + TABLE.h / 2;
            ctx.fillStyle = 'rgba(15,20,26,0.18)';
            ctx.fillRect(TABLE.x - 10, yf + 2, TABLE.w + 20, 6);
            ctx.fillStyle = 'rgba(35,52,74,0.55)';
            ctx.fillRect(TABLE.x - 10, yf - 3, TABLE.w + 20, 6);
            ctx.fillStyle = '#fff';
            ctx.fillRect(TABLE.x - 10, yf - 4, TABLE.w + 20, 2);
            ctx.fillStyle = '#23344a';
            ctx.fillRect(TABLE.x - 14, yf - 6, 6, 10);
            ctx.fillRect(TABLE.x + TABLE.w + 8, yf - 6, 6, 10);
        }

        function dessinerFigure(s, fig, top) {
            if (!s) return;
            var k = s.hauteur / 168;
            var fw = 128 * k, fh = s.hauteur;
            var fx = fig.x - fw / 2;
            ctx.save();
            ctx.shadowColor = 'rgba(15,20,26,0.25)';
            ctx.shadowBlur = 10 * echelle * dpr;
            ctx.shadowOffsetY = 4 * echelle * dpr;
            if (s.corps) ctx.drawImage(s.corps, fx, top, fw, fh);
            ctx.restore();
            if (s.tete) {
                var tw = s.tete.width / (echelle * dpr), th = s.tete.height / (echelle * dpr);
                ctx.save();
                ctx.translate(fx + 64 * k, top + 40 * k);
                ctx.rotate(inclinaison(s.cle) * Math.PI / 180);
                ctx.shadowColor = 'rgba(15,20,26,0.35)';
                ctx.shadowBlur = 4 * echelle * dpr;
                ctx.shadowOffsetY = 2 * echelle * dpr;
                ctx.drawImage(s.tete, -tw / 2, -th / 2, tw, th);
                ctx.restore();
            }
        }

        function hauteurBalle() {
            if (phase !== 'jeu') return 10;
            var total = Math.abs(HIT_J - HIT_A);
            var u = clamp(Math.abs(balle.y - balle.y0) / total, 0, 1.2);
            var ub = 0.72;
            return u < ub ? 4 * (u / ub) * (1 - u / ub) * 26 : ((u - ub) / (1 - ub)) * 16;
        }

        function dessinerBalle() {
            if (!balle.visible) return;
            var z = hauteurBalle();
            ctx.fillStyle = 'rgba(15,20,26,0.25)';
            ctx.beginPath();
            ctx.ellipse(balle.x + z * 0.35, balle.y + z * 0.2, R * 0.95, R * 0.7, 0, 0, Math.PI * 2);
            ctx.fill();
            var by = balle.y - z * 0.6, r = R * (1 + z / 90);
            var g = ctx.createRadialGradient(balle.x - r * 0.35, by - r * 0.35, r * 0.1, balle.x, by, r);
            g.addColorStop(0, '#ffffff');
            g.addColorStop(1, '#f2a24a');
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(balle.x, by, r, 0, Math.PI * 2);
            ctx.fill();
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

        function rendu() {
            var s = echelle * dpr;
            ctx.setTransform(s, 0, 0, s, 0, 0);
            dessinerTable();
            dessinerFigure(sprites.adversaire, figA, FIG_A.top);
            if (balle.y < TABLE.y + TABLE.h / 2) dessinerBalle();
            dessinerFigure(sprites.joueur, figJ, FIG_J.top);
            if (balle.y >= TABLE.y + TABLE.h / 2) dessinerBalle();
            dessinerToast();
        }

        function boucle(t) {
            var dt = Math.min((t - dernier) / 1000, 1 / 30);
            dernier = t;
            if (!document.hidden) step(dt);
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
