<?php
/**
 * Bootstrap PHPUnit : charge l'autoload Composer puis les classes métier
 * pures du plugin (sans WordPress). ABSPATH est défini pour passer la garde
 * « accès direct interdit » en tête de chaque fichier du plugin.
 */

require_once __DIR__ . '/../vendor/autoload.php';

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once __DIR__ . '/../Utils.php';

// Fonctions WordPress utilisées par les fonctions pures testées (même comportement).
if (!function_exists('wp_strip_all_tags')) {
    function wp_strip_all_tags($text, $remove_breaks = false)
    {
        $text = preg_replace('@<(script|style)[^>]*?>.*?</\\1>@si', '', (string) $text);
        $text = strip_tags($text);
        if ($remove_breaks) {
            $text = preg_replace('/[\r\n\t ]+/', ' ', $text);
        }
        return trim($text);
    }
}
