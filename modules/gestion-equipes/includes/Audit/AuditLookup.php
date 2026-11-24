<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Audit;

use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;
use MonClubTT\GestionEquipes\Repository\MagicLinkRepository;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;
use MonClubTT\GestionEquipes\Repository\TeamCompositionRepository;

/**
 * Lectures dont le journal a besoin pour écrire une phrase lisible : noms des
 * joueurs et des équipes, et l'état d'avant une écriture (qui occupait le
 * slot, quelle était la dispo).
 */
class AuditLookup
{
    /** @var array<int, string> */
    private array $names = [];

    public function playerName(int $playerId): string
    {
        if (! isset($this->names[$playerId])) {
            $player = (new PlayerRepository())->findById($playerId);
            $this->names[$playerId] = $player ? $player->fullName() : sprintf('joueur n° %d', $playerId);
        }
        return $this->names[$playerId];
    }

    public function teamName(string $teamCode): string
    {
        foreach ((array) get_option('monclubtt_ge_teams', []) as $team) {
            if (is_array($team) && ($team['code'] ?? '') === $teamCode && ! empty($team['name'])) {
                return (string) $team['name'];
            }
        }
        return $teamCode;
    }

    public function slotPlayerId(string $season, int $phase, int $round, string $teamCode, int $slot): ?int
    {
        foreach ((new TeamCompositionRepository())->findByTeamAndRound($season, $phase, $round, $teamCode) as $composition) {
            if ($composition->slotNumber === $slot) {
                return $composition->playerId;
            }
        }
        return null;
    }

    public function availabilityStatus(int $playerId, string $season, int $phase, int $round): string
    {
        foreach ((new AvailabilityRepository())->findByPlayer($playerId, $season) as $availability) {
            if ($availability->phase === $phase && $availability->round === $round) {
                return $availability->status;
            }
        }
        return 'unknown';
    }

    /** @return array{phone: string, email: string, notes: string}|null */
    public function contact(int $playerId): ?array
    {
        $player = (new PlayerRepository())->findById($playerId);
        return $player ? ['phone' => $player->phone, 'email' => $player->email, 'notes' => $player->notes] : null;
    }

    /** @return array{player_id: int, season: string}|null */
    public function magicLink(string $token): ?array
    {
        $link = (new MagicLinkRepository())->findValidByToken($token);
        return $link ? ['player_id' => $link->playerId, 'season' => $link->season] : null;
    }
}
