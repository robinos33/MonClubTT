<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Calendrier as C;
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../models/Calendrier.php';

/**
 * Tests du calendrier du club : dates en toutes lettres, prochain événement,
 * numérotation des séries, import du tableur et export iCalendar.
 */
final class CalendrierTest extends TestCase
{
    private static function evt(string $debut, string $fin = '', array $autres = []): array
    {
        return $autres + [
            'id' => 1, 'debut' => $debut, 'fin' => $fin ?: $debut, 'heure' => '', 'heureFin' => '',
            'titre' => 'Événement ' . $debut, 'categorie' => '', 'serie' => '', 'serieNom' => '',
            'lieu' => '', 'lien' => '', 'note' => '', 'saison' => C::saisonDe($debut),
        ];
    }

    public function test_should_parse_french_and_iso_dates(): void
    {
        $this->assertSame('2026-11-28', C::date('2026-11-28'));
        $this->assertSame('2026-11-28', C::date('28/11/2026'));
        $this->assertSame('2027-05-01', C::date('1/5/27'));
        $this->assertSame('2027-05-01', C::date('01.05.2027'));
        $this->assertNull(C::date('31/02/2027'));
        $this->assertNull(C::date('demain'));
    }

    public function test_should_parse_times(): void
    {
        $this->assertSame('19:30', C::heure('19h30'));
        $this->assertSame('09:00', C::heure('9H'));
        $this->assertSame('19:30', C::heure('19:30'));
        $this->assertSame('', C::heure('25h'));
        $this->assertSame('', C::heure(''));
        $this->assertSame('19 h 30', C::heureLisible('19:30'));
        $this->assertSame('9 h', C::heureLisible('09:00'));
    }

    public function test_should_write_periods_like_the_club_site(): void
    {
        $this->assertSame('28 novembre', C::periode('2026-11-28', '2026-11-28'));
        $this->assertSame('28 et 29 novembre', C::periode('2026-11-28', '2026-11-29'));
        $this->assertSame('1er et 2 mai', C::periode('2027-05-01', '2027-05-02'));
        $this->assertSame('du 19 au 23 octobre', C::periode('2026-10-19', '2026-10-23'));
        $this->assertSame('31 octobre et 1er novembre', C::periode('2026-10-31', '2026-11-01'));
        $this->assertSame('du 30 avril au 3 mai 2027', C::periode('2027-04-30', '2027-05-03', true));
        $this->assertSame('du 28 décembre 2026 au 2 janvier 2027', C::periode('2026-12-28', '2027-01-02'));
    }

    public function test_should_use_fftt_season_starting_in_july(): void
    {
        $this->assertSame(2026, C::saisonDe('2026-07-01'));
        $this->assertSame(2025, C::saisonDe('2026-06-30'));
        $this->assertSame('Saison 2026–2027', C::libelleSaison(2026));
    }

    public function test_next_event_includes_the_one_in_progress(): void
    {
        $evts = [self::evt('2026-11-28', '2026-11-29'), self::evt('2026-10-19', '2026-10-23'), self::evt('2027-02-13')];

        $this->assertSame('2026-11-28', C::prochain($evts, '2026-11-12')['debut']);
        $this->assertSame('2026-11-28', C::prochain($evts, '2026-11-29')['debut']);
        $this->assertSame('2027-02-13', C::prochain($evts, '2026-11-30')['debut']);
        $this->assertNull(C::prochain($evts, '2027-03-01'));
    }

    public function test_reference_season_follows_next_event_then_latest(): void
    {
        $evts = [
            self::evt('2026-10-01', '', ['saison' => 2026]),
            self::evt('2027-08-16', '', ['saison' => 2026]),
            self::evt('2027-09-19', '', ['saison' => 2027]),
        ];

        $this->assertSame(2026, C::saisonDeReference($evts, '2027-08-01'));
        $this->assertSame(2027, C::saisonDeReference($evts, '2027-08-20'));
        $this->assertSame(2027, C::saisonDeReference($evts, '2028-01-01'));
        $this->assertNull(C::saisonDeReference([], '2028-01-01'));
    }

