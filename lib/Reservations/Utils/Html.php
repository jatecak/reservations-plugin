<?php

namespace Reservations\Utils;

use Nette\Utils\Strings;
use Reservations;
use Reservations\Models;
use Reservations\Utils;

class Html
{
    public static function getSelect($pairs, $selected = null)
    {
        $out = "";
        foreach ($pairs as $key => $value) {
            $s = !is_null($selected) ? selected($key, $selected, false) : "";
            $out .= '<option value="' . esc_attr($key) . '"' . $s . '>' . esc_html($value) . '</option>';
        }

        return $out;
    }

    public static function getSelectRecursive($pairs, $selected = null)
    {
        $inner = function ($pairs, $optgroup = null) use (&$inner, $selected) {
            $out = !is_null($optgroup) ? '<optgroup label="' . esc_attr($optgroup) . '">' : '';

            foreach ($pairs as $key => $value) {
                if (is_array($value)) {
                    $out .= $inner($value, $key);
                } else {
                    $s = !is_null($selected) ? selected($key, $selected, false) : "";
                    $out .= '<option value="' . esc_attr($key) . '"' . $s . '>' . esc_html($value) . '</option>';
                }
            }

            if (!is_null($optgroup)) {
                $out .= '</optgroup>';
            }

            return $out;
        };

        return $inner($pairs);
    }

    public static function getCitySelect($cities = null, $selected = null)
    {
        if (is_null($cities)) {
            $cities = Models\City::all();
        }

        return self::getSelect(Utils::makePairs($cities, "id", "name"), $selected);
    }

    public static function getTrainingGroupSelect($trainingGroups = null, $selected = null)
    {
        if (is_null($trainingGroups)) {
            $trainingGroups = Models\TrainingGroup::all();
        }

        return self::getSelect(Utils::makePairs($trainingGroups, "id", "name"), $selected);
    }

    public static function getEventSelect($events = null, $selected = null)
    {
        if (is_null($events)) {
            $events = Models\Event::all();
        }

        return self::getSelect(Utils::makePairs($cities, "id", "title"), $selected);
    }

    public static function getEventTreeSelect($events = null, $selected = null)
    {
        if (is_null($events)) {
            $events = Models\Event::all();
        }

        $tree = [];
        foreach ($events as $event) {
            $city = $event->city();

            $tree[$city->name][$event->ID] = $event->title;
        }

        return self::getSelectRecursive($tree, $selected);
    }

    public static function getEventTypeSelect($eventTypes = null, $selected = null)
    {
        if (is_null($eventTypes)) {
            $eventTypes = Models\Local\EventType::all();
        }

        return self::getSelect(Utils::makePairs($eventTypes, "id", "label"), $selected);
    }

    public static function getGymSelect($gyms = null, $selected = null)
    {
        if (is_null($gyms)) {
            $gyms = Models\Gym::all();
        }

        return self::getSelect(Utils::makePairs($gyms, "id", "name"), $selected);
    }

    public static function getGymTreeSelect($gyms = null, $selected = null)
    {
        if (is_null($gyms)) {
            $gyms = Models\Gym::all();
        }

        $tree = [];
        foreach ($gyms as $gym) {
            $city     = $gym->city;
            $cityName = $city ? $city->name : __('Without city', 'reservations');

            $tree[$cityName][$gym->id] = $gym->name;
        }

        ksort($tree);

        return self::getSelectRecursive($tree, $selected);
    }

    public static function getAgeGroupSelect($ageGroups = null, $selected = null)
    {
        if (is_null($ageGroups)) {
            $ageGroups = Models\Local\AgeGroup::all();
        }

        return self::getSelect(Utils::makePairs($ageGroups, "id", "label"), $selected);
    }

