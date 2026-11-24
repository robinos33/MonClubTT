<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;
use MonClubTT\GestionEquipes\Repository\TeamForfeitRepository;
use MonClubTT\GestionEquipes\Repository\ValidatedRoundRepository;

/**
 * Rencontres disputées, complétées par les compositions prévues des journées
 * pas encore validées qui précèdent la journée étudiée : « si les journées
 * d'avant se jouent comme prévu, qui sera brûlé ? ».
 *
 * Une composition prévue compte comme l'aurait fait sa validation (même rang
 * d'équipe, mêmes joueurs). Ne sont pas projetées :
 *  - une équipe déjà validée à cette journée : la feuille ou la validation fait foi ;
 *  - une équipe déclarée forfait à cette journée : ses joueurs n'ont pas joué ;
 *  - une autre saison, une autre phase, ou les journées à partir de la journée étudiée.
 */
final class ProjectedAppearanceHistory implements AppearanceHistory
{
    /** @var array<int, MatchAppearance[]>|null Rencontres projetées, par journée. */
    private ?array $plannedByRound = null;

    public function __construct(
        private readonly AppearanceHistory $played,
        private readonly TeamCompositionRepository $compositions,
        private readonly ValidatedRoundRepository $validations,
        private readonly TeamForfeitRepository $forfeits,
        private readonly string $season,
        private readonly int $phase,
        private readonly int $untilRound,
    ) {}

    public function findByPlayerAndPhase(string $season, int $phase, int $playerId): array
    {
        $appearances = $this->played->findByPlayerAndPhase($season, $phase, $playerId);
        if (! $this->projects($season, $phase)) {
            return $appearances;
        }

        foreach ($this->plannedByRound() as $planned) {
            foreach ($planned as $appearance) {
                if ($appearance->playerId === $playerId) {
                    $appearances[] = $appearance;
                }
            }
        }

        usort($appearances, static fn (MatchAppearance $a, MatchAppearance $b): int => $a->round <=> $b->round);

        return $appearances;
    }

    public function findByPhaseAndRound(string $season, int $phase, int $round): array
    {
        $appearances = $this->played->findByPhaseAndRound($season, $phase, $round);
        if (! $this->projects($season, $phase) || $round >= $this->untilRound) {
            return $appearances;
        }

        return array_merge($appearances, $this->plannedByRound()[$round] ?? []);
    }

    private function projects(string $season, int $phase): bool
    {
        return $season === $this->season && $phase === $this->phase;
    }

    /**
     * Compositions de la phase lues en une requête : seules les journées
     * réellement composées sont parcourues, quelle que soit la journée étudiée.
     *
     * @return array<int, MatchAppearance[]>
     */
    private function plannedByRound(): array
    {
        if ($this->plannedByRound !== null) {
            return $this->plannedByRound;
        }

        $planned = [];
        foreach ($this->compositions->findByPhase($this->season, $this->phase) as $slot) {
            if (
                $slot->round >= $this->untilRound
                || $slot->playerId === null
                || $this->validations->isValidated($this->season, $this->phase, $slot->round, $slot->teamCode)
                || $this->forfeits->isForfeit($this->season, $this->phase, $slot->round, $slot->teamCode)
            ) {
                continue;
            }

            $planned[$slot->round][] = new MatchAppearance(
                id: 0,
                season: $this->season,
                phase: $this->phase,
                round: $slot->round,
                teamCode: $slot->teamCode,
                // Même rang que celui qu'enregistrerait la validation de la journée.
                teamRank: BurnageChecker::extractTeamRank($slot->teamCode) ?? 0,
                playerId: $slot->playerId,
                slotNumber: $slot->slotNumber,
                validatedAt: '',
                validatedBy: null,
            );
        }

        return $this->plannedByRound = $planned;
    }
}
