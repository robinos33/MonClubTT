<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Audit;

/**
 * Traduit une écriture de l'API en entrée de journal : une phrase lisible
 * (« a remplacé Léa DURAND par Hugo PETIT (Équipe 1, J3 de la phase 1,
 * slot 2) ») et le contexte qui sert aux filtres.
 *
 * Deux temps, parce que certaines écritures effacent ce qu'il faut raconter :
 *   snapshot() lit l'état d'avant, juste avant l'écriture ;
 *   describe() rédige l'entrée une fois l'écriture réussie, ou rend null
 *   quand il n'y a rien à raconter (slot déjà vide, même joueur remis…).
 *
 * Une opération est désignée par « Contrôleur::méthode » ; OPERATIONS liste
 * toutes les routes d'écriture, et un test vérifie qu'aucune n'y manque.
 */
class AuditDescriber
{
    public const OPERATIONS = [
        'AvailabilityController::setAvailability',
        'MagicLinkController::generate',
        'MagicLinkController::setAvailability',
        'MatchAppearanceController::declareForfeit',
        'MatchAppearanceController::importAppearances',
        'MatchAppearanceController::unvalidateRound',
        'MatchAppearanceController::validateRound',
        'MatchAppearanceController::withdrawForfeit',
        'PhaseSquadController::addPlayer',
        'PhaseSquadController::removePlayer',
        'PhaseSquadController::setCaptain',
        'PhaseSquadController::ventilate',
        'PlayersController::deletePlayer',
        'PlayersController::deletePlayerPermanently',
        'PlayersController::restorePlayer',
        'PlayersController::updatePlayer',
        'SeasonController::reset',
        'SmsTemplatesController::reset',
        'SmsTemplatesController::update',
        'SyncController::sync',
        'TeamsController::clearSlot',
        'TeamsController::setCaptain',
        'TeamsController::setSlot',
    ];

    private const STATUS_LABELS = [
        'available'   => 'Dispo',
        'unavailable' => 'Indispo',
        'uncertain'   => 'Incertain',
        'unknown'     => 'Non renseigné',
    ];

    private const SMS_LABELS = [
        'availability' => 'Demande de dispo',
        'confirmation' => 'Confirmation compo',
    ];

    /** Champs de contact : le journal dit lequel a changé, jamais sa valeur. */
    private const CONTACT_LABELS = [
        'phone' => 'le téléphone',
        'email' => "l'e-mail",
        'notes' => 'la note',
    ];

    public function __construct(private AuditLookup $lookup)
    {
    }

    /**
     * @param array<string, mixed> $p paramètres de la requête
     * @return array<string, mixed>
     */
    public function snapshot(string $operation, array $p): array
    {
        return match ($operation) {
            'TeamsController::setSlot', 'TeamsController::clearSlot' => [
                'player_id' => $this->lookup->slotPlayerId(self::str($p, 'season'), self::int($p, 'phase'), self::int($p, 'round'), self::str($p, 'team_code'), self::int($p, 'slot_number')),
            ],
            'AvailabilityController::setAvailability' => [
                'status' => $this->lookup->availabilityStatus(self::int($p, 'player_id'), self::str($p, 'season'), self::int($p, 'phase'), self::int($p, 'round')),
            ],
            'MagicLinkController::setAvailability' => $this->magicLinkSnapshot($p),
            'PlayersController::updatePlayer' => ['contact' => $this->lookup->contact(self::int($p, 'id'))],
            // Le nom doit être lu avant : la suppression efface le joueur.
            'PlayersController::deletePlayerPermanently' => ['name' => $this->lookup->playerName(self::int($p, 'id'))],
            default => [],
        };
    }

