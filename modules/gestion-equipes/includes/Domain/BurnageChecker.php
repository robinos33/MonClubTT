<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

use MonClubTT\GestionEquipes\Repository\MatchAppearanceRepository;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;
use MonClubTT\GestionEquipes\Repository\TeamForfeitRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;

/**
 * Calcule le statut de "brûlage" d'un joueur pour une équipe donnée, selon le
 * règlement sportif FFTT II.112.1 (format 4 joueurs) :
 *
 *  - Règle 2 : un joueur ayant disputé 2 rencontres (consécutives ou non) dans
 *    une phase, au sein d'une équipe de numéro N (ou d'équipes différentes),
 *    ne peut plus jouer dans une équipe de numéro supérieur à N.
 *  - Règle 3 : à la 2e journée d'une phase, une équipe ne peut comporter plus
 *    d'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.
 *
 * S'y ajoutent deux règles de participation :
 *
 *  - Points minimum (règlement régional LNATT, art. 4.1) : 1200 points en
 *    Pré-Nationale, 1000 en Régionale 1, atteints lors de l'un des deux
 *    classements officiels de la saison. Exception (FFTT II.112.4) : en
 *    phase 2, un joueur ayant disputé au moins 3 rencontres avec l'équipe en
 *    phase 1 reste qualifié.
 *  - Étrangers (FFTT II.609) : une équipe de 4 joueurs ou moins ne peut
 *    comporter qu'un seul joueur étranger.
 *
 * Et les deux conséquences d'un forfait déclaré (FFTT II.112.2) :
 *
 *  - Forfait à la 1re journée : à la 2e journée, l'équipe ne peut aligner que
 *    des joueurs n'ayant pas joué la 1re journée dans une autre équipe.
 *  - Forfait à une autre journée : les joueurs ayant disputé la journée
 *    précédente dans l'équipe forfait ne peuvent pas jouer, à cette journée,
 *    dans une équipe de numéro supérieur.
 *
 * Le "numéro" d'une équipe est déduit du `team_code` (ex. "T1" → 1, "T2" → 2).
 * Si aucun chiffre n'est trouvé, les règles de brûlage sont désactivées pour
 * cette équipe ; les règles de participation restent vérifiées.
 */
final class BurnageChecker
{
    /** Rencontres de phase 1 avec l'équipe qui maintiennent la qualification en phase 2 (FFTT II.112.4). */
    private const PROMOTION_MIN_APPEARANCES = 3;

    public function __construct(
        private readonly MatchAppearanceRepository $appearances = new MatchAppearanceRepository(),
        private readonly TeamCompositionRepository $compositions = new TeamCompositionRepository(),
        private readonly PlayerRepository $players = new PlayerRepository(),
        private readonly TeamForfeitRepository $forfeits = new TeamForfeitRepository(),
    ) {}

    /**
     * Points minimum exigés par le niveau d'une équipe, ou null si le niveau
     * n'impose rien. Accepte les libellés des réglages ("Pré-Nationale",
     * "Régionale 1") comme les abréviations ("PN", "R1").
     */
    public static function minimumPointsForLevel(string $level): ?int
    {
        $normalized = strtolower(remove_accents(trim($level)));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);
        $normalized = trim($normalized);

        if (in_array($normalized, ['pn', 'pre nationale', 'pre national'], true)) {
            return 1200;
        }
        if (in_array($normalized, ['r1', 'regionale 1', 'regional 1'], true)) {
            return 1000;
        }

