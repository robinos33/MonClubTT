<?php

declare(strict_types=1);

namespace MonClubTT\Tests\Unit;

use MonClubTT_Shortcodes;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Le catalogue de « Mon Club TT › Shortcodes » doit suivre le code : tout
 * shortcode enregistré par le plugin y figure, avec des exemples valides.
 */
final class ShortcodesTest extends TestCase
{
    /** @return string[] tags passés à add_shortcode() dans le plugin. */
    private function shortcodesEnregistres(): array
    {
        $racine  = dirname(__DIR__, 2);
        $fichiers = array($racine . '/mon-club-tt.php');
        foreach (array('models', 'modules') as $dossier) {
            $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($racine . '/' . $dossier, RecursiveDirectoryIterator::SKIP_DOTS));
            foreach ($it as $fichier) {
                if ($fichier->getExtension() === 'php') {
                    $fichiers[] = $fichier->getPathname();
                }
            }
        }
        $tags = array();
        foreach ($fichiers as $fichier) {
            preg_match_all('/add_shortcode\(\s*[\'"]([a-z0-9_]+)[\'"]/', (string) file_get_contents($fichier), $m);
            $tags = array_merge($tags, $m[1]);
        }
        sort($tags);
        return array_values(array_unique($tags));
    }

    public function test_should_list_every_registered_shortcode(): void
    {
        $enregistres = $this->shortcodesEnregistres();
        $this->assertNotEmpty($enregistres);

        $catalogue = MonClubTT_Shortcodes::tags();
        sort($catalogue);

        $this->assertSame($enregistres, $catalogue, 'Shortcode ajouté ou retiré : mettre à jour models/Shortcodes.php.');
    }

    public function test_should_give_examples_using_the_shortcode_tag(): void
    {
        foreach (MonClubTT_Shortcodes::catalogue() as $groupe) {
            foreach ($groupe['shortcodes'] as $sc) {
                $this->assertNotEmpty($sc['exemples'], $sc['tag']);
                foreach (array_keys($sc['exemples']) as $exemple) {
                    $this->assertMatchesRegularExpression('/^\[' . preg_quote($sc['tag'], '/') . '[\s\]]/', $exemple);
                }
            }
        }
    }

    public function test_should_only_use_documented_attributes_in_examples(): void
    {
        foreach (MonClubTT_Shortcodes::catalogue() as $groupe) {
            foreach ($groupe['shortcodes'] as $sc) {
                $attributs = array_column($sc['attributs'], 0);
                foreach (array_keys($sc['exemples']) as $exemple) {
                    preg_match_all('/\s([a-z_]+)=/', $exemple, $m);
                    foreach ($m[1] as $attribut) {
                        $this->assertContains($attribut, $attributs, $exemple);
                    }
                }
            }
        }
    }
}