    public static function renderPaymentTemplatesEditor($inputName, $paymentTemplates, $attachmentSets, $monthly = false)
    {
        $attachmentSetsPairs = [];
        foreach ($attachmentSets as $i => $files) {
            $attachmentSetsPairs[$i] = sprintf(__('Attachment Set #%s', 'reservations'), $i + 1);
        }

        $messageTemplates      = Models\Local\MessageTemplate::all();
        $messageTemplatesPairs = collect($messageTemplates)->pluck("name")->toArray();

        $n  = esc_attr($inputName);
        $tn = esc_attr(Strings::replace($inputName, "/^(.+?)\\[/", "\$1_tpl[", 1));

        $amountKey = $monthly ? "amount_monthly" : "amount";

        $paymentTemplates = collect($paymentTemplates)->map(function ($tpl) {
            return Utils::defaults($tpl, [
                "hash"                              => "",
                "amount"                            => "0",
                "amount_monthly"                    => "0",
                "advance"                           => "0",
                "initial"                           => false,
                "notification_email_template"       => "",
                "notification_email_attachment_set" => "",
                "confirmation_email_template"       => "",
                "confirmation_email_attachment_set" => "",
            ]);
        })->all();

        $initialNotification = Reservations::instance()->isFeatureEnabled("initial_payment_notification");

        ?>
        <table class="event-price res-active">
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
                <?php foreach ($paymentTemplates as $i => $template): ?>
                    <tr>
                        <?php if ($i === 0): ?>
                            <?php if ($initialNotification): ?>
                                <td>
                                    <input type="hidden" name="<?=$n?>[<?=esc_attr($i)?>][initial]" value="1">
                                    <?php _e('(initial payment)', 'reservations');?>
                                </td>
                                <td><select name="<?=$n?>[<?=esc_attr($i)?>][notification_email_template]">
                                    <option value=""><?php _e('None', 'reservations');?></option>
                                    <?=self::getSelect($messageTemplatesPairs, $template["notification_email_template"])?>
                                </select></td>
                                <td><select name="<?=$n?>[<?=esc_attr($i)?>][notification_email_attachment_set]">
                                    <option value=""><?php _e('None', 'reservations');?></option>
                                    <?=self::getSelect($attachmentSetsPairs, $template["notification_email_attachment_set"])?>
                                </select></td>
                            <?php else: ?>
                                <td colspan="3" class="res-initial-payment">
                                    <input type="hidden" name="<?=$n?>[<?=esc_attr($i)?>][initial]" value="1">
                                    <?php _e('&mdash; Initial Payment &mdash;', 'reservations');?>
                                </td>
                            <?php endif;?>
                        <?php else: ?>
                            <td><input type="number" name="<?=$n?>[<?=esc_attr($i)?>][advance]" class="res-inline" min="0" step="1" value="<?=esc_attr($template["advance"])?>"> <?php _e('days', 'reservations');?></td>
                            <td><select name="<?=$n?>[<?=esc_attr($i)?>][notification_email_template]">
                                <?=self::getSelect($messageTemplatesPairs, $template["notification_email_template"])?>
                            </select></td>
                            <td><select name="<?=$n?>[<?=esc_attr($i)?>][notification_email_attachment_set]">
                                <option value=""><?php _e('None', 'reservations');?></option>
                                <?=self::getSelect($attachmentSetsPairs, $template["notification_email_attachment_set"])?>
                            </select></td>
                        <?php endif;?>

                        <td><input type="number" name="<?=$n?>[<?=esc_attr($i)?>][<?=esc_attr($amountKey)?>]" class="res-inline" min="0" step="1" value="<?=esc_attr($template[$amountKey])?>">&nbsp;<?php _e('US$', 'reservations');?></td>
                        <td><select name="<?=$n?>[<?=esc_attr($i)?>][confirmation_email_template]">
                            <?=self::getSelect($messageTemplatesPairs, $template["confirmation_email_template"])?>
                        </select></td>
                        <td><select name="<?=$n?>[<?=esc_attr($i)?>][confirmation_email_attachment_set]">
                            <option value=""><?php _e('None', 'reservations');?></option>
                            <?=self::getSelect($attachmentSetsPairs, $template["confirmation_email_attachment_set"])?>
                        </select><input type="hidden" name="<?=$n?>[<?=esc_attr($i)?>][hash]" value="<?=esc_attr($template["hash"])?>"></td>
                        <td>
                            <?php if ($i !== 0): ?>
                                <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                            <?php endif;?>
                        </td>
                    </tr>
                <?php endforeach;?>

                <tr class="res-template">
                    <td><input type="number" name="<?=$tn?>[][advance]" class="res-inline" min="0" step="1" value="0"> <?php _e('days', 'reservations');?></td>
                    <td><select name="<?=$tn?>[][notification_email_template]">
                        <?=self::getSelect($messageTemplatesPairs, -1)?>
                    </select></td>
                    <td><select name="<?=$tn?>[][notification_email_attachment_set]">
                        <option value=""><?php _e('None', 'reservations');?></option>
                        <?=self::getSelect($attachmentSetsPairs, -1)?>
                    </select></td>
                    <td><input type="number" name="<?=$tn?>[][amount]" class="res-inline" min="0" step="1" value="0">&nbsp;<?php _e('US$', 'reservations');?></td>
                    <td><select name="<?=$tn?>[][confirmation_email_template]">
                        <?=self::getSelect($messageTemplatesPairs, -1)?>
                    </select></td>
                    <td><select name="<?=$tn?>[][confirmation_email_attachment_set]">
                        <option value=""><?php _e('None', 'reservations');?></option>
                        <?=self::getSelect($attachmentSetsPairs, -1)?>
                    </select><input type="hidden" name="<?=$tn?>[][hash]" value=""></td>
                    <td>
                        <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a>
                    </td>
                </tr>
            </tbody>
        </table>

        <button class="button res-add-payment" type="button"><?php _e('Add payment', 'reservations');?></button>
        <?php
}

    public static function renderAttachmentSetsEditor($inputName, $attachmentSets)
    {
        foreach ($attachmentSets as $i => $files) {
            $attachmentSets[$i] = Utils::resolveAttachmentIds($files);
        }

        $n  = esc_attr($inputName);
        $tn = esc_attr(Strings::replace($inputName, "/^(.+?)\\[/", "\$1_tpl["));
        ?>

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
                <input type="hidden" name="<?=$n?>[<?=esc_attr($i)?>]" value="<?=implode(",", array_keys($files))?>">
            </div>
        <?php endforeach;?>

        <div class="res-attachment-set res-template">
            <h4><?php _e('Attachment Set #%s', 'reservations')?> <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a></h4>
            <ul class="res-files" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-files-text="<?=esc_attr(__('No files.', 'reservations'))?>">
                <li class="no-files"><?php _e('No files.', 'reservations')?></li>
            </ul>
            <button class="button res-add-file" type="button" data-uploader-title="<?php esc_attr_e('Select files', 'reservations');?>"><?php _e('Add file', 'reservations');?></button>
            <input type="hidden" name="<?=$tn?>[]" value="">
        </div>

        <button class="button res-add-set" type="button"><?php _e('Add attachment set', 'reservations');?></button>
        <?php
}
}
