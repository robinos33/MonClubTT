<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class Availability
{
    public const STATUS_AVAILABLE   = 'available';
    public const STATUS_UNAVAILABLE = 'unavailable';
    public const STATUS_UNCERTAIN   = 'uncertain';
    public const STATUS_UNKNOWN     = 'unknown';

    public const VALID_STATUSES = [
        self::STATUS_AVAILABLE,
        self::STATUS_UNAVAILABLE,
        self::STATUS_UNCERTAIN,
        self::STATUS_UNKNOWN,
    ];

    public function __construct(
        public readonly int    $id,
        public readonly int    $playerId,
        public readonly string $season,
        public readonly int    $phase,
        public readonly int    $round,
        public readonly string $status,
        public readonly string $comment,
    ) {}

    public static function fromRow(array $row): self
    {
        return new self(
            id:       (int)    $row['id'],
            playerId: (int)    $row['player_id'],
            season:   (string) $row['season'],
            phase:    (int)    $row['phase'],
            round:    (int)    $row['round'],
            status:   (string) $row['status'],
            comment:  (string) ($row['comment'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'        => $this->id,
            'player_id' => $this->playerId,
            'season'    => $this->season,
            'phase'     => $this->phase,
            'round'     => $this->round,
            'status'    => $this->status,
            'comment'   => $this->comment,
        ];
    }
}