    /**
     * @param array<string, mixed> $p      paramètres de la requête
     * @param array<string, mixed> $before résultat de snapshot()
     * @param array<string, mixed> $result corps de la réponse
     * @return array<string, mixed>|null
     */
    public function describe(string $operation, array $p, array $before, array $result): ?array
    {
        $season = self::str($p, 'season');
        $phase  = self::int($p, 'phase');
        $round  = self::int($p, 'round');
        $team   = self::str($p, 'team_code');
        // « (Équipe 1, J3 de la phase 1) », ou « (…, slot 2) »
        $where  = fn (int $slot = 0): string => sprintf('(%s, %s%s)', $this->lookup->teamName($team), self::round($round, $phase), $slot ? ', slot ' . $slot : '');
        $entry  = fn (string $action, string $summary, array $context = []): array => $context + [
            'action'    => $action,
            'summary'   => $summary,
            'season'    => $season,
            'phase'     => $phase,
            'round'     => $round,
            'team_code' => $team,
        ];

        switch ($operation) {
            case 'TeamsController::setSlot':
                $new = self::int($p, 'player_id');
                $old = $before['player_id'] ?? null;
                if ($old === $new) {
                    return null;
                }
                $slot = self::int($p, 'slot_number');
                return $entry('composition.set', $old
                    ? sprintf('a remplacé %s par %s %s', $this->lookup->playerName($old), $this->lookup->playerName($new), $where($slot))
                    : sprintf('a placé %s %s', $this->lookup->playerName($new), $where($slot)));

            case 'TeamsController::clearSlot':
                $old = $before['player_id'] ?? null;
                if (! $old) {
                    return null;
                }
                return $entry('composition.clear', sprintf('a retiré %s de la compo %s', $this->lookup->playerName($old), $where(self::int($p, 'slot_number'))));

            case 'TeamsController::setCaptain':
                return $entry('composition.captain', sprintf('a nommé %s capitaine %s', $this->lookup->playerName(self::int($p, 'player_id')), $where()));

            case 'AvailabilityController::setAvailability':
                $status = self::str($p, 'status') ?: 'unknown';
                $old    = $before['status'] ?? 'unknown';
                if ($old === $status) {
                    return null;
                }
                return $entry('availability.set', sprintf(
                    'a passé la dispo de %s de « %s » à « %s » pour la %s',
                    $this->lookup->playerName(self::int($p, 'player_id')),
                    self::status($old),
                    self::status($status),
                    self::round($round, $phase)
                ), ['team_code' => '']);

            case 'MagicLinkController::setAvailability':
                if (empty($before['player_id'])) {
                    return null;
                }
                $name = $this->lookup->playerName((int) $before['player_id']);
                return $entry('availability.answer', sprintf(
                    'a répondu « %s » pour la %s',
                    self::status(self::str($p, 'status') ?: 'unknown'),
                    self::round($round, $phase)
                ), ['season' => (string) $before['season'], 'team_code' => '', 'actor' => $name, 'user_id' => 0, 'source' => 'lien joueur']);

            case 'MagicLinkController::generate':
                $sent = array_column(array_filter((array) ($result['results'] ?? []), fn ($r): bool => is_array($r) && ($r['status'] ?? '') === 'sent'), 'name');
                if (! $sent) {
                    return null;
                }
                return $entry('magic_link.send', sprintf('a envoyé un lien de dispo à %s', implode(', ', $sent)));

            case 'MatchAppearanceController::validateRound':
                return $entry('round.validate', sprintf('a validé la compo %s', $where()));

            case 'MatchAppearanceController::unvalidateRound':
                return $entry('round.unvalidate', sprintf('a annulé la validation de la compo %s', $where()));

            case 'MatchAppearanceController::declareForfeit':
                return $entry('forfeit.declare', sprintf('a déclaré forfait %s', $where()));

            case 'MatchAppearanceController::withdrawForfeit':
                return $entry('forfeit.withdraw', sprintf('a annulé le forfait %s', $where()));

            case 'MatchAppearanceController::importAppearances':
                return $entry('appearances.import', 'a importé les feuilles de match : ' . self::str($result, 'message'));

            case 'PhaseSquadController::addPlayer':
                return $entry('squad.add', sprintf("a ajouté %s à l'effectif (%s, phase %d)", $this->lookup->playerName(self::int($p, 'player_id')), $this->lookup->teamName($team), $phase));

            case 'PhaseSquadController::removePlayer':
                return $entry('squad.remove', sprintf("a retiré %s de l'effectif (%s, phase %d)", $this->lookup->playerName(self::int($p, 'player_id')), $this->lookup->teamName($team), $phase));

            case 'PhaseSquadController::setCaptain':
                $captain = self::int($p, 'player_id');
                return $entry('squad.captain', $captain
                    ? sprintf('a nommé %s capitaine de phase (%s, phase %d)', $this->lookup->playerName($captain), $this->lookup->teamName($team), $phase)
                    : sprintf('a retiré le capitaine de phase (%s, phase %d)', $this->lookup->teamName($team), $phase));

            case 'PhaseSquadController::ventilate':
                return $entry('squad.ventilate', sprintf(
                    'a ventilé les effectifs dans les compos de la phase %d%s : %s',
                    $phase,
                    ! empty($p['overwrite']) ? ', en écrasant les compos existantes' : '',
                    self::str($result, 'message')
                ));

            case 'PlayersController::updatePlayer':
                $old = $before['contact'] ?? null;
                if (! is_array($old)) {
                    return null;
                }
                $changed = [];
                foreach (self::CONTACT_LABELS as $field => $label) {
                    if (array_key_exists($field, $p) && trim((string) $p[$field]) !== $old[$field]) {
                        $changed[] = $label;
                    }
                }
                if (! $changed) {
                    return null;
                }
                return $entry('player.update', sprintf('a modifié %s de %s', self::join($changed), $this->lookup->playerName(self::int($p, 'id'))));

            case 'PlayersController::deletePlayer':
                return $entry('player.deactivate', sprintf('a désactivé %s (retiré des compos non validées et des effectifs)', $this->lookup->playerName(self::int($p, 'id'))));

            case 'PlayersController::restorePlayer':
                return $entry('player.restore', sprintf('a réactivé %s', $this->lookup->playerName(self::int($p, 'id'))));

            case 'PlayersController::deletePlayerPermanently':
                return $entry('player.delete', sprintf('a supprimé définitivement %s et tout son historique', (string) ($before['name'] ?? '')));

            case 'SyncController::sync':
                return $entry('players.sync', 'a synchronisé les joueurs : ' . self::str($result, 'message'));

            case 'SeasonController::reset':
                return $entry('season.reset', sprintf('a remis à zéro la phase %d : %s', $phase, self::str($result, 'message')));

            case 'SmsTemplatesController::update':
                $kinds = array_values(array_intersect_key(self::SMS_LABELS, array_filter($p, fn ($v, $k): bool => is_string($v) && isset(self::SMS_LABELS[$k]), ARRAY_FILTER_USE_BOTH)));
                return $kinds ? $entry('sms.update', sprintf('a personnalisé son SMS « %s »', implode(' » et « ', $kinds))) : null;

            case 'SmsTemplatesController::reset':
                return $entry('sms.reset', sprintf('est revenu au SMS du club « %s »', self::SMS_LABELS[self::str($p, 'kind')] ?? self::str($p, 'kind')));
        }

        return null;
    }

