<?php

namespace Reservations\Taxonomies\Training;

use KamranAhmed\Geocode\Geocode;
use Reservations\Base;
use Reservations;

class Category extends Base\Taxonomy
{
    /** @action(init) */
    public function register()
    {
        \register_taxonomy("training_category", "training", [
            "labels"             => [
                'name'                       => _x('Categories', 'taxonomy general name', 'reservations'),
                'singular_name'              => _x('Category', 'taxonomy singular name', 'reservations'),
                'search_items'               => __('Search Categories', 'reservations'),
                'all_items'                  => __('All Categories', 'reservations'),
                'edit_item'                  => __('Edit Category', 'reservations'),
                'view_item'                  => __('View Category', 'reservations'),
                'update_item'                => __('Update Category', 'reservations'),
                'add_new_item'               => __('Add New Category', 'reservations'),
                'new_item_name'              => __('New Category Name', 'reservations'),
                'separate_items_with_commas' => __('Separate categories with commas', 'reservations'),
                'add_or_remove_items'        => __('Add or remove categories', 'reservations'),
                'choose_from_most_used'      => __('Choose from the most used categories', 'reservations'),
                'not_found'                  => __('No categories found', 'reservations'),
                'no_terms'                   => __('No categories', 'reservations'),
            ],
            "show_in_quick_edit" => false,
            "meta_box_cb"        => false,
        ]);
    }
}