        return null;
    }

    public static function extractTeamRank(string $teamCode): ?int
    {
        if (preg_match('/(\d+)/', $teamCode, $matches) === 1) {
            return (int) $matches[1];
        }

        return null;
    }

    public function statusFor(string $season, int $phase, int $round, string $teamCode, int $playerId): BurnageStatus
    {
        $teamRank = self::extractTeamRank($teamCode);

        if ($teamRank !== null) {
            $status = $this->checkRule2($season, $phase, $playerId, $teamRank)
                ?? $this->checkRule3($season, $phase, $round, $teamCode, $teamRank, $playerId)
                ?? $this->checkForfeitPreviousRound($season, $phase, $round, $teamRank, $playerId);
            if ($status !== null) {
                return $status;
            }
        }

        $status = $this->checkForfeitFirstRound($season, $phase, $round, $teamCode, $playerId);
        if ($status !== null) {
            return $status;
        }

        $player = $this->players->findById($playerId);
        if ($player === null) {
            return BurnageStatus::ok();
        }

        return $this->checkMinimumPoints($season, $phase, $teamCode, $player)
            ?? $this->checkForeignLimit($season, $phase, $round, $teamCode, $player)
            ?? BurnageStatus::ok();
    }

    private function checkForfeitFirstRound(string $season, int $phase, int $round, string $teamCode, int $playerId): ?BurnageStatus
    {
        if ($round !== 2 || ! $this->forfeits->isForfeit($season, $phase, 1, $teamCode)) {
            return null;
        }

        foreach ($this->appearances->findByPhaseAndRound($season, $phase, 1) as $appearance) {
            if ($appearance->playerId === $playerId && $appearance->teamCode !== $teamCode) {
                return new BurnageStatus(
                    true,
                    'forfeit',
                    __("Forfait de l'équipe à la 1re journée : à la 2e journée, elle ne peut aligner que des joueurs n'ayant pas joué la 1re journée dans une autre équipe.", 'mon-club-tt')
                );
            }
        }

        return null;
    }

    private function checkForfeitPreviousRound(string $season, int $phase, int $round, int $teamRank, int $playerId): ?BurnageStatus
    {
        if ($round < 2) {
            return null;
        }

        $forfeitRanks = [];
        foreach ($this->forfeits->teamsForRound($season, $phase, $round) as $forfeitCode) {
            $rank = self::extractTeamRank($forfeitCode);
            if ($rank !== null && $rank < $teamRank) {
                $forfeitRanks[$forfeitCode] = $rank;
            }
        }
        if ($forfeitRanks === []) {
            return null;
        }

        foreach ($this->appearances->findByPhaseAndRound($season, $phase, $round - 1) as $appearance) {
            if ($appearance->playerId === $playerId && isset($forfeitRanks[$appearance->teamCode])) {
                return new BurnageStatus(
                    true,
                    'forfeit',
                    sprintf(
                        /* translators: %s: code de l'équipe forfait */
                        __("L'équipe %s est forfait à cette journée : ses joueurs de la journée précédente ne peuvent pas jouer dans une équipe de numéro supérieur.", 'mon-club-tt'),
                        $appearance->teamCode
                    )
                );
            }
        }

        return null;
    }

    private function checkMinimumPoints(string $season, int $phase, string $teamCode, Player $player): ?BurnageStatus
    {
        $minimum = self::minimumPointsForLevel(self::teamLevel($teamCode));
        if ($minimum === null) {
            return null;
        }

        // Points officiels inconnus pour cette saison (joueurs pas encore
        // resynchronisés) : on ne bloque pas sur une donnée absente.
        if ($player->bestOfficialSeason !== $season || $player->bestOfficialPoints <= 0) {
            return null;
        }

        if ($player->bestOfficialPoints >= $minimum) {
            return null;
        }

        if ($phase === 2) {
            $phase1Appearances = array_filter(
                $this->appearances->findByPlayerAndPhase($season, 1, $player->id),
                static fn (MatchAppearance $appearance): bool => $appearance->teamCode === $teamCode
            );
            if (count($phase1Appearances) >= self::PROMOTION_MIN_APPEARANCES) {
                return null;
            }
        }

        return new BurnageStatus(
            true,
            'min_points',
            sprintf(
                /* translators: 1: points minimum de la division, 2: meilleurs points officiels du joueur sur la saison */
                __('Points minimum non atteints : %1$d points exigés dans cette division, %2$d points au meilleur classement officiel de la saison.', 'mon-club-tt'),
                $minimum,
                $player->bestOfficialPoints
            )
        );
    }

    private function checkForeignLimit(string $season, int $phase, int $round, string $teamCode, Player $player): ?BurnageStatus
    {
        if (! $player->isForeign) {
            return null;
        }

        // Les équipes de l'application comptent 4 joueurs : un seul étranger admis.
        foreach ($this->compositions->findByTeamAndRound($season, $phase, $round, $teamCode) as $slot) {
            if ($slot->playerId === null || $slot->playerId === $player->id) {
                continue;
            }
            $teammate = $this->players->findById($slot->playerId);
            if ($teammate !== null && $teammate->isForeign) {
                return new BurnageStatus(
                    true,
                    'foreign',
                    __('Une équipe de 4 joueurs ne peut comporter qu\'un seul joueur étranger (hors UE, EEE et Suisse).', 'mon-club-tt')
                );
            }
        }

        return null;
    }

    private static function teamLevel(string $teamCode): string
    {
        foreach ((array) get_option('monclubtt_ge_teams', []) as $team) {
            if (is_array($team) && ($team['code'] ?? '') === $teamCode) {
                return (string) ($team['level'] ?? '');
            }
        }

        return '';
    }

    private function checkRule2(string $season, int $phase, int $playerId, int $teamRank): ?BurnageStatus
    {
        $appearances = $this->appearances->findByPlayerAndPhase($season, $phase, $playerId);
        if (count($appearances) < 2) {
            return null;
        }

        // Pour chaque journée distincte, on retient le rang le plus fort (numériquement le plus petit) atteint.
        $bestRankByRound = [];
        foreach ($appearances as $appearance) {
            $round = $appearance->round;
            if (! isset($bestRankByRound[$round]) || $appearance->teamRank < $bestRankByRound[$round]) {
                $bestRankByRound[$round] = $appearance->teamRank;
            }
        }

        if (count($bestRankByRound) < 2) {
            return null;
        }

        $ranks = array_values($bestRankByRound);
        sort($ranks);

        // Une fois trié, le rang plafond N est le 2e rang le plus fort rencontré :
        // c'est le plus petit N tel qu'au moins 2 journées aient été jouées à un rang <= N.
        $ceiling = $ranks[1];

        if ($teamRank > $ceiling) {
            return new BurnageStatus(
                true,
                'rule2',
                sprintf(
                    /* translators: %d: numéro d'équipe */
                    __('A déjà disputé 2 rencontres dans une équipe de numéro %d ou inférieur : ne peut plus jouer dans une équipe de numéro supérieur.', 'mon-club-tt'),
                    $ceiling
                )
            );
        }

        return null;
    }

    private function checkRule3(
        string $season,
        int $phase,
        int $round,
        string $teamCode,
        int $teamRank,
        int $playerId
    ): ?BurnageStatus {
        if ($round !== 2) {
            return null;
        }

        $previousRoundAppearances = $this->appearances->findByPhaseAndRound($season, $phase, $round - 1);
        if ($previousRoundAppearances === []) {
            return null;
        }

        $bestRankByPlayer = [];
        foreach ($previousRoundAppearances as $appearance) {
            $pid = $appearance->playerId;
            if (! isset($bestRankByPlayer[$pid]) || $appearance->teamRank < $bestRankByPlayer[$pid]) {
                $bestRankByPlayer[$pid] = $appearance->teamRank;
            }
        }

        $isPromoted = static fn (int $pid): bool =>
            isset($bestRankByPlayer[$pid]) && $bestRankByPlayer[$pid] < $teamRank;

        if (! $isPromoted($playerId)) {
            return null;
        }

        $promotedAlreadyInTeam = 0;
        foreach ($this->compositions->findByTeamAndRound($season, $phase, $round, $teamCode) as $slot) {
            if ($slot->playerId !== null && $slot->playerId !== $playerId && $isPromoted($slot->playerId)) {
                $promotedAlreadyInTeam++;
            }
        }

        if ($promotedAlreadyInTeam >= 1) {
            return new BurnageStatus(
                true,
                'rule3',
                __("Limite J2 : une équipe ne peut comporter plus d'un joueur ayant disputé la 1re journée dans une équipe de numéro inférieur.", 'mon-club-tt')
            );
        }

        return null;
    }
}
