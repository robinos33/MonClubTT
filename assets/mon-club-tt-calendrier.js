/**
 * Calendrier du club : shortcode [monclubtt_calendrier].
 *
 * Vue mois (événements sur plusieurs jours en bandeaux) ou liste, filtres par
 * catégorie, choix d'une période, détail d'un rendez-vous. Les fichiers .ics
 * (téléchargement et abonnement) sont produits côté serveur.
 */
(function () {
  'use strict';

  var MOIS_COURT = { day: 'numeric', month: 'short' };
  var MOBILE = '(max-width: 620px)';

  function esc(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function parse(d) {
    return new Date(d + 'T12:00:00');
  }

  function fmt(d, options) {
    return parse(d).toLocaleDateString('fr-FR', options || MOIS_COURT);
  }

  function heure(h) {
    if (!h) return '';
    var p = h.split(':');
    return Number(p[0]) + ' h' + (p[1] !== '00' ? ' ' + p[1] : '');
  }

  function plage(e) {
    return fmt(e.debut) +
      (e.fin !== e.debut ? ' – ' + fmt(e.fin) : '') +
      (e.heure ? ' · ' + heure(e.heure) + (e.heureFin ? ' – ' + heure(e.heureFin) : '') : '');
  }

  function ajouterParam(url, params) {
    var u = new URL(url, window.location.href);
    Object.keys(params).forEach(function (k) {
      if (params[k]) u.searchParams.set(k, params[k]);
    });
    return u.toString();
  }

  function init(root) {
    if (root.dataset.mctPret) return;
    var source = root.querySelector('.monclubtt-calendrier__donnees');
    if (!source) return;
    root.dataset.mctPret = '1';

    var data = JSON.parse(source.textContent);
    var evenements = data.evenements;
    var couleurs = data.categories;
    var $ = function (nom) { return root.querySelector('[data-mct="' + nom + '"]'); };

    // Mois proposés : de celui du premier rendez-vous à celui du dernier.
    var mois = [];
    var curseur = parse(data.premierMois + '-01');
    var dernier = data.dernierMois;
    while (mois.length < 36) {
      var cle = curseur.getFullYear() + '-' + String(curseur.getMonth() + 1).padStart(2, '0');
      mois.push(cle);
      if (cle >= dernier) break;
      curseur.setMonth(curseur.getMonth() + 1);
    }
    mois.forEach(function (m) {
      var o = document.createElement('option');
      o.value = m;
      o.textContent = fmt(m + '-01', { month: 'long', year: 'numeric' });
      $('mois').append(o);
    });

    var moisCourant = data.aujourdhui.slice(0, 7);
    var etat = {
      mois: moisCourant < mois[0] ? mois[0] : (moisCourant > mois[mois.length - 1] ? mois[mois.length - 1] : moisCourant),
      categorie: '',
      vue: 'grille',
      periode: null
    };

    var cats = Object.keys(couleurs);
    $('filtres').innerHTML = ['']
      .concat(cats)
      .map(function (c) {
        return '<button type="button" data-cat="' + esc(c) + '" style="--mct-evt:' + (couleurs[c] || 'currentColor') + '">' +
          (c ? '<span class="mct-pastille" aria-hidden="true"></span>' + esc(c) : 'Tous') + '</button>';
      })
      .join('');
    if (cats.length < 2) $('filtres').hidden = true;

    function bornesMois() {
      var p = etat.mois.split('-').map(Number);
      return { du: etat.mois + '-01', au: etat.mois + '-' + String(new Date(p[0], p[1], 0).getDate()).padStart(2, '0') };
    }

    function selection() {
      var b = etat.periode || bornesMois();
      return evenements
        .filter(function (e) {
          return e.debut <= b.au && e.fin >= b.du && (!etat.categorie || e.categorie === etat.categorie);
        })
        .sort(function (a, b2) {
          return a.debut.localeCompare(b2.debut) || a.fin.localeCompare(b2.fin) || (a.heure || '').localeCompare(b2.heure || '');
        });
    }

    function libellePeriode() {
      if (etat.periode) {
        var long = { day: 'numeric', month: 'long', year: 'numeric' };
        return 'du ' + fmt(etat.periode.du, long) + ' au ' + fmt(etat.periode.au, long);
      }
      return fmt(etat.mois + '-01', { month: 'long', year: 'numeric' });
    }

    function grille(liste) {
      var p = etat.mois.split('-').map(Number);
      var jours = new Date(p[0], p[1], 0).getDate();
      var decalage = (new Date(p[0], p[1] - 1, 1).getDay() + 6) % 7;
      var iso = function (j) { return etat.mois + '-' + String(j).padStart(2, '0'); };
      var html = ['Lun.', 'Mar.', 'Mer.', 'Jeu.', 'Ven.', 'Sam.', 'Dim.']
        .map(function (j) { return '<div class="mct-jour-semaine">' + j + '</div>'; }).join('');

      for (var s = 0; s < Math.ceil((decalage + jours) / 7); s++) {
        var premier = s * 7 - decalage + 1;
        var lo = Math.max(1, premier);
        var hi = Math.min(jours, premier + 6);
        var segments = liste
          .filter(function (e) { return e.debut <= iso(hi) && e.fin >= iso(lo); })
          .map(function (e) {
            return {
              e: e,
              debut: e.debut < iso(lo) ? lo : Number(e.debut.slice(8)),
              fin: e.fin > iso(hi) ? hi : Number(e.fin.slice(8)),
              gauche: e.debut < iso(lo),
              droite: e.fin > iso(hi)
            };
          })
          .sort(function (a, b) { return a.debut - b.debut || b.fin - a.fin; });

        // Bandeaux répartis sur des lignes sans chevauchement.
        var lignes = [];
        segments.forEach(function (sg) {
          var l = lignes.findIndex(function (fin) { return fin < sg.debut; });
          if (l < 0) l = lignes.length;
          lignes[l] = sg.fin;
          sg.ligne = l;
        });
        var rangs = Math.max(1, lignes.length);

        html += '<div class="mct-semaine" style="grid-template-rows:29px repeat(' + rangs + ',auto)">';
        for (var c = 0; c < 7; c++) {
          var d = premier + c;
          var vide = d < 1 || d > jours;
          var aujourdhui = !vide && iso(d) === data.aujourdhui;
          html += '<div class="mct-jour' + (vide ? ' mct-vide' : '') + (aujourdhui ? ' mct-aujourdhui' : '') +
            '" style="grid-column:' + (c + 1) + ';grid-row:1 / span ' + (rangs + 1) + '">' +
            (vide ? '' : '<div class="mct-num"' + (aujourdhui ? ' aria-label="Aujourd\'hui, ' + d + '"' : '') + '>' + d + '</div>') + '</div>';
        }
        segments.forEach(function (sg) {
          var e = sg.e;
          html += '<button type="button" class="mct-evt' + (sg.gauche ? ' mct-suite-gauche' : '') + (sg.droite ? ' mct-suite-droite' : '') +
            '" style="--mct-evt:' + (couleurs[e.categorie] || 'var(--mct-accent)') + ';grid-column:' + (sg.debut - premier + 1) + ' / span ' + (sg.fin - sg.debut + 1) +
            ';grid-row:' + (sg.ligne + 2) + '" data-evt="' + e.id + '" aria-label="' + esc(e.titre + ' · ' + plage(e)) + '">' +
            (e.categorie ? '<small>' + (sg.gauche ? '← ' : '') + esc(e.categorie) + (sg.droite ? ' →' : '') + '</small>' : '') +
            esc(e.titre) + '<span class="mct-plage">' + esc(plage(e)) + '</span></button>';
        });
        html += '</div>';
      }
      return html;
    }

    function agenda(liste) {
      if (!liste.length) return '<p class="mct-vide-liste">Aucun rendez-vous pour cette sélection.</p>';
      var groupe = '';
      return liste.map(function (e) {
        var titreMois = '';
        if (etat.periode) {
          var cle = (e.debut < etat.periode.du ? etat.periode.du : e.debut).slice(0, 7);
          if (cle !== groupe) {
            groupe = cle;
            titreMois = '<h3 class="mct-agenda-mois">' + fmt(cle + '-01', { month: 'long', year: 'numeric' }) + '</h3>';
          }
        }
        var passe = e.fin < data.aujourdhui;
        return titreMois +
          '<div class="mct-ligne' + (passe ? ' mct-passe' : '') + '" style="--mct-evt:' + (couleurs[e.categorie] || 'var(--mct-accent)') + '">' +
          '<div class="mct-date">' + esc(plage(e)) + '</div>' +
          '<div>' + esc(e.titre) + (e.categorie ? '<small>' + esc(e.categorie) + (e.lieu ? ' · ' + esc(e.lieu.split('\n')[0]) : '') + '</small>' : '') + '</div>' +
          '<button type="button" data-evt="' + e.id + '">Détails →</button></div>';
      }).join('');
    }

    function detail(id) {
      var e = evenements.find(function (x) { return x.id === id; });
      if (!e) return;
      var annee = e.debut.slice(0, 4);
      var zone = $('detail');
      zone.hidden = false;
      zone.style.setProperty('--mct-evt', couleurs[e.categorie] || 'var(--mct-accent)');
      zone.innerHTML =
        '<button type="button" class="mct-fermer" data-mct="detail-fermer">Fermer</button>' +
        (e.categorie ? '<span class="mct-surtitre">' + esc(e.categorie) + (e.serie ? ' · ' + esc(e.serie) : '') + '</span>' : '') +
        '<h3>' + esc(e.titre) + '</h3>' +
        '<p class="mct-detail-date">' + esc(plage(e)) + ' ' + annee + '</p>' +
        (e.lieu ? '<p>' + esc(e.lieu).replace(/\n/g, '<br>') + '</p>' : '') +
        (e.note ? '<p>' + esc(e.note).replace(/\n/g, '<br>') + '</p>' : '') +
        '<p class="mct-detail-actions">' +
        (e.lien ? '<a class="mct-principal" href="' + esc(e.lien) + '" target="_blank" rel="noopener">En savoir plus →</a> ' : '') +
        '<a href="' + esc(ajouterParam(data.ics.base, { evenement: String(e.id) })) + '">Ajouter à mon agenda (.ics)</a></p>';
      $('detail-fermer').onclick = function () { zone.hidden = true; };
      zone.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
    }

    function synchroDates() {
      var b = etat.periode || bornesMois();
      $('du').value = b.du;
      $('au').value = b.au;
      $('au').setCustomValidity('');
    }

    function rendu() {
      var mobile = window.matchMedia(MOBILE).matches;
      var liste = selection();
      $('mois').value = etat.mois;
      $('precedent').disabled = etat.mois === mois[0];
      $('suivant').disabled = etat.mois === mois[mois.length - 1];
      $('vue-mois').setAttribute('aria-pressed', String(etat.vue === 'grille'));
      $('vue-liste').setAttribute('aria-pressed', String(etat.vue === 'liste'));
      root.querySelectorAll('[data-cat]').forEach(function (b) {
        b.setAttribute('aria-pressed', String(b.dataset.cat === etat.categorie));
      });

      $('compte').textContent = liste.length + ' rendez-vous · ' + (etat.categorie || 'toutes les catégories') + ' · ' + libellePeriode();
      var enGrille = etat.vue === 'grille' && !etat.periode && !mobile;
      $('grille').hidden = !enGrille;
      $('agenda').hidden = enGrille;
      $('grille').innerHTML = enGrille ? grille(liste) : '';
      $('agenda').innerHTML = enGrille ? '' : agenda(liste);
      root.querySelectorAll('[data-evt]').forEach(function (b) {
        b.onclick = function () { detail(Number(b.dataset.evt)); };
      });
      $('detail').hidden = true;
    }

    function aller(m) {
      etat.mois = m;
      etat.periode = null;
      synchroDates();
      rendu();
    }

    $('mois').onchange = function (ev) { aller(ev.target.value); };
    $('precedent').onclick = function () { aller(mois[Math.max(0, mois.indexOf(etat.mois) - 1)]); };
    $('suivant').onclick = function () { aller(mois[Math.min(mois.length - 1, mois.indexOf(etat.mois) + 1)]); };
    $('vue-mois').onclick = function () { etat.periode = null; etat.vue = 'grille'; synchroDates(); rendu(); };
    $('vue-liste').onclick = function () { etat.vue = 'liste'; rendu(); };
    root.querySelectorAll('[data-cat]').forEach(function (b) {
      b.onclick = function () { etat.categorie = b.dataset.cat; rendu(); };
    });
    $('du').oninput = $('au').oninput = function () { $('au').setCustomValidity(''); };
    $('periode').onsubmit = function (ev) {
      ev.preventDefault();
      $('au').setCustomValidity($('du').value > $('au').value ? 'La date de fin doit être égale ou postérieure à la date de début.' : '');
      if (!ev.currentTarget.reportValidity()) return;
      etat.periode = { du: $('du').value, au: $('au').value };
      etat.vue = 'liste';
      if (mois.indexOf(etat.periode.du.slice(0, 7)) >= 0) etat.mois = etat.periode.du.slice(0, 7);
      rendu();
    };
    window.matchMedia(MOBILE).addEventListener('change', rendu);

    // Export : abonnement au flux et téléchargement côté serveur.
    var dialogue = $('export');
    var portees = root.querySelectorAll('.mct-export-options input');
    $('lien-webcal').href = data.ics.webcal;
    $('lien-google').href = data.ics.google;
    $('adresse-flux').value = data.ics.flux;
    $('adresse-flux').onfocus = function () { this.select(); };

    function urlExport() {
      var portee = root.querySelector('.mct-export-options input:checked').value;
      if (portee === 'saison') return data.ics.saison;
      if (portee === 'categorie') return ajouterParam(data.ics.saison, { categorie: etat.categorie });
      var b = etat.periode || bornesMois();
      return ajouterParam(data.ics.base, { du: b.du, au: b.au, categorie: etat.categorie });
    }

    function majExport() {
      $('telecharger').href = urlExport();
    }

    portees.forEach(function (input) { input.onchange = majExport; });
    $('export-ouvrir').onclick = function () {
      $('export-categorie').textContent = etat.categorie || 'Choisissez d\'abord une catégorie dans les filtres';
      $('export-selection').textContent = libellePeriode() + ' · ' + (etat.categorie || 'toutes les catégories');
      portees[1].disabled = !etat.categorie;
      portees[etat.periode ? 2 : (etat.categorie ? 1 : 0)].checked = true;
      majExport();
      if (typeof dialogue.showModal === 'function') {
        dialogue.showModal();
      } else {
        dialogue.setAttribute('open', '');
      }
    };
    $('export-fermer').onclick = function () {
      if (typeof dialogue.close === 'function') dialogue.close(); else dialogue.removeAttribute('open');
    };
    dialogue.addEventListener('click', function (ev) {
      if (ev.target === dialogue && typeof dialogue.close === 'function') dialogue.close();
    });

    synchroDates();
    rendu();
  }

  function demarrer() {
    document.querySelectorAll('.monclubtt-calendrier').forEach(init);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', demarrer, { once: true });
  } else {
    demarrer();
  }
})();
