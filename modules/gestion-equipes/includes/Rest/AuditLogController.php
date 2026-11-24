<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Response;
use MonClubTT\GestionEquipes\Repository\AuditLogRepository;

/**
 * GET /monclubtt-equipes/v1/audit-log
 *
 * Journal des actions des derniers jours, du plus récent au plus ancien.
 * Réservé aux administrateurs : la commission équipe écrit dans le journal
 * sans pouvoir le lire.
 */
class AuditLogController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/audit-log', [
            'methods'             => 'GET',
            'callback'            => [$this, 'list'],
            'permission_callback' => [$this, 'canRead'],
        ]);
    }

    public function list(): WP_REST_Response
    {
        return new WP_REST_Response((new AuditLogRepository())->recent(), 200);
    }

    public function canRead(): bool
    {
        return current_user_can('manage_options');
    }
}
