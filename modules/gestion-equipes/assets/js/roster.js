/* TT Team Planner — effectif d'une journée, rangé par disponibilité
 * Vanilla JS · Aucune dépendance · Pur : données in, groupes out
 *
 * Exposé sur window.TTPRoster. Utilisé par app.js (onglet Dispos de la vue
 * Journée) et par share-image.js (joueurs dispo non placés, en pied d'image).
 * captain() donne le capitaine d'une équipe pour la journée, aux deux.
 */
(function (w) {
  'use strict';

  function teamCodeOf(team) {
    return team.code || team.id || '';
  }

  function byRanking(a, b) {
    return (b.player.ranking || 0) - (a.player.ranking || 0);
  }

  /**
   * input :
   *   phase, round
   *   players          — tous les joueurs ; seuls les actifs comptent
   *   availabilities   — toutes phases et journées confondues
   *   compositions     — les slots de la journée, toutes équipes
   *   teams            — dans l'ordre d'affichage
   *   forfeits         — { codeÉquipe: true } pour la journée, facultatif
   *
   * Chaque entrée est { player, team } : team est le code de l'équipe où le
   * joueur est placé, ou null. Un joueur n'est placé qu'une fois par journée
   * (le serveur le retire de son ancien slot quand on le place ailleurs).
   */
  function group(input) {
    var phase = Number(input.phase);
    var round = Number(input.round);
    var forfeits = input.forfeits || {};
    var teams = input.teams || [];

    var status = {};
    (input.availabilities || []).forEach(function (a) {
      if (Number(a.phase) === phase && Number(a.round) === round) status[String(a.player_id)] = a.status;
    });

    var teamOf = {};
    (input.compositions || []).forEach(function (c) {
      var n = Number(c.slot_number);
      if (c.player_id && n >= 1 && n <= 4) teamOf[String(c.player_id)] = c.team_code;
    });

    var groups = { free: [], placed: [], uncertain: [], unknown: [], unavailable: [] };
    (input.players || []).forEach(function (p) {
      if (p.is_active === false) return;
      var id = String(p.id);
      var entry = { player: p, team: teamOf[id] || null };
      var s = status[id] || 'unknown';
      if (s === 'available') groups[entry.team ? 'placed' : 'free'].push(entry);
      else if (s === 'uncertain' || s === 'unavailable') groups[s].push(entry);
      else groups.unknown.push(entry);
    });

    var teamRank = {};
    teams.forEach(function (t, i) { teamRank[teamCodeOf(t)] = i; });
    groups.placed.sort(function (a, b) {
      var ra = a.team in teamRank ? teamRank[a.team] : teams.length;
      var rb = b.team in teamRank ? teamRank[b.team] : teams.length;
      return ra !== rb ? ra - rb : byRanking(a, b);
    });
    ['free', 'uncertain', 'unknown', 'unavailable'].forEach(function (k) { groups[k].sort(byRanking); });

    groups.available = groups.free.length + groups.placed.length;
    // Une équipe forfait n'aligne personne : ses 4 places ne sont pas à pourvoir.
    groups.places = 4 * teams.filter(function (t) { return !forfeits[teamCodeOf(t)]; }).length;
    return groups;
  }

  /**
   * Capitaine d'une équipe pour une journée, ou null :
   *   1. le joueur nommé capitaine de la journée (vue Journée) ;
   *   2. sinon le capitaine de phase (écran Effectifs), s'il joue ;
   *   3. sinon personne : le capitaine est facultatif.
   * Le capitaine est toujours un joueur de la compo.
   *
   * compositions — les slots de la journée, toutes équipes
   * squads       — les effectifs de phase
   */
  function captain(teamCode, phase, compositions, squads) {
    var playing = {};
    var named = null;
    (compositions || []).forEach(function (c) {
      var n = Number(c.slot_number);
      if (c.team_code !== teamCode || !c.player_id || n < 1 || n > 4) return;
      playing[String(c.player_id)] = true;
      if (c.is_captain) named = c.player_id;
    });
    if (named) return named;

    var ofPhase = (squads || []).find(function (s) {
      return s.team_code === teamCode && Number(s.phase) === Number(phase) && s.is_captain;
    });
    return ofPhase && playing[String(ofPhase.player_id)] ? ofPhase.player_id : null;
  }

  w.TTPRoster = { group: group, captain: captain };
})(window);
