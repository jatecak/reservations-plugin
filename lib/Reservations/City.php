<?php

namespace Reservations\Taxonomies;

use KamranAhmed\Geocode\Geocode;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\PostTypes;

class City extends Base\Taxonomy
{
    const NAME = Reservations::PREFIX . "city";

    /** @action(init) */
    public function register()
    {
        register_taxonomy(self::NAME, [PostTypes\Training::NAME, PostTypes\Event::NAME], [
        "labels"             => [
                'name'                       => _x('Cities', 'taxonomy general name', 'reservations'),
                'singular_name'              => _x('City', 'taxonomy singular name', 'reservations'),
                'search_items'               => __('Search Cities', 'reservations'),
                'all_items'                  => __('All Cities', 'reservations'),
                'edit_item'                  => __('Edit City', 'reservations'),
                'view_item'                  => __('View City', 'reservations'),
                'update_item'                => __('Update City', 'reservations'),
                'add_new_item'               => __('Add New City', 'reservations'),
                'new_item_name'              => __('New City Name', 'reservations'),
                'separate_items_with_commas' => __('Separate cities with commas', 'reservations'),
                'add_or_remove_items'        => __('Add or remove cities', 'reservations'),
                'choose_from_most_used'      => __('Choose from the most used cities', 'reservations'),
                'not_found'                  => __('No cities found', 'reservations'),
                'no_terms'                   => __('No cities', 'reservations'),
              ],
        "hierarchical"       => true,  // Nastavení taxonomie jako hierarchické
        "show_in_quick_edit" => false,
        "meta_box_cb"        => false,
    ]);
}

    // Funkce pro registraci taxonomie "Kraj"
    public function register_kraj_taxonomy() {
        register_taxonomy('kraj', ['city'], [
            'labels' => [
                'name'              => _x('Kraje', 'taxonomy general name'),
                'singular_name'     => _x('Kraj', 'taxonomy singular name'),
                'search_items'      => __('Search Kraje'),
                'all_items'         => __('All Kraje'),
                'parent_item'       => __('Parent Kraj'),
                'parent_item_colon' => __('Parent Kraj:'),
                'edit_item'         => __('Edit Kraj'),
                'update_item'       => __('Update Kraj'),
                'add_new_item'      => __('Add New Kraj'),
                'new_item_name'     => __('New Kraj Name'),
                'menu_name'         => __('Kraje'),
            ],
            'hierarchical'      => true,  // Aby taxonomie fungovala hierarchicky
            'show_ui'           => true,  // Zobrazení v adminu
            'show_admin_column' => true,  // Zobrazení ve sloupci adminu
            'query_var'         => true,
            'rewrite'           => ['slug' => 'kraj'],
        ]);
    }

    // Ostatní funkce pro metabox
    public function removeDescriptionTextBox()
    {
        echo '<style type="text/css">
            .term-description-wrap { display: none; }
            .wpcustom-category-form-field { display: none; }
        </style>';
    }

    public function removeDescriptionAndImageColumn($columns)
    {
        if (isset($columns["image"])) {
            unset($columns["image"]);
        }

        if (isset($columns["description"])) {
            unset($columns["description"]);
        }

        return $columns;
    }

    public function addFormFields()
    {
        ?>
        <div class="form-field city-latlng-wrap">
            <label for="city-latlng"><?php _ex('Location', 'coordinates', 'reservations');?></label>
            <input type="text" name="city_meta[lat]" id="city-lat" value="" placeholder="<?php _e('Latitude', 'reservations');?>">
            <input type="text" name="city_meta[lng]" id="city-lng" value="" placeholder="<?php _e('Longitude', 'reservations');?>">
            <p class="description"><?php _e('If left empty, location will be determined from name', 'reservations');?></p>
        </div>
        <?php
    }

    public function editFormFields($city)
    {
        $values = [
            "lat" => esc_html(get_term_meta($city->term_id, Reservations::PREFIX . "lat", true)),
            "lng" => esc_html(get_term_meta($city->term_id, Reservations::PREFIX . "lng", true)),
        ];
        ?>
        <tr class="form-field city-latlng-wrap">
            <th scope="row"><label for="city-latlng"><?php _ex('Location', 'coordinates', 'reservations');?></label></th>
            <td><input type="text" name="city_meta[lat]" id="city-lat" value="<?=$values["lat"]?>" placeholder="<?php _e('Latitude', 'reservations');?>">
            <input type="text" name="city_meta[lng]" id="city-lng" value="<?=$values["lng"]?>" placeholder="<?php _e('Longitude', 'reservations');?>">
            <p class="description"><?php _e('If left empty, location will be determined from name', 'reservations');?></p></td>
        </tr>
        <?php
    }

    public function geocodeCity($cityId, $taxonomyId)
    {
        if (!isset($_POST["city_meta"])) {
            return;
        }

        $meta = $_POST["city_meta"];
        $city = get_term($cityId);

        if (empty($meta["lat"]) && empty($meta["lng"])) {
            $geocoder = new Geocode($this->plugin->getOption("google_maps_api_key"));
            $location = $geocoder->get($city->name);

            if ($location) {
                update_term_meta($cityId, Reservations::PREFIX . "lat", $location->getLatitude());
                update_term_meta($cityId, Reservations::PREFIX . "lng", $location->getLongitude());
            } else {
                wp_die(__('Unable to determine location from city name', 'reservations'));
            }
        } else {
            update_term_meta($cityId, Reservations::PREFIX . "lat", sanitize_text_field($meta["lat"]));
            update_term_meta($cityId, Reservations::PREFIX . "lng", sanitize_text_field($meta["lng"]));
        }
    }

    public function registerRowActions($rowActions, $term)
    {
        $city = Models\City::find((int) $term->term_id);

        $gymCount   = $city->gyms()->count();
        $eventCount = $city->events()->count();

        if ($gymCount > 0 || $eventCount > 0) {
            unset($rowActions["delete"]);
        }

        return $rowActions;
    }

    public function beforeDelete($termId)
    {
        $city = Models\City::find((int) $termId);

        if (!$city) {
            return;
        }

        $gymCount   = $city->gyms()->count();
        $eventCount = $city->events()->count();

        if ($gymCount > 0 || $eventCount > 0) {
            $message = '<p><strong>' . sprintf(__('An error occurred during the deletion of city %s', 'reservations'), esc_html($city->name)) . '</strong></p>';
            $message .= '<p>' . __('The following are causes of this error:', 'reservations') . '</p><ul>';

            if ($gymCount > 0) {
                $message .= '<li>' . sprintf(_n('This city is used by %d gym.', 'This city is used by %d gyms.', $gymCount, 'reservations'), $gymCount) . '</li>';
            }

            if ($eventCount > 0) {
                $message .= '<li>' . sprintf(_n('This city is used by %d event.', 'This city is used by %d events.', $eventCount, 'reservations'), $eventCount) . '</li>';
            }

            $message .= '</ul>';

            wp_die($message);
        }
    }
}



// Registrace taxonomie "Kraj" pøi inicializaci
add_action('init', [City::class, 'register_kraj_taxonomy']);

// Pøidání metaboxu pro kraje
add_action('add_meta_boxes', function() {
    add_meta_box(
        'krajdiv',                      // ID metaboxu
        __('Kraje', 'reservations'),    // Titulek metaboxu
        'post_categories_meta_box',     // Funkce pro zobrazení
        'city',                         // Post type
        'side',                         // Pozice metaboxu
        'default'                       // Priorita
    );
});
