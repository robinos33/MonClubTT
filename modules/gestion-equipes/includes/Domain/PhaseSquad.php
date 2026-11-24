<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class PhaseSquad
{
    public function __construct(
        public readonly int $id,
        public readonly string $season,
        public readonly int $phase,
        public readonly string $teamCode,
        public readonly int $playerId,
        public readonly int $position,
        public readonly bool $isCaptain = false,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:       (int)    $row['id'],
            season:   (string) $row['season'],
            phase:    (int)    $row['phase'],
            teamCode: (string) $row['team_code'],
            playerId: (int)    $row['player_id'],
            position: (int)    ($row['position'] ?? 0),
            isCaptain: (bool)  ($row['is_captain'] ?? false),
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'season'    => $this->season,
            'phase'     => $this->phase,
            'team_code' => $this->teamCode,
            'player_id' => $this->playerId,
            'position'   => $this->position,
            'is_captain' => $this->isCaptain,
        ];
    }
}
