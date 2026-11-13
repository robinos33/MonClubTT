<?php
declare(strict_types=1);

namespace MonClubTT\GestionEquipes\Mail;

use MonClubTT\GestionEquipes\Front\Assets;

/**
 * Envoi du mail « indique tes disponibilités » contenant le lien magique,
 * dans ses deux variantes : premier envoi et rappel.
 *
 * Deux responsabilités que wp_mail() ne remplit pas seul :
 *
 * 1. L'expéditeur. Sans en-tête From, WordPress envoie depuis
 *    wordpress@domaine avec « WordPress » comme nom affiché. On envoie
 *    donc au nom du club, depuis une adresse no-reply@ sur le domaine du
 *    site : garder le même domaine est ce qui préserve l'alignement
 *    SPF/DKIM, donc la délivrabilité. Les réponses des joueurs sont
 *    renvoyées vers l'adresse admin via Reply-To.
 *
 * 2. Le multipart. Le HTML seul pénalise le score anti-spam : on fournit
 *    systématiquement la version texte en AltBody.
 *
 * Les en-têtes sont passés à wp_mail() plutôt que posés via les filtres
 * wp_mail_from* : la portée reste ce seul envoi, sans effet de bord sur
 * les mails des autres plugins.
 */
class MagicLinkMailer
{
    /** Palette alignée sur les tokens de assets/css/app.css et magic-link.js. */
    private const PRIMARY   = '#2563eb';
    private const PRIMARY_D = '#1d4ed8';
    private const CANVAS    = '#f5f7fb';
    private const INK       = '#1e293b';
    private const INK_SOFT  = '#64748b';
    private const BORDER    = '#e2e8f0';

    /** Nombre de journées à venir listées dans le mail. */
    private const JOURNEES_SHOWN = 5;

    public function send(string $email, string $firstName, string $url, int $ttlDays, bool $isReminder = false): bool
    {
        $club = $this->clubName();
        $text = $this->renderText($firstName, $club, $url, $ttlDays, $isReminder);

        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            sprintf('From: %s <%s>', $this->fromName($club), $this->fromAddress()),
        ];

        $replyTo = $this->replyTo();
        if ($replyTo !== '') {
            $headers[] = 'Reply-To: ' . $replyTo;
        }

        // AltBody n'est atteignable que sur l'instance PHPMailer : on
        // s'accroche le temps de l'envoi, puis on se détache pour ne pas
        // contaminer les mails suivants.
        $addAltBody = static function ($phpmailer) use ($text): void {
            $phpmailer->AltBody = $text; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.NotSnakeCaseMemberVar -- propriété PHPMailer
        };

        add_action('phpmailer_init', $addAltBody);
        $sent = wp_mail(
            $email,
            $this->subject($club, $isReminder),
            $this->renderHtml($firstName, $club, $url, $ttlDays, $isReminder),
            $headers
        );
        remove_action('phpmailer_init', $addAltBody);

