<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class MagicLink
{
    public function __construct(
        public readonly int     $id,
        public readonly int     $playerId,
        public readonly string  $season,
        public readonly string  $expiresAt,
        public readonly ?string $usedAt,
    ) {}

    public function isExpired(): bool
    {
        // expiresAt est stocké en UTC (gmdate) — on le relit explicitement en
        // UTC pour ne pas dépendre du fuseau horaire par défaut de PHP.
        return (new \DateTimeImmutable($this->expiresAt, new \DateTimeZone('UTC')))->getTimestamp() < time();
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id:        (int) $row['id'],
            playerId:  (int) $row['player_id'],
            season:    (string) $row['season'],
            expiresAt: (string) $row['expires_at'],
            usedAt:    $row['used_at'] ?? null,
        );
    }
}
