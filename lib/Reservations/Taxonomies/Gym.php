<?php

namespace Reservations\Taxonomies;

use Carbon\Carbon;
use KamranAhmed\Geocode\Geocode;
use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\PostTypes;
use Reservations\Utils;

class Gym extends Base\Taxonomy
{
    const NAME = "gym";

    /** @action(init) */
    public function register()
    {
        register_taxonomy(self::NAME, PostTypes\Training::NAME, [
            "labels"             => [
                'name'                       => _x('Gyms', 'taxonomy general name', 'reservations'),
                'singular_name'              => _x('Gym', 'taxonomy singular name', 'reservations'),
                'search_items'               => __('Search Gyms', 'reservations'),
                'all_items'                  => __('All Gyms', 'reservations'),
                'edit_item'                  => __('Edit Gym', 'reservations'),
                'update_item'                => __('Update Gym', 'reservations'),
                'add_new_item'               => __('Add New Gym', 'reservations'),
                'new_item_name'              => __('New Gym Name', 'reservations'),
                'separate_items_with_commas' => __('Separate gyms with commas', 'reservations'),
                'add_or_remove_items'        => __('Add or remove gyms', 'reservations'),
                'choose_from_most_used'      => __('Choose from the most used gyms', 'reservations'),
                'not_found'                  => __('No gyms found', 'reservations'),
                'no_terms'                   => __('No gyms', 'reservations'),
            ],
            "show_in_quick_edit" => false,
            "meta_box_cb"        => false,
        ]);
    }

    /**
     * @action(gym_add_form)
     * @action(gym_edit_form)
     */
    public function removeDescriptionTextBox()
    {
        echo '<style type="text/css">
            .term-description-wrap { display: none; }
            .wpcustom-category-form-field { display: none; }
        </style>';
    }

    /**
     * @action(manage_edit-gym_columns)
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

    /** @action(gym_add_form_fields) */
    public function addFormFields()
    {
        $cities     = Models\City::accessible()->get();
        $citySelect = Utils\Html::getCitySelect($cities);
        ?>
        <div class="form-field gym-address-wrap">
            <label for="gym-address"><?php _e('Address', 'reservations');?></label>
            <textarea name="gym_meta[address]" id="gym-address" rows="3"></textarea>
        </div>
        <div class="form-field gym-city-id-wrap">
            <label for="gym-city-id"><?php _e('City', 'reservations');?></label>
            <select name="gym_meta[city_id]" id="gym-city-id">
                <option><?php _e('&mdash; Select &mdash;', 'reservations');?>
                <?=$citySelect?>
            </select>
        </div>
        <div class="form-field gym-latlng-wrap">
            <label for="gym-latlng"><?php _ex('Location', 'coordinates', 'reservations');?></label>
            <input type="text" name="gym_meta[lat]" id="gym-lat" placeholder="<?php _e('Latitude', 'reservations');?>">
            <input type="text" name="gym_meta[lng]" id="gym-lng" placeholder="<?php _e('Longitude', 'reservations');?>">
            <p class="description"><?php _e('If left empty, location will be determined from address', 'reservations');?></p>
        </div>
        <div class="form-field">
            <label for="custom-subscribe-url"><?php _e('Custom Subscribe URL', 'reservations'); ?></label>
            <input type="url" id="custom-subscribe-url" name="gym_meta[custom_subscribe_url]">
            <p class="description"><?php _e('Redirects the user to the specified page instead of showing the schedule.', 'reservations'); ?></p>
        </div>
        <?php
}

