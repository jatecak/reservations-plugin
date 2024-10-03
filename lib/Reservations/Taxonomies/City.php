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
            "show_in_quick_edit" => false,
            "meta_box_cb"        => false,
        ]);
    }

    /**
     * @action(res_city_add_form)
     * @action(res_city_edit_form)
     */
    public function removeDescriptionTextBox()
    {
        echo '<style type="text/css">
            .term-description-wrap { display: none; }
            .wpcustom-category-form-field { display: none; }
        </style>';
    }

    /**
     * @action(manage_edit-res_city_columns)
     * @priority(15)
     */
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

    /** @action(res_city_add_form_fields) */
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

    /** @action(res_city_edit_form_fields) */
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

    /**
     * @action(create_res_city)
     * @action(edit_res_city)
     */
    public function geocodeCity($cityId, $taxonomyId)
    {
        if (!isset($_POST["city_meta"])) {
            return;
        }

        $meta = $_POST["city_meta"];
        $city = get_term($cityId);

        if (empty($meta["lat"]) && empty($meta["lon"])) {
            $geocoder = new Geocode($this->plugin->getOption("google_maps_api_key"));
            $location = $geocoder->get($city->name);

            update_term_meta($cityId, Reservations::PREFIX . "lat", $location->getLatitude());
            update_term_meta($cityId, Reservations::PREFIX . "lng", $location->getLongitude());
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
            $message = '<p><strong>' . sprintf(__('An error occured during the deletion of city %s', 'reservations'), esc_html($city->name)) . '</strong></p>';
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
