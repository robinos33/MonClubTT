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

require_once __DIR__ . '/../models/TopPerfs.php';
