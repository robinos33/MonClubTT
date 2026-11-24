<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Admin;

use MonClubTT\GestionEquipes\Capabilities;

/**
 * Tient hors du back-office les utilisateurs qui ne peuvent que gérer le
 * planner : wp-admin les renvoie vers le planner, et la barre d'admin
 * leur est masquée.
 *
 * Ne vise que les gestionnaires sans droit d'édition WordPress : un
 * administrateur ou un éditeur garde son back-office, un abonné aussi.
 */
final class BackOfficeGuard
{
    private const SHORTCODE = 'tt_team_planner';

    public function register(): void
    {
        add_action('admin_init', [$this, 'redirectToPlanner']);
        add_filter('show_admin_bar', [$this, 'filterAdminBar']);
    }

    public function redirectToPlanner(): void
    {
        if (! $this->shouldRedirect()) {
            return;
        }

        wp_safe_redirect($this->plannerUrl());
        exit;
    }

    public function shouldRedirect(): bool
    {
        global $pagenow;

        // admin-ajax.php et admin-post.php passent aussi par admin_init :
        // ce sont des points d'entrée techniques, pas le back-office.
        if (wp_doing_ajax() || $pagenow === 'admin-post.php') {
            return false;
        }

        return self::isConfinedToPlanner();
    }

    public function filterAdminBar(bool $show): bool
    {
        return $show && ! self::isConfinedToPlanner();
    }

    public static function isConfinedToPlanner(): bool
    {
        return Capabilities::canManagePlanner() && ! current_user_can('edit_posts');
    }

    /** Première page publiée qui contient le shortcode, sinon l'accueil. */
    public function plannerUrl(): string
    {
        $candidates = get_posts([
            'post_type'   => 'page',
            'post_status' => 'publish',
            's'           => self::SHORTCODE,
            'numberposts' => 5,
            'fields'      => 'ids',
        ]);

        foreach ($candidates as $id) {
            if (has_shortcode((string) get_post_field('post_content', $id), self::SHORTCODE)) {
                return (string) get_permalink($id);
            }
        }

        return home_url('/');
    }
}
