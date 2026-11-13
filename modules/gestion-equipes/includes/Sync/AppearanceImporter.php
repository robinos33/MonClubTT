<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Sync;

use MonClubTT\GestionEquipes\Domain\BurnageChecker;
use MonClubTT\GestionEquipes\Domain\TeamComposition;
use MonClubTT\GestionEquipes\Front\Assets;
use MonClubTT\GestionEquipes\Repository\MatchAppearanceRepository;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;
use MonClubTT\GestionEquipes\Repository\ValidatedRoundRepository;

/**
 * Importe les rencontres réellement jouées depuis les feuilles de match FFTT,
 * lues via MonClubTT (filtres monclubtt_get_equipes,
 * monclubtt_get_rencontres_poule et monclubtt_get_feuille_rencontre, ce
 * dernier à partir de MonClubTT 1.9.0).
 *
 * Chaque équipe masculine du club est rattachée à l'équipe TT Team Planner
 * de même numéro (« US TALENCE 3 » ↔ code « E3 »). Pour chaque journée jouée,
 * les joueurs de la feuille remplacent les rencontres enregistrées pour cette
 * équipe et cette journée, remplissent sa composition dans l'application et
 * la journée est marquée validée : la feuille officielle fait foi sur la
 * saisie manuelle.
 *
 * Les feuilles ne portent pas le numéro de licence : les joueurs sont
 * rapprochés par nom (« NOM Prénom »), sans accents ni casse.
 */
final class AppearanceImporter
{
    public const CRON_HOOK = 'monclubtt_ge_import_appearances';

    public function __construct(
        private readonly PlayerRepository $players = new PlayerRepository(),
        private readonly MatchAppearanceRepository $appearances = new MatchAppearanceRepository(),
        private readonly ValidatedRoundRepository $validations = new ValidatedRoundRepository(),
        private readonly TeamCompositionRepository $compositions = new TeamCompositionRepository(),
    ) {}

    public static function isAvailable(): bool
    {
        return has_filter('monclubtt_get_equipes')
            && has_filter('monclubtt_get_rencontres_poule')
            && has_filter('monclubtt_get_feuille_rencontre');
    }

