<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_Appli' ) ) {

/**
 * Règles de l'appli d'équipe (ajout d'une page d'équipe à l'écran d'accueil) :
 * lecture des shortcodes, numéro d'équipe, noms affichés sous l'icône.
 *
 * Fonctions pures, sans WordPress : le lien avec WordPress (manifest, balises,
 * redirection) est dans MonClubTT_AppliWP.
 */
class MonClubTT_Appli {

    /** Longueur du nom sous l'icône au-delà de laquelle Android le tronque. */
    const NOM_COURT_MAX = 12;

    /** Mots ignorés pour former un sigle (« Tennis de Table » → « TT »). */
    const MOTS_VIDES = array('de', 'du', 'des', 'la', 'le', 'les', 'l', 'd', 'et', 'en', 'au', 'aux', 'sur');

    /**
     * Shortcodes [monclubtt_equipe] d'un contenu, avec leurs attributs.
     *
     * @param string $contenu Contenu de la page.
     * @return array<int, array<string, string>> Un tableau d'attributs par shortcode.
     */
    public static function shortcodesEquipe($contenu) {
        $resultat = array();
        if (!preg_match_all('/\[monclubtt_equipe(\s[^\]]*)?\]/', (string) $contenu, $correspondances)) {
            return $resultat;
        }
        foreach ($correspondances[1] as $texte) {
            $resultat[] = self::attributs((string) $texte);
        }
        return $resultat;
    }

    /**
     * Attributs d'un shortcode : cle="valeur", cle='valeur' ou cle=valeur.
     * Les guillemets typographiques que l'éditeur peut insérer sont acceptés.
     *
     * @param string $texte
     * @return array<string, string>
     */
    public static function attributs($texte) {
        $texte = str_replace(array("\u{201C}", "\u{201D}", "\u{2033}", "\u{00AB}", "\u{00BB}"), '"', $texte);
        $texte = str_replace(array("\u{2018}", "\u{2019}", "\u{2032}"), "'", $texte);
        $attributs = array();
        preg_match_all('/([\w-]+)\s*=\s*(?:"([^"]*)"|\'([^\']*)\'|([^\s"\']+))/u', $texte, $paires, PREG_SET_ORDER);
        foreach ($paires as $paire) {
            $valeur = $paire[2] !== '' ? $paire[2] : ((isset($paire[3]) && $paire[3] !== '') ? $paire[3] : ($paire[4] ?? ''));
            $attributs[strtolower($paire[1])] = trim($valeur);
        }
        return $attributs;
    }

    /**
     * Libellé FFTT sans le suffixe de phase : « US TALENCE 15 - Phase 1 » → « US TALENCE 15 ».
     *
     * @param string $libequipe
     * @return string
     */
    public static function libelleSansPhase($libequipe) {
        return trim((string) preg_replace('/\s*-\s*Phase\s*\d+\s*$/iu', '', (string) $libequipe));
    }

    /**
     * Numéro de l'équipe dans le club, à la fin du libellé FFTT.
     *
     * @param string $libequipe « US TALENCE 15 - Phase 1 »
     * @return int|null 15, null si le libellé ne finit pas par un numéro.
     */
    public static function numeroEquipe($libequipe) {
        return preg_match('/(\d+)$/', self::libelleSansPhase($libequipe), $m) === 1 ? (int) $m[1] : null;
    }

    /**
     * Clé stable d'une équipe, qui ne change pas d'une phase à l'autre
     * (contrairement à la division, à la poule et à la page générée) : M3, F1.
     *
     * @param string $type   M ou F
     * @param int    $numero Numéro de l'équipe
     * @return string
     */
    public static function cleEquipe($type, $numero) {
        return ($type === 'F' ? 'F' : 'M') . (int) $numero;
    }

    /**
     * Lit une clé d'équipe (paramètre d'URL).
     *
     * @param string $cle
     * @return array{type: string, numero: int}|null
     */
    public static function lireCle($cle) {
        if (!preg_match('/^([MF])([1-9]\d{0,2})$/', (string) $cle, $m)) {
            return null;
        }
        return array('type' => $m[1], 'numero' => (int) $m[2]);
    }

    /**
     * Sigle d'un nom de club : sigle déjà présent (« ASPTT Bordeaux » → « ASPTT »),
     * sinon initiales des mots significatifs (« Stade Bordelais Tennis de Table » → « SBTT »).
     *
     * @param string $nom
     * @return string '' si aucun sigle ne se dégage (nom d'un seul mot).
     */
    public static function sigle($nom) {
        $mots = preg_split('/[\s\-\'’.]+/u', trim((string) $nom), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($mots)) {
            return '';
        }
        foreach ($mots as $mot) {
            if (mb_strlen($mot) >= 2 && preg_match('/^\p{Lu}+$/u', $mot)) {
                return $mot;
            }
        }
        $initiales = '';
        foreach ($mots as $mot) {
            if (in_array(mb_strtolower($mot), self::MOTS_VIDES, true)) {
                continue;
            }
            $initiales .= mb_strtoupper(mb_substr($mot, 0, 1));
        }
        return mb_strlen($initiales) >= 2 ? $initiales : '';
    }

