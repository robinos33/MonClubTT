<?php
if ( ! defined( 'ABSPATH' ) ) exit;

// Le plugin peut être présent en double sur une install : la garde doit englober
// la déclaration, une classe au premier niveau étant liée dès la compilation.
if ( ! class_exists( 'MonClubTT_PongImage' ) ) {

/**
 * Image de partage (og:image, 1200×630) d'un match du jeu de pong : les deux
 * joueurs (photo détourée avec liseré blanc, sinon pastille à initiales) de
 * part et d'autre du marqueur à fiches, aux couleurs du club.
 *
 * Rendu côté serveur avec GD, uniquement à partir de données vérifiées
 * (aucune image envoyée par le navigateur). Texte en police TrueType quand
 * FreeType fonctionne vraiment, sinon police bitmap de GD agrandie (certains
 * GD annoncent FreeType sans l'avoir, WordPress Playground par exemple).
 * Aucune dépendance à WordPress : chemins et textes sont fournis par l'appelant.
 */
class MonClubTT_PongImage {

    const LARGEUR = 1200;
    const HAUTEUR = 630;

    /** Police TrueType utilisable pour ce rendu (null : police bitmap de GD). */
    private static $police = null;

    /** GD avec PNG disponible. */
    public static function disponible() {
        return function_exists('imagecreatetruecolor') && function_exists('imagepng');
    }

    /** FreeType réellement opérationnel avec cette police (mesure d'essai). */
    private static function freetype($police) {
        return function_exists('imagettfbbox') && is_readable($police)
            && is_array(@imagettfbbox(12, 0, $police, 'A')); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- avertissement attendu sans FreeType
    }

    /**
     * @param array $m Données du match : joueur, adversaire, pj, pa, victoire (bool),
     *                 niveau_libelle, photo_joueur, photo_adversaire (chemins ou ''),
     *                 club, site, couleurs (primaire, secondaire, fond, maillot), police.
     * @return string|null PNG binaire, null si GD est indisponible.
     */
    public static function rendre(array $m) {
        if (!self::disponible()) {
            return null;
        }
        self::$police = self::freetype($m['police']) ? $m['police'] : null;
        $W = self::LARGEUR;
        $H = self::HAUTEUR;
        $im = imagecreatetruecolor($W, $H);
        imagealphablending($im, true);
        imagesavealpha($im, false);
        $c = function ($hex, $alpha = 0) use ($im) {
            $n = hexdec(ltrim($hex, '#'));
            return imagecolorallocatealpha($im, ($n >> 16) & 255, ($n >> 8) & 255, $n & 255, $alpha);
        };
        $police = $m['police'];
        $encre  = '#23344a';
        $coul   = $m['couleurs'];

        imagefilledrectangle($im, 0, 0, $W, $H, $c($coul['fond']));

        // Bandeau du haut : nom du jeu et du club.
        imagefilledrectangle($im, 0, 0, $W, 80, $c($coul['primaire']));
        self::texte($im, $police, 'PONG DU CLUB · ' . self::majuscules($m['club']), 30, $W / 2, 53, $c('#ffffff'), 'centre', $W - 112);

        // Joueurs de part et d'autre du marqueur.
        $places = array(
            array('nom' => $m['joueur'], 'photo' => $m['photo_joueur'], 'x' => 230, 'teinte' => $coul['maillot'], 'angle' => 7),
            array('nom' => $m['adversaire'], 'photo' => $m['photo_adversaire'], 'x' => $W - 230, 'teinte' => $coul['secondaire'], 'angle' => -7),
        );
        foreach ($places as $p) {
            if (!self::tete($im, $p['photo'], $p['x'], 268, 240, $p['angle'])) {
                self::initiales($im, $police, $p['nom'], $p['x'], 268, 108, $c($p['teinte']), $c('#ffffff'));
            }
            self::texte($im, $police, $p['nom'], 32, $p['x'], 468, $c($encre), 'centre', 400);
        }

        // Marqueur à fiches : support, deux fiches papier avec anneaux.
        $cx = $W / 2;
        self::rectArrondi($im, $cx - 190, 158, $cx + 190, 390, 16, $c('#1f2e42'));
        $fiches = array(array($m['pj'], $coul['primaire'], $cx - 92), array($m['pa'], $coul['secondaire'], $cx + 92));
        foreach ($fiches as list($valeur, $teinte, $fx)) {
            self::rectArrondi($im, $fx - 78, 200, $fx + 78, 376, 8, $c('#cfc7b4'));
            self::rectArrondi($im, $fx - 78, 192, $fx + 78, 368, 8, $c('#fbf8f1'));
            foreach (array(-36, 36) as $dx) {
                self::rectArrondi($im, $fx + $dx - 6, 176, $fx + $dx + 6, 208, 6, $c('#aab4bf'));
            }
            self::texte($im, $police, (string) $valeur, 96, $fx, 324, $c($teinte), 'centre', 128);
        }

        // Résultat (et niveau) sous le marqueur.
        $resultat = ($m['victoire'] ? 'VICTOIRE' : 'DÉFAITE') . ($m['niveau_libelle'] !== '' ? ' · ' . self::majuscules($m['niveau_libelle']) : '');
        $fondRes  = $m['victoire'] ? $coul['secondaire'] : $encre;
        $largeur  = self::largeur($police, 24, $resultat) + 48;
        self::rectArrondi($im, $cx - $largeur / 2, 404, $cx + $largeur / 2, 448, 22, $c($fondRes));
        self::texte($im, $police, $resultat, 24, $cx, 437, $c('#ffffff'), 'centre', 400);

        // Appel à l'action : bandeau du bas, invitation à rejouer le même match.
        imagefilledrectangle($im, 0, 512, $W, $H, $c($encre));
        $defi = $m['victoire']
            ? 'À TOI : BATS ' . self::majuscules($m['adversaire']) . ' !'
            : 'VENGE ' . self::majuscules($m['joueur']) . ' !';
        self::texte($im, $police, $defi, 34, 56, 566, $c('#ffffff'), 'gauche', 780);
        $invitation = $m['site'] !== '' ? 'Joue gratuitement sur ' . $m['site'] : 'Joue gratuitement sur le site du club';
        self::texte($im, $police, $invitation, 22, 56, 604, $c('#ffffff', 35), 'gauche', 780);
        // Bouton « JOUER » dessiné (triangle en polygone : pas de dépendance aux glyphes).
        $bx2 = $W - 56;
        $bx1 = $bx2 - 236;
        self::rectArrondi($im, $bx1, 536, $bx2, 606, 35, $c($coul['secondaire']));
        $lJouer = self::largeur(self::$police, 30, 'JOUER');
        $x0     = ($bx1 + $bx2) / 2 - ($lJouer + 18 + 26) / 2;
        self::texte($im, $police, 'JOUER', 30, $x0, 584, $c('#ffffff'), 'gauche', 160);
        $tx = (int) round($x0 + $lJouer + 18);
        imagefilledpolygon($im, array($tx, 555, $tx, 587, $tx + 26, 571), $c('#ffffff'));

        ob_start();
        imagepng($im, null, 6);
        imagedestroy($im);
        return ob_get_clean();
    }

    /** Majuscules UTF-8 (les accents compris quand mbstring est là). */
    private static function majuscules($texte) {
        return function_exists('mb_strtoupper') ? mb_strtoupper($texte, 'UTF-8') : strtoupper($texte);
    }

    /* Police bitmap n° 5 de GD : 9×15 px par caractère, agrandie à la taille voulue. */
    const BITMAP_L = 9;
    const BITMAP_H = 15;

    /** Échelle de la police bitmap pour une taille en points (hauteur de capitale comparable). */
    private static function echelleBitmap($taille) {
        return max(1, $taille * 1.33 / self::BITMAP_H);
    }

    /** La police bitmap ne connaît que l'ASCII : accents retirés. */
    private static function ascii($texte) {
        $texte = strtr($texte, array(
            'À' => 'A', 'Â' => 'A', 'Ä' => 'A', 'Ç' => 'C', 'É' => 'E', 'È' => 'E', 'Ê' => 'E', 'Ë' => 'E',
            'Î' => 'I', 'Ï' => 'I', 'Ô' => 'O', 'Ö' => 'O', 'Ù' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ÿ' => 'Y',
            'à' => 'a', 'â' => 'a', 'ä' => 'a', 'ç' => 'c', 'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'î' => 'i', 'ï' => 'i', 'ô' => 'o', 'ö' => 'o', 'ù' => 'u', 'û' => 'u', 'ü' => 'u', 'ÿ' => 'y',
            '–' => '-', '—' => '-', '·' => '-', '’' => "'", 'Œ' => 'OE', 'œ' => 'oe', 'Æ' => 'AE', 'æ' => 'ae',
        ));
        return preg_replace('/[^\x20-\x7e]/', '?', $texte);
    }

    private static function largeur($police, $taille, $texte) {
        if (self::$police === null) {
            return strlen(self::ascii($texte)) * self::BITMAP_L * self::echelleBitmap($taille);
        }
        $b = imagettfbbox($taille, 0, $police, $texte);
        return abs($b[2] - $b[0]);
    }

    /** Texte sur une ligne (y = ligne de base), réduit jusqu'à tenir dans $max pixels. */
    private static function texte($im, $police, $texte, $taille, $x, $y, $couleur, $align, $max) {
        $police = self::$police;
        while ($taille > 12 && self::largeur($police, $taille, $texte) > $max) {
            $taille -= 2;
        }
        $l = self::largeur($police, $taille, $texte);
        $x0 = $align === 'centre' ? $x - $l / 2 : ($align === 'droite' ? $x - $l : $x);
        if ($police !== null) {
            imagettftext($im, $taille, 0, (int) round($x0), (int) round($y), $couleur, $police, $texte);
            return;
        }
        // Repli bitmap : texte écrit petit sur un calque transparent puis agrandi.
        $ascii = self::ascii($texte);
        $k     = self::echelleBitmap($taille);
        $petit = self::calque(max(1, strlen($ascii) * self::BITMAP_L), self::BITMAP_H);
        imagealphablending($petit, true);
        $rgba = imagecolorsforindex($im, $couleur);
        imagestring($petit, 5, 0, 0, $ascii, imagecolorallocatealpha($petit, $rgba['red'], $rgba['green'], $rgba['blue'], $rgba['alpha']));
        $w = (int) round(imagesx($petit) * $k);
        $h = (int) round(self::BITMAP_H * $k);
        // Ligne de base de la police bitmap vers 12 px sur 15.
        imagecopyresized($im, $petit, (int) round($x0), (int) round($y - 12 * $k), 0, 0, $w, $h, imagesx($petit), self::BITMAP_H);
        imagedestroy($petit);
    }

    private static function rectArrondi($im, $x1, $y1, $x2, $y2, $r, $couleur) {
        $x1 = (int) round($x1); $y1 = (int) round($y1); $x2 = (int) round($x2); $y2 = (int) round($y2);
        imagefilledrectangle($im, $x1 + $r, $y1, $x2 - $r, $y2, $couleur);
        imagefilledrectangle($im, $x1, $y1 + $r, $x2, $y2 - $r, $couleur);
        foreach (array(array($x1 + $r, $y1 + $r), array($x2 - $r, $y1 + $r), array($x1 + $r, $y2 - $r), array($x2 - $r, $y2 - $r)) as $p) {
            imagefilledellipse($im, $p[0], $p[1], 2 * $r, 2 * $r, $couleur);
        }
    }

    /** Pastille à initiales quand le joueur n'a pas de photo. */
    private static function initiales($im, $police, $nom, $x, $y, $rayon, $fond, $encre) {
        $mots = preg_split('/\s+/u', trim($nom));
        $ini  = '';
        foreach (array_slice($mots, 0, 2) as $mot) {
            $ini .= function_exists('mb_substr') ? mb_substr($mot, 0, 1, 'UTF-8') : substr($mot, 0, 1);
        }
        imagefilledellipse($im, $x, $y, 2 * $rayon + 16, 2 * $rayon + 16, imagecolorallocate($im, 255, 255, 255));
        imagefilledellipse($im, $x, $y, 2 * $rayon, 2 * $rayon, $fond);
        self::texte($im, $police, self::majuscules($ini), 84, $x, $y + 30, $encre, 'centre', 2 * $rayon - 20);
    }

    /**
     * Photo détourée posée en sticker : silhouette blanche tamponnée tout
     * autour (liseré), photo par-dessus, légère inclinaison.
     * @return bool false si la photo est absente ou illisible.
     */
    private static function tete($im, $chemin, $x, $y, $hauteur, $angle) {
        if ($chemin === '' || !is_readable($chemin)) {
            return false;
        }
        $src = @imagecreatefromstring((string) file_get_contents($chemin));
        if (!$src) {
            return false;
        }
        $sw = imagesx($src);
        $sh = imagesy($src);
        $h  = $hauteur;
        $w  = (int) round($h * $sw / $sh);
        $bord = 9;
        $tw = $w + 2 * $bord;
        $th = $h + 2 * $bord;

        $photo = self::calque($w, $h);
        imagecopyresampled($photo, $src, 0, 0, 0, 0, $w, $h, $sw, $sh);
        imagedestroy($src);

        $silhouette = self::calque($w, $h);
        imagecopy($silhouette, $photo, 0, 0, 0, 0, $w, $h);
        imagefilter($silhouette, IMG_FILTER_BRIGHTNESS, 255);

        $sticker = self::calque($tw, $th);
        imagealphablending($sticker, true);
        foreach (array($bord / 3, 2 * $bord / 3, $bord) as $r) {
            for ($a = 0; $a < 24; $a++) {
                $t = $a * M_PI / 12;
                imagecopy($sticker, $silhouette, (int) round($bord + cos($t) * $r), (int) round($bord + sin($t) * $r), 0, 0, $w, $h);
            }
        }
        imagecopy($sticker, $photo, $bord, $bord, 0, 0, $w, $h);
        imagedestroy($photo);
        imagedestroy($silhouette);

        $transparent = imagecolorallocatealpha($sticker, 0, 0, 0, 127);
        $tourne = imagerotate($sticker, $angle, $transparent);
        imagedestroy($sticker);
        imagealphablending($tourne, true);
        $rw = imagesx($tourne);
        $rh = imagesy($tourne);
        imagecopy($im, $tourne, (int) round($x - $rw / 2), (int) round($y - $rh / 2), 0, 0, $rw, $rh);
        imagedestroy($tourne);
        return true;
    }

    /** Image vide transparente. */
    private static function calque($w, $h) {
        $c = imagecreatetruecolor($w, $h);
        imagealphablending($c, false);
        imagesavealpha($c, true);
        imagefill($c, 0, 0, imagecolorallocatealpha($c, 0, 0, 0, 127));
        return $c;
    }
}

}
