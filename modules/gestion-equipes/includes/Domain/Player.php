<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class Player
{
    public function __construct(
        public readonly int     $id,
        public readonly string  $externalId,
        public readonly string  $firstName,
        public readonly string  $lastName,
        public readonly string  $licenseNumber,
        public readonly string  $phone,
        public readonly string  $email,
        public readonly int     $ranking,
        public readonly string  $usualTeam,
        public readonly bool    $isForeign,
        public readonly bool    $isCaptain,
        public readonly bool    $isYoung,
        public readonly bool    $isMutation,
        public readonly string  $notes,
        public readonly ?string $syncedAt,
        public readonly bool    $isActive = true,
        public readonly int     $bestOfficialPoints = 0,
        public readonly string  $bestOfficialSeason = '',
    ) {}

    public function fullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
    }

    public function initials(): string
    {
        $parts = array_filter(explode(' ', $this->fullName()));
        return strtoupper(implode('', array_map(fn($p) => $p[0], $parts)));
    }

    public static function fromRow(array $row): self
    {
        return new self(
            id:            (int) $row['id'],
            externalId:    (string) $row['external_id'],
            firstName:     (string) $row['first_name'],
            lastName:      (string) $row['last_name'],
            licenseNumber: (string) $row['license_number'],
            phone:         (string) $row['phone'],
            email:         (string) ($row['email'] ?? ''),
            ranking:       (int) $row['ranking'],
            usualTeam:     (string) $row['usual_team'],
            isForeign:     (bool) $row['is_foreign'],
            isCaptain:     (bool) $row['is_captain'],
            isYoung:       (bool) $row['is_young'],
            isMutation:    (bool) $row['is_mutation'],
            notes:         (string) ($row['notes'] ?? ''),
            syncedAt:      $row['synced_at'] ?? null,
            isActive:      ! array_key_exists('is_active', $row) || (bool) $row['is_active'],
            bestOfficialPoints: (int) ($row['best_official_points'] ?? 0),
            bestOfficialSeason: (string) ($row['best_official_season'] ?? ''),
        );
    }

    public function toArray(): array
    {
        return [
            'id'             => $this->id,
            'external_id'    => $this->externalId,
            'first_name'     => $this->firstName,
            'last_name'      => $this->lastName,
            'license_number' => $this->licenseNumber,
            'phone'          => $this->phone,
            'email'          => $this->email,
            'ranking'        => $this->ranking,
            'usual_team'     => $this->usualTeam,
            'is_foreign'     => $this->isForeign,
            'is_captain'     => $this->isCaptain,
            'is_young'       => $this->isYoung,
            'is_mutation'    => $this->isMutation,
            'notes'          => $this->notes,
            'synced_at'      => $this->syncedAt,
            'is_active'      => $this->isActive,
            'best_official_points' => $this->bestOfficialPoints,
            'best_official_season' => $this->bestOfficialSeason,
        ];
    }
}
