/* TT Team Planner — équipe complète exigée en régional
 * Vanilla JS · Aucune dépendance · Pur : données in, verdict out
 *
 * Exposé sur window.TTPTeamCheck. Utilisé par app.js (en-tête de l'équipe
 * dans la vue Journée) et par share-image.js (statut de l'équipe).
 *
 * En Pré-Nationale, R1, R2 et R3, une équipe doit compter 4 joueurs ayant
 * disputé au moins une partie (règlement régional LNATT, art. 4.6.2). Sinon
 * elle perd la rencontre ; ce n'est pas un forfait. Le serveur dit quelles
 * équipes sont concernées (team.full_team_required).
 */
(function (w) {
  'use strict';

  // Une compo à 3 est un état normal en cours de saisie : l'alerte attend la
  // semaine de la journée.
  var ALERT_DAYS = 6;

  function dayNumber(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(iso || '');
    return m ? Date.UTC(+m[1], +m[2] - 1, +m[3]) / 86400000 : null;
  }

  /**
   * team   — équipe de TTPConfig.teams
   * filled — joueurs placés dans la compo
   * date   — date de la journée (AAAA-MM-JJ), vide si inconnue
   * today  — date du jour (AAAA-MM-JJ)
   *
   * Renvoie null (rien à signaler), 'notice' (incomplète, journée lointaine)
   * ou 'alert' (incomplète, journée dans 6 jours ou moins, ou passée).
   */
  function completeness(team, filled, date, today) {
    if (!team || !team.full_team_required || filled >= 4) return null;
    var day = dayNumber(date);
    var now = dayNumber(today);
    if (day === null || now === null) return 'notice';
    return day - now <= ALERT_DAYS ? 'alert' : 'notice';
  }

  w.TTPTeamCheck = { completeness: completeness, ALERT_DAYS: ALERT_DAYS };
})(window);
