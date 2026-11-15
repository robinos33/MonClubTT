/* ================= PUBLIER UN ARTICLE SUR LES RÉSEAUX (éditeur) =================
 * Bouton « Publier sur les réseaux » de la boîte « Réseaux sociaux » : fenêtre
 * avec un onglet par réseau (texte modifiable, choix des photos), envoi via
 * l'API Graph de Meta côté serveur (MonClubTT_PublicationMetaWP).
 * Marche dans l'éditeur de blocs comme dans l'éditeur classique.
 */
(function () {
    'use strict';
    if (typeof MonClubTTPublication === 'undefined') return;
    var DATA = MonClubTTPublication;
    var RESEAUX = { facebook: 'Facebook', instagram: 'Instagram' };

    var dialog = null;
    var donnees = null;
    var postId = 0;

    function el(tag, attrs, enfants) {
        var e = document.createElement(tag);
        Object.keys(attrs || {}).forEach(function (k) {
            if (k === 'text') e.textContent = attrs[k];
            else if (k === 'className') e.className = attrs[k];
            else e.setAttribute(k, attrs[k]);
        });
        (enfants || []).forEach(function (c) { if (c) e.appendChild(c); });
        return e;
    }

    function post(params) {
        var fd = new FormData();
        fd.append('nonce', DATA.nonce);
        fd.append('post_id', postId);
        Object.keys(params).forEach(function (k) {
            if (Array.isArray(params[k])) params[k].forEach(function (v) { fd.append(k + '[]', v); });
            else fd.append(k, params[k]);
        });
        return fetch(DATA.ajaxurl, { method: 'POST', credentials: 'same-origin', body: fd })
            .then(function (r) { return r.json(); });
    }

    function lienReglages() {
        return el('a', { href: DATA.reglages, text: 'Réglages de la connexion Meta' });
    }

    /* Onglet d'un réseau : avertissement « déjà publié », texte, photos, envoi. */
    function panneau(reseau) {
        var p = el('div', { className: 'monclubtt-pub-panneau', role: 'tabpanel', id: 'monclubtt-pub-' + reseau, 'aria-labelledby': 'monclubtt-pub-tab-' + reseau });
        if (!donnees.reseaux[reseau]) {
            p.appendChild(el('p', { text: RESEAUX[reseau] + ' n\'est pas configuré. ' }, [lienReglages()]));
            return p;
        }

        var deja = donnees.deja[reseau];
        if (deja) {
            var avert = el('div', { className: 'notice notice-warning inline' }, [
                el('p', { text: 'Article déjà publié sur ' + RESEAUX[reseau] + ' le ' + deja.date + '. ' }, [
                    deja.lien ? el('a', { href: deja.lien, target: '_blank', rel: 'noopener', text: 'Voir la publication' }) : null
                ])
            ]);
            p.appendChild(avert);
        }

        var idTexte = 'monclubtt-pub-texte-' + reseau;
        var texte = el('textarea', { id: idTexte, rows: '10', className: 'large-text' });
        texte.value = donnees.textes[reseau];
        p.appendChild(el('label', { for: idTexte, className: 'monclubtt-pub-label', text: 'Texte' }));
        p.appendChild(texte);

        var aide = el('p', { className: 'description' });
        if (reseau === 'facebook') {
            aide.textContent = 'Le lien vers l\'article est ajouté à la publication : ' + donnees.lien;
        } else {
            var compteur = function () {
                var n = Array.from(texte.value).length;
                aide.textContent = n + ' / ' + DATA.maxIg + ' caractères. Pas de lien cliquable sur Instagram : pensez au lien en bio.';
                aide.classList.toggle('monclubtt-pub-trop', n > DATA.maxIg);
            };
            texte.addEventListener('input', compteur);
            compteur();
        }
        p.appendChild(aide);

        // Photos : image à la une cochée par défaut ; recadrage 4:5 pour Instagram.
        var choix = el('fieldset', { className: 'monclubtt-pub-photos' + (reseau === 'instagram' ? ' is-portrait' : '') });
        choix.appendChild(el('legend', { text: 'Photos (' + DATA.max + ' au maximum)' + (reseau === 'instagram' ? ', recadrées en 4:5' : '') }));
        if (!donnees.photos.length) {
            choix.appendChild(el('p', { className: 'description', text: 'Aucune image dans l\'article.' + (reseau === 'instagram' ? ' Instagram demande au moins une photo.' : '') }));
        }
        donnees.photos.forEach(function (ph, i) {
            var cb = el('input', { type: 'checkbox', value: ph.id });
            cb.checked = i === 0;
            choix.appendChild(el('label', { className: 'monclubtt-pub-photo' }, [
                cb,
                el('img', { src: ph.apercu || '', alt: ph.alt || (ph.une ? 'Image à la une' : 'Image ' + (i + 1)) })
            ]));
        });
        var limiter = function () {
            var coches = choix.querySelectorAll('input:checked').length;
            choix.querySelectorAll('input').forEach(function (c) { c.disabled = !c.checked && coches >= DATA.max; });
        };
        choix.addEventListener('change', limiter);
        limiter();
        p.appendChild(choix);

        var statut = el('p', { className: 'monclubtt-pub-statut', role: 'status', 'aria-live': 'polite' });
        // Déjà publié : l'avertissement est affiché en haut de l'onglet, le bouton le rappelle.
        var bouton = el('button', { type: 'button', className: 'button button-primary', text: (deja ? 'Publier à nouveau sur ' : 'Publier sur ') + RESEAUX[reseau] });
        bouton.addEventListener('click', function () {
            var images = Array.from(choix.querySelectorAll('input:checked')).map(function (c) { return c.value; });
            bouton.disabled = true;
            statut.textContent = reseau === 'instagram' ? 'Envoi à Instagram… (jusqu\'à une minute, le temps qu\'Instagram traite les images)' : 'Envoi à Facebook…';
            post({ action: 'monclubtt_meta_publier', reseau: reseau, texte: texte.value, images: images })
                .then(function (res) {
                    var d = (res && res.data) || {};
                    if (d.historique) majHistorique(d.historique);
                    statut.textContent = '';
                    statut.appendChild(document.createTextNode((res && res.success ? '✓ ' : '⚠ ') + (d.message || 'Erreur.') + ' '));
                    if (d.lien) statut.appendChild(el('a', { href: d.lien, target: '_blank', rel: 'noopener', text: 'Voir la publication' }));
                    if (res && res.success) bouton.textContent = 'Publier à nouveau sur ' + RESEAUX[reseau];
                })
                .catch(function () { statut.textContent = '⚠ Envoi impossible : erreur réseau.'; })
                .then(function () { bouton.disabled = false; });
        });
        p.appendChild(el('p', { className: 'monclubtt-pub-envoi' }, [bouton]));
        p.appendChild(statut);
        return p;
    }

    function majHistorique(html) {
        var zone = document.getElementById('monclubtt-publications');
        if (zone) zone.innerHTML = html; // HTML échappé côté serveur
    }

    function construire() {
        var tabs = el('div', { className: 'monclubtt-pub-onglets', role: 'tablist' });
        var corps = el('div');
        var reseaux = Object.keys(RESEAUX);
        var boutons = reseaux.map(function (r, i) {
            var b = el('button', {
                type: 'button', role: 'tab', id: 'monclubtt-pub-tab-' + r, className: 'monclubtt-pub-onglet',
                'aria-controls': 'monclubtt-pub-' + r, 'aria-selected': i === 0 ? 'true' : 'false', text: RESEAUX[r]
            });
            b.tabIndex = i === 0 ? 0 : -1;
            return b;
        });
        var panneaux = reseaux.map(panneau);
        panneaux.forEach(function (pn, i) { pn.hidden = i !== 0; corps.appendChild(pn); });
        var activer = function (i) {
            boutons.forEach(function (b, j) {
                b.setAttribute('aria-selected', i === j ? 'true' : 'false');
                b.tabIndex = i === j ? 0 : -1;
                panneaux[j].hidden = i !== j;
            });
            boutons[i].focus();
        };
        boutons.forEach(function (b, i) {
            b.addEventListener('click', function () { activer(i); });
            b.addEventListener('keydown', function (e) {
                if (e.key === 'ArrowRight' || e.key === 'ArrowLeft') {
                    e.preventDefault();
                    activer((i + (e.key === 'ArrowRight' ? 1 : boutons.length - 1)) % boutons.length);
                }
            });
            tabs.appendChild(b);
        });

        var contenu = [tabs, corps];
        if (!donnees.public) {
            contenu.unshift(el('div', { className: 'notice notice-warning inline' }, [
                el('p', { text: 'Ce site ne semble pas accessible depuis Internet : Meta ne pourra pas télécharger les photos.' })
            ]));
        }
        return contenu;
    }

    function ouvrir(bouton) {
        postId = bouton.getAttribute('data-post');
        if (!dialog) {
            dialog = el('dialog', { className: 'monclubtt-pub-dialog', 'aria-labelledby': 'monclubtt-pub-titre' });
            document.body.appendChild(dialog);
        }
        dialog.innerHTML = '';
        var fermer = el('button', { type: 'button', className: 'monclubtt-pub-fermer button-link', 'aria-label': 'Fermer', text: '×' });
        fermer.addEventListener('click', function () { dialog.close(); });
        var corps = el('div', { className: 'monclubtt-pub-corps' }, [el('p', { text: 'Chargement…' })]);
        dialog.appendChild(el('div', { className: 'monclubtt-pub-entete' }, [el('h2', { id: 'monclubtt-pub-titre', text: 'Publier sur les réseaux' }), fermer]));
        dialog.appendChild(corps);
        dialog.showModal();

        post({ action: 'monclubtt_meta_preparer' }).then(function (res) {
            corps.innerHTML = '';
            if (!res || !res.success) {
                corps.appendChild(el('p', { text: (res && res.data && res.data.message) || 'Chargement impossible.' }));
                return;
            }
            donnees = res.data;
            construire().forEach(function (n) { corps.appendChild(n); });
        }).catch(function () {
            corps.innerHTML = '';
            corps.appendChild(el('p', { text: 'Chargement impossible : erreur réseau.' }));
        });
    }

    // La boîte peut être rendue après le chargement (éditeur de blocs) : délégation.
    document.addEventListener('click', function (e) {
        var b = e.target.closest && e.target.closest('#monclubtt-publier-reseaux');
        if (b) {
            e.preventDefault();
            ouvrir(b);
        }
    });
}());
