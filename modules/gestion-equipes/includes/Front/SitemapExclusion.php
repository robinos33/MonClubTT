<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Front;

/**
 * Retire du sitemap natif de WordPress (wp-sitemap.xml) toute publication
 * contenant le shortcode [tt_team_planner].
 *
 * L'application est réservée aux membres connectés (StandaloneTemplate
 * redirige les visiteurs vers la connexion et envoie déjà noindex) : la
 * proposer aux moteurs de recherche n'a aucun sens. Les pages concernées
 * sont détectées par leur contenu, sans avoir à connaître leur slug.
 */
class SitemapExclusion
{
    private const SHORTCODE = 'tt_team_planner';

    /** @var array<string, int[]> IDs exclus, mémorisés par type de contenu. */
    private array $cache = [];

    public function register(): void
    {
        add_filter('wp_sitemaps_posts_query_args', [$this, 'excludePosts'], 10, 2);
    }

    /**
     * @param array<string, mixed> $args
     * @return array<string, mixed>
     */
    public function excludePosts(array $args, string $postType): array
    {
        $ids = $this->findPostIds($postType);
        if ($ids === []) {
            return $args;
        }

        $existing             = isset($args['post__not_in']) ? (array) $args['post__not_in'] : [];
        $args['post__not_in'] = array_values(array_unique(array_merge($existing, $ids)));

        return $args;
    }

    /** @return int[] */
    private function findPostIds(string $postType): array
    {
        if (isset($this->cache[$postType])) {
            return $this->cache[$postType];
        }

        global $wpdb;

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- exécuté uniquement à la génération du sitemap, mémorisé pour la requête
        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT ID, post_content FROM {$wpdb->posts}
                 WHERE post_status  = 'publish'
                   AND post_type    = %s
                   AND post_content LIKE %s",
                $postType,
                '%' . $wpdb->esc_like('[' . self::SHORTCODE) . '%'
            )
        );

        $ids = [];
        foreach ((array) $rows as $row) {
            // Le LIKE dégrossit, has_shortcode() confirme (évite [tt_team_planner_xxx]).
            if (has_shortcode((string) $row->post_content, self::SHORTCODE)) {
                $ids[] = (int) $row->ID;
            }
        }

        return $this->cache[$postType] = $ids;
    }
}
