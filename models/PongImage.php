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
 * Rendu côté serveur avec GD + FreeType, uniquement à partir de données
 * vérifiées (aucune image envoyée par le navigateur). Aucune dépendance à
 * WordPress : chemins de fichiers et textes sont fournis par l'appelant.
 */
class MonClubTT_PongImage {

    const LARGEUR = 1200;
    const HAUTEUR = 630;

    /** GD avec FreeType et PNG disponibles. */
    public static function disponible() {
        return function_exists('imagecreatetruecolor') && function_exists('imagettftext') && function_exists('imagepng');
    }

    /**
     * @param array $m Données du match : joueur, adversaire, pj, pa, victoire (bool),
     *                 niveau_libelle, photo_joueur, photo_adversaire (chemins ou ''),
     *                 club, site, couleurs (primaire, secondaire, fond, maillot), police.
     * @return string|null PNG binaire, null si GD est indisponible.
     */
    public static function rendre(array $m) {
        if (!self::disponible() || !is_readable($m['police'])) {
            return null;
        }
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

        // Bandeau du haut : nom du jeu et du club, site à droite.
        imagefilledrectangle($im, 0, 0, $W, 92, $c($coul['primaire']));
        self::texte($im, $police, 'PONG DU CLUB · ' . self::majuscules($m['club']), 30, 56, 60, $c('#ffffff'), 'gauche', 760);
        if ($m['site'] !== '') {
            self::texte($im, $police, $m['site'], 22, $W - 56, 58, $c('#ffffff', 30), 'droite', 360);
        }

        // Joueurs de part et d'autre du marqueur.
        $places = array(
            array('nom' => $m['joueur'], 'photo' => $m['photo_joueur'], 'x' => 230, 'teinte' => $coul['maillot'], 'angle' => 7),
            array('nom' => $m['adversaire'], 'photo' => $m['photo_adversaire'], 'x' => $W - 230, 'teinte' => $coul['secondaire'], 'angle' => -7),
        );
        foreach ($places as $p) {
            if (!self::tete($im, $p['photo'], $p['x'], 300, 270, $p['angle'])) {
                self::initiales($im, $police, $p['nom'], $p['x'], 300, 120, $c($p['teinte']), $c('#ffffff'));
            }
            self::texte($im, $police, $p['nom'], 34, $p['x'], 520, $c($encre), 'centre', 400);
        }

        // Marqueur à fiches : support, deux fiches papier avec anneaux.
        $cx = $W / 2;
        self::rectArrondi($im, $cx - 190, 190, $cx + 190, 430, 16, $c('#1f2e42'));
        $fiches = array(array($m['pj'], $coul['primaire'], $cx - 92), array($m['pa'], $coul['secondaire'], $cx + 92));
        foreach ($fiches as list($valeur, $teinte, $fx)) {
            self::rectArrondi($im, $fx - 78, 232, $fx + 78, 412, 8, $c('#cfc7b4'));
            self::rectArrondi($im, $fx - 78, 224, $fx + 78, 404, 8, $c('#fbf8f1'));
            foreach (array(-36, 36) as $dx) {
                self::rectArrondi($im, $fx + $dx - 6, 208, $fx + $dx + 6, 240, 6, $c('#aab4bf'));
            }
            self::texte($im, $police, (string) $valeur, 96, $fx, 358, $c($teinte), 'centre', 128);
        }

        // Résultat sous le marqueur.
        $resultat = $m['victoire'] ? 'VICTOIRE' : 'DÉFAITE';
        $fondRes  = $m['victoire'] ? $coul['secondaire'] : $encre;
        $largeur  = self::largeur($police, 26, $resultat) + 48;
        self::rectArrondi($im, $cx - $largeur / 2, 446, $cx + $largeur / 2, 494, 24, $c($fondRes));
        self::texte($im, $police, $resultat, 26, $cx, 482, $c('#ffffff'), 'centre', 400);
        if ($m['niveau_libelle'] !== '') {
            self::texte($im, $police, 'Niveau ' . $m['niveau_libelle'], 22, $cx, 584, $c($encre, 40), 'centre', 400);
        }

        ob_start();
        imagepng($im, null, 6);
        imagedestroy($im);
        return ob_get_clean();
    }

    /** Majuscules UTF-8 (les accents compris quand mbstring est là). */
    private static function majuscules($texte) {
        return function_exists('mb_strtoupper') ? mb_strtoupper($texte, 'UTF-8') : strtoupper($texte);
    }

    private static function largeur($police, $taille, $texte) {
        $b = imagettfbbox($taille, 0, $police, $texte);
        return abs($b[2] - $b[0]);
    }

    /** Texte sur une ligne, réduit jusqu'à tenir dans $max pixels. */
    private static function texte($im, $police, $texte, $taille, $x, $y, $couleur, $align, $max) {
        while ($taille > 12 && self::largeur($police, $taille, $texte) > $max) {
            $taille -= 2;
        }
        $l = self::largeur($police, $taille, $texte);
        $x0 = $align === 'centre' ? $x - $l / 2 : ($align === 'droite' ? $x - $l : $x);
        imagettftext($im, $taille, 0, (int) round($x0), (int) round($y), $couleur, $police, $texte);
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
