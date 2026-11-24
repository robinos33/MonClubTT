<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class TeamComposition
{
    public function __construct(
        public readonly int $id,
        public readonly string $season,
        public readonly int $phase,
        public readonly int $round,
        public readonly string $teamCode,
        public readonly int $slotNumber,
        public readonly ?int $playerId,
        public readonly bool $isCaptain = false,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:          (int)    $row['id'],
            season:      (string) $row['season'],
            phase:       (int)    $row['phase'],
            round:       (int)    $row['round'],
            teamCode:    (string) $row['team_code'],
            slotNumber:  (int)    $row['slot_number'],
            playerId:    isset($row['player_id']) ? (int) $row['player_id'] : null,
            // Vider un slot laisse le drapeau en place : sans joueur, il ne vaut rien.
            isCaptain:   isset($row['player_id']) && ! empty($row['is_captain']),
        );
    }

    public function toArray(): array
    {
        return [
            'id'          => $this->id,
            'season'      => $this->season,
            'phase'       => $this->phase,
            'round'       => $this->round,
            'team_code'   => $this->teamCode,
            'slot_number' => $this->slotNumber,
            'player_id'   => $this->playerId,
            'is_captain'  => $this->isCaptain,
        ];
    }
}
