<?php

namespace Reservations\Admin;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\Utils;

class InstructorProfile extends Base\Service
{
    /**
     * @action(show_user_profile)
     * @action(edit_user_profile)
     */
    public function addProfileFields($user)
    {
        if (current_user_can("subscriber")) {
            return;
        }

        $userModel = Models\User::find($user->ID);

        if (count($userModel->accessibleCityIds) === 0 || $userModel->can("administrator")) {
            $accessibleCities = collect([]);
        } else {
            $accessibleCities = $userModel->accessibleCities->sortBy("id");
        }

        $accessibleCityIds = $accessibleCities->pluck("id");

        $values = [
            "is_instructor" => (bool) $userModel->getPrefixedMeta("is_instructor"),
            "nickname"      => esc_attr($userModel->getPrefixedMeta("nickname")),
            "experience"    => esc_attr($userModel->getPrefixedMeta("experience")),
            "contact_phone" => esc_attr($userModel->getPrefixedMeta("contact_phone")),
            "contact_email" => esc_attr($userModel->getPrefixedMeta("contact_email")),
        ];

        $cities = get_terms([
            "taxonomy"   => $this->plugin->prefix("city"),
            "hide_empty" => false,
        ]);

        $availableCities = array_filter($cities, function ($city) use ($accessibleCityIds) {
            return !$accessibleCityIds->contains($city->term_id);
        });

        $citiesEditable = current_user_can("administrator");

        ?>
        <h3><?php _e('Instructor info', 'reservations');?></h3>

        <table class="form-table res-wrap">
        <tr>
            <th><label for="instructor-is"><?php _e('Is instructor?', 'reservations');?></label></th>
            <td>
                <input type="checkbox" name="instructor_meta[is_instructor]" id="instructor-is"<?php checked($values["is_instructor"]);?>>
                <p class="description"><?php _e('Check, if this user should be marked as instructor.', 'reservations');?></p>
            </td>
        </tr>
        <tr>
            <th><label for="instructor-nickname"><?php _e('Nickname', 'reservations');?></label></th>
            <td>
                <input type="text" name="instructor_meta[nickname]" id="instructor-nickname" value="<?=$values["nickname"]?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="instructor-experience"><?php _e('Experience', 'reservations');?></label></th>
            <td>
                <input type="text" name="instructor_meta[experience]" id="instructor-experience" value="<?=$values["experience"]?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="instructor-email"><?php _e('Contact Email', 'reservations');?></label></th>
            <td>
                <input type="email" name="instructor_meta[contact_email]" id="instructor-email" value="<?=$values["contact_email"]?>" class="regular-text" />
            </td>
        </tr>
        <tr>
            <th><label for="instructor-phone"><?php _e('Contact Phone', 'reservations');?></label></th>
            <td>
                <input type="tel" name="instructor_meta[contact_phone]" id="instructor-phone" value="<?=$values["contact_phone"]?>" class="regular-text" />
            </td>
        </tr>
        <?php if ($this->cityAclEnabled()): ?>
            <tr>
                <th><label for="instructor-accessible-cities"><?php _e('Accessible Cities', 'reservations');?></label></th>
                <td>
                    <ul id="instructor-accessible-cities" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-empty-text="<?=esc_attr(__('No selected cities. All cities are accessible.', 'reservations'))?>">
                        <?php if (!count($accessibleCities)): ?>
                            <li class="res-empty"><?php _e('No selected cities. All cities are accessible.', 'reservations')?></li>
                        <?php endif;?>

                        <?php foreach ($accessibleCities as $city): ?>
                            <li data-id="<?=esc_attr($city->id)?>" data-name="<?=esc_attr($city->name)?>"><?=esc_html($city->name)?><?php if ($citiesEditable): ?> <a href="#" class="delete"><?php _e('Delete', 'reservations');?></a><?php endif;?></li>
                        <?php endforeach;?>
                    </ul>
                    <?php if ($citiesEditable): ?>
                        <label for="instructor-accessible-cities-add"><?php _e('Add City:', 'reservations');?></label>
                        <select class="res-list-add" id="instructor-accessible-cities-add">
                            <option value=""><?php _e('&mdash; Select &mdash;', 'reservations');?>
                            <?php foreach ($availableCities as $city): ?>
                                <option value="<?=esc_attr($city->term_id)?>"><?=esc_html($city->name)?></option>
                            <?php endforeach;?>
                        </select><br>
                    <?php endif;?>

                    <input type="hidden" name="instructor_meta[accessible_cities]" id="instructor-accessible-cities-input" value="<?=$accessibleCityIds->implode(",")?>">
                </td>
            </tr>
        <?php endif;?>
        </table>
    <?php
}

    /**
     * @action(personal_options_update)
     * @action(edit_user_profile_update)
     */
    public function saveProfile($userId)
    {
        if (current_user_can("subscriber") || !current_user_can('edit_user', $userId)) {
            return false;
        }

        if (!isset($_POST["instructor_meta"])) {
            return false;
        }

        $meta = $_POST["instructor_meta"];

        if (!Utils::allSet($meta, [
            "nickname", "experience", "contact_email", "contact_phone",
        ])) {
            return false;
        }

        if ($this->cityAclEnabled() && !isset($meta["accessible_cities"])) {
            return false;
        }

        $userModel = Models\User::find($userId);

        $userModel->setPrefixedMetaBulk([
            "is_instructor" => isset($meta["is_instructor"]),
            "nickname"      => sanitize_text_field($meta["nickname"]),
            "experience"    => sanitize_text_field($meta["experience"]),
            "contact_email" => sanitize_email($meta["contact_email"]),
            "contact_phone" => sanitize_text_field($meta["contact_phone"]),
        ]);

        if ($this->cityAclEnabled() && current_user_can("administrator")) {
            $accessible_city_ids = [];
            foreach (explode(",", $meta["accessible_cities"]) as $id) {
                $id = (int) $id;

                if (in_array($id, $accessible_city_ids)) {
                    continue;
                }

                if (Models\City::find($id)) {
                    $accessible_city_ids[] = $id;
                }
            }

            $userModel->setPrefixedMeta("accessible_city_ids", $accessible_city_ids);
        }
    }

    protected function cityAclEnabled()
    {
        return $this->plugin->isFeatureEnabled("city_acl");
    }
}
