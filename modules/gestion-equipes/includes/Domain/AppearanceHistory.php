<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

/**
 * Rencontres disputées d'une phase, telles que les lit le calcul du brûlage.
 */
interface AppearanceHistory
{
    /** @return MatchAppearance[] Rencontres du joueur dans la phase, par journée croissante. */
    public function findByPlayerAndPhase(string $season, int $phase, int $playerId): array;

    /** @return MatchAppearance[] Rencontres de tous les joueurs à cette journée. */
    public function findByPhaseAndRound(string $season, int $phase, int $round): array;
}
