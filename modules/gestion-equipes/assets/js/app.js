/* TT Team Planner — Direction A "Coach"
 * Vanilla JS · No build step · No dependencies
 * Tokens from app-coach.jsx (Claude Design handoff)
 */
(function (w, d) {
  'use strict';

  // ═══════════════════════════════════════════
  // CONFIG — injected by wp_localize_script
  // ═══════════════════════════════════════════
  var cfg = w.TTPConfig || {};
  var API = cfg.apiBase || '/wp-json/monclubtt-equipes/v1';
  var NONCE = cfg.nonce || '';

  // ═══════════════════════════════════════════
  // DESIGN TOKENS — Direction A "Coach"
  // ═══════════════════════════════════════════
  var C = {
    bg: '#f5f7fb',      bgD: '#0b1220',
    surf: '#ffffff',    surfD: '#131c2e',
    surf2: '#eef2f8',   surf2D: '#1b263d',
    bord: '#e2e8f0',    bordD: '#243049',
    ink: '#0f172a',     inkD: '#e7eaf3',
    ink2: '#475569',    ink2D: '#94a3b8',
    pri: '#2563eb',     priInk: '#1d4ed8',
    priSoft: '#dbeafe', priSoftD: '#1e3a5f',
    ok: '#16a34a',      okSoft: '#dcfce7',
    warn: '#ca8a04',    warnSoft: '#fef3c7',
    err: '#dc2626',     errSoft: '#fee2e2',
    purp: '#7c3aed',    purpSoft: '#ede9fe'
  };

  function tk(dark) {
    return {
      bg:      dark ? C.bgD    : C.bg,
      surf:    dark ? C.surfD  : C.surf,
      surf2:   dark ? C.surf2D : C.surf2,
      bord:    dark ? C.bordD  : C.bord,
      ink:     dark ? C.inkD   : C.ink,
      ink2:    dark ? C.ink2D  : C.ink2,
      priSoft: dark ? C.priSoftD : C.priSoft
    };
  }

  // ═══════════════════════════════════════════
  // STATE
  // ═══════════════════════════════════════════
  var S = {
    screen: 'dashboard', tab: 'dashboard',
    dark: false, phase: cfg.phase || 0,
    journeeN: 3, playerId: null,
    activeTeamI: 0,
    offline: !navigator.onLine,
    players: [], availabilities: [], compositions: [], phaseCompositions: [],
    loading: true, syncing: false, loadError: null, syncError: null,
    picker: null, pickerQ: '',
    searchQ: '', playerFilter: 'all',
    bulkMode: false, bulkSelected: {}, bulkSending: false, bulkResult: null,
    playerEdit: false, editPhone: '', editEmail: '', editNotes: '', editSaving: false,
    deleteConfirm: null, wipeConfirm: null,
    squads: [], squadLoading: false, squadPickerTeam: null, squadPickerQ: '',
    burnage: {}, validations: {}, forfeits: {},
    teams: cfg.teams || [],
    journees: buildJournees(cfg.phase || 0),
    pwaPrompt: null, pwaIos: false, pwaDismissed: !!localStorage.getItem('ttp-pwa-dismissed')
  };

  function todayISO() {
    var d = new Date();
    return d.getFullYear() + '-' +
      String(d.getMonth() + 1).padStart(2, '0') + '-' +
      String(d.getDate()).padStart(2, '0');
  }

  function buildJournees(phase) {
    if (phase === undefined) phase = S ? S.phase : (cfg.phase || 0);
    var allDates = cfg.journeeDates || [];
    // journeeDates est maintenant [[p1 dates], [p2 dates]]
    var phDates = Array.isArray(allDates[phase]) ? allDates[phase] : allDates;
    var today = todayISO();
    var foundNext = false;
    return Array.from({ length: 7 }, function (_, i) {
      var date = phDates[i] || '';
      var done = !!(date && date < today);
      var current = false;
      if (!done && date && !foundNext) { current = true; foundNext = true; }
      return { n: i + 1, date: date, done: done, complete: 0, alerts: 0, current: current };
    });
  }

  function findGlobalNext() {
    var allDates = cfg.journeeDates || [];
    if (!Array.isArray(allDates[0])) return null;
    var today = todayISO();
    var best = null;
    for (var ph = 0; ph < 2; ph++) {
      var dates = allDates[ph] || [];
      for (var i = 0; i < dates.length; i++) {
        var d = dates[i];
        if (d && d >= today && (!best || d < best.date)) {
          best = { phase: ph + 1, round: i + 1, date: d };
        }
      }
    }
    return best;
  }

  // ═══════════════════════════════════════════
  // UTILS
  // ═══════════════════════════════════════════
  function esc(s) {
    return String(s == null ? '' : s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function initials(name) {
    return (name || '').split(' ').filter(Boolean).map(function (w) { return w[0] || ''; }).join('').slice(0, 2).toUpperCase() || '?';
  }

  function avStatus(av) {
    return { available: 'Dispo', unavailable: 'Indispo', uncertain: 'Incertain', unknown: 'Non renseigné' }[av] || '—';
  }
  function avEmoji(av) {
    return { available: '✅', unavailable: '🚫', uncertain: '❓', unknown: '⚪' }[av] || '⚪';
  }
  function avColor(av, dark) {
    if (av === 'available') return C.ok;
    if (av === 'unavailable') return C.err;
    if (av === 'uncertain') return C.warn;
    return tk(dark).ink2;
  }

  function smsHref(phone, firstName, jn, date) {
    var tmpl = (cfg.smsTemplates && cfg.smsTemplates.availability) ||
      'Bonjour {prenom}, es-tu dispo pour la J{journee} ({date}) ?';
    var body = tmpl
      .replace('{prenom}', firstName || '')
      .replace('{journee}', String(jn || ''))
      .replace('{date}', date || '');
    return 'sms:' + (phone || '').replace(/\s/g, '') + '?body=' + encodeURIComponent(body);
  }

  // ═══════════════════════════════════════════
  // API
  // ═══════════════════════════════════════════
  function apiUrl(path) {
    // When pretty permalinks are off, API ends with ?rest_route=...
    // Extra query params must use & not ? to avoid breaking the route.
    if (API.indexOf('?') !== -1 && path.indexOf('?') !== -1) {
      return API + path.replace('?', '&');
    }
    return API + path;
  }

  function apiFetch(path, opts) {
    return fetch(apiUrl(path), Object.assign(
      { headers: { 'X-WP-Nonce': NONCE, 'Content-Type': 'application/json' } },
      opts || {}
    )).then(function (r) {
      if (!r.ok) return r.text().then(function (t) { throw new Error(t); });
      return r.json();
    });
  }

  function loadAll() {
    var season = encodeURIComponent(cfg.season || '');
    var phase  = S.phase + 1;
    return Promise.all([
      apiFetch('/players'),
      apiFetch('/availability?season=' + season),
      apiFetch('/phase-squads?season=' + season + '&phase=' + phase),
      apiFetch('/compositions?season=' + season + '&phase=' + phase)
    ]).then(function (res) {
      setState({ players: res[0] || [], availabilities: res[1] || [], squads: res[2] || [], phaseCompositions: res[3] || [], loading: false, loadError: null });
    }).catch(function (err) {
      setState({ loading: false, loadError: String(err) });
    });
  }

  function countCompleteTeams(phase, round) {
    var tc = S.teams.length;
    if (!tc) return 0;
    var complete = 0;
    S.teams.forEach(function (team) {
      var code  = team.code || team.id || '';
      var slots = S.phaseCompositions.filter(function (c) {
        return c.team_code === code && Number(c.phase) === phase && Number(c.round) === round && c.player_id;
      });
      if (slots.length >= 4) complete++;
    });
    return complete;
  }

  function loadCompositions() {
    apiFetch('/compositions?season=' + encodeURIComponent(cfg.season || '') +
             '&phase=' + (S.phase + 1) + '&round=' + S.journeeN)
      .then(function (c) {
        setState({ compositions: c || [] });
        loadBurnageContext(currentTeamCode(), S.journeeN);
      })
      .catch(function () {});
  }

  // ── Brûlage : statut de validation + statut de brûlage par joueur ──
  function currentTeamCode() {
    var team = S.teams[S.activeTeamI];
    return team ? (team.code || team.id || '') : '';
  }

  function burnageKey(teamCode, round) {
    return teamCode + '_r' + round;
  }

  function loadBurnageContext(teamCode, round) {
    if (!teamCode || !round) return;
    var key = burnageKey(teamCode, round);
    var ph  = S.phase + 1;

    // Forfaits déclarés : chargés une fois par phase, indexés comme le brûlage.
    if (S.forfeits['p' + ph] === undefined) {
      S.forfeits['p' + ph] = {};
      apiFetch('/forfeits?season=' + encodeURIComponent(cfg.season || '') + '&phase=' + ph)
        .then(function (res) {
          var map = {};
          (res || []).forEach(function (f) { map[burnageKey(f.team_code, f.round)] = true; });
          var fo = Object.assign({}, S.forfeits); fo['p' + ph] = map;
          setState({ forfeits: fo });
        })
        .catch(function () {});
    }

    if (S.validations[key] === undefined) {
      apiFetch('/appearances/validate?season=' + encodeURIComponent(cfg.season || '') +
               '&phase=' + (S.phase + 1) + '&round=' + round + '&team_code=' + encodeURIComponent(teamCode))
        .then(function (res) {
          var v = Object.assign({}, S.validations);
          v[key] = !!(res && res.validated);
          setState({ validations: v });
        })
        .catch(function () {});
    }

    if (!S.burnage[key] && S.players.length) {
      var ids = S.players.map(function (p) { return p.id; }).join(',');
      apiFetch('/burnage?season=' + encodeURIComponent(cfg.season || '') +
               '&phase=' + (S.phase + 1) + '&round=' + round + '&team_code=' + encodeURIComponent(teamCode) +
               '&player_ids=' + ids)
        .then(function (res) {
          var b = Object.assign({}, S.burnage);
          b[key] = res || {};
          setState({ burnage: b });
        })
        .catch(function () {});
    }
  }

  function validateTeamRound(teamCode, round) {
    apiFetch('/appearances/validate', {
      method: 'POST',
      body: JSON.stringify({ season: cfg.season || '', phase: S.phase + 1, round: round, team_code: teamCode })
    }).then(function () {
      var key = burnageKey(teamCode, round);
      var v = Object.assign({}, S.validations); v[key] = true;
      var b = Object.assign({}, S.burnage); delete b[key];
      setState({ validations: v, burnage: b });
    }).catch(function (err) { alert('Erreur : ' + err.message); });
  }

  function unvalidateTeamRound(teamCode, round) {
    if (!confirm('Annuler la validation de cette composition ? Cela peut modifier le statut de brûlage des journées suivantes.')) return;
    apiFetch('/appearances/validate', {
      method: 'DELETE',
      body: JSON.stringify({ season: cfg.season || '', phase: S.phase + 1, round: round, team_code: teamCode })
    }).then(function () {
      var key = burnageKey(teamCode, round);
      var v = Object.assign({}, S.validations); v[key] = false;
      var b = Object.assign({}, S.burnage); delete b[key];
      setState({ validations: v, burnage: b });
    }).catch(function (err) { alert('Erreur : ' + err.message); });
  }

  function isForfeit(teamCode, round) {
    return !!(S.forfeits['p' + (S.phase + 1)] || {})[burnageKey(teamCode, round)];
  }

  function setForfeit(teamCode, round, declared) {
    if (declared && !confirm('Déclarer le forfait de cette équipe pour la J' + round + ' ? Les règles de qualification des autres équipes en tiennent compte.')) return;
    var ph = S.phase + 1;
    apiFetch('/forfeits', {
      method: declared ? 'POST' : 'DELETE',
      body: JSON.stringify({ season: cfg.season || '', phase: ph, round: round, team_code: teamCode })
    }).then(function () {
      var fo = Object.assign({}, S.forfeits);
      var map = Object.assign({}, fo['p' + ph] || {});
      if (declared) map[burnageKey(teamCode, round)] = true; else delete map[burnageKey(teamCode, round)];
      fo['p' + ph] = map;
      // Un forfait change la qualification des autres équipes : tout le brûlage est à recalculer.
      setState({ forfeits: fo, burnage: {} });
      loadBurnageContext(currentTeamCode(), S.journeeN);
    }).catch(function (err) { alert('Erreur : ' + err.message); });
  }

  function invalidateBurnage(teamCode, round) {
    var b = Object.assign({}, S.burnage);
    delete b[burnageKey(teamCode, round)];
    setState({ burnage: b });
  }

  function assignSlot(teamCode, slotNum, playerId) {
    var ph = S.phase + 1, rn = S.journeeN, se = cfg.season || '';
    var compos = S.compositions.filter(function (c) {
      return !(c.team_code === teamCode && c.slot_number === slotNum);
    });
    compos.push({ team_code: teamCode, slot_number: slotNum, player_id: playerId, phase: ph, round: rn, season: se });
    invalidateBurnage(teamCode, rn);
    setState({ compositions: compos, picker: null, pickerQ: '' });
    apiFetch('/compositions', {
      method: 'POST',
      body: JSON.stringify({ team_code: teamCode, slot_number: slotNum, player_id: playerId, phase: ph, round: rn, season: se })
    }).then(function () {
      loadBurnageContext(teamCode, rn);
    }).catch(function () { loadCompositions(); });
  }

  function removeSlot(teamCode, slotNum) {
    var ph = S.phase + 1, rn = S.journeeN, se = cfg.season || '';
    var compos = S.compositions.map(function (c) {
      return (c.team_code === teamCode && c.slot_number === slotNum)
        ? Object.assign({}, c, { player_id: null }) : c;
    });
    invalidateBurnage(teamCode, rn);
    setState({ compositions: compos });
    apiFetch('/compositions', {
      method: 'DELETE',
      body: JSON.stringify({ team_code: teamCode, slot_number: slotNum, phase: ph, round: rn, season: se })
    }).then(function () {
      loadBurnageContext(teamCode, rn);
    }).catch(function () { loadCompositions(); });
  }

  function syncPlayers() {
    setState({ syncing: true, syncError: null });
    apiFetch('/players/sync', { method: 'POST' })
      .then(function (res) {
        return loadAll().then(function () {
          setState({ syncError: null });
          if (res && res.message) alert('✅ ' + res.message);
        });
      })
      .catch(function (err) {
        setState({ syncError: String(err) });
      })
      .finally(function () { setState({ syncing: false }); });
  }

  // ═══════════════════════════════════════════
  // COMPUTED
  // ═══════════════════════════════════════════
  function getPlayer(id) {
    var sid = String(id);
    return S.players.find(function (p) { return String(p.id) === sid; });
  }

  function getAvail(playerId) {
    var sid = String(playerId);
    var av = S.availabilities.find(function (a) {
      return String(a.player_id) === sid &&
             Number(a.phase) === (S.phase + 1) &&
             Number(a.round) === S.journeeN;
    });
    return av ? av.status : 'unknown';
  }

  function getAvailForRound(playerId, phase, round) {
    var sid = String(playerId);
    var av = S.availabilities.find(function (a) {
      return String(a.player_id) === sid &&
             Number(a.phase) === phase &&
             Number(a.round) === round;
    });
    return av ? av.status : 'unknown';
  }

  function getCompoSlots(teamCode) {
    return S.compositions
      .filter(function (c) { return c.team_code === teamCode; })
      .sort(function (a, b) { return a.slot_number - b.slot_number; });
  }

  function getSquadIds(teamCode, phase) {
    return S.squads
      .filter(function (s) { return s.team_code === teamCode && s.phase === phase; })
      .map(function (s) { return s.player_id; });
  }

  function isInSquad(playerId, teamCode, phase) {
    return S.squads.some(function (s) {
      return s.team_code === teamCode && s.phase === phase && String(s.player_id) === String(playerId);
    });
  }

  function loadSquads() {
    apiFetch('/phase-squads?season=' + encodeURIComponent(cfg.season || '') + '&phase=' + (S.phase + 1))
      .then(function (res) { setState({ squads: res || [] }); })
      .catch(function () {});
  }

  function filteredPlayers() {
    var q = S.searchQ.toLowerCase();
    return S.players.filter(function (p) {
      var name = ((p.first_name || '') + ' ' + (p.last_name || '')).toLowerCase();
      if (q && !name.includes(q)) return false;
      var f = S.playerFilter;
      if (f === 'all') return true;
      if (f === 'ko')    return getAvail(p.id) === 'unavailable';
      if (f === 'capt')  return !!p.is_captain;
      if (f === 'E')     return !!p.is_foreign;
      if (f === 'jeune') return !!p.is_young;
      if (f === 'brule') return !!p.is_burned;
      return true;
    });
  }

  // ═══════════════════════════════════════════
  // RENDER HELPERS
  // ═══════════════════════════════════════════
  function badge(text, color, dark, xs) {
    var pal = {
      primary: { bg: dark ? C.priSoftD : C.priSoft, fg: dark ? '#bfdbfe' : C.priInk },
      ok:      { bg: dark ? '#14532d'  : C.okSoft,  fg: dark ? '#86efac' : '#15803d' },
      warn:    { bg: dark ? '#713f12'  : C.warnSoft, fg: dark ? '#fde68a' : '#a16207' },
      danger:  { bg: dark ? '#7f1d1d'  : C.errSoft,  fg: dark ? '#fca5a5' : '#b91c1c' },
      purple:  { bg: dark ? '#3b1e6b'  : C.purpSoft, fg: dark ? '#c4b5fd' : '#6d28d9' },
      neutral: { bg: dark ? C.surf2D   : '#f1f5f9',  fg: dark ? '#cbd5e1' : C.ink2 }
    };
    var p = pal[color] || pal.neutral;
    var sz = xs ? '10px' : '11px';
    var pad = xs ? '2px 6px' : '3px 8px';
    return '<span style="display:inline-flex;align-items:center;gap:4px;background:' + p.bg + ';color:' + p.fg + ';font-size:' + sz + ';font-weight:600;padding:' + pad + ';border-radius:999px;line-height:1.2;white-space:nowrap">' + esc(text) + '</span>';
  }

  function playerBadges(p, dark, burnStatus) {
    var h = '<div style="display:flex;gap:3px;flex-wrap:wrap;margin-top:3px">';
    if (p.is_active === false) h += badge('🗑️ Hors effectif', 'neutral', dark, true);
    if (p.is_captain) h += badge('© Cap.', 'primary', dark, true);
    if (p.is_foreign) h += badge('E', 'purple', dark, true);
    if (p.is_young)   h += badge('Jeune', 'ok', dark, true);
    if (p.is_mutation)h += badge('Mut.', 'warn', dark, true);
    if (p.is_burned)  h += badge('🔥 Brûlé', 'danger', dark, true);
    if (burnStatus && burnStatus.burned) {
      var burnLabels = { rule3: '⚠ Limite J2', min_points: '⚠ Points min.', foreign: '⚠ 2e étranger', forfeit: '⚠ Forfait' };
      h += badge(burnLabels[burnStatus.reason] || '🔥 Brûlé', 'danger', dark, true);
    }
    h += '</div>';
    return h;
  }

  function avatar(init, avail, dark, size) {
    size = size || 38;
    var grad = avail === 'unavailable'
      ? 'linear-gradient(135deg,#ef4444,#b91c1c)'
      : 'linear-gradient(135deg,' + C.pri + ',' + C.priInk + ')';
    var dot = avColor(avail, dark);
    var surf = tk(dark).surf;
    return '<div style="position:relative;width:' + size + 'px;height:' + size + 'px;flex-shrink:0">' +
      '<div style="width:' + size + 'px;height:' + size + 'px;border-radius:50%;background:' + grad + ';color:white;display:flex;align-items:center;justify-content:center;font-size:' + Math.round(size * 0.34) + 'px;font-weight:700">' + esc(init) + '</div>' +
      '<div style="position:absolute;bottom:-2px;right:-2px;width:13px;height:13px;border-radius:50%;background:' + dot + ';border:2px solid ' + surf + '"></div>' +
    '</div>';
  }

  function topBar(title, sub, dark, back, action) {
    var t = tk(dark);
    return '<div style="padding:14px 16px 12px;display:flex;align-items:flex-start;gap:10px;border-bottom:1px solid ' + t.bord + ';background:' + t.surf + ';position:sticky;top:0;z-index:5">' +
      (back ? '<button data-action="back" style="width:32px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink + ';font-size:18px;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0">‹</button>' : '') +
      '<div style="flex:1;min-width:0">' +
        '<div style="font-size:17px;font-weight:700;color:' + t.ink + ';letter-spacing:-0.2px">' + esc(title) + '</div>' +
        (sub ? '<div style="font-size:12px;color:' + t.ink2 + ';margin-top:2px">' + esc(sub) + '</div>' : '') +
      '</div>' +
      (action || '') +
    '</div>';
  }

  function sectionWrap(title, content, dark) {
    var t = tk(dark);
    return '<div style="margin-bottom:14px">' +
      '<div style="font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px;padding:0 2px">' + esc(title) + '</div>' +
      '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:10px;padding:4px 0;overflow:hidden">' +
        content +
      '</div></div>';
  }

  function deleteRestoreBtn(p, dark, size) {
    var t = tk(dark);
    size = size || 34;
    var fs = size >= 34 ? '14px' : '13px';
    if (p.is_active === false) {
      var restoreBtn = '<button data-action="player-restore" data-value="' + esc(p.id) + '" title="Réintégrer à l\'effectif" style="width:' + size + 'px;height:' + size + 'px;border-radius:8px;background:' + t.surf2 + ';color:' + t.ink2 + ';border:none;display:flex;align-items:center;justify-content:center;font-size:' + fs + ';cursor:pointer;flex-shrink:0">♻️</button>';
      var wipeBtn = '<button data-action="player-wipe-open" data-value="' + esc(p.id) + '" title="Supprimer définitivement" style="width:' + size + 'px;height:' + size + 'px;border-radius:8px;background:' + C.errSoft + ';color:' + C.err + ';border:none;display:flex;align-items:center;justify-content:center;font-size:' + fs + ';cursor:pointer;flex-shrink:0">❌</button>';
      return restoreBtn + wipeBtn;
    }
    return '<button data-action="player-delete-open" data-value="' + esc(p.id) + '" title="Supprimer de l\'effectif" style="width:' + size + 'px;height:' + size + 'px;border-radius:8px;background:' + C.errSoft + ';color:' + C.err + ';border:none;display:flex;align-items:center;justify-content:center;font-size:' + fs + ';cursor:pointer;flex-shrink:0">🗑️</button>';
  }

  function renderDeleteConfirm() {
    var dark = S.dark; var t = tk(dark);
    var p = getPlayer(S.deleteConfirm);
    var name = p ? ((p.first_name || '') + ' ' + (p.last_name || '')) : 'ce joueur';
    return '<div style="position:absolute;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px">' +
      '<div data-action="player-delete-cancel" style="position:absolute;inset:0;background:rgba(0,0,0,0.5)"></div>' +
      '<div style="position:relative;background:' + t.surf + ';border-radius:14px;padding:20px;max-width:320px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,0.3)">' +
        '<div style="font-size:28px;margin-bottom:8px">🗑️</div>' +
        '<div style="font-size:15px;font-weight:700;color:' + t.ink + ';margin-bottom:6px">Supprimer ' + esc(name) + ' de l’effectif ?</div>' +
        '<div style="font-size:12px;color:' + t.ink2 + ';line-height:1.5;margin-bottom:16px">Il ne sera plus sélectionnable dans les compositions ni les effectifs de phase. Son historique est conservé et vous pourrez le réintégrer à tout moment depuis sa fiche.</div>' +
        '<div style="display:flex;gap:8px">' +
          '<button data-action="player-delete-cancel" style="flex:1;padding:10px;border-radius:8px;border:1px solid ' + t.bord + ';background:transparent;color:' + t.ink + ';font-size:13px;font-weight:600;cursor:pointer">Annuler</button>' +
          '<button data-action="player-delete-confirm" style="flex:1;padding:10px;border-radius:8px;border:none;background:' + C.err + ';color:white;font-size:13px;font-weight:700;cursor:pointer">Supprimer</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function renderBulkResult() {
    var dark = S.dark; var t = tk(dark);
    var results = S.bulkResult || [];
    var sent    = results.filter(function (r) { return r.status === 'sent'; });
    var noEmail = results.filter(function (r) { return r.status === 'no_email'; });
    var failed  = results.filter(function (r) { return r.status === 'mail_failed' || r.status === 'not_found'; });
    var line = function (r) {
      return '<div style="font-size:12px;color:' + t.ink2 + ';padding:2px 0">' + esc(r.name || ('#' + r.player_id)) + '</div>';
    };
    return '<div style="position:absolute;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px">' +
      '<div data-action="bulk-result-close" style="position:absolute;inset:0;background:rgba(0,0,0,0.5)"></div>' +
      '<div style="position:relative;background:' + t.surf + ';border-radius:14px;padding:20px;max-width:340px;width:100%;max-height:70vh;overflow-y:auto;box-shadow:0 10px 40px rgba(0,0,0,0.3)">' +
        '<div style="font-size:28px;margin-bottom:8px">✉️</div>' +
        '<div style="font-size:15px;font-weight:700;color:' + t.ink + ';margin-bottom:10px">' + sent.length + ' lien' + (sent.length !== 1 ? 's' : '') + ' envoyé' + (sent.length !== 1 ? 's' : '') + ' (valable 3 mois)</div>' +
        (noEmail.length ? '<div style="font-size:11px;font-weight:700;color:' + t.ink2 + ';text-transform:uppercase;letter-spacing:0.4px;margin-top:10px">Sans e-mail (' + noEmail.length + ')</div>' + noEmail.map(line).join('') : '') +
        (failed.length ? '<div style="font-size:11px;font-weight:700;color:' + C.err + ';text-transform:uppercase;letter-spacing:0.4px;margin-top:10px">Échec d’envoi (' + failed.length + ')</div>' + failed.map(line).join('') : '') +
        '<button data-action="bulk-result-close" style="margin-top:16px;width:100%;padding:10px;border-radius:8px;border:none;background:' + C.pri + ';color:white;font-size:13px;font-weight:700;cursor:pointer">OK</button>' +
      '</div>' +
    '</div>';
  }

  function renderWipeConfirm() {
    var dark = S.dark; var t = tk(dark);
    var p = getPlayer(S.wipeConfirm);
    var name = p ? ((p.first_name || '') + ' ' + (p.last_name || '')) : 'ce joueur';
    return '<div style="position:absolute;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:20px">' +
      '<div data-action="player-wipe-cancel" style="position:absolute;inset:0;background:rgba(0,0,0,0.5)"></div>' +
      '<div style="position:relative;background:' + t.surf + ';border-radius:14px;padding:20px;max-width:320px;width:100%;box-shadow:0 10px 40px rgba(0,0,0,0.3)">' +
        '<div style="font-size:28px;margin-bottom:8px">❌</div>' +
        '<div style="font-size:15px;font-weight:700;color:' + t.ink + ';margin-bottom:6px">Supprimer définitivement ' + esc(name) + ' ?</div>' +
        '<div style="font-size:12px;color:' + t.ink2 + ';line-height:1.5;margin-bottom:16px">Action <strong>irréversible</strong> : le joueur et tout son historique (disponibilités, compositions, présences en match) seront effacés de la base de données. Impossible à annuler.</div>' +
        '<div style="display:flex;gap:8px">' +
          '<button data-action="player-wipe-cancel" style="flex:1;padding:10px;border-radius:8px;border:1px solid ' + t.bord + ';background:transparent;color:' + t.ink + ';font-size:13px;font-weight:600;cursor:pointer">Annuler</button>' +
          '<button data-action="player-wipe-confirm" style="flex:1;padding:10px;border-radius:8px;border:none;background:' + C.err + ';color:white;font-size:13px;font-weight:700;cursor:pointer">Supprimer définitivement</button>' +
        '</div>' +
      '</div>' +
    '</div>';
  }

  function infoRow(label, value, dark, hi) {
    var t = tk(dark);
    return '<div style="display:flex;justify-content:space-between;padding:8px 12px;font-size:12px;align-items:center">' +
      '<span style="color:' + t.ink2 + '">' + esc(label) + '</span>' +
      '<span style="color:' + (hi ? C.pri : t.ink) + ';font-weight:' + (hi ? '600' : '500') + '">' + value + '</span>' +
    '</div>';
  }

  // ═══════════════════════════════════════════
  // BOTTOM NAV
  // ═══════════════════════════════════════════
  function renderNav() {
    var t = tk(S.dark);
    var tabs = [
      { id: 'dashboard', icon: '🏠', label: 'Accueil' },
      { id: 'journees',  icon: '📅', label: 'Journées' },
      { id: 'joueurs',   icon: '🏓', label: 'Joueurs' },
      { id: 'squads',    icon: '📋', label: 'Effectifs' },
      { id: 'reglages',  icon: '⚙️',  label: 'Réglages' }
    ];
    // padding-bottom : en mode application, viewport-fit=cover etend le viewport
    // sous la barre de gestes Android / l'indicateur d'accueil iOS. Vaut 0 ailleurs.
    var h = '<nav style="display:flex;border-top:1px solid ' + t.bord + ';background:' + t.surf + ';padding:4px 0;padding-bottom:calc(4px + env(safe-area-inset-bottom, 0px));flex-shrink:0">';
    tabs.forEach(function (tab) {
      var active = S.tab === tab.id;
      var color  = active ? C.priInk : t.ink2;
      var pillBg = active ? t.priSoft : 'transparent';
      h += '<button data-action="tab" data-value="' + tab.id + '" style="flex:1;border:none;background:transparent;padding:6px 4px 8px;display:flex;flex-direction:column;align-items:center;gap:2px;cursor:pointer;color:' + color + ';position:relative">' +
        '<div style="width:52px;height:28px;border-radius:14px;background:' + pillBg + ';display:flex;align-items:center;justify-content:center">' +
          '<span style="font-size:17px;line-height:1">' + tab.icon + '</span>' +
        '</div>' +
        '<span style="font-size:10px;font-weight:' + (active ? '700' : '600') + '">' + tab.label + '</span>' +
      '</button>';
    });
    h += '</nav>';
    return h;
  }

  // ═══════════════════════════════════════════
  // DASHBOARD
  // ═══════════════════════════════════════════
  function renderDashboard() {
    var dark = S.dark; var t = tk(dark);
    var jj = S.journees;
    var done = jj.filter(function (j) { return j.done; }).length;
    var tc = S.teams.length;

    // Prochaine journée globale (toutes phases confondues)
    var nextGlobal = findGlobalNext();
    // Affiche la journée globale si elle est dans une autre phase que la courante
    var showGlobal = !!(nextGlobal && nextGlobal.phase !== S.phase + 1);
    // Journée courante dans la phase affichée (première avec date future)
    var nextLocal  = jj.find(function (j) { return j.current; }) || jj[jj.length - 1];
    // Objet unifié : { round, date, phase, complete }
    var next = showGlobal
      ? nextGlobal
      : { round: nextLocal.n, date: nextLocal.date, phase: S.phase + 1, complete: nextLocal.complete };

    var h = '<div style="padding:16px;display:flex;flex-direction:column;gap:14px">';

    // Phase switcher
    h += '<div style="display:flex;background:' + t.surf2 + ';padding:3px;border-radius:10px">';
    ['Phase 1', 'Phase 2'].forEach(function (lbl, i) {
      var active = S.phase === i;
      h += '<button data-action="phase" data-value="' + i + '" style="flex:1;padding:8px 6px;border:none;border-radius:8px;background:' + (active ? t.surf : 'transparent') + ';color:' + (active ? t.ink : t.ink2) + ';font-size:11px;font-weight:600;cursor:pointer;box-shadow:' + (active ? '0 1px 2px rgba(0,0,0,0.08)' : 'none') + '">' + lbl + '</button>';
    });
    h += '</div>';

    // Next journée card
    var nextPhaseLabel = showGlobal ? ' · Phase ' + next.phase : '';
    var nextAction = showGlobal
      ? 'data-action="goto-phase-journee" data-phase="' + (next.phase - 1) + '" data-journee="' + next.round + '"'
      : 'data-action="journee" data-value="' + next.round + '"';
    h += '<div style="background:linear-gradient(135deg,' + C.pri + ',' + C.priInk + ');color:white;border-radius:14px;padding:14px;position:relative;overflow:hidden">' +
      '<div style="font-size:11px;opacity:0.85;font-weight:500;letter-spacing:0.4px;text-transform:uppercase">Prochaine journée' + esc(nextPhaseLabel) + '</div>' +
      '<div style="font-size:22px;font-weight:700;margin-top:4px;letter-spacing:-0.3px">J' + next.round + (next.date ? ' · ' + esc(next.date) : '') + '</div>' +
      '<div style="font-size:12px;opacity:0.9;margin-top:2px">' + countCompleteTeams(next.phase, next.round) + ' / ' + tc + ' compositions complètes</div>' +
      '<div style="display:flex;gap:6px;margin-top:12px">' +
        '<button ' + nextAction + ' style="background:white;color:' + C.priInk + ';border:none;padding:8px 14px;border-radius:8px;font-size:12px;font-weight:700;cursor:pointer">Préparer les compos →</button>' +
      '</div>' +
      '<div style="position:absolute;right:-20px;top:-30px;font-size:90px;opacity:0.12;line-height:1;pointer-events:none">🏓</div>' +
    '</div>';

    // Journées list
    h += '<div><div style="font-size:11px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;padding:0 2px">Journées de la phase</div>';
    h += '<div style="display:flex;flex-direction:column;gap:6px">';
    jj.forEach(function (j) {
      var cur = j.current;
      var numBg = j.done ? C.okSoft : cur ? t.priSoft : t.surf2;
      var numFg = j.done ? '#15803d' : cur ? C.priInk : t.ink2;
      h += '<button data-action="journee" data-value="' + j.n + '" style="display:flex;align-items:center;gap:12px;padding:10px 12px;background:' + t.surf + ';border:1px solid ' + (cur ? C.pri : t.bord) + ';border-radius:10px;cursor:pointer;text-align:left;width:100%;box-shadow:' + (cur ? '0 0 0 3px ' + C.pri + '22' : 'none') + '">' +
        '<div style="width:36px;height:36px;border-radius:8px;background:' + numBg + ';color:' + numFg + ';display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700;flex-shrink:0">J' + j.n + '</div>' +
        '<div style="flex:1;min-width:0;text-align:left">' +
          '<div style="font-size:13px;font-weight:600;color:' + t.ink + '">' + (j.date ? esc(j.date) : 'Journée ' + j.n) + '</div>' +
          '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:1px">' +
            (j.done ? 'Terminée' : (countCompleteTeams(S.phase + 1, j.n) + ' / ' + tc + ' compos')) +
          '</div>' +
        '</div>' +
        (j.done ? '<span style="font-size:14px">✅</span>' : '') +
        '<span style="color:' + t.ink2 + ';font-size:16px">›</span>' +
      '</button>';
    });
    h += '</div></div></div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // JOURNÉE
  // ═══════════════════════════════════════════
  function renderJournee() {
    var dark = S.dark; var t = tk(dark);
    var jn = S.journeeN;
    var j = S.journees.find(function (x) { return x.n === jn; }) || S.journees[0];
    var tc = S.teams.length;
    var teams = S.teams;

    var tcLabel = tc ? ((j.complete || 0) + '/' + tc + ' compos') : 'Pas d\'équipe configurée';
    var h = topBar('Journée ' + jn, (j.date ? esc(j.date) + ' · ' : '') + tcLabel, dark, true);

    // Journée tabs (scrollable)
    h += '<div style="display:flex;overflow-x:auto;padding:10px 12px;gap:6px;background:' + t.surf + ';border-bottom:1px solid ' + t.bord + ';-webkit-overflow-scrolling:touch">';
    S.journees.forEach(function (jx) {
      var active = jx.n === jn;
      h += '<button data-action="journee" data-value="' + jx.n + '" style="padding:5px 11px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid ' + (active ? C.pri : t.bord) + ';background:' + (active ? C.pri : t.surf) + ';color:' + (active ? 'white' : t.ink2) + ';cursor:pointer;white-space:nowrap;flex-shrink:0">J' + jx.n + '</button>';
    });
    h += '</div>';

    // Team carousel
    if (teams.length) {
      h += '<div style="display:flex;overflow-x:auto;padding:8px 12px 4px;gap:8px;-webkit-overflow-scrolling:touch">';
      teams.forEach(function (team, i) {
        var active = i === S.activeTeamI;
        var slots = getCompoSlots(team.code || team.id || '');
        var filled = slots.filter(function (s) { return s.player_id; }).length;
        h += '<button data-action="team-tab" data-value="' + i + '" style="padding:6px 10px;border-radius:8px;border:1px solid ' + (active ? (team.color || C.pri) : t.bord) + ';background:' + (active ? (team.color || C.pri) : t.surf) + ';color:' + (active ? 'white' : t.ink) + ';cursor:pointer;font-size:11px;font-weight:600;white-space:nowrap;display:flex;align-items:center;gap:6px;flex-shrink:0">' +
          '<span>' + esc(team.code || team.id || 'E' + (i + 1)) + '</span>' +
          '<span style="opacity:0.7">' + filled + '/4</span>' +
        '</button>';
      });
      h += '</div>';
      if (teams[S.activeTeamI]) {
        h += renderTeamCompo(teams[S.activeTeamI], dark);
      }
    } else {
      h += '<div style="padding:32px;text-align:center;color:' + t.ink2 + ';font-size:13px"><div style="font-size:32px;margin-bottom:8px">📋</div>Aucune équipe.<br><small>Configurez vos équipes dans les réglages admin.</small></div>';
    }
    return h;
  }

  function renderTeamCompo(team, dark) {
    var t = tk(dark);
    var tc = team.color || C.pri;
    var tcode = team.code || team.id || '';
    var round = S.journeeN;
    var slots = getCompoSlots(tcode);
    var arr = [null, null, null, null];
    slots.forEach(function (s) { if (s.slot_number >= 1 && s.slot_number <= 4) arr[s.slot_number - 1] = s; });
    var filled = arr.filter(function (s) { return s && s.player_id; }).length;

    var bKey      = burnageKey(tcode, round);
    var burnMap   = S.burnage[bKey] || {};
    var validated = !!S.validations[bKey];
    var forfeit   = isForfeit(tcode, round);

    var h = '<div style="padding:12px">';
    // Team header
    h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-left:4px solid ' + tc + ';border-radius:10px;padding:12px;margin-bottom:10px">' +
      '<div style="display:flex;align-items:center;gap:8px">' +
        '<div style="flex:1"><div style="font-size:15px;font-weight:700;color:' + t.ink + '">' + esc(team.name || team.id || 'Équipe') + '</div><div style="font-size:11px;color:' + t.ink2 + '">' + esc(team.level || '') + '</div></div>' +
        (validated ? badge('✓ Journée validée', 'ok', dark, true) : '') +
        (forfeit ? badge('Forfait', 'danger', dark, true) : badge(filled + '/4', filled === 4 ? 'ok' : 'warn', dark)) +
      '</div>';
    var ghostBtn = 'margin-top:10px;width:100%;padding:9px;border-radius:8px;background:transparent;border:1px solid ' + t.bord + ';color:' + t.ink2 + ';font-size:12px;font-weight:600;cursor:pointer';
    var roundAttrs = ' data-team="' + esc(tcode) + '" data-round="' + round + '"';
    if (validated) {
      h += '<button data-action="round-unvalidate"' + roundAttrs + ' style="' + ghostBtn + '">Annuler la validation</button>';
    } else if (forfeit) {
      h += '<div style="margin-top:10px;font-size:12px;color:' + t.ink2 + ';line-height:1.5">Forfait déclaré pour cette journée. Les compositions des autres équipes en tiennent compte (règlement FFTT II.112.2).</div>';
      h += '<button data-action="round-forfeit-cancel"' + roundAttrs + ' style="' + ghostBtn + '">Annuler le forfait</button>';
    } else {
      // Les journées jouées sont importées des feuilles FFTT ; la validation
      // manuelle reste possible (exempt, feuille pas encore publiée…).
      if (filled === 4) {
        h += '<button data-action="round-validate"' + roundAttrs + ' style="margin-top:10px;width:100%;padding:9px;border-radius:8px;background:' + C.pri + ';border:none;color:white;font-size:12px;font-weight:600;cursor:pointer">✓ Valider cette composition (rencontre jouée)</button>';
      }
      h += '<button data-action="round-forfeit"' + roundAttrs + ' style="' + ghostBtn + '">Déclarer forfait</button>';
    }
    h += '</div>';

    // Slots
    h += '<div style="font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;padding:0 2px">4 slots joueurs</div>';
    h += '<div style="display:flex;flex-direction:column;gap:8px">';
    arr.forEach(function (slot, i) {
      var num = i + 1;
      var tcodeAttr = esc(tcode);
      if (!slot || !slot.player_id) {
        h += '<button data-action="slot-open" data-team="' + tcodeAttr + '" data-slot="' + num + '" style="width:100%;display:flex;align-items:center;gap:10px;padding:10px;background:transparent;border:1px dashed ' + t.bord + ';border-radius:10px;cursor:pointer;text-align:left;min-height:56px">' +
          '<div style="width:22px;height:22px;border-radius:6px;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center">' + num + '</div>' +
          '<div style="flex:1;font-size:13px;color:' + t.ink2 + ';font-weight:500">Slot vide<div style="font-size:11px;opacity:0.7;margin-top:2px">Toucher pour ajouter</div></div>' +
          '<span style="color:' + C.pri + ';font-size:18px;font-weight:600">+</span>' +
        '</button>';
      } else {
        var p = getPlayer(slot.player_id);
        if (!p) { h += '<div style="padding:10px;background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:10px;font-size:12px;color:' + t.ink2 + '">Joueur introuvable</div>'; return; }
        var avail = getAvail(p.id);
        var burnStatus = burnMap[p.id];
        var danger = avail === 'unavailable' || p.is_burned || (burnStatus && burnStatus.burned);
        var init = initials((p.first_name || '') + ' ' + (p.last_name || ''));
        h += '<div style="display:flex;align-items:center;background:' + (danger ? C.errSoft : t.surf) + ';border:' + (danger ? '2px' : '1px') + ' solid ' + (danger ? C.err : t.bord) + ';border-radius:10px;overflow:hidden">' +
          '<button data-action="player" data-value="' + esc(p.id) + '" style="flex:1;display:flex;align-items:center;gap:10px;padding:10px;background:transparent;border:none;cursor:pointer;text-align:left;min-width:0">' +
            '<div style="width:22px;height:22px;border-radius:6px;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:11px;font-weight:700;display:flex;align-items:center;justify-content:center;flex-shrink:0">' + num + '</div>' +
            '<div style="width:34px;height:34px;border-radius:50%;background:linear-gradient(135deg,' + C.pri + ',' + C.priInk + ');color:white;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:700;flex-shrink:0">' + esc(init) + '</div>' +
            '<div style="flex:1;min-width:0">' +
              '<div style="font-size:13px;font-weight:600;color:' + t.ink + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</div>' +
              '<div style="display:flex;align-items:center;gap:6px;margin-top:3px">' +
                '<span style="font-size:11px;color:' + t.ink2 + '">' + (p.ranking || 0) + '</span>' +
                '<span style="font-size:11px;color:' + t.ink2 + '">·</span>' +
                '<span style="font-size:11px;color:' + avColor(avail, dark) + '">' + avEmoji(avail) + ' ' + avStatus(avail) + '</span>' +
              '</div>' +
              playerBadges(p, dark, burnStatus) +
            '</div>' +
          '</button>' +
          '<button data-action="slot-remove" data-team="' + tcodeAttr + '" data-slot="' + num + '" style="width:40px;height:100%;min-height:56px;border:none;border-left:1px solid ' + t.bord + ';background:transparent;color:' + t.ink2 + ';font-size:15px;cursor:pointer;flex-shrink:0" title="Retirer">✕</button>' +
        '</div>';
      }
    });
    h += '</div>';
    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // PLAYER PICKER
  // ═══════════════════════════════════════════
  function renderPicker() {
    var dark = S.dark; var t = tk(dark);
    var teamCode = S.picker.teamCode;
    var slotNum  = S.picker.slotNum;
    var q = S.pickerQ.toLowerCase();

    var team = S.teams.find(function (tm) { return (tm.code || tm.id) === teamCode; });
    var tc       = team ? (team.color || C.pri) : C.pri;
    var teamName = team ? (team.name || teamCode) : teamCode;

    // Joueurs déjà placés dans une AUTRE équipe ce round
    var elsewhere = {};
    S.compositions.forEach(function (c) {
      if (c.player_id && c.team_code !== teamCode) elsewhere[c.player_id] = c.team_code;
    });
    // Joueurs déjà dans CETTE équipe (autre slot)
    var inTeam = {};
    S.compositions.forEach(function (c) {
      if (c.player_id && c.team_code === teamCode && c.slot_number !== slotNum) inTeam[c.player_id] = true;
    });

    // Filtrage + tri : squad en tête, puis joueurs habituels, puis classement DESC
    var squadIds = getSquadIds(teamCode, S.phase + 1);
    var filtered = S.players.filter(function (p) {
      if (p.is_active === false) return false; // supprimé de l'effectif : non sélectionnable
      if (!q) return true;
      return ((p.first_name || '') + ' ' + (p.last_name || '')).toLowerCase().includes(q)
          || String(p.ranking || '').includes(q);
    }).sort(function (a, b) {
      var aInSquad = squadIds.indexOf(a.id) !== -1 ? 0 : 1;
      var bInSquad = squadIds.indexOf(b.id) !== -1 ? 0 : 1;
      if (aInSquad !== bInSquad) return aInSquad - bInSquad;
      var au = (a.usual_team === teamCode) ? 0 : 1;
      var bu = (b.usual_team === teamCode) ? 0 : 1;
      return au !== bu ? au - bu : (b.ranking || 0) - (a.ranking || 0);
    });

    var h = '<div style="position:absolute;inset:0;z-index:50;display:flex;flex-direction:column">';

    // Backdrop semi-transparent (tap = fermer)
    h += '<div data-action="picker-close" style="background:rgba(0,0,0,0.45);flex:0 0 64px;cursor:pointer"></div>';

    // Panel
    h += '<div style="flex:1;background:' + t.surf + ';display:flex;flex-direction:column;overflow:hidden">';

    // En-tête
    h += '<div style="padding:14px 16px 10px;border-bottom:1px solid ' + t.bord + ';display:flex;align-items:center;gap:10px;flex-shrink:0">';
    h += '<div style="width:8px;height:8px;border-radius:50%;background:' + tc + ';flex-shrink:0"></div>';
    h += '<div style="flex:1"><div style="font-size:15px;font-weight:700;color:' + t.ink + '">Choisir un joueur</div>';
    h += '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:1px">' + esc(teamName) + ' &mdash; Slot ' + slotNum + '</div></div>';
    h += '<button data-action="picker-close" style="width:28px;height:28px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:14px;cursor:pointer">✕</button>';
    h += '</div>';

    // Recherche
    h += '<div style="padding:10px 12px;border-bottom:1px solid ' + t.bord + ';flex-shrink:0">';
    h += '<div style="display:flex;align-items:center;gap:8px;background:' + t.surf2 + ';border-radius:8px;padding:8px 12px">';
    h += '<span style="font-size:13px">🔍</span>';
    h += '<input data-input="picker-search" value="' + esc(S.pickerQ) + '" placeholder="Rechercher…" autocomplete="off" style="flex:1;border:none;background:transparent;outline:none;color:' + t.ink + ';font-size:13px">';
    if (S.pickerQ) {
      h += '<button data-action="picker-clear" style="border:none;background:none;color:' + t.ink2 + ';cursor:pointer;font-size:14px;padding:0;line-height:1">✕</button>';
    }
    h += '</div></div>';

    // Liste joueurs
    h += '<div class="ttp-screen-content">';
    if (filtered.length === 0) {
      h += '<div style="padding:32px;text-align:center;color:' + t.ink2 + ';font-size:13px">Aucun joueur trouvé</div>';
    } else {
      var lastGroup = null;
      filtered.forEach(function (p) {
        var inSq = squadIds.indexOf(p.id) !== -1;
        var grp  = inSq ? 'Effectif de phase' : (p.usual_team === teamCode ? 'Joueurs habituels' : 'Autres joueurs');
        if (grp !== lastGroup) {
          lastGroup = grp;
          h += '<div style="padding:8px 12px 4px;font-size:10px;font-weight:600;text-transform:uppercase;letter-spacing:0.5px;color:' + t.ink2 + '">' + grp + '</div>';
        }

        var avail  = getAvail(p.id);
        var init   = initials((p.first_name || '') + ' ' + (p.last_name || ''));
        var busy   = !!elsewhere[p.id];   // placé ailleurs — déconseillé mais autorisé
        var taken  = !!inTeam[p.id];      // déjà dans ce slot ou autre slot de l'équipe
        var burnStatus = (S.burnage[burnageKey(teamCode, S.journeeN)] || {})[p.id];
        var grad   = avail === 'unavailable' ? 'linear-gradient(135deg,#ef4444,#b91c1c)' : 'linear-gradient(135deg,' + C.pri + ',' + C.priInk + ')';

        h += '<button data-action="slot-assign" data-player-id="' + p.id + '"'
           + (taken ? ' disabled' : '')
           + ' style="width:100%;display:flex;align-items:center;gap:10px;padding:10px 12px;background:transparent;border:none;border-bottom:1px solid ' + t.bord + ';cursor:' + (taken ? 'not-allowed' : 'pointer') + ';text-align:left;opacity:' + (taken ? '0.35' : '1') + '">';

        // Avatar
        h += '<div style="position:relative;flex-shrink:0">'
           + '<div style="width:36px;height:36px;border-radius:50%;background:' + grad + ';color:white;display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:700">' + esc(init) + '</div>'
           + '<div style="position:absolute;bottom:-1px;right:-1px;width:10px;height:10px;border-radius:50%;background:' + avColor(avail, dark) + ';border:2px solid ' + t.surf + '"></div>'
           + '</div>';

        // Infos
        h += '<div style="flex:1;min-width:0">';
        h += '<div style="font-size:13px;font-weight:600;color:' + t.ink + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</div>';
        h += '<div style="display:flex;align-items:center;gap:6px;margin-top:2px;flex-wrap:wrap">';
        h += '<span style="font-size:11px;color:' + t.ink2 + '">' + (p.ranking || 0) + '</span>';
        if (squadIds.indexOf(p.id) !== -1) h += badge('📋 ' + teamName, 'primary', dark, true);
        if (busy) h += badge('Déjà en ' + elsewhere[p.id], 'warn', dark, true);
        h += '</div>' + playerBadges(p, dark, burnStatus) + '</div>';

        h += (taken ? '' : '<span style="color:' + t.ink2 + ';font-size:18px;opacity:0.4">›</span>');
        h += '</button>';
      });
    }
    h += '</div></div></div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // JOUEURS
  // ═══════════════════════════════════════════
  function renderJoueurs() {
    var dark = S.dark; var t = tk(dark);
    var players = filteredPlayers();
    var bulkMode = S.bulkMode;
    var addBtn = '<button style="width:32px;height:32px;border-radius:8px;border:none;background:' + C.pri + ';color:white;font-size:18px;cursor:pointer">+</button>';
    var selectBtn = '<button data-action="bulk-mode-toggle" style="height:32px;padding:0 10px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink + ';font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap">' + (bulkMode ? 'Annuler' : 'Sélectionner') + '</button>';
    var h = topBar('Joueurs', S.players.length + ' licenciés', dark, false, '<div style="display:flex;gap:6px">' + selectBtn + (bulkMode ? '' : addBtn) + '</div>');

    // Search + filters
    h += '<div style="padding:12px;background:' + t.surf + ';border-bottom:1px solid ' + t.bord + '">';
    h += '<div style="display:flex;align-items:center;gap:8px;background:' + t.surf2 + ';padding:9px 12px;border-radius:10px">' +
      '<span style="font-size:14px;opacity:0.6">🔍</span>' +
      '<input data-input="search" value="' + esc(S.searchQ) + '" placeholder="Rechercher…" style="flex:1;border:none;background:transparent;outline:none;color:' + t.ink + ';font-size:13px">' +
    '</div>';
    var filters = [['all','Tous'],['ko','🚫 Indispo'],['capt','© Capitaines'],['E','E Étrangers'],['jeune','Jeunes'],['brule','🔥 Brûlés']];
    h += '<div style="display:flex;gap:6px;margin-top:10px;overflow-x:auto;-webkit-overflow-scrolling:touch">';
    filters.forEach(function (f) {
      var active = S.playerFilter === f[0];
      h += '<button data-action="filter" data-value="' + f[0] + '" style="padding:5px 10px;border-radius:999px;font-size:11px;font-weight:600;border:1px solid ' + (active ? C.pri : t.bord) + ';background:' + (active ? C.pri : t.surf) + ';color:' + (active ? 'white' : t.ink2) + ';cursor:pointer;white-space:nowrap;flex-shrink:0">' + f[1] + '</button>';
    });
    h += '</div></div>';

    h += '<div style="padding:12px">';
    var teams = S.teams;
    var shown = false;
    var knownCodes = teams.map(function (tm) { return tm.code || tm.id || ''; });
    if (teams.length) {
      teams.forEach(function (team) {
        var tp = players.filter(function (p) { return p.usual_team === (team.code || team.id || ''); });
        if (!tp.length) return;
        shown = true;
        h += '<div style="margin-bottom:16px">';
        h += '<div style="display:flex;align-items:center;gap:6px;padding:0 2px 6px;font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px">' +
          '<div style="width:6px;height:6px;border-radius:50%;background:' + (team.color || C.pri) + '"></div>' +
          esc(team.name || team.id || '') + (team.level ? ' · ' + esc(team.level) : '') +
        '</div>';
        h += '<div style="display:flex;flex-direction:column;gap:6px">';
        tp.forEach(function (p) { h += renderPlayerCard(p, dark, bulkMode); });
        h += '</div></div>';
      });
      // Joueurs dont l'équipe habituelle ne correspond à aucune équipe configurée
      var unmatched = players.filter(function (p) { return knownCodes.indexOf(p.usual_team) === -1; });
      if (unmatched.length) {
        shown = true;
        h += '<div style="margin-bottom:16px">';
        h += '<div style="display:flex;align-items:center;gap:6px;padding:0 2px 6px;font-size:10px;color:' + t.ink2 + ';font-weight:600;text-transform:uppercase;letter-spacing:0.5px">' +
          '<div style="width:6px;height:6px;border-radius:50%;background:' + t.ink2 + '"></div>Sans équipe assignée</div>';
        h += '<div style="display:flex;flex-direction:column;gap:6px">';
        unmatched.forEach(function (p) { h += renderPlayerCard(p, dark, bulkMode); });
        h += '</div></div>';
      }
    }
    // Pas d'équipes configurées : liste à plat
    if (!teams.length) {
      h += '<div style="display:flex;flex-direction:column;gap:6px">';
      players.forEach(function (p) { h += renderPlayerCard(p, dark, bulkMode); });
      h += '</div>';
      shown = players.length > 0;
    }
    if (!shown) {
      h += '<div style="padding:40px;text-align:center;color:' + t.ink2 + ';font-size:13px"><div style="font-size:36px;margin-bottom:8px">🔍</div>Aucun joueur trouvé</div>';
    }
    h += '</div>';

    if (bulkMode) {
      var selCount   = Object.keys(S.bulkSelected).length;
      var visibleIds = players.map(function (p) { return String(p.id); });
      var allSel     = visibleIds.length > 0 && visibleIds.every(function (id) { return S.bulkSelected[id]; });
      h += '<div style="position:sticky;bottom:0;left:0;right:0;background:' + t.surf + ';border-top:1px solid ' + t.bord + ';padding:10px 12px;display:flex;align-items:center;gap:8px;box-shadow:0 -4px 12px rgba(0,0,0,0.08)">' +
        '<button data-action="bulk-select-all" style="padding:8px 10px;border-radius:8px;border:1px solid ' + t.bord + ';background:transparent;color:' + t.ink + ';font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap">' + (allSel ? 'Tout désélectionner' : 'Tout sélectionner') + '</button>' +
        '<div style="flex:1;font-size:12px;color:' + t.ink2 + '">' + selCount + ' sélectionné' + (selCount > 1 ? 's' : '') + '</div>' +
        (S.bulkSending
          ? '<button disabled style="padding:9px 14px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:13px;font-weight:700;cursor:not-allowed;white-space:nowrap">Envoi…</button>'
          : '<button data-action="bulk-generate"' + (selCount ? '' : ' disabled') + ' style="padding:9px 14px;border-radius:8px;border:none;background:' + (selCount ? C.pri : t.surf2) + ';color:' + (selCount ? 'white' : t.ink2) + ';font-size:13px;font-weight:700;cursor:' + (selCount ? 'pointer' : 'not-allowed') + ';white-space:nowrap">✉️ Envoyer (3 mois)</button>') +
      '</div>';
    }

    return h;
  }

  function renderPlayerCard(p, dark, bulkMode) {
    var t = tk(dark);
    var avail = getAvail(p.id);
    var init = initials((p.first_name || '') + ' ' + (p.last_name || ''));
    var phone = p.phone || '';
    var jDate = (S.journees[S.journeeN - 1] || {}).date || '';
    var inactive = p.is_active === false;
    var selected = !!S.bulkSelected[String(p.id)];
    var h = '<div style="background:' + t.surf + ';border:1px solid ' + (bulkMode && selected ? C.pri : t.bord) + ';border-radius:10px;padding:10px;display:flex;align-items:center;gap:10px;opacity:' + (inactive ? '0.6' : '1') + '">';
    h += '<button data-action="' + (bulkMode ? 'bulk-toggle' : 'player') + '" data-value="' + esc(p.id) + '" style="flex:1;display:flex;align-items:center;gap:10px;background:transparent;border:none;padding:0;cursor:pointer;text-align:left;min-width:0">' +
      avatar(init, avail, dark, 38) +
      '<div style="flex:1;min-width:0">' +
        '<div style="display:flex;align-items:center;gap:5px">' +
          '<span style="font-size:13px;font-weight:600;color:' + t.ink + ';overflow:hidden;text-overflow:ellipsis;white-space:nowrap">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</span>' +
          '<span style="font-size:11px;color:' + t.ink2 + ';font-weight:500;flex-shrink:0">' + (p.ranking || 0) + '</span>' +
        '</div>' +
        playerBadges(p, dark) +
      '</div>' +
    '</button>';
    if (bulkMode) {
      h += '<div style="width:26px;height:26px;border-radius:50%;flex-shrink:0;display:flex;align-items:center;justify-content:center;border:2px solid ' + (selected ? C.pri : t.bord) + ';background:' + (selected ? C.pri : 'transparent') + ';color:white;font-size:13px;font-weight:700">' + (selected ? '✓' : '') + '</div>';
    } else {
      h += '<div style="display:flex;gap:6px;flex-shrink:0">';
      if (phone && !inactive) {
        h += '<a href="tel:' + esc(phone.replace(/\s/g, '')) + '" onclick="event.stopPropagation()" style="width:34px;height:34px;border-radius:8px;background:' + C.okSoft + ';color:#15803d;display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px">📞</a>';
        h += '<a href="' + esc(smsHref(phone, p.first_name || '', S.journeeN, jDate)) + '" onclick="event.stopPropagation()" style="width:34px;height:34px;border-radius:8px;background:' + t.priSoft + ';color:' + C.priInk + ';display:flex;align-items:center;justify-content:center;text-decoration:none;font-size:14px">💬</a>';
      }
      h += deleteRestoreBtn(p, dark, 34);
      h += '</div>';
    }
    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // PLAYER DETAIL
  // ═══════════════════════════════════════════
  function renderPlayerDetail() {
    var dark = S.dark; var t = tk(dark);
    var p = getPlayer(S.playerId);
    if (!p) return '<div style="padding:32px;text-align:center;color:' + t.ink2 + '">Joueur introuvable.</div>';

    if (S.playerEdit) return renderPlayerEdit(p, dark, t);

    var avail = getAvail(p.id);
    var init = initials((p.first_name || '') + ' ' + (p.last_name || ''));
    var phone = p.phone || '';
    var jDate = (S.journees[S.journeeN - 1] || {}).date || '';
    var team = S.teams.find(function (tm) { return (tm.code || tm.id) === p.usual_team; });
    var editBtn = '<button data-action="player-edit-open" style="width:32px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink + ';font-size:14px;cursor:pointer">✏️</button>';
    var actions = '<div style="display:flex;gap:6px">' + deleteRestoreBtn(p, dark, 32) + editBtn + '</div>';

    var h = topBar('Fiche joueur', '', dark, true, actions);
    h += '<div style="padding:16px">';

    if (p.is_active === false) {
      h += '<div style="display:flex;align-items:center;gap:10px;background:' + t.surf2 + ';border:1px solid ' + t.bord + ';color:' + t.ink2 + ';padding:10px 12px;border-radius:10px;margin-bottom:14px;font-size:12px">' +
        '<span style="font-size:16px">🗑️</span>' +
        '<span style="flex:1"><strong style="font-weight:700;color:' + t.ink + '">Retiré de l’effectif</strong> — non sélectionnable dans les équipes.</span>' +
        '<button data-action="player-restore" data-value="' + esc(p.id) + '" style="padding:6px 10px;border-radius:8px;border:none;background:' + C.pri + ';color:white;font-size:11px;font-weight:600;cursor:pointer;white-space:nowrap">Réintégrer</button>' +
      '</div>';
    }

    // Hero
    var heroKo = avail === 'unavailable';
    var heroBg = heroKo ? (dark ? '#7f1d1d33' : C.errSoft) : 'transparent';
    var heroBord = heroKo ? '1px solid ' + (dark ? '#7f1d1d' : '#fecaca') : 'none';
    h += '<div style="display:flex;align-items:center;gap:14px;margin-bottom:12px;padding:' + (heroKo ? '12px' : '0') + ';background:' + heroBg + ';border:' + heroBord + ';border-radius:14px">';
    h += '<div style="position:relative;width:64px;height:64px;flex-shrink:0">' +
      '<div style="width:64px;height:64px;border-radius:50%;background:' + (heroKo ? 'linear-gradient(135deg,#ef4444,#b91c1c)' : 'linear-gradient(135deg,' + C.pri + ',' + C.priInk + ')') + ';color:white;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700">' + esc(init) + '</div>' +
      // Photo MonClubTT par-dessus les initiales, qui restent en repli si l'image ne charge pas
      (p.photo_url ? '<img src="' + esc(p.photo_url) + '" alt="" onerror="this.remove()" style="position:absolute;inset:0;width:64px;height:64px;border-radius:50%;object-fit:cover;background:' + t.surf2 + (heroKo ? ';box-shadow:0 0 0 2px #ef4444' : '') + '">' : '') +
      (heroKo ? '<span style="position:absolute;bottom:-2px;right:-2px;width:22px;height:22px;border-radius:50%;background:white;display:flex;align-items:center;justify-content:center;font-size:12px;box-shadow:0 1px 3px rgba(0,0,0,0.15)">🚫</span>' : '') +
    '</div>';
    h += '<div style="flex:1;min-width:0">' +
      '<div style="font-size:18px;font-weight:700;color:' + (heroKo ? (dark ? '#fca5a5' : '#b91c1c') : t.ink) + ';letter-spacing:-0.3px">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</div>' +
      '<div style="font-size:12px;color:' + t.ink2 + ';margin-top:2px">' + (p.ranking || 0) + ' pts · ' + esc(team ? team.name : p.usual_team || '—') + '</div>' +
      playerBadges(p, dark) +
    '</div></div>';

    // Status banners
    if (avail === 'unavailable') {
      h += '<div style="display:flex;align-items:flex-start;gap:10px;background:' + (dark ? '#7f1d1d40' : '#fee2e2') + ';border:1px solid ' + (dark ? '#991b1b' : '#fca5a5') + ';color:' + (dark ? '#fca5a5' : '#991b1b') + ';padding:10px 12px;border-radius:10px;margin-bottom:14px">' +
        '<span style="font-size:16px;line-height:1.2">🚫</span>' +
        '<div style="flex:1;font-size:12px;line-height:1.45"><strong style="font-weight:700">Indisponible</strong> pour la J' + S.journeeN + ' · ' + esc(jDate) +
          (p.notes ? '<div style="font-weight:500;opacity:0.85;margin-top:2px">' + esc(p.notes) + '</div>' : '') +
        '</div></div>';
    } else if (avail === 'uncertain') {
      h += '<div style="display:flex;align-items:center;gap:10px;background:' + (dark ? '#713f1240' : '#fef3c7') + ';border:1px solid ' + (dark ? '#854d0e' : '#fde68a') + ';color:' + (dark ? '#fde68a' : '#92400e') + ';padding:10px 12px;border-radius:10px;margin-bottom:14px;font-size:12px">' +
        '<span style="font-size:16px">❓</span>' +
        '<span style="flex:1"><strong style="font-weight:700">Réponse en attente</strong> — relance par SMS pré-rédigé.</span>' +
      '</div>';
    }

    // Quick actions
    h += '<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-bottom:16px">';
    h += '<a href="tel:' + esc(phone.replace(/\s/g, '')) + '" style="display:flex;align-items:center;justify-content:center;gap:6px;background:' + C.ok + ';color:white;padding:12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600">📞 Appeler</a>';
    h += '<a href="' + esc(smsHref(phone, p.first_name || '', S.journeeN, jDate)) + '" style="display:flex;align-items:center;justify-content:center;gap:6px;background:' + C.pri + ';color:white;padding:12px;border-radius:10px;text-decoration:none;font-size:13px;font-weight:600">💬 SMS</a>';
    h += '</div>';

    // Availability grids — both phases, interactive
    h += renderAvailGrid(p, 1, dark, t);
    h += renderAvailGrid(p, 2, dark, t);

    // Coordonnées
    h += sectionWrap('Coordonnées',
      infoRow('Téléphone',  phone ? '<a href="tel:' + esc(phone.replace(/\s/g,'')) + '" style="color:' + C.pri + ';text-decoration:none;font-weight:600">' + esc(phone) + '</a>' : '<span style="color:' + t.ink2 + '">—</span>', dark, false) +
      infoRow('E-mail',     p.email ? '<a href="mailto:' + esc(p.email) + '" style="color:' + C.pri + ';text-decoration:none;font-weight:600">' + esc(p.email) + '</a>' : '<span style="color:' + t.ink2 + '">—</span>', dark, false) +
      infoRow('Licence',    esc(p.license_number || '—'), dark, false) +
      infoRow('Classement', (p.ranking || 0) + ' pts', dark, false),
      dark);

    h += sectionWrap('Notes internes',
      '<div style="padding:10px 12px;font-size:12px;color:' + (p.notes ? t.ink : t.ink2) + ';line-height:1.5;font-style:' + (p.notes ? 'normal' : 'italic') + '">' + esc(p.notes || 'Aucune note') + '</div>',
      dark);

    h += '</div>';
    return h;
  }

  function renderAvailGrid(p, phase, dark, t) {
    var CYCLE = ['unknown', 'available', 'unavailable', 'uncertain'];
    var cells = S.journees.map(function (jx) {
      var av  = getAvailForRound(p.id, phase, jx.n);
      var bg  = av === 'available' ? C.okSoft : av === 'unavailable' ? C.errSoft : av === 'uncertain' ? C.warnSoft : t.surf2;
      var fg  = av === 'available' ? '#15803d' : av === 'unavailable' ? '#b91c1c' : av === 'uncertain' ? '#a16207' : t.ink2;
      var brd = av === 'unknown' ? '1px dashed ' + t.bord : '1px solid transparent';
      return '<button data-action="avail-toggle" data-player-id="' + p.id + '" data-phase="' + phase + '" data-round="' + jx.n + '"' +
        ' style="padding:7px 2px;border-radius:8px;background:' + bg + ';color:' + fg + ';text-align:center;font-size:11px;font-weight:600;border:' + brd + ';cursor:pointer;width:100%">' +
        '<div>J' + jx.n + '</div><div style="font-size:13px;margin-top:2px">' + avEmoji(av) + '</div>' +
      '</button>';
    }).join('');
    var hint = '<div style="font-size:10px;color:' + t.ink2 + ';padding:4px 8px 8px;text-align:right">Tap pour changer</div>';
    return sectionWrap('Disponibilités · Phase ' + phase,
      '<div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;padding:8px">' + cells + '</div>' + hint,
      dark);
  }

  function renderPlayerEdit(p, dark, t) {
    var cancelBtn = '<button data-action="player-edit-cancel" style="padding:0 12px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:13px;font-weight:600;cursor:pointer">Annuler</button>';
    var saveBtn   = S.editSaving
      ? '<button disabled style="padding:0 14px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:13px;font-weight:600;cursor:not-allowed">…</button>'
      : '<button data-action="player-edit-save" style="padding:0 14px;height:32px;border-radius:8px;border:none;background:' + C.pri + ';color:white;font-size:13px;font-weight:600;cursor:pointer">Enregistrer</button>';
    var actions = '<div style="display:flex;gap:6px">' + cancelBtn + saveBtn + '</div>';

    var h = topBar('Modifier', (p.first_name || '') + ' ' + (p.last_name || ''), dark, false, actions);
    h += '<div style="padding:16px;display:flex;flex-direction:column;gap:14px">';

    var fieldStyle = 'width:100%;box-sizing:border-box;padding:10px 12px;border:1px solid ' + t.bord + ';border-radius:10px;background:' + t.surf + ';color:' + t.ink + ';font-size:14px;outline:none;font-family:inherit';

    // Phone field
    h += '<div>';
    h += '<label style="display:block;font-size:11px;font-weight:600;color:' + t.ink2 + ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Téléphone</label>';
    h += '<input data-input="edit-phone" type="tel" value="' + esc(S.editPhone) + '" placeholder="06 XX XX XX XX" style="' + fieldStyle + '">';
    h += '</div>';

    // Email field
    h += '<div>';
    h += '<label style="display:block;font-size:11px;font-weight:600;color:' + t.ink2 + ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">E-mail</label>';
    h += '<input data-input="edit-email" type="email" value="' + esc(S.editEmail) + '" placeholder="joueur@exemple.fr" style="' + fieldStyle + '">';
    h += '</div>';

    // Notes textarea
    h += '<div>';
    h += '<label style="display:block;font-size:11px;font-weight:600;color:' + t.ink2 + ';text-transform:uppercase;letter-spacing:0.5px;margin-bottom:6px">Notes internes</label>';
    h += '<textarea data-input="edit-notes" rows="5" placeholder="Disponibilités habituelles, contraintes, remarques…" style="' + fieldStyle + ';resize:vertical;line-height:1.5">' + esc(S.editNotes) + '</textarea>';
    h += '</div>';

    // Read-only info
    h += sectionWrap('Informations FFTT (non modifiables)',
      infoRow('Licence',    esc(p.license_number || '—'), dark, false) +
      infoRow('Classement', (p.ranking || 0) + ' pts',    dark, false),
      dark);

    h += '</div>';
    return h;
  }

  // ═══════════════════════════════════════════
  // EFFECTIFS DE PHASE
  // ═══════════════════════════════════════════
  function renderSquads() {
    var dark = S.dark; var t = tk(dark);
    var phase = S.phase + 1; // 1-indexed
    var teams = S.teams;

    // Bouton ventilation
    var ventBtn = S.squadLoading
      ? '<button disabled style="padding:0 14px;height:32px;border-radius:8px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:13px;font-weight:600;cursor:not-allowed">…</button>'
      : '<button data-action="squad-ventilate" style="padding:0 12px;height:32px;border-radius:8px;border:none;background:' + C.ok + ';color:white;font-size:13px;font-weight:600;cursor:pointer">⚡ Ventiler</button>';
    var h = topBar('Effectifs · Phase ' + phase, teams.length + ' équipes', dark, true, ventBtn);

    if (!teams.length) {
      h += '<div style="padding:32px;text-align:center;color:' + t.ink2 + ';font-size:13px">Aucune équipe configurée.<br>Allez dans Réglages pour en ajouter.</div>';
      return h;
    }

    h += '<div style="padding:12px;display:flex;flex-direction:column;gap:12px">';

    // Picker joueur (overlay inline)
    if (S.squadPickerTeam) {
      h += renderSquadPicker(S.squadPickerTeam, phase, dark, t);
      h += '</div>';
      return h;
    }

    teams.forEach(function (team) {
      var teamCode = team.code || team.id || '';
      var squadIds = getSquadIds(teamCode, phase);
      var squadPlayers = squadIds.map(getPlayer).filter(Boolean);

      h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:12px;overflow:hidden">';
      // En-tête équipe
      h += '<div style="display:flex;align-items:center;justify-content:space-between;padding:10px 12px;border-bottom:1px solid ' + t.bord + '">';
      h += '<div style="display:flex;align-items:center;gap:8px">';
      h += '<div style="width:8px;height:8px;border-radius:50%;background:' + (team.color || C.pri) + ';flex-shrink:0"></div>';
      h += '<span style="font-size:13px;font-weight:700;color:' + t.ink + '">' + esc(team.name || teamCode) + '</span>';
      if (team.level) h += '<span style="font-size:11px;color:' + t.ink2 + '">' + esc(team.level) + '</span>';
      h += '<span style="font-size:11px;color:' + t.ink2 + '">' + squadPlayers.length + ' joueur' + (squadPlayers.length > 1 ? 's' : '') + '</span>';
      h += '</div>';
      h += '<button data-action="squad-picker-open" data-team="' + esc(teamCode) + '" style="width:28px;height:28px;border-radius:6px;border:none;background:' + C.pri + ';color:white;font-size:16px;cursor:pointer;display:flex;align-items:center;justify-content:center">+</button>';
      h += '</div>';

      // Liste des joueurs du squad
      if (!squadPlayers.length) {
        h += '<div style="padding:14px 12px;font-size:12px;color:' + t.ink2 + ';font-style:italic;text-align:center">Aucun joueur — appuyez sur + pour en ajouter</div>';
      } else {
        h += '<div>';
        squadPlayers.forEach(function (p) {
          var avail = getAvail(p.id);
          var init  = initials((p.first_name || '') + ' ' + (p.last_name || ''));
          h += '<div style="display:flex;align-items:center;gap:10px;padding:8px 12px;border-bottom:1px solid ' + t.bord + '">';
          h += avatar(init, avail, dark, 32);
          h += '<div style="flex:1;min-width:0">';
          h += '<span style="font-size:13px;font-weight:600;color:' + t.ink + '">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + '</span>';
          h += '<span style="font-size:11px;color:' + t.ink2 + ';margin-left:6px">' + (p.ranking || 0) + '</span>';
          h += playerBadges(p, dark);
          h += '</div>';
          h += '<button data-action="squad-remove" data-team="' + esc(teamCode) + '" data-player-id="' + p.id + '" style="width:28px;height:28px;border-radius:6px;border:none;background:' + C.errSoft + ';color:' + C.err + ';font-size:14px;cursor:pointer;flex-shrink:0">×</button>';
          h += '</div>';
        });
        h += '</div>';
      }
      h += '</div>';
    });

    h += '</div>';
    return h;
  }

  function renderSquadPicker(teamCode, phase, dark, t) {
    var team = S.teams.find(function (tm) { return (tm.code || tm.id) === teamCode; });
    var squadIds = getSquadIds(teamCode, phase);
    var q = S.squadPickerQ.toLowerCase();

    var available = S.players.filter(function (p) {
      if (p.is_active === false) return false; // supprimé de l'effectif : non sélectionnable
      if (squadIds.indexOf(p.id) !== -1) return false; // déjà dans le squad
      if (q) {
        var name = ((p.first_name || '') + ' ' + (p.last_name || '')).toLowerCase();
        if (!name.includes(q)) return false;
      }
      return true;
    }).sort(function (a, b) {
      // Joueurs habituels de l'équipe en premier
      var aUsual = a.usual_team === teamCode;
      var bUsual = b.usual_team === teamCode;
      if (aUsual && !bUsual) return -1;
      if (!aUsual && bUsual) return 1;
      return (b.ranking || 0) - (a.ranking || 0);
    });

    var teamName = team ? (team.name || teamCode) : teamCode;
    var h = '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:12px;overflow:hidden">';
    h += '<div style="display:flex;align-items:center;gap:8px;padding:10px 12px;border-bottom:1px solid ' + t.bord + '">';
    h += '<button data-action="squad-picker-close" style="width:28px;height:28px;border-radius:6px;border:none;background:' + t.surf2 + ';color:' + t.ink2 + ';font-size:16px;cursor:pointer">‹</button>';
    h += '<span style="flex:1;font-size:13px;font-weight:700;color:' + t.ink + '">Ajouter à ' + esc(teamName) + '</span>';
    h += '</div>';
    h += '<div style="padding:8px 10px;border-bottom:1px solid ' + t.bord + '">';
    h += '<input data-input="squad-picker-search" value="' + esc(S.squadPickerQ) + '" placeholder="Rechercher…" style="width:100%;box-sizing:border-box;padding:7px 10px;border:1px solid ' + t.bord + ';border-radius:8px;background:' + t.surf2 + ';color:' + t.ink + ';font-size:13px;outline:none">';
    h += '</div>';
    h += '<div style="max-height:340px;overflow-y:auto">';
    if (!available.length) {
      h += '<div style="padding:20px;text-align:center;font-size:12px;color:' + t.ink2 + '">Aucun joueur disponible</div>';
    }
    available.forEach(function (p) {
      var init  = initials((p.first_name || '') + ' ' + (p.last_name || ''));
      var avail = getAvail(p.id);
      var isUsual = p.usual_team === teamCode;
      h += '<button data-action="squad-add" data-team="' + esc(teamCode) + '" data-player-id="' + p.id + '"';
      h += ' style="display:flex;align-items:center;gap:10px;padding:8px 12px;width:100%;background:transparent;border:none;border-bottom:1px solid ' + t.bord + ';cursor:pointer;text-align:left">';
      h += avatar(init, avail, dark, 32);
      h += '<div style="flex:1;min-width:0">';
      var pTeamObj2 = S.teams.find(function (tm) { return (tm.code || tm.id) === p.usual_team; });
      var pTeamLbl2 = pTeamObj2 ? (pTeamObj2.name || p.usual_team) : p.usual_team;
      h += '<div style="font-size:13px;font-weight:600;color:' + t.ink + '">' + esc((p.first_name || '') + ' ' + (p.last_name || '')) + (pTeamLbl2 ? ' <span style="font-size:10px;color:' + t.ink2 + ';font-weight:600">' + esc(pTeamLbl2) + '</span>' : '') + '</div>';
      h += '<div style="font-size:11px;color:' + t.ink2 + '">' + (p.ranking || 0) + ' pts</div>';
      h += '</div>';
      h += '</button>';
    });
    h += '</div></div>';
    return h;
  }


  // ═══════════════════════════════════════════
  // RÉGLAGES
  // ═══════════════════════════════════════════
  function renderReglages() {
    var dark = S.dark; var t = tk(dark);
    var h = topBar('Réglages', '', dark, false, '');
    h += '<div style="padding:16px;display:flex;flex-direction:column;gap:14px">';

    // Club card
    h += '<div style="background:' + t.surf + ';border:1px solid ' + t.bord + ';border-radius:12px;padding:14px">' +
      '<div style="display:flex;align-items:center;gap:12px">' +
        '<div style="width:48px;height:48px;border-radius:12px;background:linear-gradient(135deg,#fbbf24,' + C.pri + ');display:flex;align-items:center;justify-content:center;font-size:22px">🏓</div>' +
        '<div>' +
          '<div style="font-size:14px;font-weight:700;color:' + t.ink + '">' + esc(cfg.clubName || 'Mon Club TT') + '</div>' +
          '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:2px">Saison ' + esc(cfg.season || '—') + ' · ' + S.teams.length + ' équipe' + (S.teams.length > 1 ? 's' : '') + '</div>' +
        '</div>' +
      '</div></div>';

    // Affichage
    h += sectionWrap('Affichage',
      renderToggle('Mode sombre', S.dark, 'toggle-dark', t),
      dark);

    // Données
    h += sectionWrap('Données',
      infoRow('Joueurs synchronisés', String(S.players.length), dark, false) +
      '<div style="padding:8px 12px">' +
        '<button data-action="sync" style="width:100%;background:' + C.pri + ';color:white;border:none;padding:10px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer">' + (S.syncing ? '⏳ Synchronisation…' : '🔄 Synchroniser les joueurs') + '</button>' +
        (S.syncError ? '<div style="margin-top:8px;padding:10px;background:' + C.errSoft + ';border-radius:8px;font-size:12px;color:' + C.err + '">❌ Erreur de synchro : ' + esc(S.syncError) + '</div>' : '') +
      '</div>',
      dark);

    // SMS
    h += sectionWrap('SMS pré-rédigés',
      infoRow('Demande de dispo', 'Modifier →', dark, true) +
      infoRow('Confirmation compo', 'Modifier →', dark, true),
      dark);

    // About
    h += sectionWrap('À propos',
      infoRow('Version', esc(cfg.version || '—'), dark, false),
      dark);

    h += '</div>';
    return h;
  }

  function renderToggle(label, value, action, t) {
    var bg = value ? C.pri : t.surf2;
    var left = value ? '18px' : '2px';
    return '<div style="display:flex;justify-content:space-between;padding:10px 12px;font-size:12px;align-items:center;color:' + t.ink + '">' +
      '<span>' + label + '</span>' +
      '<button data-action="' + action + '" style="width:38px;height:22px;border-radius:999px;border:none;cursor:pointer;background:' + bg + ';position:relative">' +
        '<div style="position:absolute;top:2px;left:' + left + ';width:18px;height:18px;border-radius:50%;background:white;box-shadow:0 1px 3px rgba(0,0,0,0.2)"></div>' +
      '</button>' +
    '</div>';
  }

  // ═══════════════════════════════════════════
  // LOADING
  // ═══════════════════════════════════════════
  function renderLoading() {
    var t = tk(S.dark);
    if (S.loadError) {
      return '<div style="padding:24px;display:flex;flex-direction:column;gap:12px">'
        + '<div style="background:' + C.errSoft + ';border:1px solid ' + C.err + ';border-radius:10px;padding:14px">'
        + '<div style="font-size:13px;font-weight:700;color:' + C.err + ';margin-bottom:6px">⚠️ Erreur de chargement</div>'
        + '<code style="font-size:11px;color:' + t.ink + ';word-break:break-all;white-space:pre-wrap">' + esc(S.loadError) + '</code>'
        + '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:8px">URL API : <code>' + esc(API) + '</code></div>'
        + '</div></div>';
    }
    return '<div style="display:flex;align-items:center;justify-content:center;height:60%;color:' + t.ink2 + ';font-size:13px;flex-direction:column;gap:12px"><div style="font-size:40px">🏓</div>Chargement…</div>';
  }

  // ═══════════════════════════════════════════
  // PWA INSTALL BANNER
  // ═══════════════════════════════════════════
  function renderPwaBanner() {
    if (S.pwaDismissed) return '';
    var isStandalone = w.navigator.standalone === true || w.matchMedia('(display-mode: standalone)').matches;
    if (isStandalone) return '';
    var dark = S.dark; var t = tk(dark);
    var base = 'display:flex;align-items:center;gap:10px;padding:10px 14px;background:' + C.priSoft + ';border-bottom:1px solid ' + C.pri + '30;';
    if (S.pwaIos) {
      return '<div style="' + base + 'flex-wrap:wrap">' +
        '<div style="font-size:20px">📲</div>' +
        '<div style="flex:1;min-width:0">' +
          '<div style="font-size:13px;font-weight:700;color:' + C.pri + '">Installer l\'application</div>' +
          '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:2px">Appuyez sur <strong>⎙ Partager</strong> puis <strong>« Sur l\'écran d\'accueil »</strong></div>' +
        '</div>' +
        '<button data-action="pwa-dismiss" style="background:transparent;border:none;font-size:18px;color:' + t.ink2 + ';cursor:pointer;padding:4px;line-height:1">×</button>' +
      '</div>';
    }
    if (S.pwaPrompt) {
      return '<div style="' + base + '">' +
        '<div style="font-size:20px">📲</div>' +
        '<div style="flex:1;min-width:0">' +
          '<div style="font-size:13px;font-weight:700;color:' + C.pri + '">Installer l\'application</div>' +
          '<div style="font-size:11px;color:' + t.ink2 + ';margin-top:2px">Accès rapide depuis l\'écran d\'accueil</div>' +
        '</div>' +
        '<button data-action="pwa-install" style="padding:7px 12px;background:' + C.pri + ';color:white;border:none;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer">Installer</button>' +
        '<button data-action="pwa-dismiss" style="background:transparent;border:none;font-size:18px;color:' + t.ink2 + ';cursor:pointer;padding:4px;line-height:1">×</button>' +
      '</div>';
    }
    return '';
  }

  // ═══════════════════════════════════════════
  // RENDER APP
  // ═══════════════════════════════════════════
  function renderApp() {
    var dark = S.dark; var t = tk(dark);
    var off = S.offline
      ? '<div class="ttp-offline">📡 Hors ligne — données du cache</div>'
      : '';
    var screen;
    if (S.loading) {
      screen = renderLoading();
    } else {
      switch (S.screen) {
        case 'journee':  screen = renderJournee(); break;
        case 'joueurs':  screen = renderJoueurs(); break;
        case 'player':   screen = renderPlayerDetail(); break;
        case 'squads':   screen = renderSquads();  break;
        case 'reglages': screen = renderReglages(); break;
        default:         screen = renderDashboard(); break;
      }
    }
    return '<div style="width:100%;height:100%;display:flex;flex-direction:column;background:' + t.bg + ';color:' + t.ink + ';overflow:hidden;position:relative">' +
      renderPwaBanner() +
      off +
      '<div style="flex:1;overflow-y:auto;overflow-x:hidden;background:' + t.bg + ';-webkit-overflow-scrolling:touch">' + screen + '</div>' +
      renderNav() +
      (S.picker ? renderPicker() : '') +
      (S.deleteConfirm ? renderDeleteConfirm() : '') +
      (S.bulkResult ? renderBulkResult() : '') +
      (S.wipeConfirm ? renderWipeConfirm() : '') +
    '</div>';
  }

  // ═══════════════════════════════════════════
  // STATE + EVENTS
  // ═══════════════════════════════════════════
  var root;

  function setState(patch) {
    var prevScreen = S.screen;
    Object.assign(S, patch);
    if ('screen' in patch && S.screen !== prevScreen) pushHash(S);
    render();
  }

  function render() {
    if (!root) return;
    var active = document.activeElement && root.contains(document.activeElement) ? document.activeElement : null;
    var prevInput = active ? active.dataset.input : null;
    root.innerHTML = renderApp();
    attachEvents();
    if (S.picker) {
      var pi = root.querySelector('[data-input="picker-search"]');
      if (pi) { pi.focus(); }
    } else if (prevInput === 'search' && S.screen === 'joueurs') {
      var si = root.querySelector('[data-input="search"]');
      if (si) { si.focus(); try { si.setSelectionRange(S.searchQ.length, S.searchQ.length); } catch (e) {} }
    } else if (prevInput === 'edit-phone') {
      var ef = root.querySelector('[data-input="edit-phone"]');
      if (ef) { ef.focus(); try { ef.setSelectionRange(ef.value.length, ef.value.length); } catch (e) {} }
    } else if (prevInput === 'edit-notes') {
      var nt = root.querySelector('[data-input="edit-notes"]');
      if (nt) { nt.focus(); }
    }
  }

  function attachEvents() {
    root.addEventListener('click', handleClick);
    var inp = root.querySelector('[data-input="search"]');
    if (inp) inp.addEventListener('input', function (e) { S.searchQ = e.target.value; render(); });
    var pi = root.querySelector('[data-input="picker-search"]');
    if (pi) pi.addEventListener('input', function (e) { S.pickerQ = e.target.value; render(); });
    var sp = root.querySelector('[data-input="squad-picker-search"]');
    if (sp) sp.addEventListener('input', function (e) { S.squadPickerQ = e.target.value; render(); });
    var ep = root.querySelector('[data-input="edit-phone"]');
    if (ep) ep.addEventListener('input', function (e) { S.editPhone = e.target.value; });
    var ee = root.querySelector('[data-input="edit-email"]');
    if (ee) ee.addEventListener('input', function (e) { S.editEmail = e.target.value; });
    var en = root.querySelector('[data-input="edit-notes"]');
    if (en) en.addEventListener('input', function (e) { S.editNotes = e.target.value; });
  }

  function squadAdd(teamCode, playerId) {
    var season = cfg.season || '';
    var phase  = S.phase + 1;
    // Optimiste
    var next = S.squads.concat([{ team_code: teamCode, phase: phase, season: season, player_id: playerId, position: 999 }]);
    setState({ squads: next, squadPickerTeam: null, squadPickerQ: '' });
    apiFetch('/phase-squads', {
      method: 'POST',
      body: JSON.stringify({ season: season, phase: phase, team_code: teamCode, player_id: playerId })
    }).then(function () { loadSquads(); }).catch(function () { loadSquads(); });
  }

  function squadRemove(teamCode, playerId) {
    var season = cfg.season || '';
    var phase  = S.phase + 1;
    // Optimiste
    var next = S.squads.filter(function (s) {
      return !(s.team_code === teamCode && s.phase === phase && String(s.player_id) === String(playerId));
    });
    setState({ squads: next });
    apiFetch('/phase-squads', {
      method: 'DELETE',
      body: JSON.stringify({ season: season, phase: phase, team_code: teamCode, player_id: playerId })
    }).catch(function () { loadSquads(); });
  }

  function squadVentilate() {
    setState({ squadLoading: true });
    apiFetch('/phase-squads/ventilate', {
      method: 'POST',
      body: JSON.stringify({ season: cfg.season || '', phase: S.phase + 1, overwrite: false })
    }).then(function (res) {
      setState({ squadLoading: false });
      loadCompositions();
     
      alert('✅ ' + (res.message || 'Ventilation terminée'));
    }).catch(function () {
      setState({ squadLoading: false });
      alert('❌ Erreur lors de la ventilation.');
    });
  }

  function toggleAvailability(playerId, phase, round) {
    var CYCLE = ['unknown', 'available', 'unavailable', 'uncertain'];
    var cur = S.availabilities.find(function (a) {
      return String(a.player_id) === String(playerId) && a.phase === phase && a.round === round;
    });
    var curStatus = cur ? cur.status : 'unknown';
    var nextStatus = CYCLE[(CYCLE.indexOf(curStatus) + 1) % CYCLE.length];
    var season = cfg.season || '';

    // Optimistic update
    var found = false;
    var updated = S.availabilities.map(function (a) {
      if (String(a.player_id) === String(playerId) && a.phase === phase && a.round === round) {
        found = true;
        return Object.assign({}, a, { status: nextStatus });
      }
      return a;
    });
    if (!found) updated.push({ player_id: playerId, season: season, phase: phase, round: round, status: nextStatus, comment: '' });
    setState({ availabilities: updated });

    apiFetch('/availability', {
      method: 'POST',
      body: JSON.stringify({ player_id: playerId, season: season, phase: phase, round: round, status: nextStatus, comment: '' })
    }).catch(function () {
      // Rollback on error
      setState({ availabilities: S.availabilities.map(function (a) {
        if (String(a.player_id) === String(playerId) && a.phase === phase && a.round === round) {
          return Object.assign({}, a, { status: curStatus });
        }
        return a;
      })});
    });
  }

  function savePlayerEdit() {
    var id = S.playerId;
    setState({ editSaving: true });
    apiFetch('/players/' + id, {
      method: 'PATCH',
      body: JSON.stringify({ phone: S.editPhone, email: S.editEmail, notes: S.editNotes })
    }).then(function (updated) {
      // Mise à jour optimiste dans S.players
      S.players = S.players.map(function (p) {
        return String(p.id) === String(id) ? updated : p;
      });
      setState({ playerEdit: false, editSaving: false });
    }).catch(function () {
      setState({ editSaving: false });
    });
  }

  function deletePlayer(id) {
    apiFetch('/players/' + id, { method: 'DELETE' }).then(function (updated) {
      S.players = S.players.map(function (p) { return String(p.id) === String(id) ? updated : p; });
      setState({ deleteConfirm: null });
      // Le serveur a retiré le joueur des compositions non validées et des
      // effectifs de phase : on recharge pour refléter ce nettoyage côté écran
      // (effectifs de phase, compteurs de compos, et la journée courante si affichée).
      loadAll().then(function () {
        if (S.screen === 'journee') loadCompositions();
      });
    }).catch(function (err) {
      setState({ deleteConfirm: null });
      alert('Erreur : ' + err.message);
    });
  }

  function sendMagicLinks() {
    var ids = Object.keys(S.bulkSelected).map(Number).filter(Boolean);
    if (!ids.length) return;
    setState({ bulkSending: true });
    apiFetch('/magic-links/generate', {
      method: 'POST',
      body: JSON.stringify({ player_ids: ids, ttl_days: 90, season: cfg.season || '' })
    }).then(function (res) {
      setState({ bulkSending: false, bulkMode: false, bulkSelected: {}, bulkResult: res.results || [] });
    }).catch(function (err) {
      setState({ bulkSending: false });
      alert('Erreur : ' + err.message);
    });
  }

  function wipePlayerPermanently(id) {
    apiFetch('/players/' + id + '/permanent', { method: 'DELETE' }).then(function () {
      S.players = S.players.filter(function (p) { return String(p.id) !== String(id); });
      var patch = { wipeConfirm: null };
      if (S.screen === 'player' && String(S.playerId) === String(id)) {
        patch.screen = 'joueurs';
        patch.tab = 'joueurs';
      }
      setState(patch);
      loadAll().then(function () {
        if (S.screen === 'journee') loadCompositions();
      });
    }).catch(function (err) {
      setState({ wipeConfirm: null });
      alert('Erreur : ' + err.message);
    });
  }

  function restorePlayer(id) {
    apiFetch('/players/' + id + '/restore', { method: 'POST' }).then(function (updated) {
      S.players = S.players.map(function (p) { return String(p.id) === String(id) ? updated : p; });
      render();
    }).catch(function (err) {
      alert('Erreur : ' + err.message);
    });
  }

  function handleClick(e) {
    var el = e.target.closest('[data-action]');
    if (!el || el.tagName === 'A') return;
    var a = el.dataset.action;
    var v = el.dataset.value;
    switch (a) {
      case 'tab':          goTab(v); break;
      case 'journee':      setState({ journeeN: parseInt(v), screen: 'journee', tab: 'journees' }); loadCompositions(); break;
      case 'goto-phase-journee':
        var targetPhase = parseInt(el.dataset.phase);
        var targetJournee = parseInt(el.dataset.journee);
        S.phase = targetPhase;
        S.journees = buildJournees();
        setState({ journeeN: targetJournee, screen: 'journee', tab: 'journees' });
        loadCompositions();
        break;
      case 'player':       if (!S.picker) setState({ playerId: parseInt(v), screen: 'player' }); break;
      case 'back':         goBack(); break;
      case 'goto':         setState({ screen: el.dataset.screen, tab: el.dataset.tab || el.dataset.screen }); break;
      case 'pwa-install':
        if (S.pwaPrompt) {
          S.pwaPrompt.prompt();
          S.pwaPrompt.userChoice.then(function () { setState({ pwaPrompt: null, pwaDismissed: true }); });
        }
        break;
      case 'pwa-dismiss':
        localStorage.setItem('ttp-pwa-dismissed', '1');
        setState({ pwaDismissed: true });
        break;
      case 'phase':        var newPhase = parseInt(v); setState({ phase: newPhase, journees: buildJournees(newPhase) }); break;
      case 'team-tab':
        setState({ activeTeamI: parseInt(v) });
        loadBurnageContext(currentTeamCode(), S.journeeN);
        break;
      case 'filter':       setState({ playerFilter: v }); break;
      case 'toggle-dark':  setState({ dark: !S.dark }); break;
      case 'sync':         syncPlayers(); break;
      case 'slot-open':    setState({ picker: { teamCode: el.dataset.team, slotNum: parseInt(el.dataset.slot) }, pickerQ: '' }); break;
      case 'slot-assign':  if (S.picker) assignSlot(S.picker.teamCode, S.picker.slotNum, parseInt(el.dataset.playerId)); break;
      case 'slot-remove':  e.stopPropagation(); removeSlot(el.dataset.team, parseInt(el.dataset.slot)); break;
      case 'round-validate':   validateTeamRound(el.dataset.team, parseInt(el.dataset.round)); break;
      case 'round-unvalidate': unvalidateTeamRound(el.dataset.team, parseInt(el.dataset.round)); break;
      case 'round-forfeit':        setForfeit(el.dataset.team, parseInt(el.dataset.round), true); break;
      case 'round-forfeit-cancel': setForfeit(el.dataset.team, parseInt(el.dataset.round), false); break;
      case 'picker-close': setState({ picker: null, pickerQ: '' }); break;
      case 'picker-clear':      S.pickerQ = ''; render(); break;
      case 'squad-picker-open':
        setState({ squadPickerTeam: el.dataset.team, squadPickerQ: '' }); break;
      case 'squad-picker-close':
        setState({ squadPickerTeam: null, squadPickerQ: '' }); break;
      case 'squad-add':
        squadAdd(el.dataset.team, parseInt(el.dataset.playerId)); break;
      case 'squad-remove':
        squadRemove(el.dataset.team, parseInt(el.dataset.playerId)); break;
      case 'squad-ventilate':
        squadVentilate(); break;
      case 'avail-toggle':
        toggleAvailability(parseInt(el.dataset.playerId), parseInt(el.dataset.phase), parseInt(el.dataset.round));
        break;
      case 'player-edit-open':
        var ep = getPlayer(S.playerId);
        if (ep) setState({ playerEdit: true, editPhone: ep.phone || '', editEmail: ep.email || '', editNotes: ep.notes || '', editSaving: false });
        break;
      case 'player-edit-cancel': setState({ playerEdit: false }); break;
      case 'player-edit-save':   savePlayerEdit(); break;
      case 'player-delete-open':    e.stopPropagation(); setState({ deleteConfirm: parseInt(v) }); break;
      case 'player-delete-cancel':  setState({ deleteConfirm: null }); break;
      case 'player-wipe-open':      e.stopPropagation(); setState({ wipeConfirm: parseInt(v) }); break;
      case 'player-wipe-cancel':    setState({ wipeConfirm: null }); break;
      case 'player-wipe-confirm':   if (S.wipeConfirm) wipePlayerPermanently(S.wipeConfirm); break;
      case 'player-delete-confirm': if (S.deleteConfirm) deletePlayer(S.deleteConfirm); break;
      case 'player-restore':        e.stopPropagation(); restorePlayer(parseInt(v)); break;
      case 'bulk-mode-toggle':
        setState({ bulkMode: !S.bulkMode, bulkSelected: {} });
        break;
      case 'bulk-toggle':
        var bid = String(v);
        var sel = Object.assign({}, S.bulkSelected);
        if (sel[bid]) delete sel[bid]; else sel[bid] = true;
        setState({ bulkSelected: sel });
        break;
      case 'bulk-select-all':
        var visible = filteredPlayers().map(function (p) { return String(p.id); });
        var allSel  = visible.length > 0 && visible.every(function (id) { return S.bulkSelected[id]; });
        if (allSel) {
          setState({ bulkSelected: {} });
        } else {
          var next = {};
          visible.forEach(function (id) { next[id] = true; });
          setState({ bulkSelected: next });
        }
        break;
      case 'bulk-generate':      sendMagicLinks(); break;
      case 'bulk-result-close':  setState({ bulkResult: null }); break;
    }
  }

  // ═══════════════════════════════════════════
  // ROUTING — hash-based
  // ═══════════════════════════════════════════
  function screenToHash(s) {
    if (s.screen === 'journee')  return '#journee/' + s.journeeN;
    if (s.screen === 'player')   return '#joueur/'  + s.playerId;
    if (s.screen === 'joueurs')  return '#joueurs';
    if (s.screen === 'squads')   return '#squads';
    if (s.screen === 'reglages') return '#reglages';
    return '#';
  }

  function hashToState(hash) {
    var h = (hash || '').replace(/^#\/?/, '');
    if (!h || h === 'dashboard') return { screen: 'dashboard', tab: 'dashboard' };
    if (h === 'joueurs')  return { screen: 'joueurs',  tab: 'joueurs' };
    if (h === 'squads')   return { screen: 'squads',   tab: 'squads' };
    if (h === 'reglages') return { screen: 'reglages', tab: 'reglages' };
    var m = h.match(/^journee\/(\d+)$/);
    if (m) return { screen: 'journee', tab: 'journees', journeeN: parseInt(m[1]) };
    m = h.match(/^joueur\/(\d+)$/);
    if (m) return { screen: 'player', tab: 'joueurs', playerId: parseInt(m[1]) };
    return { screen: 'dashboard', tab: 'dashboard' };
  }

  function pushHash(s) {
    var hash = screenToHash(s);
    if (w.location.hash !== hash) history.pushState(null, '', hash);
  }

  function goTab(id) {
    var map = { dashboard: 'dashboard', journees: 'journee', joueurs: 'joueurs', squads: 'squads', reglages: 'reglages' };
    setState({ tab: id, screen: map[id] || id, squadPickerTeam: null, squadPickerQ: '' });
    if (id === 'journees') { loadCompositions(); }
    if (id === 'squads')   { loadSquads(); }
  }

  function goBack() {
    if (S.screen === 'player')  { setState({ screen: S.tab === 'joueurs' ? 'joueurs' : 'journee' }); }
    else if (S.screen === 'journee') { setState({ screen: 'dashboard', tab: 'dashboard' }); }
    else { setState({ screen: 'dashboard', tab: 'dashboard' }); }
  }

  // ═══════════════════════════════════════════
  // PWA — register service worker
  // ═══════════════════════════════════════════
  function registerSW() {
    if ('serviceWorker' in navigator && cfg.swUrl) {
      navigator.serviceWorker.register(cfg.swUrl).catch(function () {});
    }
  }

  function initPwa() {
    // Installation desactivee en admin : pas de banniere. La banniere iOS est
    // detectee a l'UA, elle s'afficherait sinon malgre l'absence de manifest.
    if (!cfg.pwaEnabled) return;

    // Android / Chrome : capture beforeinstallprompt
    w.addEventListener('beforeinstallprompt', function (e) {
      e.preventDefault();
      setState({ pwaPrompt: e });
    });
    // iOS Safari : pas d'événement natif, on détecte l'UA
    var isIos = /iphone|ipad|ipod/i.test(navigator.userAgent);
    var isStandalone = w.navigator.standalone === true;
    if (isIos && !isStandalone) {
      setState({ pwaIos: true });
    }
  }

  // ═══════════════════════════════════════════
  // INIT
  // ═══════════════════════════════════════════
  function applyHash() {
    var parsed = hashToState(w.location.hash);
    Object.assign(S, parsed);
    if (parsed.screen === 'journee') { loadCompositions(); }
    render();
  }

  function init() {
    root = d.getElementById('ttp-app');
    if (!root) return;

    w.addEventListener('online',  function () { setState({ offline: false }); });
    w.addEventListener('offline', function () { setState({ offline: true });  });

    // Restore state from URL hash (deep link / browser back-forward)
    w.addEventListener('popstate', applyHash);

    // Apply hash from URL on first load
    var initial = hashToState(w.location.hash);
    Object.assign(S, initial);
    // Ensure the initial URL reflects the canonical hash
    history.replaceState(null, '', screenToHash(S));

    render();          // show loading spinner immediately
    // After data loads, re-apply hash (player detail needs players in S)
    loadAll().then(function () {
      var h = hashToState(w.location.hash);
      if (h.screen !== 'dashboard') { Object.assign(S, h); render(); }
      if (h.screen === 'journee') { loadCompositions(); }
    });
    registerSW();
    initPwa();
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

})(window, document);