    /** @action(gym_edit_form_fields) */
    public function editFormFields($gymTerm)
    {
        $gym = Models\Gym::find($gymTerm->term_id);

        $meta = $gym->getPrefixedMetaBulk([
            "address", "lat", "lng", "city_id", "capacity", "biannual_enable", "price_biannual", "monthly_enable", "price_monthly", "price_single", "password", "custom_subscribe_url",
        ]);

        $values = [
            "address"      => esc_html($meta["address"]),
            "lat"          => esc_attr($meta["lat"]),
            "lng"          => esc_attr($meta["lng"]),
            "capacity"     => esc_attr($meta["capacity"]),
            "password"     => esc_attr($meta["password"]),
            "custom_subscribe_url" => esc_attr($meta["custom_subscribe_url"]),
            "price_single" => [],
            "term_periods" => [],
        ];

        if(is_array($meta["price_single"])) {
            foreach ($meta["price_single"] as $id => $price) {
                $values["price_single"][$id] = esc_attr($price);
            }
        }

        foreach ($gym->termPeriods as $period) {
            $values["term_periods"][] = [
                $period[0]->format("Y-m-d"),
                $period[1]->format("Y-m-d"),
            ];
        }

        $cities     = Models\City::accessible()->get();
        $citySelect = Utils\Html::getCitySelect($cities, $meta["city_id"]);

        $age_groups = $ageGroups = Utils::getAgeGroupsFlat();

        $attachmentSets = $gym->attachmentSets;

        if (!count($attachmentSets)) {
            $attachmentSets[] = [];
        }

        $paymentTemplates = $gym->paymentTemplates;

        $paymentTemplatesBiannual = [];
        $paymentTemplatesMonthly  = [];

        foreach ($ageGroups as $group) {
            $paymentTemplatesBiannual[$group["id"]] = [];
            $paymentTemplatesMonthly[$group["id"]]  = [];

            foreach ($paymentTemplates as $template) {
                if (in_array($group["id"], $template["age_groups"]) && in_array("biannual", $template["subscription_types"])) {
                    $paymentTemplatesBiannual[$group["id"]][] = $template;
                }

                if (in_array($group["id"], $template["age_groups"]) && in_array("monthly", $template["subscription_types"])) {
                    $paymentTemplatesMonthly[$group["id"]][] = $template;
                }
            }

            if (!count($paymentTemplatesBiannual[$group["id"]])) {
                $paymentTemplatesBiannual[$group["id"]][] = [
                    "id"     => 0,
                    "amount" => 0,
                    "hash"   => "",
                ];
            }

            if (!count($paymentTemplatesMonthly[$group["id"]])) {
                $paymentTemplatesMonthly[$group["id"]][] = [
                    "id"     => 0,
                    "amount" => 0,
                    "hash"   => "",
                ];
            }
        }

        foreach ($attachmentSets as $i => $files) {
            $attachmentSets[$i] = Utils::resolveAttachmentIds($files);
        }

        $attachmentSetsPairs = [];
        foreach ($attachmentSets as $i => $files) {
            $attachmentSetsPairs[$i] = sprintf(__('Attachment Set #%s', 'reservations'), $i + 1);
        }

        $messageTemplates      = Models\Local\MessageTemplate::all();
        $messageTemplatesPairs = collect($messageTemplates)->pluck("name")->toArray();

        ?>
        <tr class="form-field gym-address-wrap">
            <th scope="row"><label for="gym-address"><?php _e('Address', 'reservations');?></label></th>
            <td><textarea name="gym_meta[address]" id="gym-address" rows="3" aria-required="true"><?=$values["address"]?></textarea></td>
        </tr>
        <tr class="form-field gym-city-id-wrap">
            <th scope="row"><label for="gym-city-id"><?php _e('City', 'reservations');?></label></th>
            <td><select name="gym_meta[city_id]" id="gym-city-id">
                <option><?php _e('&mdash; Select &mdash;', 'reservations');?>
                <?=$citySelect?>
            </select></td>
        </tr>
        <tr class="form-field gym-latlng-wrap">
            <th scope="row"><label for="gym-latlng"><?php _ex('Location', 'coordinates', 'reservations');?></label></th>
            <td><input type="text" name="gym_meta[lat]" id="gym-lat" value="<?=$values["lat"]?>" placeholder="<?php _e('Latitude', 'reservations');?>">
            <input type="text" name="gym_meta[lng]" id="gym-lng" value="<?=$values["lng"]?>" placeholder="<?php _e('Longitude', 'reservations');?>">
            <p class="description"><?php _e('If left empty, location will be determined from name', 'reservations');?></p></td>
        </tr>
        <tr class="form-field">
            <th scope="row"><label for="custom-subscribe-url"><?php _e('Custom Subscribe URL', 'reservations'); ?></label></th>
            <td><input type="url" id="custom-subscribe-url" name="gym_meta[custom_subscribe_url]" value="<?=$values["custom_subscribe_url"]?>">
            <p class="description"><?php _e('Redirects the user to the specified page instead of showing the schedule.', 'reservations'); ?></p></td>
        </div>

        <?php if (Reservations::DEBUG): ?>
        <tr>
            <td colspan="2"><h3 style="text-align: center;font-weight:bold;color:red;"><?php _e('The following fields are read only and show old data.', 'reservations');?></td>
        </tr>
        <tr class="form-field gym-capacity-wrap">
            <th scope="row"><label for="gym-capacity"><?php _e('Capacity', 'reservations');?></label></th>
            <td><input type="number" name="gym_meta[capacity]" id="gym-capacity" min="0" step="1" value="<?=$values["capacity"]?>"></td>
        </tr>

        <tr class="form-field gym-biannual-enable-wrap">
            <th scope="row"><label for="gym-biannual-enable"><?php _e('Enable biannual subscription', 'reservations');?></label></th>
            <td><input type="checkbox" name="gym_meta[biannual_enable]" id="gym-biannual-enable"<?php checked($meta["biannual_enable"]);?>></td>
        </tr>
        <tr class="form-field gym-price-monthly-enable-wrap">
            <th scope="row"><label for="gym-monthly-enable"><?php _e('Enable monthly subscription', 'reservations');?></label></th>
            <td><input type="checkbox" name="gym_meta[monthly_enable]" id="gym-monthly-enable"<?php checked($meta["monthly_enable"]);?>></td>
        </tr>
        <tr class="form-field gym-price-wrap gym-price-biannual-wrap event-price-wrap">
            <th scope="row"><label for=""><?php _e('Price of biannual subscription', 'reservations');?></label></th>

            <td>
                <ul class="res-price-tabs res-tabs">
                    <?php foreach ($ageGroups as $i => $group): ?>
                        <li<?php if ($i === 0): ?> class="res-tab-active"<?php endif;?>><a href="#"><?=esc_html($group["label"])?></a></li>
                    <?php endforeach;?>
                </ul>

                <?php foreach ($ageGroups as $j => $group): ?>
                    <table class="event-price<?php if ($j === 0): ?> res-active<?php endif;?>">
                        <thead><tr>
                            <th><?php _e('Notification Email Advance', 'reservations');?></th>
                            <th><?php _e('Notification Email Template', 'reservations');?></th>
                            <th><?php _e('Notification Email Attachment Set', 'reservations');?></th>
                            <th><?php _e('Amount', 'reservations');?></th>
                            <th><?php _e('Confirmation Email Template', 'reservations');?></th>
                            <th><?php _e('Confirmation Email Attachment Set', 'reservations');?></th>
                            <th></th>
                        </tr></thead>

                        <tbody>
                            <?php foreach ($paymentTemplatesBiannual[$group["id"]] as $i => $template): ?>
                                <tr>
                                    <?php if ($i === 0): ?>
                                        <td colspan="3" class="res-initial-payment">
                                            <?php _e('&mdash; Initial Payment &mdash;', 'reservations');?>
                                            <input type="hidden" name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][initial]" value="1">
                                        </td>
                                    <?php else: ?>
                                        <td><input type="number" name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][advance]" class="res-inline" min="0" step="1" value="<?=esc_attr($template["advance"])?>"> <?php _e('days', 'reservations');?></td>
                                        <td><select name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][notification_email_template]">
                                            <?=Utils\Html::getSelect($messageTemplatesPairs, $template["notification_email_template"])?>
                                        </select></td>
                                        <td><select name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][notification_email_attachment_set]">
                                            <option value=""><?php _e('None', 'reservations');?></option>
                                            <?=Utils\Html::getSelect($attachmentSetsPairs, $template["notification_email_attachment_set"])?>
                                        </select></td>
                                    <?php endif;?>

                                    <td><input type="number" name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][amount]" class="res-inline" min="0" step="1" value="<?=esc_attr($template["amount"])?>">&nbsp;<?php _e('US$', 'reservations');?></td>
                                    <td><select name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][confirmation_email_template]">
                                        <?=Utils\Html::getSelect($messageTemplatesPairs, $template["confirmation_email_template"])?>
                                    </select></td>
                                    <td><select name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][confirmation_email_attachment_set]">
                                        <option value=""><?php _e('None', 'reservations');?></option>
                                        <?=Utils\Html::getSelect($attachmentSetsPairs, $template["confirmation_email_attachment_set"])?>
                                    </select><input type="hidden" name="gym_meta[payments_biannual][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][hash]" value="<?=esc_attr($template["hash"])?>"></td>
                                    <td>
                                        <?php if ($i !== 0): ?>
                                            <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                                        <?php endif;?>
                                    </td>
                                </tr>
                            <?php endforeach;?>

                            <tr class="res-template">
                                <td><input type="number" name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][advance]" class="res-inline" min="0" step="1" value="0"> <?php _e('days', 'reservations');?></td>
                                <td><select name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][notification_email_template]">
                                    <?=Utils\Html::getSelect($messageTemplatesPairs, -1)?>
                                </select></td>
                                <td><select name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][notification_email_attachment_set]">
                                    <option value=""><?php _e('None', 'reservations');?></option>
                                    <?=Utils\Html::getSelect($attachmentSetsPairs, -1)?>
                                </select></td>
                                <td><input type="number" name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][amount]" class="res-inline" min="0" step="1" value="0">&nbsp;<?php _e('US$', 'reservations');?></td>
                                <td><select name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][confirmation_email_template]">
                                    <?=Utils\Html::getSelect($messageTemplatesPairs, -1)?>
                                </select></td>
                                <td><select name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][confirmation_email_attachment_set]">
                                    <option value=""><?php _e('None', 'reservations');?></option>
                                    <?=Utils\Html::getSelect($attachmentSetsPairs, -1)?>
                                </select><input type="hidden" name="gym_meta[payments_biannual_tpl][<?=esc_attr($group["id"])?>][][hash]" value=""></td>
                                <td>
                                    <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php endforeach;?>
                <button class="button res-add-payment" type="button"><?php _e('Add payment', 'reservations');?></button>

                <p class="description">
                    <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations');?>
                </p>
            </td>
        </tr>

        <tr class="form-field gym-price-wrap gym-price-monthly-wrap event-price-wrap">
            <th scope="row"><label for=""><?php _e('Price of monthly subscription', 'reservations');?></label></th>

            <td>
                <ul class="res-price-tabs res-tabs">
                    <?php foreach ($ageGroups as $i => $group): ?>
                        <li<?php if ($i === 0): ?> class="res-tab-active"<?php endif;?>><a href="#"><?=esc_html($group["label"])?></a></li>
                    <?php endforeach;?>
                </ul>

                <?php foreach ($ageGroups as $j => $group): ?>
                    <table class="event-price<?php if ($j === 0): ?> res-active<?php endif;?>">
                        <thead><tr>
                            <th><?php _e('Notification Email Advance', 'reservations');?></th>
                            <th><?php _e('Notification Email Template', 'reservations');?></th>
                            <th><?php _e('Notification Email Attachment Set', 'reservations');?></th>
                            <th><?php _e('Monthly Amount', 'reservations');?></th>
                            <th><?php _e('Confirmation Email Template', 'reservations');?></th>
                            <th><?php _e('Confirmation Email Attachment Set', 'reservations');?></th>
                            <th></th>
                        </tr></thead>

                        <tbody>
                            <?php foreach ($paymentTemplatesMonthly[$group["id"]] as $i => $template): ?>
                                <tr>
                                    <?php if ($i === 0): ?>
                                        <td colspan="3" class="res-initial-payment">
                                            <?php _e('&mdash; Initial Payment &mdash;', 'reservations');?>
                                            <input type="hidden" name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][initial]" value="1">
                                        </td>
                                    <?php else: ?>
                                        <td><input type="number" name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][advance]" class="res-inline" min="0" step="1" value="<?=esc_attr($template["advance"])?>"> <?php _e('days', 'reservations');?></td>
                                        <td><select name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][notification_email_template]">
                                            <?=Utils\Html::getSelect($messageTemplatesPairs, $template["notification_email_template"])?>
                                        </select></td>
                                        <td><select name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][notification_email_attachment_set]">
                                            <option value=""><?php _e('None', 'reservations');?></option>
                                            <?=Utils\Html::getSelect($attachmentSetsPairs, $template["notification_email_attachment_set"])?>
                                        </select></td>
                                    <?php endif;?>

                                    <td><input type="number" name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][amount_monthly]" class="res-inline" min="0" step="1" value="<?=esc_attr($template["amount_monthly"])?>">&nbsp;<?php _e('US$', 'reservations');?></td>
                                    <td><select name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][confirmation_email_template]">
                                        <?=Utils\Html::getSelect($messageTemplatesPairs, $template["confirmation_email_template"])?>
                                    </select></td>
                                    <td><select name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][confirmation_email_attachment_set]">
                                        <option value=""><?php _e('None', 'reservations');?></option>
                                        <?=Utils\Html::getSelect($attachmentSetsPairs, $template["confirmation_email_attachment_set"])?>
                                    </select><input type="hidden" name="gym_meta[payments_monthly][<?=esc_attr($group["id"])?>][<?=esc_attr($i)?>][hash]" value="<?=esc_attr($template["hash"])?>"></td>
                                    <td>
                                        <?php if ($i !== 0): ?>
                                            <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                                        <?php endif;?>
                                    </td>
                                </tr>
                            <?php endforeach;?>

                            <tr class="res-template">
                                <td><input type="number" name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][advance]" class="res-inline" min="0" step="1" value="0"> <?php _e('days', 'reservations');?></td>
                                <td><select name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][notification_email_template]">
                                    <?=Utils\Html::getSelect($messageTemplatesPairs, -1)?>
                                </select></td>
                                <td><select name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][notification_email_attachment_set]">
                                    <option value=""><?php _e('None', 'reservations');?></option>
                                    <?=Utils\Html::getSelect($attachmentSetsPairs, -1)?>
                                </select></td>
                                <td><input type="number" name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][amount_monthly]" class="res-inline" min="0" step="1" value="0">&nbsp;<?php _e('US$', 'reservations');?></td>
                                <td><select name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][confirmation_email_template]">
                                    <?=Utils\Html::getSelect($messageTemplatesPairs, -1)?>
                                </select></td>
                                <td><select name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][confirmation_email_attachment_set]">
                                    <option value=""><?php _e('None', 'reservations');?></option>
                                    <?=Utils\Html::getSelect($attachmentSetsPairs, -1)?>
                                </select><input type="hidden" name="gym_meta[payments_monthly_tpl][<?=esc_attr($group["id"])?>][][hash]" value=""></td>
                                <td>
                                    <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                <?php endforeach;?>
                <button class="button res-add-payment" type="button"><?php _e('Add payment', 'reservations');?></button>

                <p class="description">
                    <?php _e('Notification Email Advance &ndash; how many days after subscribing should the notification email be sent', 'reservations');?>
                </p>
            </td>
        </tr>

        <tr class="form-field event-attachment-sets-wrap">
            <th scope="row"><label for="event-files"><?php _e('Attachment Sets', 'reservations');?></label></th>

            <td>
                <?php foreach ($attachmentSets as $i => $files): ?>
                    <div class="res-attachment-set">
                        <h4><?=sprintf(__('Attachment Set #%s', 'reservations'), $i + 1)?>
                            <?php if ($i !== 0): ?>
                                <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                            <?php endif;?>
                        </h4>
                        <ul class="res-files" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-files-text="<?=esc_attr(__('No files.', 'reservations'))?>">
                            <?php if (!count($files)): ?>
                                <li class="no-files"><?php _e('No files.', 'reservations')?></li>
                            <?php endif;?>

                            <?php foreach ($files as $id => $filename): ?>
                                <li data-id="<?=esc_attr($id)?>"><?=esc_html($filename)?> <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a></li>
                            <?php endforeach;?>
                        </ul>
                        <button class="button res-add-file" type="button" data-uploader-title="<?=esc_attr(__('Select files', 'reservations'))?>"><?php _e('Add file', 'reservations');?></button>
                        <input type="hidden" name="gym_meta[attachment_sets][<?=esc_attr($i)?>]" value="<?=implode(",", array_keys($files))?>">
                    </div>
                <?php endforeach;?>

                <div class="res-attachment-set res-template">
                    <h4><?php _e('Attachment Set #%s', 'reservations')?> <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a></h4>
                    <ul class="res-files" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-files-text="<?=esc_attr(__('No files.', 'reservations'))?>">
                        <li class="no-files"><?php _e('No files.', 'reservations')?></li>
                    </ul>
                    <button class="button res-add-file" type="button" data-uploader-title="<?php esc_attr_e('Select files', 'reservations');?>"><?php _e('Add file', 'reservations');?></button>
                    <input type="hidden" name="gym_meta[attachment_sets_tpl][]" value="">
                </div>

                <button class="button res-add-set" type="button"><?php _e('Add attachment set', 'reservations');?></button>
            </td>
        </tr>
        <tr class="form-field gym-price-single-wrap">
            <th scope="row"><label for="gym-price-single"><?php _e('Default price of single training (no subscription)', 'reservations');?></label></th>
            <td><?php foreach ($age_groups as $group): ?>
                <?=$group["label"]?>: <input type="number" name="gym_meta[price_single][<?=$group["id"]?>]" class="gym-price-single res-inline" min="0" step="1" value="<?=$values["price_single"][$group["id"]]?>"> <?php _e('US$', 'reservations');?><br>
            <?php endforeach;?></td>
        </tr>
        <tr class="form-field gym-term-1-period-wrap">
            <th scope="row"><label for="gym-term-1-period-start"><?php _e('Start and end date of 1. term', 'reservations');?></label></th>
            <td><input type="date" class="res-inline" name="gym_meta[term_periods][0][0]" id="gym-term-1-period-start" value="<?=$values["term_periods"][0][0]?>">
            &ndash;
            <input type="date" class="res-inline" name="gym_meta[term_periods][0][1]" id="gym-term-1-period-end" value="<?=$values["term_periods"][0][1]?>"></td>
        </tr>
        <tr class="form-field gym-term-2-period-wrap">
            <th scope="row"><label for="gym-term-2-period-start"><?php _e('Start and end date of 2. term', 'reservations');?></label></th>
            <td><input type="date" class="res-inline" name="gym_meta[term_periods][1][0]" id="gym-term-2-period-start" value="<?=$values["term_periods"][1][0]?>">
            &ndash;
            <input type="date" class="res-inline" name="gym_meta[term_periods][1][1]" id="gym-term-2-period-end" value="<?=$values["term_periods"][1][1]?>"></td>
        </tr>

        <tr class="form-field gym-password-wrap">
            <th scope="row"><label for="gym-password"><?php _e('Access Password', 'reservations');?></label></th>
            <td><input type="text" name="gym_meta[password]" id="gym-password" value="<?=$values["password"];?>">
            <p class="description"><?php _e('If left empty, access will be unrestricted', 'reservations');?></p></td>
        </tr>
        <?php endif;?>
        <?php
}

    /**
     * @action(create_gym)
     * @action(edit_gym)
     */
    public function saveGym($gymId, $taxonomyId)
    {
        if (!isset($_POST["gym_meta"])) {
            return;
        }

        $meta = $_POST["gym_meta"];

        if (!Utils::allSet($meta, ["city_id", "custom_subscribe_url"])) {
            return;
        }

        $gym = Models\Gym::find($gymId);

        $cityId    = $gym->cityId;
        $newCityId = (int) sanitize_text_field($meta["city_id"]);

        if ($cityId !== $newCityId) {
            foreach (Models\Training::inGym($gymId)->get() as $training) {
                $training->setCity($newCityId ? $newCityId : null);
            }
        }

        $gym->setPrefixedMetaBulk([
            "address" => sanitize_textarea_field($meta["address"]),
            "city_id" => $newCityId,
            "custom_subscribe_url" => sanitize_text_field($meta["custom_subscribe_url"]),
        ]);

        if (empty($meta["lat"]) && empty($meta["lon"]) && !empty($meta["address"])) {
            $geocoder = new Geocode($this->plugin->getOption("google_maps_api_key"));
            $location = $geocoder->get($meta["address"]);

            $gym->setPrefixedMetaBulk([
                "lat" => $location->getLatitude(),
                "lng" => $location->getLongitude(),
            ]);
        } else {
            $gym->setPrefixedMetaBulk([
                "lat" => sanitize_text_field($meta["lat"]),
                "lng" => sanitize_text_field($meta["lng"]),
            ]);
        }
    }

    public function displayFilters()
    {
        $cities  = Models\City::accessible()->get();
        $current = isset($_GET['gym_filter_city']) ? (int) $_GET['gym_filter_city'] : "";

        ?>
        <select name="gym_filter_city">
            <option value=""><?php _e('&mdash; City &mdash;', 'reservations');?></option>

            <?=Utils\Html::getCitySelect($cities, $current)?>
        </select>
        <input type="submit" name="filter_action" id="term-query-submit" class="button" value="<?php esc_attr_e('Filter');?>" formmethod="get">
        <?php
}

    public function applyFilters($args)
    {
        if (isset($_GET['gym_filter_city']) && !empty($_GET['gym_filter_city'])) {
            $args["meta_query"] = [
                [
                    "key"   => $this->plugin->prefix("city_id"),
                    "value" => (int) $_GET['gym_filter_city'],
                ],
            ];
        }

        return $args;
    }

    public function registerRowActions($rowActions, $term)
    {
        if (!current_user_can("edit_term", $term->term_id)) {
            return $rowActions;
        }

        $gym = Models\Gym::find((int) $term->term_id);

        $trainingCount = $gym->trainings()->count();

        if ($trainingCount > 0) {
            unset($rowActions["delete"]);

            $rowActions["merge"] = '<a href="' . admin_url("edit-tags.php?taxonomy=" . self::NAME . "&post_type=" . PostTypes\Training::NAME . "&page=" . $this->plugin->slug("-merge-gym") . "&gym_id=" . $gym->id) . '">' . __('Merge', 'reservations') . '</a>';
        }

        return $rowActions;
    }

    public function beforeDelete($termId)
    {
        $gym = Models\Gym::find((int) $termId);

        if (!$gym) {
            return;
        }

        $trainingCount = $gym->trainings()->count();

        if ($trainingCount > 0) {
            $message = '<p><strong>' . sprintf(__('An error occured during the deletion of gym %s', 'reservations'), esc_html($gym->name)) . '</strong></p>';
            $message .= '<p>' . __('The following are causes of this error:', 'reservations') . '</p><ul>';

            if ($trainingCount > 0) {
                $message .= '<li>' . sprintf(_n('This gym is used by %d training.', 'This gym is used by %d trainings.', $trainingCount, 'reservations'), $trainingCount) . '</li>';
            }

            $message .= '</ul>';

            wp_die($message);
        }
    }

}
