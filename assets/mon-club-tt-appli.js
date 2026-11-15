/*
 * Mon Club TT — bandeau « ajoute l'équipe à ton écran d'accueil ».
 *
 * - Android (Chrome, Edge, Samsung Internet…) : bouton « Installer » qui ouvre
 *   la fenêtre native (événement beforeinstallprompt). Sans cet événement
 *   (appli déjà installée, navigateur qui ne le propose pas), pas de bandeau.
 * - Firefox Android et iPhone/iPad : mode d'emploi en deux étapes.
 * - Jamais sur ordinateur, ni dans l'appli elle-même, ni dans le navigateur
 *   intégré de Facebook/Instagram (qui ne permet pas l'ajout).
 * - Fermé : plus affiché pendant 30 jours pour cette équipe.
 *
 * Configuration (PHP) : MonClubTTAppli = { cle, nom, equipe, icone, stable }.
 */
(function (w, d) {
    'use strict';

    var cfg = w.MonClubTTAppli;
    if (!cfg || !cfg.cle) return;

    var JOUR = 24 * 60 * 60 * 1000;
    var CLE_FERME = 'monclubtt_appli_ferme_' + cfg.cle;
    var DELAI_AFFICHAGE = 2500;

    var ua = navigator.userAgent || '';
    var ios = /iphone|ipad|ipod/i.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var android = /android/i.test(ua);
    var firefoxAndroid = android && /firefox\//i.test(ua);
    var navigateurIntegre = /FBAN|FBAV|FB_IAB|Instagram|Line\/|Snapchat|TikTok/i.test(ua);
    var autonome = (w.matchMedia && w.matchMedia('(display-mode: standalone)').matches) || navigator.standalone === true;

    // ─── Mémoire du bandeau fermé (stockage indisponible : on ne retient rien) ───
    function lire(cle) {
        try { return w.localStorage.getItem(cle); } catch (e) { return null; }
    }
    function ecrire(cle, valeur) {
        try { w.localStorage.setItem(cle, valeur); } catch (e) { /* navigation privée */ }
    }
    function fermeRecemment() {
        var jusqua = parseInt(lire(CLE_FERME), 10);
        return !isNaN(jusqua) && jusqua > Date.now();
    }
    function retenirFermeture(jours) {
        ecrire(CLE_FERME, String(Date.now() + jours * JOUR));
    }

    if (autonome || navigateurIntegre || !(ios || android)) return;

    // Sur iPhone, l'icône garde l'adresse affichée au moment de l'ajout :
    // l'adresse stable de l'équipe reste valable après le changement de phase.
    if (ios && cfg.stable && w.history && w.history.replaceState) {
        try {
            var stable = new URL(cfg.stable, w.location.href);
            if (stable.origin === w.location.origin) {
                w.history.replaceState(w.history.state, '', stable.pathname + stable.search + w.location.hash);
            }
        } catch (e) { /* URL non gérée : on garde l'adresse de la page */ }
    }

    if (fermeRecemment()) return;


    // ─── Construction ──────────────────────────────────────────────────────────
    var ICONE_PARTAGE = '<svg class="monclubtt-appli-picto" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        + '<path d="M12 3v12M7.5 7.5 12 3l4.5 4.5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        + '<path d="M8 10H6a1 1 0 0 0-1 1v9a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-9a1 1 0 0 0-1-1h-2" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';
    var ICONE_AJOUT = '<svg class="monclubtt-appli-picto" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        + '<rect x="4" y="4" width="16" height="16" rx="4" fill="none" stroke="currentColor" stroke-width="2"/>'
        + '<path d="M12 8v8M8 12h8" stroke="currentColor" stroke-width="2" stroke-linecap="round"/></svg>';

    function echapper(texte) {
        var el = d.createElement('span');
        el.textContent = String(texte);
        return el.innerHTML;
    }

    var bandeau = null;
    var invite = null; // beforeinstallprompt mis de côté

    function etapes() {
        if (ios) {
            return '<ol class="monclubtt-appli-etapes">'
                + '<li>Touche ' + ICONE_PARTAGE + ' <strong>Partager</strong> <span class="monclubtt-appli-aide">(dans le menu ⋯ si tu ne le vois pas)</span></li>'
                + '<li>Choisis ' + ICONE_AJOUT + ' <strong>Sur l’écran d’accueil</strong></li>'
                + '</ol>';
        }
        return '<ol class="monclubtt-appli-etapes">'
            + '<li>Ouvre le menu <strong>⋮</strong> du navigateur</li>'
            + '<li>Choisis ' + ICONE_AJOUT + ' <strong>Ajouter à l’écran d’accueil</strong> <span class="monclubtt-appli-aide">(ou « Installer »)</span></li>'
            + '</ol>';
    }

    function construire(avecBouton) {
        var nom = echapper(cfg.nom);
        var accroche = cfg.equipe
            ? 'Suis <strong>' + nom + '</strong> en un geste'
            : 'Garde les résultats de <strong>' + nom + '</strong> sous la main';
        var detail = cfg.equipe
            ? 'Ajoute l’équipe à ton écran d’accueil : résultats et classement à portée de pouce.'
            : 'Ajoute cette page à ton écran d’accueil pour la retrouver en un geste.';

        var el = d.createElement('aside');
        el.className = 'monclubtt-appli';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-label', 'Ajouter à l’écran d’accueil');
        el.innerHTML = ''
            + '<div class="monclubtt-appli-texte">'
            +   '<p class="monclubtt-appli-accroche">' + accroche + '</p>'
            +   (avecBouton ? '<p class="monclubtt-appli-detail">' + detail + '</p>' : etapes())
            + '</div>'
            + (avecBouton ? '<button type="button" class="monclubtt-appli-installer">Installer</button>' : '')
            + '<button type="button" class="monclubtt-appli-fermer" aria-label="Fermer">×</button>';

        if (cfg.icone) {
            var icone = d.createElement('img');
            icone.className = 'monclubtt-appli-icone';
            icone.src = cfg.icone;
            icone.alt = '';
            icone.width = 48;
            icone.height = 48;
            el.insertBefore(icone, el.firstChild);
        }

        el.querySelector('.monclubtt-appli-fermer').addEventListener('click', function () {
            retenirFermeture(30);
            masquer();
        });
        var bouton = el.querySelector('.monclubtt-appli-installer');
        if (bouton) {
            bouton.addEventListener('click', installer);
        }
        return el;
    }

    function afficher(avecBouton) {
        if (bandeau) return;
        bandeau = construire(avecBouton);
        d.body.appendChild(bandeau);
        // Laisse la fin de la page lisible au-dessus du bandeau.
        d.body.style.paddingBottom = bandeau.offsetHeight + 'px';
        w.requestAnimationFrame(function () {
            if (bandeau) bandeau.classList.add('monclubtt-appli-visible');
        });
    }

    function masquer() {
        if (!bandeau) return;
        var el = bandeau;
        bandeau = null;
        d.body.style.paddingBottom = '';
        el.classList.remove('monclubtt-appli-visible');
        w.setTimeout(function () {
            if (el.parentNode) el.parentNode.removeChild(el);
        }, 300);
    }

    function installer() {
        if (!invite) return;
        var e = invite;
        invite = null;
        e.prompt();
        if (e.userChoice && e.userChoice.then) {
            e.userChoice.then(function (choix) {
                if (choix && choix.outcome === 'dismissed') retenirFermeture(30);
            });
        }
        masquer();
    }

    function plusTard(fn) {
        var attente = DELAI_AFFICHAGE - (Date.now() - debut);
        w.setTimeout(fn, Math.max(0, attente));
    }
    var debut = Date.now();

    if (ios || firefoxAndroid) {
        plusTard(function () { afficher(false); });
        return;
    }

    // Android, navigateurs Chromium : seulement si le navigateur propose l'installation.
    w.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        invite = e;
        plusTard(function () { afficher(true); });
    });
    w.addEventListener('appinstalled', function () {
        retenirFermeture(365);
        masquer();
    });
})(window, document);
