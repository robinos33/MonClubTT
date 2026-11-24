<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes;

/**
 * Droit de gérer le planner (compos, effectifs, dispos, joueurs, équipes,
 * magic links), indépendant des droits d'édition de WordPress.
 *
 * Le rôle « Membre commission équipe » ne porte que ce droit et la lecture :
 * on peut confier le planner à un capitaine sans lui ouvrir le back-office.
 */
final class Capabilities
{
    public const MANAGE = 'monclubtt_ge_manage_planner';
    public const ROLE   = 'monclubtt_ge_planner';

    /** Rôles natifs qui reçoivent le droit à la création du rôle. */
    private const GRANTED_ROLES = ['administrator', 'editor'];

    /** Rôle et droit équivalents de TT Team Planner, repris à la création du rôle. */
    private const TTP_ROLE   = 'ttp_planner';
    private const TTP_MANAGE = 'ttp_manage_planner';

    public static function canManagePlanner(): bool
    {
        return current_user_can(self::MANAGE);
    }

    /**
     * Crée le rôle et donne le droit aux administrateurs et éditeurs.
     *
     * Ne fait rien si le rôle existe déjà : un droit retiré à la main
     * (aux éditeurs, par exemple) n'est pas rendu à la mise à jour suivante.
     */
    public static function install(): void
    {
        if (get_role(self::ROLE) !== null) {
            return;
        }

        add_role(self::ROLE, self::label(), [
            'read'       => true,
            self::MANAGE => true,
        ]);

        foreach (self::GRANTED_ROLES as $name) {
            get_role($name)?->add_cap(self::MANAGE);
        }

        self::takeOverTtpGrants();
    }

    /**
     * Site venu de TT Team Planner : ses membres commission équipe reçoivent
     * le rôle du module, et un rôle à qui son droit avait été donné à la main
     * reçoit celui du module. Rien à reprendre si TT Team Planner a été
     * supprimé : sa désinstallation retire déjà son rôle et son droit.
     */
    private static function takeOverTtpGrants(): void
    {
        foreach (wp_roles()->roles as $name => $role) {
            if (! empty($role['capabilities'][self::TTP_MANAGE])) {
                get_role($name)?->add_cap(self::MANAGE);
            }
        }

        foreach (get_users(['role' => self::TTP_ROLE, 'fields' => 'ID']) as $userId) {
            (new \WP_User((int) $userId))->add_role(self::ROLE);
        }
    }

    private static function label(): string
    {
        return __('Membre commission équipe', 'mon-club-tt');
    }

    /** Retire le rôle et le droit de tous les rôles qui l'ont reçu. */
    public static function uninstall(): void
    {
        remove_role(self::ROLE);

        foreach (array_keys(wp_roles()->roles) as $name) {
            get_role($name)?->remove_cap(self::MANAGE);
        }
    }
}
