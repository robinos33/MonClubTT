<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class MatchAppearance
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $season,
        public readonly int     $phase,
        public readonly int     $round,
        public readonly string  $teamCode,
        public readonly int     $teamRank,
        public readonly int     $playerId,
        public readonly int     $slotNumber,
        public readonly string  $validatedAt,
        public readonly ?int    $validatedBy,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:          (int)    $row['id'],
            season:      (string) $row['season'],
            phase:       (int)    $row['phase'],
            round:       (int)    $row['round'],
            teamCode:    (string) $row['team_code'],
            teamRank:    (int)    $row['team_rank'],
            playerId:    (int)    $row['player_id'],
            slotNumber:  (int)    $row['slot_number'],
            validatedAt: (string) $row['validated_at'],
            validatedBy: isset($row['validated_by']) ? (int) $row['validated_by'] : null,
        );
    }

    public function toArray(): array
    {
        return [
            'id'           => $this->id,
            'season'       => $this->season,
            'phase'        => $this->phase,
            'round'        => $this->round,
            'team_code'    => $this->teamCode,
            'team_rank'    => $this->teamRank,
            'player_id'    => $this->playerId,
            'slot_number'  => $this->slotNumber,
            'validated_at' => $this->validatedAt,
            'validated_by' => $this->validatedBy,
        ];
    }
}
