<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Domain;

/**
 * Niveau d'une équipe, tel que saisi dans les réglages : saisie libre, qui
 * accepte les libellés ("Pré-Nationale", "Régionale 2") comme les
 * abréviations ("PN", "R2").
 */
final class Division
{
    /** "Pré-Nationale" → "pre nationale", "R 1" → "r 1". */
    public static function normalize(string $level): string
    {
        $normalized = strtolower(remove_accents(trim($level)));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return trim($normalized);
    }
}