    public function test_should_number_rounds_per_series_and_season(): void
    {
        $evts = C::numeroter([
            self::evt('2027-02-13', '', ['serie' => 'gp', 'titre' => 'GP', 'serieNom' => 'Grand Prix']),
            self::evt('2026-11-28', '', ['serie' => 'gp', 'titre' => 'GP', 'serieNom' => 'Grand Prix']),
            self::evt('2026-12-01', '', ['serie' => 'stage', 'titre' => 'Stage']),
            self::evt('2027-09-01', '', ['serie' => 'gp', 'titre' => 'GP']),
        ]);

        $this->assertSame([1, 1, 2, 1], array_column($evts, 'numero'));
        $this->assertSame('Tour 2', C::libelle($evts[2], 'Tour %n'));
        $this->assertSame('Grand Prix · GP', C::libelle($evts[0], '%s · %t'));
        $this->assertSame('GP', C::libelle($evts[0]));
    }

    public function test_should_build_month_table_for_several_series(): void
    {
        $evts = [
            self::evt('2026-10-19', '2026-10-23', ['serie' => 'multi']),
            self::evt('2026-10-26', '2026-10-30', ['serie' => 'perf']),
            self::evt('2027-07-05', '2027-07-09', ['serie' => 'multi']),
            self::evt('2027-07-12', '2027-07-16', ['serie' => 'multi']),
            self::evt('2027-07-14', '', ['serie' => 'autre']),
        ];
        $lignes = C::parMois($evts, ['multi', 'perf']);

        $this->assertSame(['Octobre', 'Juillet'], array_column($lignes, 'nom'));
        $this->assertCount(2, $lignes[1]['cellules']['multi']);
        $this->assertSame([], $lignes[1]['cellules']['perf']);

        $lignes = C::parMois([
            self::evt('2026-08-17', '', ['serie' => 'multi']),
            self::evt('2026-10-19', '', ['serie' => 'multi']),
            self::evt('2027-08-16', '', ['serie' => 'multi']),
        ], ['multi']);
        $this->assertSame(['Août 2026', 'Octobre', 'Août 2027'], array_column($lignes, 'nom'));
    }

    public function test_known_categories_keep_their_colour_and_others_get_the_palette(): void
    {
        $couleurs = C::couleurs(['Stages', 'Tournoi', 'Équipes', 'Stages', '']);

        $this->assertSame(['Équipes', 'Stages', 'Tournoi'], array_keys($couleurs));
        $this->assertSame('#468ca0', $couleurs['Stages']);
        $this->assertSame(C::PALETTE[0], $couleurs['Tournoi']);
    }

    public function test_should_import_legacy_calendar_json(): void
    {
        $json = '[{"start": "2026-10-08", "end": "2026-10-08", "title": "Assemblée générale", "cat": "Vie du club", "source": "ag", "time": "19:30", "startUtc": "20261008T173000Z"},'
            . '{"start": "2026-11-28", "end": "2026-11-29", "title": "Conseil depart T1", "cat": "Jeunes et féminines", "source": "AB29"}]';
        $r = C::analyserImport($json);

        $this->assertSame([], $r['erreurs']);
        $this->assertCount(2, $r['lignes']);
        $this->assertSame('19:30', $r['lignes'][0]['heure']);
        $this->assertSame('AB29', $r['lignes'][1]['source']);
        $this->assertSame('Jeunes et féminines', $r['lignes'][1]['categorie']);
    }