    /** Programme l'import quotidien s'il ne l'est pas déjà (se répare seul, comme les relances). */
    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK) === false) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'twicedaily', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    /**
     * @return array{imported: int, rounds: list<string>, exempt: list<string>, empty: list<string>, unmatched: list<string>, unmapped: list<string>}
     */
    public function run(): array
    {
        $report = ['imported' => 0, 'rounds' => [], 'exempt' => [], 'empty' => [], 'unmatched' => [], 'unmapped' => []];

        if (! self::isAvailable()) {
            return $report;
        }

        $season    = Assets::computeSeason();
        $teamCodes = $this->teamCodesByRank();
        $byName    = $this->playerIdsByName();

        foreach ((array) apply_filters('monclubtt_get_equipes', 'M') as $team) {
            if (! is_array($team) || empty($team['iddiv']) || empty($team['idpoule'])) {
                continue;
            }

            $rank = self::teamNumber((string) ($team['libequipe'] ?? ''));
            if ($rank === null || ! isset($teamCodes[$rank])) {
                $report['unmapped'][] = (string) ($team['libequipe'] ?? '?');
                continue;
            }
            $teamCode = $teamCodes[$rank];
            $fftTeamId = (string) ($team['idequipe'] ?? '');

            $matches = (array) apply_filters('monclubtt_get_rencontres_poule', null, [
                'division' => $team['iddiv'],
                'poule'    => $team['idpoule'],
            ]);

            foreach ($matches as $match) {
                $this->importMatch($match, $fftTeamId, $teamCode, $rank, $season, $byName, $report);
            }
        }

        update_option('monclubtt_ge_last_appearance_import', current_time('mysql'), false);

        return $report;
    }

    /** Numéro d'équipe dans un libellé FFTT : « US TALENCE 3 - Phase 1 » → 3. */
    public static function teamNumber(string $label): ?int
    {
        $label = (string) preg_replace('/\s*-\s*Phase\s*\d+\s*$/iu', '', $label);
        return preg_match('/(\d+)\s*$/', trim($label), $m) === 1 ? (int) $m[1] : null;
    }

    /** Clé de rapprochement d'un nom de joueur : sans accents, majuscules, espaces normalisés. */
    public static function nameKey(string $name): string
    {
        return strtoupper((string) preg_replace('/\s+/', ' ', trim(remove_accents($name))));
    }

    private function importMatch(mixed $match, string $fftTeamId, string $teamCode, int $rank, string $season, array $byName, array &$report): void
    {
        if (! is_array($match)) {
            return;
        }

        $link = [];
        parse_str(self::text($match['lien'] ?? ''), $link);

        // Une rencontre de l'équipe : son identifiant FFTT figure d'un côté.
        $side = null;
        foreach ([1, 2] as $i) {
            if ((string) ($link['equip_id' . $i] ?? '') === $fftTeamId) {
                $side = $i;
            }
        }
        if ($side === null) {
            return;
        }

        $phase = (int) ($link['phase'] ?? 0);
        $round = preg_match('/tour\s+n\D{0,2}(\d+)/iu', self::text($match['libelle'] ?? ''), $m) === 1 ? (int) $m[1] : 0;
        if ($phase < 1 || $round < 1) {
            return;
        }
        $label = sprintf('%s J%d (phase %d)', $teamCode, $round, $phase);

        // Exempt : l'adversaire est vide. Le club envoie malgré tout une
        // feuille, qui n'est pas publiée : la saisie reste manuelle.
        if (self::text($match['equa'] ?? '') === '' || self::text($match['equb'] ?? '') === '') {
            if (self::isPast(self::text($match['dateprevue'] ?? ''))) {
                $report['exempt'][] = $label;
            }
            return;
        }

        // Pas encore jouée.
        if (self::text($match['scorea'] ?? '') === '' || self::text($match['scoreb'] ?? '') === '') {
            return;
        }

        $sheet = apply_filters('monclubtt_get_feuille_rencontre', null, [
            'renc_id'   => $link['renc_id'] ?? '',
            'is_retour' => (int) ($link['is_retour'] ?? 0),
        ]);
        if (! is_array($sheet)) {
            $report['empty'][] = $label;
            return;
        }

        $names = $this->sheetPlayers($sheet, self::text($link['equip_' . $side] ?? ''));
        if ($names === []) {
            // Rencontre jouée sans joueurs du club sur la feuille : forfait
            // probable, à déclarer dans l'application.
            $report['empty'][] = $label;
            return;
        }

        $slots = [];
        foreach ($names as $i => $name) {
            $playerId = $byName[self::nameKey($name)] ?? null;
            if ($playerId === null) {
                $report['unmatched'][] = $name . ' (' . $label . ')';
                continue;
            }
            $slots[] = new TeamComposition(0, $season, $phase, $round, $teamCode, $i + 1, $playerId);
        }
        if ($slots === []) {
            return;
        }

        $this->appearances->deleteForRound($season, $phase, $round, $teamCode);
        $this->appearances->recordForRound($season, $phase, $round, $teamCode, $rank, $slots, null);
        $this->validations->markValidated($season, $phase, $round, $teamCode, null);

        // La composition affichée dans l'application reprend la feuille.
        // setSlot() retire aussi le joueur d'une autre équipe de la même
        // journée, où il aurait pu être prévu avant le match.
        $this->compositions->clearTeam($season, $phase, $round, $teamCode);
        foreach ($slots as $slot) {
            $this->compositions->setSlot($season, $phase, $round, $teamCode, $slot->slotNumber, (int) $slot->playerId);
        }

        $report['imported'] += count($slots);
        $report['rounds'][]  = $label;
    }

    /**
     * Joueurs de l'équipe du club sur une feuille. La feuille ne reprend pas
     * forcément l'ordre domicile/extérieur de la poule : le côté se retrouve
     * par le nom de l'équipe.
     *
     * @return list<string>
     */
    private function sheetPlayers(array $sheet, string $teamName): array
    {
        $result = is_array($sheet['resultat'] ?? null) ? $sheet['resultat'] : [];
        $column = null;
        foreach (['a', 'b'] as $c) {
            if (self::nameKey(self::text($result['equ' . $c] ?? '')) === self::nameKey($teamName)) {
                $column = $c;
            }
        }
        if ($column === null) {
            return [];
        }

        $rows = $sheet['joueur'] ?? [];
        if (is_array($rows) && isset($rows['xja'])) {
            $rows = [$rows]; // Un seul élément : la conversion XML ne produit pas de liste.
        }

        $names = [];
        foreach ((array) $rows as $row) {
            $name = is_array($row) ? self::text($row['xj' . $column] ?? '') : '';
            if ($name !== '') {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return array<int, string> Code d'équipe TT Team Planner par numéro d'équipe. */
    private function teamCodesByRank(): array
    {
        $codes = [];
        foreach ((array) get_option('monclubtt_ge_teams', []) as $team) {
            $code = is_array($team) ? (string) ($team['code'] ?? '') : '';
            $rank = BurnageChecker::extractTeamRank($code);
            if ($rank !== null && ! isset($codes[$rank])) {
                $codes[$rank] = $code;
            }
        }
        return $codes;
    }

    /** @return array<string, int> Identifiant joueur par clé « NOM PRÉNOM ». */
    private function playerIdsByName(): array
    {
        $ids = [];
        foreach ($this->players->findAll() as $player) {
            $ids[self::nameKey($player->lastName . ' ' . $player->firstName)] = $player->id;
        }
        return $ids;
    }

    /** Date FFTT « JJ/MM/AAAA » passée ou du jour (heure du site) ; faux si illisible. */
    private static function isPast(string $date): bool
    {
        if (preg_match('#^(\d{2})/(\d{2})/(\d{4})$#', $date, $m) !== 1) {
            return false;
        }
        return "{$m[3]}-{$m[2]}-{$m[1]}" <= current_time('Y-m-d');
    }

    /** Les éléments XML vides deviennent des tableaux vides après conversion. */
    private static function text(mixed $value): string
    {
        return is_scalar($value) ? trim((string) $value) : '';
    }
}
