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
    /** Divisions du championnat régional LNATT (règlement régional, § 2). */
    private const REGIONAL = [
        'pn', 'pre nationale', 'pre national',
        'r1', 'regionale 1', 'regional 1',
        'r2', 'regionale 2', 'regional 2',
        'r3', 'regionale 3', 'regional 3',
    ];

    /** "Pré-Nationale" → "pre nationale", "R 1" → "r 1". */
    public static function normalize(string $level): string
    {
        $normalized = strtolower(remove_accents(trim($level)));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', ' ', $normalized);

        return trim($normalized);
    }

    /**
     * Le championnat régional exige des équipes complètes : 4 joueurs ayant
     * disputé au moins une partie (règlement régional LNATT, art. 4.6.2).
     * La Pré-Régionale relève des comités, et le niveau national des
     * règlements fédéraux, non vérifiés ici : ni l'une ni l'autre n'est
     * concernée.
     */
    public static function requiresFullTeam(string $level): bool
    {
        return in_array(self::normalize($level), self::REGIONAL, true);
    }
}
