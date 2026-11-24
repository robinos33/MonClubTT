<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Capabilities;
use MonClubTT\GestionEquipes\Repository\SmsTemplateRepository;

/**
 * PUT /monclubtt-equipes/v1/sms-templates
 *
 * Enregistre les SMS pré-rédigés propres au membre connecté. Le modèle du
 * club, réglé au back-office, reste celui des autres membres.
 *
 * Body JSON : { "availability"?: string, "confirmation"?: string }
 * Réponse : { templates: { availability, confirmation }, custom: { availability, confirmation } }
 */
class SmsTemplatesController
{
    private const NS = 'monclubtt-equipes/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NS, '/sms-templates', [
            'methods'             => 'PUT',
            'callback'            => [$this, 'update'],
            'permission_callback' => [$this, 'canWrite'],
            'args'                => [
                'availability' => ['type' => 'string'],
                'confirmation' => ['type' => 'string'],
            ],
        ]);
    }

    /**
     * Un modèle vide est refusé : le front retomberait en silence sur un texte
     * codé en dur. Rien n'est enregistré si l'un des deux est refusé.
     */
    public function update(WP_REST_Request $request): WP_REST_Response
    {
        $changes = [];
        foreach (SmsTemplateRepository::KINDS as $kind) {
            $value = $request->get_param($kind);
            if ($value === null) {
                continue;
            }
            $value = sanitize_textarea_field((string) $value);
            if (trim($value) === '') {
                return new WP_REST_Response(['message' => __('Le texte du SMS ne peut pas être vide.', 'mon-club-tt')], 400);
            }
            $changes[$kind] = $value;
        }

        $repo   = new SmsTemplateRepository();
        $userId = get_current_user_id();
        foreach ($changes as $kind => $value) {
            $repo->save($userId, $kind, $value);
        }

        return new WP_REST_Response($repo->forUser($userId), 200);
    }

    public function canWrite(): bool
    {
        return Capabilities::canManagePlanner();
    }
}
