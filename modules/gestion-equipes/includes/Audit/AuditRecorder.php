<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Audit;

use Throwable;
use MonClubTT\GestionEquipes\Repository\AuditLogRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Accroche le journal aux écritures, à un seul endroit : toute requête
 * monclubtt-equipes/v1 autre que GET qui réussit est enregistrée, sans rien toucher aux
 * contrôleurs.
 *
 * Le journal ne doit jamais faire échouer l'action qu'il raconte : toute
 * erreur de journalisation est avalée.
 */
class AuditRecorder
{
    /** @var array<int, array<string, mixed>> état d'avant, par requête */
    private array $pending = [];

    public function __construct(
        private AuditDescriber $describer = new AuditDescriber(new AuditLookup()),
        private AuditLogRepository $log = new AuditLogRepository()
    ) {
    }

    public function register(): void
    {
        add_filter('rest_request_before_callbacks', [$this, 'beforeCallbacks'], 10, 3);
        add_filter('rest_request_after_callbacks', [$this, 'afterCallbacks'], 10, 3);
    }

    /**
     * Appelé après le contrôle des droits : $response est une WP_Error si la
     * requête est refusée, et rien n'est alors lu.
     */
    public function beforeCallbacks(mixed $response, array $handler, WP_REST_Request $request): mixed
    {
        $operation = self::operation($handler, $request);
        if ($operation === null || $response instanceof WP_Error) {
            return $response;
        }
        try {
            $this->pending[spl_object_id($request)] = $this->describer->snapshot($operation, $request->get_params());
        } catch (Throwable) {
            // Sans état d'avant, l'entrée sera moins précise, pas absente.
        }
        return $response;
    }

    public function afterCallbacks(mixed $response, array $handler, WP_REST_Request $request): mixed
    {
        $operation = self::operation($handler, $request);
        if ($operation === null) {
            return $response;
        }
        $before = $this->pending[spl_object_id($request)] ?? [];
        unset($this->pending[spl_object_id($request)]);

        if ($response instanceof WP_Error || ($response instanceof WP_REST_Response && $response->get_status() >= 400)) {
            return $response;
        }
        $result = $response instanceof WP_REST_Response ? $response->get_data() : $response;

        try {
            $entry = $this->describer->describe($operation, $request->get_params(), $before, is_array($result) ? $result : []);
            if ($entry !== null) {
                $this->record($entry, 'app');
            }
        } catch (Throwable) {
        }
        return $response;
    }

    /** @param array<string, mixed> $entry */
    private function record(array $entry, string $source): void
    {
        $user = wp_get_current_user();
        $this->log->add([
            'user_id'   => (int) ($entry['user_id'] ?? $user->ID),
            // Copié : le nom reste lisible si le compte est supprimé.
            'actor'     => (string) ($entry['actor'] ?? ($user->display_name ?: $user->user_login)),
            'source'    => (string) ($entry['source'] ?? $source),
            'action'    => (string) $entry['action'],
            // Espaces insécables : un guillemet ne finit pas seul en bout de ligne.
            'summary'   => str_replace(['« ', ' »'], ["«\u{a0}", "\u{a0}»"], (string) $entry['summary']),
            'season'    => (string) $entry['season'],
            'phase'     => (int) $entry['phase'],
            'round'     => (int) $entry['round'],
            'team_code' => (string) $entry['team_code'],
        ]);
    }

    /** « Contrôleur::méthode » d'une écriture monclubtt-equipes/v1, null pour le reste. */
    public static function operation(array $handler, WP_REST_Request $request): ?string
    {
        $callback = $handler['callback'] ?? null;
        if ($request->get_method() === 'GET' || ! str_starts_with((string) $request->get_route(), '/monclubtt-equipes/v1/') || ! is_array($callback) || ! is_object($callback[0] ?? null)) {
            return null;
        }
        $class = get_class($callback[0]);
        return substr($class, (int) strrpos($class, '\\') + 1) . '::' . $callback[1];
    }
}
