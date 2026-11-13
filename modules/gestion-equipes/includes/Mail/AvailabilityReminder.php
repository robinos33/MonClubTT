<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Mail;

use MonClubTT\GestionEquipes\Domain\Availability;
use MonClubTT\GestionEquipes\Front\Assets;
use MonClubTT\GestionEquipes\Front\MagicLinkTemplate;
use MonClubTT\GestionEquipes\Repository\AvailabilityRepository;
use MonClubTT\GestionEquipes\Repository\MagicLinkRepository;
use MonClubTT\GestionEquipes\Repository\PlayerRepository;

/**
 * Relance quotidienne des joueurs qui n'ont pas répondu.
 *
 * Un joueur est relancé une seule fois par envoi initial : le scan ignore
 * les liens déjà marqués reminded_at, et ignore aussi les liens émis par une
 * relance (is_reminder = 1). Sans cette seconde condition, chaque relance
 * redeviendrait elle-même éligible N jours plus tard — le joueur recevrait
 * un mail tous les N jours jusqu'à la fin de la saison.
 *
 * Le token en clair n'existe qu'au moment de l'envoi (seul son hash est en
 * base), donc une relance ne peut pas renvoyer le lien d'origine : elle en
 * émet un nouveau. L'ancien reste valable, ce qui est sans risque puisqu'il
 * appartient déjà au même joueur.
 */
class AvailabilityReminder
{
    public const CRON_HOOK = 'monclubtt_ge_send_availability_reminders';

    /** Délai par défaut avant relance, en jours. */
    private const DEFAULT_DELAY_DAYS = 7;

    /** Durée de validité minimale d'un lien de relance, en jours. */
    private const MIN_TTL_DAYS = 30;

    /**
     * Programme le scan quotidien s'il ne l'est pas déjà.
     * Appelé à l'activation et à chaque chargement : le cron se répare seul
     * si un vidage de wp_cron l'a perdu au passage.
     */
    public static function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::CRON_HOOK) === false) {
            // 8h du matin, heure du site : le mail arrive avant l'entraînement,
            // pas au milieu de la nuit.
            wp_schedule_event(self::nextMorning(), 'daily', self::CRON_HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::CRON_HOOK);
    }

    private static function nextMorning(): int
    {
        $offset = (int) (get_option('gmt_offset', 0) * HOUR_IN_SECONDS);
        $today8 = strtotime(gmdate('Y-m-d', time() + $offset) . ' 08:00:00') - $offset;

        return $today8 > time() ? $today8 : $today8 + DAY_IN_SECONDS;
    }

    /**
     * Point d'entrée du cron.
     *
     * @return int Nombre de relances effectivement envoyées.
     */
    public function run(): int
    {
        $delayDays = $this->delayDays();
        if ($delayDays <= 0) {
            return 0; // relances désactivées via le filtre
        }

        $season = Assets::computeSeason();
        $links   = new MagicLinkRepository();
        $pending = $links->findAwaitingReminder($season, $delayDays);

        if ($pending === []) {
            return 0;
        }

        $players       = new PlayerRepository();
        $availabilities = new AvailabilityRepository();
        $mailer        = new MagicLinkMailer();
        $sent          = 0;

        foreach ($pending as $row) {
            $player = $players->findById($row['player_id']);

            // Joueur supprimé, désactivé ou sans mail valide : on marque le
            // lien comme relancé pour ne pas le rescanner chaque jour.
            if (! $player || ! $player->isActive || empty($player->email) || ! is_email($player->email)) {
                $links->markReminded($row['id']);
                continue;
            }

            // Réponse arrivée par un autre lien entre-temps : rien à relancer.
            if ($this->hasAnswered($availabilities, $row['player_id'], $season)) {
                $links->markReminded($row['id']);
                continue;
            }

            $ttlDays = $this->reminderTtlDays($row['expires_at']);
            $token   = $links->create($row['player_id'], $season, $ttlDays, true);
            $url     = MagicLinkTemplate::buildUrl($token);

            // Marqué avant l'envoi : un wp_mail() qui lève ne doit pas
            // provoquer une nouvelle tentative chaque jour.
            $links->markReminded($row['id']);

            if ($mailer->send($player->email, $player->firstName, $url, $ttlDays, true)) {
                $sent++;
            }
        }

        return $sent;
    }

    /** Une seule réponse non « unknown » suffit à considérer le joueur comme actif. */
    private function hasAnswered(AvailabilityRepository $repository, int $playerId, string $season): bool
    {
        foreach ($repository->findByPlayer($playerId, $season) as $availability) {
            if ($availability->status !== Availability::STATUS_UNKNOWN) {
                return true;
            }
        }

        return false;
    }

    /** Délai avant relance — 0 ou moins désactive complètement les relances. */
    private function delayDays(): int
    {
        return (int) apply_filters('monclubtt_ge_reminder_delay_days', self::DEFAULT_DELAY_DAYS);
    }

    /**
     * Le lien de relance vit au moins aussi longtemps que celui qu'il
     * remplace, avec un plancher : relancer avec un lien qui expire dans
     * deux jours n'aurait pas de sens.
     */
    private function reminderTtlDays(string $originalExpiresAt): int
    {
        $expires = strtotime($originalExpiresAt . ' UTC');
        $left    = $expires !== false ? (int) ceil(($expires - time()) / DAY_IN_SECONDS) : 0;

        return max($left, self::MIN_TTL_DAYS);
    }
}
