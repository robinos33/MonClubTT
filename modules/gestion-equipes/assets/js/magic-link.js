/* TT Team Planner — page joueur "lien magique"
 * Vanilla JS, isolé de app.js (la vraie app coach) : ce script ne connaît
 * que le token du lien, jamais la liste des joueurs.
 */
(function (w, d) {
  'use strict';

  var cfg   = w.TTPMagicConfig || {};
  var API   = cfg.apiBase || '/wp-json/monclubtt-equipes/v1';
  var TOKEN = cfg.token || '';
  var ROOT  = d.getElementById('ttp-magic-app');

  var STATUSES = [
    ['available',   '✅', 'Dispo',     '#16a34a'],
    ['unavailable', '🚫', 'Indispo',   '#dc2626'],
    ['uncertain',   '❓', 'Incertain', '#d97706']
  ];
  var ROUNDS_PER_PHASE = 7;

  var state = { player: null, club: '', season: '', dates: [[], []], avail: {}, saving: {}, error: null };

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function key(phase, round) { return phase + '_' + round; }

  function apiFetch(path, opts) {
    return fetch(API + path, Object.assign({ headers: { 'Content-Type': 'application/json' } }, opts || {}))
      .then(function (r) {
        if (!r.ok) return r.text().then(function (t) { throw new Error(t); });
        return r.json();
      });
  }

  function load() {
    apiFetch('/magic-links/' + TOKEN).then(function (data) {
      state.player = data.player;
      state.club   = data.club_name || '';
      state.season = data.season || '';
      state.dates  = data.journee_dates || [[], []];
      (data.availabilities || []).forEach(function (a) {
        state.avail[key(a.phase, a.round)] = a.status;
      });
      render();
    }).catch(function () {
      state.error = 'Ce lien est invalide ou a expiré.';
      render();
    });
  }

  function setStatus(phase, round, status) {
    var k = key(phase, round);
    if (state.saving[k]) return;
    state.saving[k] = true;
    render();

    apiFetch('/magic-links/' + TOKEN + '/availability', {
      method: 'POST',
      body: JSON.stringify({ phase: phase, round: round, status: status, comment: '' })
    }).then(function () {
      state.avail[k] = status;
      state.saving[k] = false;
      render();
    }).catch(function () {
      state.saving[k] = false;
      state.error = 'Enregistrement impossible, réessaie.';
      render();
    });
  }

  function renderPhase(phase) {
    var dates = state.dates[phase - 1] || [];
    var rows  = '';

    for (var r = 1; r <= ROUNDS_PER_PHASE; r++) {
      var k       = key(phase, r);
      var current = state.avail[k] || 'unknown';
      var date    = dates[r - 1] || '';
      var saving  = !!state.saving[k];

      var btns = STATUSES.map(function (s) {
        var active = current === s[0];
        return '<button data-phase="' + phase + '" data-round="' + r + '" data-status="' + s[0] + '"' +
          (saving ? ' disabled' : '') +
          ' style="flex:1;padding:10px 4px;border-radius:8px;border:' + (active ? '2px solid ' + s[3] : '1px solid #e2e8f0') +
          ';background:' + (active ? s[3] + '1a' : '#fff') + ';color:' + (active ? s[3] : '#64748b') +
          ';font-size:12px;font-weight:600;cursor:' + (saving ? 'default' : 'pointer') + '">' + s[1] + ' ' + s[2] + '</button>';
      }).join('');

      rows += '<div style="margin-bottom:10px">' +
        '<div style="font-size:12px;color:#64748b;margin-bottom:6px;font-weight:600">J' + r + (date ? ' · ' + esc(date) : '') + '</div>' +
        '<div style="display:flex;gap:6px">' + btns + '</div>' +
      '</div>';
    }

    return '<div style="margin-bottom:22px">' +
      '<div style="font-size:12px;font-weight:700;color:#1e293b;margin-bottom:10px;text-transform:uppercase;letter-spacing:0.5px">Phase ' + phase + '</div>' +
      rows +
    '</div>';
  }

  function render() {
    if (!state.player) {
      ROOT.innerHTML = '<div style="max-width:420px;margin:15vh auto 0;padding:24px;text-align:center;font-size:13px;color:' +
        (state.error ? '#b91c1c' : '#94a3b8') + '">' + esc(state.error || 'Chargement…') + '</div>';
      return;
    }

    var h = '<div style="max-width:480px;margin:0 auto;padding:24px 16px 60px">';
    h += '<div style="font-size:20px;font-weight:800;color:#1e293b;margin-bottom:2px">Bonjour ' + esc(state.player.first_name) + ' 👋</div>';
    h += '<div style="font-size:13px;color:#64748b;margin-bottom:22px;line-height:1.5">' +
      esc(state.club) + ' · saison ' + esc(state.season) + '<br>Indique tes disponibilités pour chaque journée.</div>';

    if (state.error) {
      h += '<div style="background:#fee2e2;color:#b91c1c;padding:10px 12px;border-radius:8px;font-size:12px;margin-bottom:18px">' + esc(state.error) + '</div>';
    }

    h += renderPhase(1) + renderPhase(2);
    h += '</div>';
    ROOT.innerHTML = h;
  }

  ROOT.addEventListener('click', function (e) {
    var el = e.target.closest('button[data-status]');
    if (!el) return;
    setStatus(parseInt(el.dataset.phase, 10), parseInt(el.dataset.round, 10), el.dataset.status);
  });

  load();
})(window, document);
