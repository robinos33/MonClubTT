<?php
if ( ! defined( 'ABSPATH' ) ) exit;

if ( ! class_exists( 'MonClubTT_CollectionsPhotos' ) ) {

class MonClubTT_CollectionsPhotos
{
    const TAXONOMIE = 'monclubtt_collection';

    public static function enregistrer()
    {
        register_taxonomy(self::TAXONOMIE, 'attachment', array(
            'labels' => array(
                'name'          => 'Collections de photos',
                'singular_name' => 'Collection de photos',
                'add_new_item'  => 'Ajouter une collection de photos',
                'edit_item'     => 'Modifier la collection de photos',
                'search_items'  => 'Rechercher des collections de photos',
            ),
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_admin_column' => true,
            'capabilities'      => array(
                'manage_terms' => 'manage_options',
                'edit_terms'   => 'manage_options',
                'delete_terms' => 'manage_options',
                'assign_terms' => 'manage_options',
            ),
            // Les associations sont gérées depuis le générateur.
            'meta_box_cb'       => false,
        ));
    }
}

}