        return $sent;
    }

    // =========================================================================
    // Expéditeur
    // =========================================================================

    private function subject(string $club, bool $isReminder): string
    {
        if ($isReminder) {
            /* translators: %s: nom du club */
            return sprintf(__('[%s] Rappel : tes disponibilités', 'mon-club-tt'), $club);
        }

        /* translators: %s: nom du club */
        return sprintf(__('[%s] Tes disponibilités', 'mon-club-tt'), $club);
    }

    private function clubName(): string
    {
        $club = \monclubtt_nom_club();

        return $club !== '' ? $club : (string) get_bloginfo('name');
    }

    private function fromName(string $club): string
    {
        // Les virgules et guillemets casseraient l'en-tête From.
        $name = str_replace(['"', ',', '<', '>'], ' ', $club);
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? '');

        if ($name === '') {
            $name = 'Team Planner';
        }

        return (string) apply_filters('monclubtt_ge_mail_from_name', $name);
    }

    private function fromAddress(): string
    {
        $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
        $host = strtolower(preg_replace('/^www\./i', '', $host) ?? '');

        $from = $host !== '' ? 'no-reply@' . $host : (string) get_option('admin_email');

        return (string) apply_filters('monclubtt_ge_mail_from', $from);
    }

    private function replyTo(): string
    {
        $admin = (string) get_option('admin_email');

        $replyTo = is_email($admin) ? $admin : '';

        return (string) apply_filters('monclubtt_ge_mail_reply_to', $replyTo);
    }

    // =========================================================================
    // Gabarit HTML
    // =========================================================================

    /**
     * Gabarit HTML « bulletproof » : tables imbriquées, styles en ligne et
     * couleurs opaques uniquement — pas de flexbox, pas de rgba(), pas de
     * webfont (Outlook et Gmail les ignorent). L'aperçu des trois statuts
     * rappelle visuellement l'écran que le joueur va trouver derrière le
     * lien.
     */
    private function renderHtml(string $firstName, string $club, string $url, int $ttlDays, bool $isReminder): string
    {
        $safeClub = esc_html($club);
        $safeUrl  = esc_url($url);

        $font = "-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Helvetica,Arial,sans-serif";

        /* translators: %s: prénom du joueur */
        $hello = sprintf(esc_html__('Salut %s 👋', 'mon-club-tt'), esc_html($firstName));

        if ($isReminder) {
            $eyebrow   = esc_html__('Rappel', 'mon-club-tt');
            $preheader = esc_html__('On attend encore tes disponibilités.', 'mon-club-tt');
            $intro     = esc_html__(
                'On n\'a pas encore reçu tes disponibilités pour les prochaines journées. Trois clics, et c\'est réglé.',
                'mon-club-tt'
            );
        } else {
            $eyebrow   = esc_html__('Disponibilités', 'mon-club-tt');
            $preheader = esc_html__('Indique tes disponibilités pour les prochaines journées.', 'mon-club-tt');
            $intro     = esc_html__(
                'Merci d\'indiquer tes disponibilités pour les prochaines journées de championnat. Trois clics, et c\'est réglé.',
                'mon-club-tt'
            );
        }

        $validity = sprintf(
            esc_html(
                /* translators: %d: nombre de jours de validité du lien */
                _n(
                    'Ce lien t\'est personnel et reste actif %d jour.',
                    'Ce lien t\'est personnel et reste actif %d jours.',
                    $ttlDays,
                    'mon-club-tt'
                )
            ),
            $ttlDays
        );

        $cta       = esc_html__('Indiquer mes disponibilités', 'mon-club-tt');
        $noForward = esc_html__('Ne le transfère pas : il donne accès à ta fiche.', 'mon-club-tt');
        $fallback  = esc_html__('Le bouton ne fonctionne pas ? Copie ce lien dans ton navigateur :', 'mon-club-tt');

        $chips    = $this->renderChips($font);
        $journees = $this->renderJournees($font);

        $primary  = self::PRIMARY;
        $primaryD = self::PRIMARY_D;
        $canvas   = self::CANVAS;
        $ink      = self::INK;
        $inkSoft  = self::INK_SOFT;
        $border   = self::BORDER;

        // Gabarit e-mail : toutes les variables sont échappées plus haut (esc_html, esc_url, esc_attr).
        return "<!DOCTYPE html>
<html lang=\"fr\">
<head>
<meta charset=\"utf-8\">
<meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">
<meta name=\"color-scheme\" content=\"light only\">
<meta name=\"supported-color-schemes\" content=\"light only\">
<title>{$safeClub}</title>
</head>
<body style=\"margin:0;padding:0;background:{$canvas};\">
<div style=\"display:none;max-height:0;overflow:hidden;opacity:0;\">{$preheader}</div>
<table role=\"presentation\" width=\"100%\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"background:{$canvas};\">
<tr><td align=\"center\" style=\"padding:32px 16px;\">

<table role=\"presentation\" width=\"600\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\" style=\"width:100%;max-width:600px;background:#ffffff;border-radius:14px;overflow:hidden;border:1px solid {$border};\">

  <tr><td style=\"background:{$primary};padding:26px 32px;\">
    <div style=\"font:700 11px/1 {$font};letter-spacing:1.4px;text-transform:uppercase;color:#bfdbfe;\">{$eyebrow}</div>
    <div style=\"font:800 21px/1.25 {$font};color:#ffffff;padding-top:7px;\">{$safeClub}</div>
  </td></tr>

  <tr><td style=\"padding:34px 32px 8px 32px;\">
    <div style=\"font:800 24px/1.25 {$font};color:{$ink};\">{$hello}</div>
    <div style=\"font:400 15px/1.6 {$font};color:{$inkSoft};padding-top:12px;\">{$intro}</div>
  </td></tr>
{$journees}
  <tr><td style=\"padding:24px 32px 4px 32px;\">{$chips}</td></tr>

  <tr><td align=\"center\" style=\"padding:28px 32px 10px 32px;\">
    <table role=\"presentation\" cellpadding=\"0\" cellspacing=\"0\" border=\"0\">
    <tr><td align=\"center\" style=\"background:{$primary};border-radius:10px;border-bottom:3px solid {$primaryD};\">
      <a href=\"{$safeUrl}\" style=\"display:inline-block;padding:15px 34px;font:700 16px/1 {$font};color:#ffffff;text-decoration:none;\">{$cta}</a>
    </td></tr>
    </table>
  </td></tr>

  <tr><td style=\"padding:14px 32px 30px 32px;\">
    <div style=\"font:400 13px/1.55 {$font};color:{$inkSoft};text-align:center;\">
      {$validity}<br><span style=\"color:#94a3b8;\">{$noForward}</span>
    </div>
  </td></tr>

  <tr><td style=\"background:{$canvas};border-top:1px solid {$border};padding:20px 32px;\">
    <div style=\"font:400 12px/1.5 {$font};color:#94a3b8;\">{$fallback}</div>
    <div style=\"font:400 12px/1.5 {$font};padding-top:6px;word-break:break-all;\">
      <a href=\"{$safeUrl}\" style=\"color:{$primaryD};\">{$safeUrl}</a>
    </div>
  </td></tr>

</table>

<div style=\"font:400 12px/1.5 {$font};color:#94a3b8;padding-top:18px;\">{$safeClub}</div>

</td></tr>
</table>
</body>
</html>";
    }

    /** Aperçu des trois statuts proposés derrière le lien. */
    private function renderChips(string $font): string
    {
        $chips = [
            ['✅', __('Dispo', 'mon-club-tt'), '#dcfce7', '#166534'],
            ['🚫', __('Indispo', 'mon-club-tt'), '#fee2e2', '#b91c1c'],
            ['❓', __('Incertain', 'mon-club-tt'), '#fef3c7', '#92400e'],
        ];

        $cells = '';
        foreach ($chips as $i => [$icon, $label, $bg, $fg]) {
            $pad    = $i === 0 ? '0 4px 0 0' : ($i === 2 ? '0 0 0 4px' : '0 4px');
            $cells .= '<td width="33.33%" style="padding:' . $pad . ';">'
                . '<div style="background:' . $bg . ';border-radius:10px;padding:13px 6px;text-align:center;">'
                . '<div style="font-size:19px;line-height:1;">' . $icon . '</div>'
                . '<div style="font:700 12px/1 ' . $font . ';color:' . $fg . ';padding-top:7px;">'
                . esc_html($label) . '</div>'
                . '</div></td>';
        }

        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>'
            . $cells . '</tr></table>';
    }

    /**
     * Rappelle les dates concernées, pour que le joueur sache de quelles
     * journées on parle avant même de cliquer. Renvoie une chaîne vide si
     * aucune date n'est saisie en réglages : le bloc disparaît alors
     * proprement du gabarit.
     */
    private function renderJournees(string $font): string
    {
        $journees = $this->upcomingJournees();
        if ($journees === []) {
            return '';
        }

        $phase = $journees[0]['phase'];
        /* translators: %d: numéro de phase */
        $title = sprintf(esc_html__('Prochaines journées — phase %d', 'mon-club-tt'), $phase);

        $rows = '';
        foreach ($journees as $i => $journee) {
            $top   = $i === 0 ? '0' : '1px solid ' . self::BORDER;
            $rows .= '<tr>'
                . '<td style="border-top:' . $top . ';padding:9px 0;width:44px;">'
                . '<span style="display:inline-block;background:#e0e9ff;color:' . self::PRIMARY_D . ';'
                . 'border-radius:6px;padding:4px 8px;font:700 12px/1 ' . $font . ';">'
                . esc_html($journee['label']) . '</span></td>'
                . '<td style="border-top:' . $top . ';padding:9px 0;font:500 14px/1.3 ' . $font . ';'
                . 'color:' . self::INK . ';">' . esc_html($journee['date']) . '</td>'
                . '</tr>';
        }

        return '  <tr><td style="padding:26px 32px 0 32px;">'
            . '<div style="background:' . self::CANVAS . ';border:1px solid ' . self::BORDER . ';'
            . 'border-radius:12px;padding:16px 18px;">'
            . '<div style="font:700 11px/1 ' . $font . ';letter-spacing:1.1px;text-transform:uppercase;'
            . 'color:' . self::INK_SOFT . ';padding-bottom:6px;">' . $title . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">'
            . $rows . '</table>'
            . '</div></td></tr>' . "\n";
    }

    /**
     * Journées encore à venir, prises dans la phase active ; si celle-ci est
     * terminée on bascule sur l'autre phase.
     *
     * @return list<array{label: string, date: string, phase: int}>
     */
    private function upcomingJournees(): array
    {
        $phase = Assets::detectPhase();

        $journees = $this->journeesForPhase($phase);

        return $journees !== [] ? $journees : $this->journeesForPhase($phase === 1 ? 2 : 1);
    }

    /** @return list<array{label: string, date: string, phase: int}> */
    private function journeesForPhase(int $phase): array
    {
        $dates = (array) get_option('monclubtt_ge_journee_dates_p' . $phase, []);
        $today = current_time('Y-m-d');
        $out   = [];

        foreach ($dates as $index => $date) {
            $date = trim((string) $date);
            // Dates au format ISO : la comparaison de chaînes suffit.
            if ($date === '' || $date < $today) {
                continue;
            }

            $timestamp = strtotime($date . ' 12:00:00');
            if ($timestamp === false) {
                continue;
            }

            $out[] = [
                'label' => 'J' . ((int) $index + 1),
                'date'  => wp_date('D j M', $timestamp),
                'phase' => $phase,
            ];

            if (count($out) >= self::JOURNEES_SHOWN) {
                break;
            }
        }

        return $out;
    }

    // =========================================================================
    // Version texte
    // =========================================================================

    /** Version texte (AltBody) — aussi ce que voient les clients sans HTML. */
    private function renderText(string $firstName, string $club, string $url, int $ttlDays, bool $isReminder): string
    {
        $intro = $isReminder
            ? __("On n'a pas encore reçu tes disponibilités pour les prochaines journées.", 'mon-club-tt')
            : __("Merci d'indiquer tes disponibilités pour les prochaines journées.", 'mon-club-tt');

        $journees = '';
        foreach ($this->upcomingJournees() as $journee) {
            $journees .= sprintf("  %s — %s\n", $journee['label'], $journee['date']);
        }
        if ($journees !== '') {
            $journees = "\n" . __('Prochaines journées :', 'mon-club-tt') . "\n" . $journees;
        }

        return sprintf(
            /* translators: 1: prénom, 2: phrase d'introduction, 3: liste des journées, 4: lien personnel, 5: durée en jours, 6: nom du club */
            __(
                "Salut %1\$s,\n\n%2\$s\n%3\$s\nTon lien personnel :\n%4\$s\n\nCe lien n'est valable que pour toi et reste actif pendant %5\$d jours. Ne le transfère pas.\n\n— %6\$s",
                'mon-club-tt'
            ),
            $firstName,
            $intro,
            $journees,
            $url,
            $ttlDays,
            $club
        );
    }
}
