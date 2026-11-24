/* TT Team Planner — SMS pré-rédigés
 * Vanilla JS · Aucune dépendance · Pur : modèle et valeurs in, texte out
 *
 * Exposé sur window.TTPSms. Utilisé par app.js : relance de dispo (vue
 * Joueurs, fiche joueur), confirmation de compo (vue Journée) et aperçu de
 * la modale d'édition des modèles (Réglages).
 */
(function (w) {
  'use strict';

  var VARIABLES = ['prenom', 'nom', 'equipe', 'journee', 'date'];

  // Repli si l'option est vide : les défauts d'Activator, sans la signature.
  var DEFAULTS = {
    availability: 'Bonjour {prenom}, es-tu disponible pour la J{journee} ({date}) ? Merci de répondre.',
    confirmation: 'Bonjour {prenom}, tu joues en {equipe} pour la J{journee} le {date}. À bientôt !'
  };

  // Chaque occurrence est remplacée : un modèle peut citer deux fois {prenom}.
  // Une variable inconnue reste telle quelle, pour que l'aperçu la montre.
  function body(template, values) {
    values = values || {};
    return String(template || '').replace(/\{(\w+)\}/g, function (match, name) {
      if (VARIABLES.indexOf(name) === -1) return match;
      var v = values[name];
      return v === undefined || v === null ? '' : String(v);
    });
  }

  function href(phone, text) {
    return 'sms:' + String(phone || '').replace(/\s/g, '') + '?body=' + encodeURIComponent(text);
  }

  w.TTPSms = { body: body, href: href, VARIABLES: VARIABLES, DEFAULTS: DEFAULTS };
})(window);
