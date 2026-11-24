<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Repository;

/**
 * SMS pré-rédigés : un modèle du club par type (option, réglé au
 * back-office), que chaque membre peut remplacer pour lui-même (user meta).
 * Sans modèle personnel, le membre envoie celui du club, y compris quand le
 * club le change ensuite.
 */
class SmsTemplateRepository
{
    public const KINDS = ['availability', 'confirmation'];

    /** Option du modèle du club ; c'est aussi la clé du modèle personnel. */
    public static function key(string $kind): string
    {
        return 'monclubtt_ge_sms_template_' . $kind;
    }

    /**
     * @return array{templates: array<string, string>, custom: array<string, bool>}
     */
    public function forUser(int $userId): array
    {
        $templates = [];
        $custom    = [];
        foreach (self::KINDS as $kind) {
            $own = $userId > 0 ? (string) get_user_meta($userId, self::key($kind), true) : '';
            $custom[$kind]    = $own !== '';
            $templates[$kind] = $own !== '' ? $own : (string) get_option(self::key($kind), '');
        }

        return ['templates' => $templates, 'custom' => $custom];
    }

    public function save(int $userId, string $kind, string $text): void
    {
        update_user_meta($userId, self::key($kind), $text);
    }

    /** Le membre revient au modèle du club. */
    public function reset(int $userId, string $kind): void
    {
        delete_user_meta($userId, self::key($kind));
    }
}
