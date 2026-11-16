/* ================= PUBLIER UN ARTICLE SUR LES RÉSEAUX (éditeur) =================
 * Bouton « Publier sur les réseaux » de la boîte « Réseaux sociaux » : fenêtre
 * avec un onglet par réseau (texte modifiable, choix des photos), puis selon
 * le mode du réseau : envoi via l'API Graph de Meta côté serveur
 * (MonClubTT_PublicationMetaWP), ou partage manuel (copier le texte,
 * télécharger les photos, partage du téléphone, lien de partage Facebook).
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

    /* Onglet d'un réseau : avertissement « déjà publié », texte, photos, puis
       envoi automatique (API Meta) ou outils de partage manuel. */
    function panneau(reseau) {
        var p = el('div', { className: 'monclubtt-pub-panneau', role: 'tabpanel', id: 'monclubtt-pub-' + reseau, 'aria-labelledby': 'monclubtt-pub-tab-' + reseau });
        var auto = donnees.modes[reseau] === 'auto';

        p.appendChild(el('p', { className: 'monclubtt-pub-mode' }, [
            el('strong', { text: auto ? 'Publication automatique' : 'Partage manuel' }),
            document.createTextNode(auto
                ? ' : le plugin publie directement sur ' + RESEAUX[reseau] + '.'
                : ' : le plugin prépare le texte et les photos, vous publiez sur ' + RESEAUX[reseau] + '. '),
            auto ? null : el('a', { href: DATA.reglages, text: 'Publication automatique ?' })
        ]));

        var deja = donnees.deja[reseau];
        if (deja) {
            p.appendChild(el('div', { className: 'notice notice-warning inline' }, [
                el('p', { text: 'Article déjà publié sur ' + RESEAUX[reseau] + ' le ' + deja.date + '. ' }, [
                    deja.lien ? el('a', { href: deja.lien, target: '_blank', rel: 'noopener', text: 'Voir la publication' }) : null
                ])
            ]));
        }

        var idTexte = 'monclubtt-pub-texte-' + reseau;
        var texte = el('textarea', { id: idTexte, rows: '10', className: 'large-text' });
        // Partage manuel sur Facebook : le lien de l'article fait partie du texte collé.
        texte.value = donnees.textes[reseau] + (!auto && reseau === 'facebook' ? '\n\n' + donnees.lien : '');
        p.appendChild(el('label', { for: idTexte, className: 'monclubtt-pub-label', text: 'Texte' }));
        p.appendChild(texte);

        var aide = el('p', { className: 'description' });
        if (reseau === 'facebook') {
            aide.textContent = auto ? 'Le lien vers l\'article est ajouté à la publication : ' + donnees.lien : 'Le lien vers l\'article est à la fin du texte.';
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
        var images = function () {
            return Array.from(choix.querySelectorAll('input:checked')).map(function (c) { return c.value; });
        };
        var annoncer = function (ok, message, lien) {
            statut.textContent = '';
            statut.appendChild(document.createTextNode((ok ? '✓ ' : '⚠ ') + message + ' '));
            if (lien) statut.appendChild(el('a', { href: lien, target: '_blank', rel: 'noopener', text: 'Voir la publication' }));
        };

        if (auto) {
            // Déjà publié : l'avertissement est affiché en haut de l'onglet, le bouton le rappelle.
            var bouton = el('button', { type: 'button', className: 'button button-primary', text: (deja ? 'Publier à nouveau sur ' : 'Publier sur ') + RESEAUX[reseau] });
            bouton.addEventListener('click', function () {
                bouton.disabled = true;
                statut.textContent = reseau === 'instagram' ? 'Envoi à Instagram… (jusqu\'à une minute, le temps qu\'Instagram traite les images)' : 'Envoi à Facebook…';
                post({ action: 'monclubtt_meta_publier', reseau: reseau, texte: texte.value, images: images() })
                    .then(function (res) {
                        var d = (res && res.data) || {};
                        if (d.historique) majHistorique(d.historique);
                        annoncer(res && res.success, d.message || 'Erreur.', d.lien);
                        if (res && res.success) bouton.textContent = 'Publier à nouveau sur ' + RESEAUX[reseau];
                    })
                    .catch(function () { statut.textContent = '⚠ Envoi impossible : erreur réseau.'; })
                    .then(function () { bouton.disabled = false; });
            });
            p.appendChild(el('p', { className: 'monclubtt-pub-envoi' }, [bouton]));
        } else {
            p.appendChild(outilsManuels(reseau, texte, images, annoncer));
        }
        p.appendChild(statut);
        return p;
    }

    /* ------------------------------------------------------ partage manuel */

    function copier(textarea) {
        if (navigator.clipboard && window.isSecureContext) {
            return navigator.clipboard.writeText(textarea.value).then(function () { return true; }, function () { return repliCopie(textarea); });
        }
        return Promise.resolve(repliCopie(textarea));
    }

    function repliCopie(textarea) {
        textarea.select();
        try { return document.execCommand('copy'); } catch (e) { return false; }
    }

    /* Fichiers des photos choisies (Instagram : JPEG 4:5 préparé par le serveur),
       gardés en mémoire : un second clic sur « Partager… » part tout de suite. */
    var cacheFichiers = {};
    function fichiers(reseau, ids) {
        var cle = reseau + ':' + ids.join(',');
        if (!cacheFichiers[cle]) {
            cacheFichiers[cle] = post({ action: 'monclubtt_meta_fichiers', reseau: reseau, images: ids }).then(function (res) {
                if (!res || !res.success) throw new Error((res && res.data && res.data.message) || 'Photos indisponibles.');
                return Promise.all(res.data.fichiers.map(function (f) {
                    return fetch(f.url, { credentials: 'same-origin' }).then(function (r) {
                        if (!r.ok) throw new Error('Photo introuvable : ' + f.nom);
                        return r.blob();
                    }).then(function (b) { return new File([b], f.nom, { type: b.type || 'image/jpeg' }); });
                }));
            });
            cacheFichiers[cle].catch(function () { delete cacheFichiers[cle]; });
        }
        return cacheFichiers[cle];
    }

    function telecharger(liste) {
        liste.forEach(function (f, i) {
            setTimeout(function () {
                var a = el('a', { href: URL.createObjectURL(f), download: f.name });
                document.body.appendChild(a);
                a.click();
                a.remove();
                setTimeout(function () { URL.revokeObjectURL(a.href); }, 2000);
            }, i * 400); // espacés : certains navigateurs bloquent les téléchargements simultanés
        });
    }

    function partageFichiersPossible() {
        try {
            return !!(navigator.canShare && navigator.canShare({ files: [new File([''], 'test.jpg', { type: 'image/jpeg' })] }));
        } catch (e) {
            return false;
        }
    }

    function outilsManuels(reseau, texte, images, annoncer) {
        var zone = el('div', { className: 'monclubtt-pub-manuel' });
        var nom = RESEAUX[reseau];

        var etapes = el('ol', { className: 'monclubtt-pub-etapes' });
        if (reseau === 'facebook') {
            etapes.appendChild(el('li', { text: 'Copiez le texte et téléchargez les photos.' }));
            etapes.appendChild(el('li', { text: 'Sur Facebook, créez la publication : collez le texte, ajoutez les photos.' }));
            etapes.appendChild(el('li', { text: 'Ou partagez seulement le lien de l\'article : l\'aperçu reprend le titre et l\'image à la une.' }));
        } else {
            etapes.appendChild(el('li', { text: 'Sur téléphone : « Partager… » copie le texte et envoie les photos à l\'appli Instagram ; collez le texte dans la légende.' }));
            etapes.appendChild(el('li', { text: 'Sur ordinateur : copiez le texte, téléchargez les photos (déjà au format 4:5), puis créez la publication sur instagram.com.' }));
        }
        zone.appendChild(etapes);

        var boutons = el('p', { className: 'monclubtt-pub-actions' });
        var btnCopier = el('button', { type: 'button', className: 'button', text: 'Copier le texte' });
        btnCopier.addEventListener('click', function () {
            copier(texte).then(function (ok) {
                annoncer(ok, ok ? 'Texte copié.' : 'Copie impossible : le texte est sélectionné, copiez-le avec Ctrl/Cmd + C.');
            });
        });
        boutons.appendChild(btnCopier);

        var btnTelecharger = el('button', { type: 'button', className: 'button', text: 'Télécharger les photos' });
        btnTelecharger.addEventListener('click', function () {
            var ids = images();
            if (!ids.length) { annoncer(false, 'Cochez au moins une photo.'); return; }
            btnTelecharger.disabled = true;
            annoncer(true, 'Préparation des photos…');
            fichiers(reseau, ids)
                .then(function (liste) { telecharger(liste); annoncer(true, liste.length + (liste.length > 1 ? ' photos téléchargées.' : ' photo téléchargée.')); })
                .catch(function (e) { annoncer(false, e.message); })
                .then(function () { btnTelecharger.disabled = false; });
        });
        boutons.appendChild(btnTelecharger);

        if (partageFichiersPossible()) {
            var btnPartager = el('button', { type: 'button', className: 'button button-primary', text: 'Partager…' });
            btnPartager.addEventListener('click', function () {
                copier(texte);
                fichiers(reseau, images()).then(function (liste) {
                    var contenu = { text: texte.value };
                    if (liste.length) contenu.files = liste;
                    return navigator.share(contenu).then(function () {
                        annoncer(true, 'Envoyé. Le texte est copié : collez-le dans la légende si ' + nom + ' ne l\'a pas repris.');
                    });
                }).catch(function (e) {
                    if (e && e.name === 'AbortError') return;
                    // Photos préparées trop lentement pour le navigateur : elles sont prêtes au second clic.
                    annoncer(false, e && e.name === 'NotAllowedError' ? 'Photos prêtes : touchez de nouveau « Partager… ».' : (e && e.message) || 'Partage impossible.');
                });
            });
            boutons.appendChild(btnPartager);
        }

        boutons.appendChild(el('a', {
            className: 'button', href: donnees.partage[reseau], target: '_blank', rel: 'noopener',
            text: reseau === 'facebook' ? 'Partager le lien sur Facebook' : 'Ouvrir Instagram'
        }));
        zone.appendChild(boutons);

        // Historique : noter la publication faite à la main.
        var idLien = 'monclubtt-pub-lien-' + reseau;
        var champLien = el('input', { type: 'url', id: idLien, className: 'regular-text', placeholder: 'https://…' });
        var btnMarquer = el('button', { type: 'button', className: 'button', text: 'Marquer comme publié' });
        btnMarquer.addEventListener('click', function () {
            btnMarquer.disabled = true;
            post({ action: 'monclubtt_meta_marquer', reseau: reseau, lien: champLien.value })
                .then(function (res) {
                    var d = (res && res.data) || {};
                    if (d.historique) majHistorique(d.historique);
                    annoncer(res && res.success, d.message || 'Erreur.');
                })
                .catch(function () { annoncer(false, 'Enregistrement impossible : erreur réseau.'); })
                .then(function () { btnMarquer.disabled = false; });
        });
        zone.appendChild(el('div', { className: 'monclubtt-pub-marquer' }, [
            el('label', { for: idLien, text: 'Une fois publié, lien de la publication (facultatif)' }),
            el('p', {}, [champLien, document.createTextNode(' '), btnMarquer])
        ]));
        return zone;
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
        var autoUtilise = Object.keys(donnees.modes).some(function (r) { return donnees.modes[r] === 'auto'; });
        if (autoUtilise && !donnees.public) {
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
