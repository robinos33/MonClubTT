<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau d'un fichier étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_Calendrier' ) ) {

/**
 * Service de domaine (sans état) : calendrier du club.
 *
 * Un événement est un tableau normalisé :
 *   id, debut (AAAA-MM-JJ), fin (AAAA-MM-JJ), heure (HH:MM ou ''), heureFin,
 *   titre, categorie (nom), serie (slug ou ''), serieNom, lieu, lien, note,
 *   saison (année de début, ex. 2026 pour 2026–2027).
 *
 * Les dates sont des chaînes ISO : leur ordre alphabétique est l'ordre
 * chronologique, ce qui évite toute conversion de fuseau horaire.
 * Aucune dépendance à WordPress, pour rester testable unitairement.
 */
class MonClubTT_Calendrier {

    /** Catégories proposées par défaut, dans l'ordre d'affichage, avec leur couleur. */
    const CATEGORIES = array(
        'Équipes'             => '#498b6b',
        'Individuelles'       => '#7b75bc',
        'Jeunes et féminines' => '#ba7a42',
        'Stages'              => '#468ca0',
        'Vie du club'         => '#b65e72',
        'Repères'             => '#808880',
    );

    /** Couleurs des autres catégories, attribuées dans l'ordre. */
    const PALETTE = array('#5b7fa6', '#a0794b', '#6f8f3a', '#9a5ca0', '#4f8f8a', '#b0654a');

    const MOIS = array(
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    );

    /** Colonnes reconnues à l'import, par nom de colonne normalisé (sans accents, minuscules). */
    const COLONNES = array(
        'debut'     => array('debut', 'date', 'date debut', 'date de debut', 'du', 'start'),
        'fin'       => array('fin', 'date fin', 'date de fin', 'au', 'end'),
        'titre'     => array('titre', 'intitule', 'evenement', 'nom', 'title'),
        'categorie' => array('categorie', 'cat', 'type', 'category'),
        'serie'     => array('serie', 'series'),
        'heure'     => array('heure', 'horaire', 'heure debut', 'heure de debut', 'time'),
        'heureFin'  => array('heure fin', 'heure de fin', 'endtime', 'time end'),
        'lieu'      => array('lieu', 'lieux', 'ville', 'location'),
        'lien'      => array('lien', 'url', 'inscription', 'link'),
        'note'      => array('note', 'notes', 'remarque', 'commentaire', 'description'),
        'source'    => array('source', 'ref', 'reference', 'id'),
    );

    /**
     * Date saisie (AAAA-MM-JJ, JJ/MM/AAAA, JJ/MM/AA, JJ-MM-AAAA, JJ.MM.AAAA) au format ISO.
     *
     * @param mixed $valeur
     * @return string|null AAAA-MM-JJ, ou null si la date est invalide.
     */
    public static function date($valeur) {
        $valeur = trim((string) $valeur);
        if (preg_match('/^(\d{4})-(\d{1,2})-(\d{1,2})(?:[T ].*)?$/', $valeur, $m)) {
            list(, $a, $mo, $j) = $m;
        } elseif (preg_match('#^(\d{1,2})[/.\-](\d{1,2})[/.\-](\d{2}|\d{4})$#', $valeur, $m)) {
            list(, $j, $mo, $a) = $m;
            if (strlen($a) === 2) {
                $a = '20' . $a;
            }
        } else {
            return null;
        }
        if (!checkdate((int) $mo, (int) $j, (int) $a)) {
            return null;
        }
        return sprintf('%04d-%02d-%02d', (int) $a, (int) $mo, (int) $j);
    }

    /**
     * Heure saisie (19:30, 19h30, 19h, 9H) au format HH:MM.
     *
     * @param mixed $valeur
     * @return string HH:MM, ou '' si vide ou invalide.
     */
    public static function heure($valeur) {
        $valeur = strtolower(trim((string) $valeur));
        if (!preg_match('/^(\d{1,2})\s*(?:[:h]\s*(\d{2})?)?$/', $valeur, $m)) {
            return '';
        }
        $h  = (int) $m[1];
        $mi = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 0;
        if ($h > 23 || $mi > 59) {
            return '';
        }
        return sprintf('%02d:%02d', $h, $mi);
    }

    /**
     * Saison FFTT d'une date (1er juillet – 30 juin).
     *
     * @param string $date AAAA-MM-JJ
     * @return int Année de début de la saison.
     */
    public static function saisonDe($date) {
        $annee = (int) substr($date, 0, 4);
        return (int) substr($date, 5, 2) >= 7 ? $annee : $annee - 1;
    }

    /** « Saison 2026–2027 ». */
    public static function libelleSaison($saison) {
        return 'Saison ' . (int) $saison . '–' . ((int) $saison + 1);
    }

    /**
     * Trie par date de début, puis de fin, puis titre.
     *
     * @param array $evenements
     * @return array
     */
    public static function trier(array $evenements) {
        usort($evenements, function ($a, $b) {
            return strcmp($a['debut'], $b['debut'])
                ?: strcmp($a['fin'], $b['fin'])
                ?: strcmp((string) $a['heure'], (string) $b['heure'])
                ?: strcmp($a['titre'], $b['titre']);
        });
        return $evenements;
    }

    /**
     * Prochain événement : le premier qui n'est pas terminé (en cours ou à venir).
     *
     * @param array  $evenements
     * @param string $aujourdhui AAAA-MM-JJ
     * @return array|null
     */
    public static function prochain(array $evenements, $aujourdhui) {
        foreach (self::trier($evenements) as $evenement) {
            if ($evenement['fin'] >= $aujourdhui) {
                return $evenement;
            }
        }
        return null;
    }

    /**
     * Saison à afficher : celle du prochain événement ; à défaut (tout est
     * passé), la plus récente.
     *
     * @param array  $evenements
     * @param string $aujourdhui AAAA-MM-JJ
     * @return int|null null s'il n'y a aucun événement.
     */
    public static function saisonDeReference(array $evenements, $aujourdhui) {
        $prochain = self::prochain($evenements, $aujourdhui);
        if ($prochain !== null) {
            return (int) $prochain['saison'];
        }
        $saisons = array_map('intval', array_column($evenements, 'saison'));
        return $saisons ? max($saisons) : null;
    }

    /**
     * Événements d'une saison.
     *
     * @param array $evenements
     * @param int   $saison
     * @return array
     */
    public static function deLaSaison(array $evenements, $saison) {
        return array_values(array_filter($evenements, function ($e) use ($saison) {
            return (int) $e['saison'] === (int) $saison;
        }));
    }

    /**
     * Ajoute à chaque événement son rang dans sa série et sa saison
     * (clé « numero », 1 pour le premier tour) et trie le tout.
     *
     * @param array $evenements
     * @return array
     */
    public static function numeroter(array $evenements) {
        $compteurs  = array();
        $evenements = self::trier($evenements);
        foreach ($evenements as $i => $e) {
            $cle = (int) $e['saison'] . '|' . ($e['serie'] !== '' ? $e['serie'] : '#' . $e['titre']);
            $compteurs[$cle] = isset($compteurs[$cle]) ? $compteurs[$cle] + 1 : 1;
            $evenements[$i]['numero'] = $compteurs[$cle];
        }
        return $evenements;
    }

    /**
     * Libellé d'un événement selon un modèle : %n (rang dans la série),
     * %t (titre), %s (nom de la série). Sans modèle : le titre.
     *
     * @param array  $evenement Événement numéroté (voir numeroter()).
     * @param string $modele
     * @return string
     */
    public static function libelle(array $evenement, $modele = '') {
        if ($modele === '') {
            return $evenement['titre'];
        }
        return strtr($modele, array(
            '%n' => (string) ($evenement['numero'] ?? ''),
            '%t' => $evenement['titre'],
            '%s' => $evenement['serieNom'] ?? '',
        ));
    }

    /** « 1er », « 2 »… */
    private static function jour($date) {
        $j = (int) substr($date, 8, 2);
        return $j === 1 ? '1er' : (string) $j;
    }

    private static function mois($date) {
        return self::MOIS[(int) substr($date, 5, 2)];
    }

    /**
     * Période en toutes lettres : « 28 novembre », « 28 et 29 novembre »,
     * « du 19 au 23 octobre », « du 30 avril au 2 mai »,
     * « du 28 décembre 2026 au 2 janvier 2027 ».
     *
     * @param string $debut AAAA-MM-JJ
     * @param string $fin   AAAA-MM-JJ
     * @param bool   $annee Ajouter l'année (toujours ajoutée si la période change d'année).
     * @return string
     */
    public static function periode($debut, $fin, $annee = false) {
        $fin = $fin !== '' && $fin >= $debut ? $fin : $debut;
        $aD  = substr($debut, 0, 4);
        $aF  = substr($fin, 0, 4);
        if ($aD !== $aF) {
            return 'du ' . self::jour($debut) . ' ' . self::mois($debut) . ' ' . $aD
                . ' au ' . self::jour($fin) . ' ' . self::mois($fin) . ' ' . $aF;
        }
        $suffixe = $annee ? ' ' . $aF : '';
        if ($debut === $fin) {
            return self::jour($debut) . ' ' . self::mois($debut) . $suffixe;
        }
        $consecutifs = self::lendemain($debut) === $fin;
        if (substr($debut, 0, 7) === substr($fin, 0, 7)) {
            return ($consecutifs ? self::jour($debut) . ' et ' : 'du ' . self::jour($debut) . ' au ')
                . self::jour($fin) . ' ' . self::mois($fin) . $suffixe;
        }
        return ($consecutifs ? '' : 'du ')
            . self::jour($debut) . ' ' . self::mois($debut)
            . ($consecutifs ? ' et ' : ' au ')
            . self::jour($fin) . ' ' . self::mois($fin) . $suffixe;
    }

    /** « 19 h 30 », « 9 h ». */
    public static function heureLisible($heure) {
        if ($heure === '') {
            return '';
        }
        list($h, $m) = explode(':', $heure);
        return (int) $h . ' h' . ($m !== '00' ? ' ' . $m : '');
    }

    /** Lendemain d'une date ISO. */
    public static function lendemain($date) {
        return gmdate('Y-m-d', (int) strtotime($date . 'T12:00:00Z') + 86400);
    }

    /**
     * Couleur de chaque catégorie présente : couleur par défaut si c'est une
     * catégorie connue, sinon la suivante de la palette.
     *
     * @param string[] $noms
     * @return array nom => couleur, dans l'ordre d'affichage.
     */
    public static function couleurs(array $noms) {
        $noms     = array_values(array_unique(array_filter($noms, 'strlen')));
        $connues  = array_values(array_intersect(array_keys(self::CATEGORIES), $noms));
        $autres   = array_values(array_diff($noms, $connues));
        sort($autres);
        $couleurs = array();
        foreach ($connues as $nom) {
            $couleurs[$nom] = self::CATEGORIES[$nom];
        }
        foreach ($autres as $i => $nom) {
            $couleurs[$nom] = self::PALETTE[$i % count(self::PALETTE)];
        }
        return $couleurs;
    }

    /**
     * Tableau par mois de plusieurs séries (ex. stages Multisports et
     * Perfectionnements côte à côte).
     *
     * @param array    $evenements Événements numérotés et triés.
     * @param string[] $series     Slugs des séries, dans l'ordre des colonnes.
     * @return array Lignes [ 'mois' => 'AAAA-MM', 'nom' => 'Octobre', 'cellules' => [ slug => [événements] ] ].
     */
    public static function parMois(array $evenements, array $series) {
        $lignes = array();
        foreach (self::trier($evenements) as $e) {
            if (!in_array($e['serie'], $series, true)) {
                continue;
            }
            $mois = substr($e['debut'], 0, 7);
            if (!isset($lignes[$mois])) {
                $lignes[$mois] = array(
                    'mois'     => $mois,
                    'nom'      => ucfirst(self::mois($e['debut'])),
                    'cellules' => array_fill_keys($series, array()),
                );
            }
            $lignes[$mois]['cellules'][$e['serie']][] = $e;
        }
        // Un même mois sur deux années (stages d'août en début et en fin de saison) : on précise l'année.
        $noms = array_count_values(array_column($lignes, 'nom'));
        foreach ($lignes as $mois => $ligne) {
            if ($noms[$ligne['nom']] > 1) {
                $lignes[$mois]['nom'] .= ' ' . substr($mois, 0, 4);
            }
        }
        return array_values($lignes);
    }

    /* ------------------------------------------------------------------
     * Import (CSV du tableur ou JSON de l'ancien calendrier)
     * ------------------------------------------------------------------ */

    /** Nom de colonne normalisé : minuscules, sans accents ni ponctuation. */
    private static function normaliserColonne($nom) {
        $nom = strtolower(trim((string) $nom));
        $nom = strtr($nom, array(
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e', 'à' => 'a', 'â' => 'a',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'û' => 'u', 'ù' => 'u', 'ç' => 'c',
            'É' => 'e', 'È' => 'e',
        ));
        return trim(preg_replace('/[^a-z0-9]+/', ' ', $nom));
    }

    /** Champ du calendrier correspondant à un nom de colonne, ou null. */
    private static function champ($colonne) {
        $colonne = self::normaliserColonne($colonne);
        foreach (self::COLONNES as $champ => $alias) {
            if (in_array($colonne, $alias, true) || self::normaliserColonne($champ) === $colonne) {
                return $champ;
            }
        }
        return null;
    }

    /**
     * Lit un fichier d'import : JSON (tableau d'objets, clés françaises ou
     * celles de l'ancien calendrier : start, end, title, cat, time, source)
     * ou CSV (séparateur ; , ou tabulation, première ligne = noms de colonnes).
     *
     * @param string $contenu
     * @return array [ 'lignes' => [ ligne normalisée… ], 'erreurs' => [ message… ] ]
     */
    public static function analyserImport($contenu) {
        $contenu = (string) $contenu;
        if (strncmp($contenu, "\xEF\xBB\xBF", 3) === 0) {
            $contenu = substr($contenu, 3);
        }
        $contenu = trim($contenu);
        if ($contenu === '') {
            return array('lignes' => array(), 'erreurs' => array('Le fichier est vide.'));
        }
        if ($contenu[0] === '[' || $contenu[0] === '{') {
            $brut = json_decode($contenu, true);
            if (!is_array($brut)) {
                return array('lignes' => array(), 'erreurs' => array('JSON illisible : ' . json_last_error_msg() . '.'));
            }
            if (isset($brut['evenements']) && is_array($brut['evenements'])) {
                $brut = $brut['evenements'];
            }
            $enregistrements = array();
            foreach (array_values($brut) as $i => $objet) {
                $ligne = array();
                foreach ((array) $objet as $cle => $valeur) {
                    $champ = self::champ((string) $cle);
                    if ($champ !== null && is_scalar($valeur)) {
                        $ligne[$champ] = (string) $valeur;
                    }
                }
                $enregistrements[$i + 1] = $ligne;
            }
        } else {
            $enregistrements = self::lireCsv($contenu);
            if (is_string($enregistrements)) {
                return array('lignes' => array(), 'erreurs' => array($enregistrements));
            }
        }

        $lignes  = array();
        $erreurs = array();
        $sources = array();
        foreach ($enregistrements as $numero => $brut) {
            $ligne = self::normaliserLigne($brut, $numero, $erreur);
            if ($ligne === null) {
                if ($erreur !== '') {
                    $erreurs[] = $erreur;
                }
                continue;
            }
            // Deux lignes sans référence au même titre et à la même date : la seconde
            // garderait la même clé et écraserait la première.
            $cle = $ligne['source'];
            for ($n = 2; isset($sources[$ligne['source']]); $n++) {
                $ligne['source'] = $cle . '-' . $n;
            }
            $sources[$ligne['source']] = true;
            $lignes[] = $ligne;
        }
        return array('lignes' => $lignes, 'erreurs' => $erreurs);
    }

    /**
     * @return array|string Enregistrements indexés par numéro de ligne, ou message d'erreur.
     */
    private static function lireCsv($contenu) {
        $premiere   = strtok($contenu, "\n");
        $separateur = ';';
        $max        = -1;
        foreach (array(';', ',', "\t") as $candidat) {
            $n = substr_count((string) $premiere, $candidat);
            if ($n > $max) {
                $max        = $n;
                $separateur = $candidat;
            }
        }

        // Flux en mémoire (pas un fichier) : fgetcsv() gère les champs entre guillemets sur plusieurs lignes.
        // phpcs:disable WordPress.WP.AlternativeFunctions
        $flux = fopen('php://temp', 'r+');
        fwrite($flux, $contenu);
        rewind($flux);
        $entetes = fgetcsv($flux, 0, $separateur, '"', '');
        $champs  = array();
        foreach ((array) $entetes as $i => $nom) {
            $champ = self::champ((string) $nom);
            if ($champ !== null && !in_array($champ, $champs, true)) {
                $champs[$i] = $champ;
            }
        }
        if (!in_array('debut', $champs, true) || !in_array('titre', $champs, true)) {
            fclose($flux);
            return 'Colonnes « debut » et « titre » introuvables sur la première ligne (colonnes reconnues : '
                . implode(', ', array_keys(self::COLONNES)) . ').';
        }

        $enregistrements = array();
        $numero          = 1;
        while (($valeurs = fgetcsv($flux, 0, $separateur, '"', '')) !== false) {
            $numero++;
            $ligne = array();
            foreach ($champs as $i => $champ) {
                $ligne[$champ] = isset($valeurs[$i]) ? (string) $valeurs[$i] : '';
            }
            $enregistrements[$numero] = $ligne;
        }
        fclose($flux);
        // phpcs:enable
        return $enregistrements;
    }

    /**
     * Valide une ligne d'import.
     *
     * @param array  $brut   Champ => valeur brute.
     * @param int    $numero Numéro de ligne, pour les messages.
     * @param string $erreur Message d'erreur ('' si la ligne est vide et simplement ignorée).
     * @return array|null
     */
    private static function normaliserLigne(array $brut, $numero, &$erreur) {
        $erreur = '';
        $brut   = array_map(function ($v) { return trim((string) $v); }, $brut);
        if (implode('', $brut) === '') {
            return null;
        }
        $titre = preg_replace('/\s+/u', ' ', $brut['titre'] ?? '');
        $debut = self::date($brut['debut'] ?? '');
        $fin   = ($brut['fin'] ?? '') !== '' ? self::date($brut['fin']) : $debut;
        if ($titre === '') {
            $erreur = "Ligne $numero : titre manquant.";
            return null;
        }
        if ($debut === null) {
            $erreur = "Ligne $numero (« $titre ») : date de début invalide (« " . ($brut['debut'] ?? '') . ' »).';
            return null;
        }
        if ($fin === null || $fin < $debut) {
            $erreur = "Ligne $numero (« $titre ») : date de fin invalide ou antérieure au début.";
            return null;
        }
        $source = $brut['source'] ?? '';
        if ($source === '') {
            $source = $debut . '-' . substr(md5(strtolower($titre)), 0, 8);
        }
        return array(
            'debut'     => $debut,
            'fin'       => $fin,
            'titre'     => $titre,
            'categorie' => $brut['categorie'] ?? '',
            'serie'     => $brut['serie'] ?? '',
            'heure'     => self::heure($brut['heure'] ?? ''),
            'heureFin'  => self::heure($brut['heureFin'] ?? ''),
            'lieu'      => $brut['lieu'] ?? '',
            'lien'      => $brut['lien'] ?? '',
            'note'      => $brut['note'] ?? '',
            'source'    => substr($source, 0, 100),
        );
    }

    /* ------------------------------------------------------------------
     * Export iCalendar (.ics), pour l'abonnement et le téléchargement
     * ------------------------------------------------------------------ */

    /** Échappe un texte iCalendar (RFC 5545 §3.3.11). */
    private static function texteIcs($texte) {
        return str_replace(
            array('\\', "\r\n", "\r", "\n", ';', ','),
            array('\\\\', '\n', '\n', '\n', '\;', '\,'),
            (string) $texte
        );
    }

    /** Replie une ligne à 75 octets sans couper un caractère UTF-8. */
    private static function plierIcs($ligne) {
        if (strlen($ligne) <= 75) {
            return $ligne;
        }
        $resultat = '';
        $morceau  = '';
        foreach (preg_split('//u', $ligne, -1, PREG_SPLIT_NO_EMPTY) as $car) {
            if (strlen($morceau) + strlen($car) > 75) {
                $resultat .= $morceau . "\r\n";
                $morceau   = ' ';
            }
            $morceau .= $car;
        }
        return $resultat . $morceau;
    }

    /** AAAA-MM-JJ + HH:MM → AAAAMMJJTHHMMSS (heure locale). */
    private static function dateHeureIcs($date, $heure) {
        return str_replace('-', '', $date) . 'T' . str_replace(':', '', $heure) . '00';
    }

    /**
     * Calendrier iCalendar. Un événement d'un seul jour avec une heure est
     * horodaté (heure de Paris, 2 h par défaut sans heure de fin) ; les
     * autres occupent des journées entières.
     *
     * @param array $evenements
     * @param array $options nom (du calendrier), domaine (pour les UID),
     *                       horodatage (DTSTAMP, AAAAMMJJTHHMMSSZ), url (du site).
     * @return string
     */
    public static function ics(array $evenements, array $options) {
        $domaine = $options['domaine'] ?? 'localhost';
        $stamp   = $options['horodatage'] ?? gmdate('Ymd\THis\Z');
        $lignes  = array(
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Mon Club TT//Calendrier//FR',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . self::texteIcs($options['nom'] ?? 'Calendrier du club'),
            'X-WR-TIMEZONE:Europe/Paris',
            'REFRESH-INTERVAL;VALUE=DURATION:PT12H',
            'X-PUBLISHED-TTL:PT12H',
            'BEGIN:VTIMEZONE',
            'TZID:Europe/Paris',
            'BEGIN:DAYLIGHT',
            'TZOFFSETFROM:+0100',
            'TZOFFSETTO:+0200',
            'TZNAME:CEST',
            'DTSTART:19700329T020000',
            'RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=-1SU',
            'END:DAYLIGHT',
            'BEGIN:STANDARD',
            'TZOFFSETFROM:+0200',
            'TZOFFSETTO:+0100',
            'TZNAME:CET',
            'DTSTART:19701025T030000',
            'RRULE:FREQ=YEARLY;BYMONTH=10;BYDAY=-1SU',
            'END:STANDARD',
            'END:VTIMEZONE',
        );

        foreach (self::trier($evenements) as $e) {
            $lignes[] = 'BEGIN:VEVENT';
            $lignes[] = 'UID:monclubtt-' . $e['id'] . '@' . $domaine;
            $lignes[] = 'DTSTAMP:' . $stamp;
            if ($e['heure'] !== '' && $e['debut'] === $e['fin']) {
                $debut = self::dateHeureIcs($e['debut'], $e['heure']);
                if ($e['heureFin'] !== '' && $e['heureFin'] > $e['heure']) {
                    $fin = self::dateHeureIcs($e['fin'], $e['heureFin']);
                } else {
                    $fin = gmdate('Ymd\THis', (int) strtotime($e['debut'] . 'T' . $e['heure'] . ':00Z') + 7200);
                }
                $lignes[] = 'DTSTART;TZID=Europe/Paris:' . $debut;
                $lignes[] = 'DTEND;TZID=Europe/Paris:' . $fin;
            } else {
                $lignes[] = 'DTSTART;VALUE=DATE:' . str_replace('-', '', $e['debut']);
                $lignes[] = 'DTEND;VALUE=DATE:' . str_replace('-', '', self::lendemain($e['fin']));
            }
            $lignes[] = 'SUMMARY:' . self::texteIcs($e['titre']);
            if ($e['categorie'] !== '') {
                $lignes[] = 'CATEGORIES:' . self::texteIcs($e['categorie']);
            }
            if ($e['lieu'] !== '') {
                $lignes[] = 'LOCATION:' . self::texteIcs(preg_replace('/\s*\R\s*/', ' · ', $e['lieu']));
            }
            $description = array();
            if ($e['heure'] !== '' && $e['debut'] !== $e['fin']) {
                $description[] = 'Début à ' . self::heureLisible($e['heure']) . '.';
            }
            if ($e['note'] !== '') {
                $description[] = $e['note'];
            }
            if ($e['lien'] !== '') {
                $description[] = $e['lien'];
                $lignes[]      = 'URL:' . $e['lien'];
            }
            if (!empty($options['url'])) {
                $description[] = 'Calendrier du club : ' . $options['url'];
            }
            if ($description) {
                $lignes[] = 'DESCRIPTION:' . self::texteIcs(implode("\n", $description));
            }
            $lignes[] = 'TRANSP:TRANSPARENT';
            $lignes[] = 'END:VEVENT';
        }
        $lignes[] = 'END:VCALENDAR';

        return implode("\r\n", array_map(array(__CLASS__, 'plierIcs'), $lignes)) . "\r\n";
    }
}

}