    /**
     * Nom sous l'icône : nom du club tel quel s'il tient, sinon sans son sigle
     * générique de tête, sinon son sigle, sinon tronqué.
     *
     * @param string $nomClub
     * @param string $suffixe « 3 », « D1 » ou ''
     * @param int    $max
     * @return string
     */
    public static function nomCourt($nomClub, $suffixe, $max = self::NOM_COURT_MAX) {
        $nomClub = trim((string) $nomClub);
        $suffixe = trim((string) $suffixe);
        $avecSuffixe = function ($nom) use ($suffixe) {
            return trim($nom . ' ' . $suffixe);
        };

        if (mb_strlen($avecSuffixe($nomClub)) <= $max) {
            return $avecSuffixe($nomClub);
        }
        $sigle = self::sigle($nomClub);
        // Sigle générique (US, AS, CA…) : la ville parle davantage (« US Talence » → « Talence »).
        if ($sigle !== '' && mb_strlen($sigle) <= 3 && mb_strpos($nomClub, $sigle . ' ') === 0) {
            $reste = trim(mb_substr($nomClub, mb_strlen($sigle)));
            if ($reste !== '' && mb_strlen($avecSuffixe($reste)) <= $max) {
                return $avecSuffixe($reste);
            }
        }
        if ($sigle !== '' && mb_strlen($avecSuffixe($sigle)) <= $max) {
            return $avecSuffixe($sigle);
        }
        $place = max(1, $max - ($suffixe === '' ? 0 : mb_strlen($suffixe) + 1));
        return $avecSuffixe(rtrim(mb_substr($sigle !== '' ? $sigle : $nomClub, 0, $place)));
    }

    /**
     * Noms et description de l'appli d'une page.
     *
     * Le nom ne reprend pas la division : elle change à chaque phase, alors
     * qu'une appli installée garde son nom. La division va dans la description.
     *
     * @param string                    $nomClub
     * @param array<string, mixed>|null $equipe    Équipe FFTT (libequipe, libdivision) ou null
     *                                             (page de plusieurs équipes, poule disparue).
     * @param string                    $type      M ou F
     * @param array<string, string>     $attributs Attributs du shortcode (nom_appli, nom_court).
     * @return array{nom: string, nom_court: string, accroche: string, description: string, cle: string}
     *         accroche : nom dans le bandeau (« l’équipe 3 ») ;
     *         cle : clé d'équipe (M3), '' pour une appli de page.
     */
    public static function identite($nomClub, $equipe, $type, array $attributs = array()) {
        $nomClub  = trim((string) $nomClub);
        $numero   = is_array($equipe) ? self::numeroEquipe((string) ($equipe['libequipe'] ?? '')) : null;
        $division = is_array($equipe) ? trim((string) ($equipe['libdivision'] ?? '')) : '';

        if ($numero !== null) {
            // Sous l'icône, le logo du club suffit à l'identifier : « Équipe 3 ».
            // Le nom complet (fenêtre d'installation, liste des applis) garde le club.
            $equipeLib = ($type === 'F' ? 'Équipe Dames ' : 'Équipe ') . $numero;
            $nom       = $equipeLib . ' · ' . $nomClub;
            $court     = $type === 'F' ? 'Dames ' . $numero : $equipeLib;
            $accroche  = 'l’' . mb_strtolower(mb_substr($equipeLib, 0, 1)) . mb_substr($equipeLib, 1);
            $desc      = 'Résultats et classement de ' . $accroche . ' de ' . $nomClub . ($division !== '' ? ' en ' . $division : '') . '.';
            $cle       = self::cleEquipe($type, $numero);
        } elseif (is_array($equipe) && trim((string) ($equipe['libequipe'] ?? '')) !== '') {
            $nom      = self::libelleSansPhase((string) $equipe['libequipe']);
            $court    = self::nomCourt($nom, '');
            $accroche = $nom;
            $desc     = 'Résultats et classement de ' . $nom . ($division !== '' ? ' en ' . $division : '') . '.';
            $cle      = '';
        } else {
            $nom      = $nomClub . ' · Résultats';
            $court    = self::nomCourt($nomClub, '');
            $accroche = $nomClub;
            $desc     = 'Résultats et classements des équipes de ' . $nomClub . '.';
            $cle      = '';
        }

        $nomImpose   = trim((string) ($attributs['nom_appli'] ?? ''));
        $courtImpose = trim((string) ($attributs['nom_court'] ?? ''));
        if ($nomImpose !== '') {
            $nom      = $nomImpose;
            $accroche = $nomImpose;
            $court    = mb_strlen($nomImpose) <= self::NOM_COURT_MAX ? $nomImpose : self::nomCourt($nomImpose, '');
        }
        if ($courtImpose !== '') {
            $court = $courtImpose;
        }

        return array('nom' => $nom, 'nom_court' => $court, 'accroche' => $accroche, 'description' => $desc, 'cle' => $cle);
    }

    /**
     * L'appli est-elle désactivée par l'attribut appli="non" du shortcode ?
     *
     * @param array<string, string> $attributs
     * @return bool
     */
    public static function desactiveeParAttribut(array $attributs) {
        return in_array(strtolower((string) ($attributs['appli'] ?? '')), array('non', 'no', '0', 'false'), true);
    }
}

}
