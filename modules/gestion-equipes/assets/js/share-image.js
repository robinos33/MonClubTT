/* TT Team Planner — image de partage des compositions d'une journée
 * Vanilla JS · Aucune dépendance · Canvas 2D
 *
 * Deux temps, séparés pour pouvoir tester le premier sans navigateur :
 *   buildModel(input)          → ce que dit l'image (pur : données in, modèle out)
 *   paint(canvas, model, dpr)  → comment elle le dit (dessin)
 *
 * Exposé sur window.TTPShareImage, utilisé par app.js.
 */
(function (w) {
  'use strict';

  // ═══════════════════════════════════════════
  // MODÈLE
  // ═══════════════════════════════════════════

  function teamCodeOf(team) {
    return team.code || team.id || '';
  }

  function fullName(p) {
    return ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
  }

  function chip(label, tone) {
    return { label: label, tone: tone };
  }

  /**
   * Signaux d'un joueur placé, dans l'ordre où on les lit en discussion :
   * dispo, brûlage, puis statuts (étranger, mutation, capitaine).
   * Les libellés de brûlage sont ceux de l'app (input.burnLabels).
   */
  function playerSlot(num, p, avail, burn, burnLabels) {
    var chips = [];
    if (avail === 'unavailable')    chips.push(chip('Indispo', 'danger'));
    else if (avail === 'uncertain') chips.push(chip('Incertain', 'warn'));
    else if (avail === 'unknown')   chips.push(chip('Non renseigné', 'neutral'));

    var burned = !!(burn && burn.burned);
    if (p.is_burned) chips.push(chip('🔥 Brûlé', 'danger'));
    if (burned) {
      var label = burnLabels[burn.reason] || '🔥 Brûlé';
      if (!(p.is_burned && label === '🔥 Brûlé')) chips.push(chip(label, 'danger'));
    }

    if (p.is_foreign)  chips.push(chip('E', 'purple'));
    if (p.is_mutation) chips.push(chip('Mut.', 'warn'));
    if (p.is_captain)  chips.push(chip('© Cap.', 'primary'));

    return {
      num: num,
      empty: false,
      name: fullName(p) || 'Joueur #' + p.id,
      points: p.ranking || 0,
      // Même règle que la ligne rouge de la vue Journée.
      danger: avail === 'unavailable' || !!p.is_burned || burned,
      chips: chips
    };
  }

  function teamStatus(forfeit, validated, filled) {
    if (forfeit)      return chip('Forfait', 'danger');
    if (validated)    return chip('✓ Validée', 'ok');
    if (filled === 4) return chip('Complète', 'ok');
    return chip('Incomplète ' + filled + '/4', 'warn');
  }

  function formatDay(iso) {
    if (!iso) return '';
    var d = new Date(iso + 'T12:00:00');
    if (isNaN(d.getTime())) return String(iso);
    return d.toLocaleDateString('fr-FR', { weekday: 'long', day: 'numeric', month: 'long' });
  }

  function formatStamp(date) {
    return date.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' }) +
      ' à ' + date.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' });
  }

  /**
   * input :
   *   clubName, phase (1|2), round, date (AAAA-MM-JJ), generatedAt (Date)
   *   teams          — cfg.teams, dans l'ordre d'affichage
   *   compositions   — les slots de la journée, toutes équipes
   *   players, availabilities
   *   contexts       — { codeÉquipe: { burnage: {idJoueur: statut} | null, validated: bool | null } }
   *                    null = pas chargé (hors ligne sans cache)
   *   forfeits       — { codeÉquipe: true } | null si pas chargé
   *   burnLabels     — { raison: libellé } de l'app
   */
  function buildModel(input) {
    var phase = input.phase;
    var round = input.round;
    var burnLabels = input.burnLabels || {};
    var contexts = input.contexts || {};

    var players = {};
    (input.players || []).forEach(function (p) { players[String(p.id)] = p; });

    var avail = {};
    (input.availabilities || []).forEach(function (a) {
      if (Number(a.phase) === phase && Number(a.round) === round) avail[String(a.player_id)] = a.status;
    });
    function availOf(id) { return avail[String(id)] || 'unknown'; }

    var slotsByTeam = {};
    var placed = {};
    (input.compositions || []).forEach(function (c) {
      var n = Number(c.slot_number);
      if (!c.player_id || n < 1 || n > 4) return;
      placed[String(c.player_id)] = true;
      (slotsByTeam[c.team_code] = slotsByTeam[c.team_code] || {})[n] = c.player_id;
    });

    var teams = (input.teams || []).map(function (team) {
      var code = teamCodeOf(team);
      var ctx = contexts[code] || {};
      var burnMap = ctx.burnage || {};
      var forfeit = !!(input.forfeits && input.forfeits[code]);
      var teamSlots = slotsByTeam[code] || {};

      var slots = [1, 2, 3, 4].map(function (num) {
        var pid = teamSlots[num];
        if (!pid) return { num: num, empty: true };
        var p = players[String(pid)];
        if (!p) return { num: num, empty: false, name: 'Joueur inconnu', points: null, danger: false, chips: [] };
        return playerSlot(num, p, availOf(p.id), burnMap[p.id], burnLabels);
      });
      var filled = slots.filter(function (s) { return !s.empty; }).length;

      return {
        name: team.name || code || 'Équipe',
        level: team.level || '',
        color: team.color || '#2563eb',
        forfeit: forfeit,
        filled: filled,
        status: teamStatus(forfeit, ctx.validated === true, filled),
        // Une image sans badge doit vouloir dire « rien à signaler », pas « pas chargé ».
        unverified: ctx.burnage == null || ctx.validated == null || input.forfeits == null,
        slots: slots
      };
    });

    var playing = teams.filter(function (t) { return !t.forfeit; });
    var complete = playing.filter(function (t) { return t.filled === 4; }).length;
    var forfeits = teams.length - playing.length;

    // « Qui peut remplacer X ? » : la question qui suit, en discussion.
    var bench = (input.players || [])
      .filter(function (p) { return p.is_active !== false && !placed[String(p.id)] && availOf(p.id) === 'available'; })
      .sort(function (a, b) { return (b.ranking || 0) - (a.ranking || 0); })
      .map(function (p) { return fullName(p) + ' · ' + (p.ranking || 0); });

    return {
      title: input.clubName || 'Mon Club TT',
      subtitle: 'Journée ' + round + ' · Phase ' + phase,
      date: formatDay(input.date),
      counter: chip(
        complete + '/' + playing.length + ' compos' + (forfeits ? ' · ' + forfeits + ' forfait' + (forfeits > 1 ? 's' : '') : ''),
        complete === playing.length ? 'ok' : 'warn'
      ),
      teams: teams,
      bench: bench,
      footer: 'Généré le ' + formatStamp(input.generatedAt || new Date())
    };
  }

  // ═══════════════════════════════════════════
  // DESSIN
  // ═══════════════════════════════════════════

  // Thème clair forcé : l'image est lue dans WhatsApp, quel que soit le thème
  // de l'app. Valeurs reprises des tokens clairs de app.js.
  var COL = {
    bg: '#f5f7fb', surf: '#ffffff', row: '#f8fafc', bord: '#e2e8f0',
    ink: '#0f172a', ink2: '#475569', num: '#eef2f8',
    err: '#dc2626', errSoft: '#fee2e2',
    warn: '#ca8a04', warnSoft: '#fef3c7', warnInk: '#a16207'
  };
  var TONES = {
    primary: { bg: '#dbeafe', fg: '#1d4ed8' },
    ok:      { bg: '#dcfce7', fg: '#15803d' },
    warn:    { bg: '#fef3c7', fg: '#a16207' },
    // Plein, et non pastel comme dans l'app : doit se voir sans zoomer,
    // y compris sur le fond rouge clair d'une ligne en défaut.
    danger:  { bg: '#dc2626', fg: '#ffffff' },
    purple:  { bg: '#ede9fe', fg: '#6d28d9' },
    neutral: { bg: '#e2e8f0', fg: '#334155' }
  };
  var FONT = "system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif";
  function font(weight, size) { return weight + ' ' + size + 'px ' + FONT; }

  // Largeur logique ; multipliée par l'échelle (2 à 3) → 1080 à 1620 px réels.
  var W = 540;
  var PAD = 18;
  var STRIPE = 6;
  var CARD_PAD = 12;
  var ROW_H = 38;
  var ROW_GAP = 6;
  var CHIP_H = 20;
  var CHIP_GAP = 4;
  var CHIP_FONT = font(700, 12);
  // Plafond d'aire de canvas d'iOS Safari (~16,7 Mpx) : au-delà, l'image sort vide.
  var MAX_PIXELS = 16000000;

  function roundRect(ctx, x, y, bw, bh, r) {
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + bw, y, x + bw, y + bh, r);
    ctx.arcTo(x + bw, y + bh, x, y + bh, r);
    ctx.arcTo(x, y + bh, x, y, r);
    ctx.arcTo(x, y, x + bw, y, r);
    ctx.closePath();
  }

  /**
   * Pinceau à deux modes : `on` dessine, sinon il ne fait que mesurer. Le même
   * code de mise en page calcule la hauteur (à blanc), puis dessine.
   * P.dry est la variante à blanc, pour pré-mesurer un bloc avant d'en
   * dessiner le fond.
   */
  function painter(ctx, on) {
    var P = {
      measure: function (text, f) { ctx.font = f; return ctx.measureText(text).width; },
      fit: function (text, f, max) {
        if (P.measure(text, f) <= max) return text;
        var s = text;
        while (s.length > 1 && P.measure(s + '…', f) > max) s = s.slice(0, -1);
        return s + '…';
      },
      text: function (text, x, y, f, color, align) {
        if (!on) return;
        ctx.font = f;
        ctx.fillStyle = color;
        ctx.textAlign = align || 'left';
        ctx.textBaseline = 'middle';
        ctx.fillText(text, x, y);
      },
      box: function (x, y, bw, bh, r, o) {
        if (!on) return;
        roundRect(ctx, x, y, bw, bh, r);
        if (o.fill) { ctx.fillStyle = o.fill; ctx.fill(); }
        if (o.stroke) {
          ctx.setLineDash(o.dash || []);
          ctx.lineWidth = o.width || 1;
          ctx.strokeStyle = o.stroke;
          ctx.stroke();
          ctx.setLineDash([]);
        }
      },
      stripe: function (x, y, bw, bh, r, color) {
        if (!on) return;
        ctx.save();
        roundRect(ctx, x, y, bw, bh, r);
        ctx.clip();
        ctx.fillStyle = color;
        ctx.fillRect(x, y, STRIPE, bh);
        ctx.restore();
      }
    };
    P.dry = on ? painter(ctx, false) : P;
    return P;
  }

  function chipWidth(P, c, f) {
    return P.measure(c.label, f || CHIP_FONT) + 16;
  }

  function drawChip(P, c, x, y, f, h) {
    h = h || CHIP_H;
    var tone = TONES[c.tone] || TONES.neutral;
    var cw = chipWidth(P, c, f);
    P.box(x, y, cw, h, h / 2, { fill: tone.bg });
    P.text(c.label, x + cw / 2, y + h / 2 + 0.5, f || CHIP_FONT, tone.fg, 'center');
    return cw;
  }

  function chipsWidth(P, chips) {
    return chips.reduce(function (acc, c, i) { return acc + chipWidth(P, c) + (i ? CHIP_GAP : 0); }, 0);
  }

  /** Pose les chips en lignes de largeur max ; renvoie la hauteur occupée. */
  function flowChips(P, chips, x, y, max) {
    var cx = x, cy = y;
    chips.forEach(function (c) {
      var cw = chipWidth(P, c);
      if (cx > x && cx + cw > x + max) { cx = x; cy += CHIP_H + CHIP_GAP; }
      drawChip(P, c, cx, cy);
      cx += cw + CHIP_GAP;
    });
    return chips.length ? cy + CHIP_H - y : 0;
  }

  /** Une ligne de slot ; renvoie sa hauteur. */
  function drawSlot(P, s, x, y, rw) {
    var mid = y + ROW_H / 2;
    var nameX = x + 38;
    var inner = rw - 38 - 10;
    var rh = ROW_H;

    if (s.empty) {
      P.box(x, y, rw, rh, 8, { fill: COL.warnSoft, stroke: COL.warn, width: 1.5, dash: [5, 4] });
      P.text('À compléter', nameX, mid, font(700, 15), COL.warnInk);
    } else {
      var nameF = font(600, 15);
      var ptsF = font(500, 13);
      var pts = s.points === null ? '' : s.points + ' pts';
      var ptsW = pts ? P.measure(pts, ptsF) + 8 : 0;
      var cw = chipsWidth(P, s.chips);
      // Badges sur la ligne du nom s'ils tiennent, sinon en dessous.
      var sameLine = !s.chips.length || P.measure(s.name, nameF) + ptsW + 10 + cw <= inner;
      if (!sameLine) rh += flowChips(P.dry, s.chips, 0, 0, inner);

      P.box(x, y, rw, rh, 8, s.danger
        ? { fill: COL.errSoft, stroke: COL.err, width: 2 }
        : { fill: COL.row, stroke: COL.bord, width: 1 });

      var name = P.fit(s.name, nameF, inner - ptsW - (sameLine && cw ? cw + 10 : 0));
      P.text(name, nameX, mid, nameF, COL.ink);
      if (pts) P.text(pts, nameX + P.measure(name, nameF) + 8, mid + 1, ptsF, COL.ink2);
      if (sameLine) {
        var cx = x + rw - 10 - cw;
        s.chips.forEach(function (c) { cx += drawChip(P, c, cx, mid - CHIP_H / 2) + CHIP_GAP; });
      } else {
        flowChips(P, s.chips, nameX, y + ROW_H - 4, inner);
        rh += 6;
      }
    }

    P.box(x + 8, mid - 11, 22, 22, 6, { fill: s.danger ? COL.surf : COL.num });
    P.text(String(s.num), x + 19, mid + 0.5, font(700, 12), COL.ink2, 'center');
    return rh;
  }

  /** Contenu d'une carte équipe ; renvoie l'ordonnée de fin. */
  function drawTeamBody(P, t, x, y, cw) {
    var inner = cw - STRIPE - 2 * CARD_PAD;
    var x0 = x + STRIPE + CARD_PAD;
    var cy = y + CARD_PAD;

    // En-tête : nom · division, statut à droite.
    var statusW = chipWidth(P, t.status, font(700, 13));
    var nameF = font(700, 17);
    var levelF = font(500, 13);
    var level = t.level ? P.fit(t.level, levelF, inner / 3) : '';
    var levelW = level ? P.measure(level, levelF) + 8 : 0;
    var name = P.fit(t.name, nameF, inner - statusW - 10 - levelW);
    P.text(name, x0, cy + 12, nameF, COL.ink);
    if (level) P.text(level, x0 + P.measure(name, nameF) + 8, cy + 13, levelF, COL.ink2);
    drawChip(P, t.status, x0 + inner - statusW, cy + 1, font(700, 13), 22);
    cy += 24 + 8;

    if (t.unverified) {
      drawChip(P, { label: '⚠ Brûlage non vérifié', tone: 'warn' }, x0, cy);
      cy += CHIP_H + 8;
    }

    if (t.forfeit) {
      P.box(x0, cy, inner, ROW_H, 8, { fill: COL.errSoft, stroke: COL.err, width: 2 });
      P.text('Forfait déclaré : l’équipe ne joue pas', x0 + 12, cy + ROW_H / 2, font(700, 15), COL.err);
      cy += ROW_H;
    } else {
      t.slots.forEach(function (s, i) {
        cy += drawSlot(P, s, x0, cy, inner) + (i < 3 ? ROW_GAP : 0);
      });
    }
    return cy + CARD_PAD;
  }

  function drawTeam(P, t, x, y, cw) {
    var end = drawTeamBody(P.dry, t, x, y, cw);
    P.box(x, y, cw, end - y, 12, { fill: COL.surf, stroke: COL.bord, width: 1 });
    P.stripe(x, y, cw, end - y, 12, t.color);
    return drawTeamBody(P, t, x, y, cw);
  }

  /** Mise en page complète ; renvoie la hauteur totale. */
  function layout(P, m) {
    var cw = W - 2 * PAD;
    var y = PAD;

    // En-tête : club, compteur à droite ; journée · phase · date en dessous.
    var counterF = font(700, 14);
    var counterW = chipWidth(P, m.counter, counterF);
    var titleF = font(800, 22);
    P.text(P.fit(m.title, titleF, cw - counterW - 12), PAD, y + 14, titleF, COL.ink);
    drawChip(P, m.counter, W - PAD - counterW, y + 1, counterF, 26);
    y += 34;
    var sub = m.subtitle + (m.date ? ' — ' + m.date : '');
    P.text(P.fit(sub, font(600, 15), cw), PAD, y + 9, font(600, 15), COL.ink2);
    y += 18 + 14;

    m.teams.forEach(function (t) {
      y = drawTeam(P, t, PAD, y, cw) + 12;
    });

    if (m.bench.length) {
      P.text('DISPONIBLES NON PLACÉS · ' + m.bench.length, PAD + 2, y + 8, font(700, 12), COL.ink2);
      y += 22;
      y += flowChips(P, m.bench.map(function (label) { return { label: label, tone: 'neutral' }; }), PAD, y, cw) + 12;
    }

    P.text(m.footer, W / 2, y + 8, font(500, 12), COL.ink2, 'center');
    return y + 16 + PAD;
  }

  /**
   * Dessine le modèle dans le canvas, redimensionné à la bonne hauteur.
   * dpr : window.devicePixelRatio ; l'échelle reste entre 2 (≈ 1080 px de
   * large, le format WhatsApp) et 3, plafonnée par l'aire maximale d'iOS.
   */
  function paint(canvas, model, dpr) {
    var ctx = canvas.getContext('2d');
    var h = layout(painter(ctx, false), model);
    var scale = Math.min(Math.max(dpr || 1, 2), 3, Math.sqrt(MAX_PIXELS / (W * h)));

    canvas.width = Math.round(W * scale);
    canvas.height = Math.round(h * scale);
    ctx.setTransform(scale, 0, 0, scale, 0, 0);
    ctx.fillStyle = COL.bg;
    ctx.fillRect(0, 0, W, h);
    layout(painter(ctx, true), model);
    return canvas;
  }

  w.TTPShareImage = { buildModel: buildModel, paint: paint };
})(window);