    /**
     * Changement d'un réglage du back-office.
     *
     * @return array<string, mixed>|null
     */
    public function describeOption(string $option, mixed $old, mixed $new): ?array
    {
        $entry = fn (string $summary, int $phase = 0): array => [
            'action' => 'settings.' . $option, 'summary' => $summary,
            'season' => '', 'phase' => $phase, 'round' => 0, 'team_code' => '',
        ];

        if (preg_match('/^ttp_journee_dates_p([12])$/', $option, $m)) {
            $changes = [];
            foreach ((array) $new as $i => $date) {
                $before = (string) (((array) $old)[$i] ?? '');
                if ((string) $date !== $before) {
                    $changes[] = sprintf('J%d %s → %s', $i + 1, self::day($before), self::day((string) $date));
                }
            }
            return $changes ? $entry(sprintf('a modifié les dates de la phase %s : %s', $m[1], implode(', ', $changes)), (int) $m[1]) : null;
        }

        if (preg_match('/^ttp_sms_template_(availability|confirmation)$/', $option, $m)) {
            return $entry(sprintf('a modifié le SMS du club « %s »', self::SMS_LABELS[$m[1]]));
        }

        return match ($option) {
            'monclubtt_ge_teams'       => $entry('a modifié les équipes'),
            'monclubtt_ge_club_name'   => $entry(sprintf('a renommé le club en « %s »', (string) $new)),
            'monclubtt_ge_pwa_enabled' => $entry($new ? "a activé l'installation de l'application" : "a désactivé l'installation de l'application"),
            default           => null,
        };
    }

    private function magicLinkSnapshot(array $p): array
    {
        return $this->lookup->magicLink(self::str($p, 'token')) ?? [];
    }

    private static function round(int $round, int $phase): string
    {
        return sprintf('J%d de la phase %d', $round, $phase);
    }

    private static function status(string $status): string
    {
        return self::STATUS_LABELS[$status] ?? $status;
    }

    /** « 2026-11-07 » → « 07/11 », une date vide → « — ». */
    private static function day(string $iso): string
    {
        return preg_match('/^\d{4}-(\d{2})-(\d{2})$/', $iso, $m) ? $m[2] . '/' . $m[1] : '—';
    }

    /** @param list<string> $items */
    private static function join(array $items): string
    {
        $last = array_pop($items);
        return $items ? implode(', ', $items) . ' et ' . $last : (string) $last;
    }

    private static function str(array $p, string $key): string
    {
        return is_scalar($p[$key] ?? null) ? trim((string) $p[$key]) : '';
    }

    private static function int(array $p, string $key): int
    {
        return is_numeric($p[$key] ?? null) ? (int) $p[$key] : 0;
    }
}
