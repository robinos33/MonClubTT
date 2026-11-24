<?php

declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Rest;

use WP_REST_Request;
use WP_REST_Response;
use MonClubTT\GestionEquipes\Capabilities;

/**
 * PUT /monclubtt-equipes/v1/sms-templates
 *
 * Modifie les SMS pré-rédigés depuis le front (Réglages), pour ceux qui gèrent
 * le planner sans accès au back-office.
 *
 * Body JSON : { "availability"?: string, "confirmation"?: string }
 * Réponse : les deux modèles enregistrés.
 */
class SmsTemplatesController
{
    private const NS = 'monclubtt-equipes/v1';

    /** Clé côté API => option WordPress. */
    private const OPTIONS = [
        'availability' => 'monclubtt_ge_sms_template_availability',
        'confirmation' => 'monclubtt_ge_sms_template_confirmation',
    ];

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
        foreach (self::OPTIONS as $key => $option) {
            $value = $request->get_param($key);
            if ($value === null) {
                continue;
            }
            $value = sanitize_textarea_field((string) $value);
            if (trim($value) === '') {
                return new WP_REST_Response(['message' => __('Le texte du SMS ne peut pas être vide.', 'mon-club-tt')], 400);
            }
            $changes[$option] = $value;
        }

        foreach ($changes as $option => $value) {
            update_option($option, $value);
        }

        $templates = [];
        foreach (self::OPTIONS as $key => $option) {
            $templates[$key] = (string) get_option($option, '');
        }

        return new WP_REST_Response($templates, 200);
    }

    public function canWrite(): bool
    {
        return Capabilities::canManagePlanner();
    }
}
