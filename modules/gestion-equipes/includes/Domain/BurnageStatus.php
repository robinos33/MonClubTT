<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

final class BurnageStatus
{
    public function __construct(
        public readonly bool    $burned,
        public readonly ?string $reason,
        public readonly string  $detail,
    ) {}

    public static function ok(): self
    {
        return new self(false, null, '');
    }

    public function toArray(): array
    {
        return [
            'burned' => $this->burned,
            'reason' => $this->reason,
            'detail' => $this->detail,
        ];
    }
}