    public function test_should_import_spreadsheet_csv_with_french_headers(): void
    {
        $csv = "\xEF\xBB\xBFDébut;Fin;Titre;Catégorie;Série;Lieu\n"
            . "28/11/2026;29/11/2026;Grand Prix T1;Jeunes et féminines;Grand Prix;\"Jeunes (garçons) : TALENCE\nFéminines : CESTAS\"\n"
            . ";;;;;\n"
            . "13/02/2027;;Grand Prix T2;Jeunes et féminines;Grand Prix;\n"
            . "13/02/2027;;Grand Prix T2;Jeunes et féminines;Grand Prix;\n"
            . "30/02/2027;;Faux;;;\n"
            . "02/03/2027;01/03/2027;À l'envers;;;\n"
            . "02/03/2027;;;;;\n";
        $r = C::analyserImport($csv);

        $this->assertCount(3, $r['lignes']);
        $this->assertSame('2027-02-13', $r['lignes'][1]['fin']);
        $this->assertSame("Jeunes (garçons) : TALENCE\nFéminines : CESTAS", $r['lignes'][0]['lieu']);
        $this->assertSame('Grand Prix', $r['lignes'][0]['serie']);
        $this->assertNotSame($r['lignes'][1]['source'], $r['lignes'][2]['source']);
        $this->assertCount(3, $r['erreurs']);
        $this->assertStringContainsString('Ligne 6', $r['erreurs'][0]);
    }

    public function test_should_accept_comma_separated_csv(): void
    {
        $r = C::analyserImport("date,title,cat\n2026-09-05,Forum des Assos,Vie du club\n");

        $this->assertSame([], $r['erreurs']);
        $this->assertSame('Forum des Assos', $r['lignes'][0]['titre']);
    }

    public function test_should_reject_unknown_columns_and_bad_json(): void
    {
        $this->assertNotEmpty(C::analyserImport("a;b\n1;2")['erreurs']);
        $this->assertNotEmpty(C::analyserImport('[{"start": ')['erreurs']);
        $this->assertNotEmpty(C::analyserImport('  ')['erreurs']);
    }

    public function test_ics_has_all_day_and_timed_events(): void
    {
        $ics = C::ics([
            self::evt('2026-11-28', '2026-11-29', ['id' => 7, 'titre' => 'GP; T1, Talence', 'lieu' => "Talence\nCestas", 'categorie' => 'Jeunes et féminines']),
            self::evt('2026-10-08', '', ['id' => 8, 'titre' => 'AG', 'heure' => '19:30']),
            self::evt('2026-12-17', '', ['id' => 9, 'titre' => 'Tournoi', 'heure' => '18:00', 'heureFin' => '22:30', 'lien' => 'https://example.org/x']),
        ], ['nom' => 'Club', 'domaine' => 'club.fr', 'horodatage' => '20261112T100000Z']);

        $this->assertStringStartsWith("BEGIN:VCALENDAR\r\n", $ics);
        $this->assertStringContainsString("UID:monclubtt-7@club.fr\r\n", $ics);
        $this->assertStringContainsString("DTSTART;VALUE=DATE:20261128\r\nDTEND;VALUE=DATE:20261130\r\n", $ics);
        $this->assertStringContainsString("DTSTART;TZID=Europe/Paris:20261008T193000\r\nDTEND;TZID=Europe/Paris:20261008T213000\r\n", $ics);
        $this->assertStringContainsString('DTEND;TZID=Europe/Paris:20261217T223000', $ics);
        $this->assertStringContainsString('SUMMARY:GP\; T1\, Talence', $ics);
        $this->assertStringContainsString('LOCATION:Talence · Cestas', $ics);
        $this->assertStringContainsString('URL:https://example.org/x', $ics);
        // L'AG (8 octobre) sort avant le Grand Prix (28 novembre).
        $this->assertLessThan(strpos($ics, 'monclubtt-7@'), strpos($ics, 'monclubtt-8@'));
        foreach (explode("\r\n", $ics) as $ligne) {
            $this->assertLessThanOrEqual(75, strlen($ligne));
        }
    }

    public function test_ics_folds_long_utf8_lines_without_breaking_characters(): void
    {
        $ics = C::ics([self::evt('2026-11-28', '', ['titre' => str_repeat('Événement très long ', 10)])], ['domaine' => 'x']);
        $deplie = str_replace("\r\n ", '', $ics);

        $this->assertStringContainsString('SUMMARY:' . rtrim(str_repeat('Événement très long ', 10)), $deplie);
        $this->assertTrue(mb_check_encoding($ics, 'UTF-8'));
    }
}
