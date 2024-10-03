<?php

namespace Reservations\Admin\Settings;

use Reservations\Mail\MessageTemplateTester;
use Reservations\Models;
use Reservations\Models\Local\EventType;
use Reservations\Models\Local\ObjectType;
use Reservations\Models\Local\SubscriptionType;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\Utils;

class MessageTemplates extends AbstractPlug
{
    public $name = "message_templates";

    public function prepare()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public function render()
    {
        $messageTemplates = Models\Local\MessageTemplate::all();

        if (!count($messageTemplates)) {
            $messageTemplates[] = [
                "name"                    => "",
                "attachments"             => [],
                "subject"                 => "",
                "body"                    => "",
                "attach_application_form" => false,
                "attach_invoice_if_paid" => false,
            ];
        }

        foreach ($messageTemplates as &$t) {
            $t["attachments"] = Utils::resolveAttachmentIds($t["attachments"]);
        }

        $subscriptionTypesPairs = Utils\Arrays::arrayToPairs(Models\Local\SubscriptionType::forObjectType(ObjectType::TRAININGS), TranslatableEnums::subscriptionTypesUcFirst());
        $messageTemplatesPairs  = collect(Models\Local\MessageTemplate::all())->pluck("name")->toArray();
        $defaultRecipient       = Models\User::current()->user_email;

        ?>
        <h2><?php _e('Email Templates', 'reservations');?></h2>
        <div class="res-message-templates">
            <?php foreach ($messageTemplates as $i => $template): ?>
                <?php $tName = $this->sName . "[" . $i . "]";?>
                <div class="res-message-template">
                    <h3><input type="text" name="<?=esc_attr($tName)?>[name]" value="<?=esc_attr($template["name"]);?>" size="40" placeholder="<?=esc_attr(sprintf(__('Template #%s', 'reservations'), $i + 1))?>"> <?php if ($i !== 0): ?><a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a><?php endif;?></h3>
                    <table class="form-table">
                        <tr>
                            <th scope="row"><?php _e('Subject Template', 'reservations');?></th>
                            <td><input type="text" size="50" name="<?=esc_attr($tName)?>[subject]" value="<?=esc_attr($template["subject"])?>"></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Body Template', 'reservations');?></th>
                            <td><?php wp_editor($template["body"], "template_" . $i . "_body", [
            "textarea_name" => $tName . "[body]",
            "textarea_rows" => 6,
            "media_buttons" => false,
        ]);?></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Attachments', 'reservations');?></th>
                            <td>
                                <label class="res-checkbox"><input type="checkbox" name="<?=esc_attr($tName)?>[attach_application_form]" value="1" <?php checked($template["attach_application_form"]);?>> <?php _e('Attach filled application form', 'reservations');?></label>
                                <label class="res-checkbox"><input type="checkbox" name="<?=esc_attr($tName)?>[attach_invoice_if_paid]" value="1" <?php checked($template["attach_invoice_if_paid"]);?>> <?php _e('Attach invoice PDF if subscription is paid', 'reservations');?></label>
                                <ul class="res-files" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-files-text="<?=esc_attr(__('No files.', 'reservations'))?>">
                                    <?php if (!count($template["attachments"])): ?>
                                        <li class="no-files"><?php _e('No files.', 'reservations')?></li>
                                    <?php endif;?>

                                    <?php foreach ($template["attachments"] as $id => $filename): ?>
                                        <li data-id="<?=esc_attr($id)?>"><?=esc_html($filename)?> <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a></li>
                                    <?php endforeach;?>
                                </ul>
                                <button class="button res-add-file" type="button" data-uploader-title="<?=esc_attr(__('Select files', 'reservations'))?>"><?php _e('Add file', 'reservations');?></button>
                                <input type="hidden" name="<?=esc_attr($tName)?>[attachments]" value="<?=implode(",", array_keys($template["attachments"]))?>">
                            </td>
                        </tr>
                    </table>
                </div>
            <?php endforeach;?>

            <?php $tName = $this->sName . "_tpl[]";?>
            <div class="res-message-template res-template">
                <h3><input type="text" size="40" name="<?=esc_attr($tName)?>[name]" value="" placeholder="<?=esc_attr(__('Template #%s', 'reservations'))?>"> <a href="#" class="res-delete"><?php _e('Delete', 'reservations');?></a></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row"><?php _e('Subject Template', 'reservations');?></th>
                        <td><input type="text" size="50" name="<?=esc_attr($tName)?>[subject]" value=""></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Body Template', 'reservations');?></th>
                        <td><textarea name="<?=esc_attr($tName)?>[body]" rows="6"></textarea></td>
                    </tr>
                    <tr>
                        <th scope="row"><?php _e('Attachments', 'reservations');?></th>
                        <td>
                            <label class="res-checkbox"><input type="checkbox" name="<?=esc_attr($tName)?>[attach_application_form]" value="1"> <?php _e('Attach filled application form', 'reservations');?></label>
                            <label class="res-checkbox"><input type="checkbox" name="<?=esc_attr($tName)?>[attach_invoice_if_paid]" value="1"> <?php _e('Attach invoice PDF if subscription is paid', 'reservations');?></label>
                            <ul class="res-files" data-delete-text="<?=esc_attr(__('Delete', 'reservations'))?>" data-no-files-text="<?=esc_attr(__('No files.', 'reservations'))?>">
                                <li class="no-files"><?php _e('No files.', 'reservations')?></li>
                            </ul>
                            <button class="button res-add-file" type="button" data-uploader-title="<?=esc_attr(__('Select files', 'reservations'))?>"><?php _e('Add file', 'reservations');?></button>
                            <input type="hidden" name="<?=esc_attr($tName)?>[attachments]" value="">
                        </td>
                    </tr>
                </table>
            </div>
            <button class="button res-add-template" type="button"><?php _e('Add email template', 'reservations');?></button>

            <div class="res-available-variables">
                <h3><?php _e('Available Variables', 'reservations');?></h3>

                <h4><?php _e('Common Attributes', 'reservations');?></h4>
                {{name}} &ndash; <?php _e('Name of the subscriber', 'reservations');?><br>
                {{repName}} &ndash; <?php _e('Name of the subscriber representative', 'reservations');?><br>
                {{contactEmail}} &ndash; <?php _e('Contact email address', 'reservations');?><br>
                {{dateFrom}} &ndash; <?php _e('Start date of the subscription or event', 'reservations');?><br>
                {{dateTo}} &ndash; <?php _e('End date of the subscription or event', 'reservations');?><br>
                {{daysRemaining}} &ndash; <?php _e('Remaining day count', 'reservations');?><br>
                {{daysRemainingSuff}} &ndash; <?php _e('Remaining day count with \'days\' suffix', 'reservations');?><br>
                {{monthsRemaining}} &ndash; <?php _e('Remaining month count', 'reservations');?><br>
                {{monthsRemainingSuff}} &ndash; <?php _e('Remaining month count with \'months\' suffix', 'reservations');?><br>
                {{subscriptionType}} &ndash; <?php _e('Subscription type (first letter uppercase)', 'reservations');?><br>
                {{subscriptionTypeLC}} &ndash; <?php _e('Subscription type (all lowercase)', 'reservations');?><br>
                {{city}} &ndash; <?php _e('Name of the city', 'reservations');?><br>
                {{address}} &ndash; <?php _e('Address of the gym or event', 'reservations');?>

                <h4><?php _e('Payment Attributes', 'reservations');?></h4>
                {{amount}} &ndash; <?php _e('Amount of this payment', 'reservations');?><br>
                {{totalAmount}} &ndash; <?php _e('Price of this event/subscription', 'reservations');?><br>
                {{paidAmount}} &ndash; <?php _e('Already paid amount of this event/subscription', 'reservations');?><br>
                {{toPayAmount}} &ndash; <?php _e('Amount left to pay of this event/subscription', 'reservations');?><br>
                {{payUrl}} &ndash; <?php _e('URL to status of this payment', 'reservations');?>

                <h4><?php _e('Training Attributes', 'reservations');?></h4>
                {{trainingGroup}} &ndash; <?php _e('Name of the training group', 'reservations');?><br>
                {{ageGroup}} &ndash; <?php _e('Age group (first letter uppercase)', 'reservations');?><br>
                {{ageGroupLC}} &ndash; <?php _e('Age group (all lowercase)', 'reservations');?><br>
                {{gym}} &ndash; <?php _e('Gym', 'reservations');?><br>
                {{gymWithCity}} &ndash; <?php _e('Gym with city (separated by comma)', 'reservations');?><br>
                {{gymWithAddress}} &ndash; <?php _e('Gym with full address', 'reservations');?><br>
                {{subscribedTrainings}} &ndash; <?php _e('List of trainings (see edit screen or subscription form)', 'reservations');?>

                <h4><?php _e('Event Attributes', 'reservations');?></h4>
                {{event}} &ndash; <?php _e('Name of the event', 'reservations');?><br>
                {{eventType}} &ndash; <?php _e('Event type (first letter uppercase)', 'reservations');?><br>
                {{eventTypeLC}} &ndash; <?php _e('Event type (all lowercase)', 'reservations');?>

                <h4><?php _e('Other Attibutes', 'reservations');?></h4>
                {{subscribeUrl}} &ndash; <?php _e('URL to prefilled registration form', 'reservations');?><br>
                {{username}} &ndash; <?php _e('Account username (available only in account created email)', 'reservations');?><br>
                {{password}} &ndash; <?php _e('Account password (available only in account created email)', 'reservations');?>
            </div>

            <?php if ($this->plugin->isFeatureEnabled("test_message")): ?>
                <div class="res-test-message">
                    <h3><?php _e('Test Message Template', 'reservations');?></h3>

                    <table class="form-table"><tbody>
                        <tr>
                            <th scope="row"><?php _e('Message Template', 'reservations');?></th>
                            <td><select name="test_message[template]">
                                <?=Utils\Html::getSelect($messageTemplatesPairs)?>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Subscription Type', 'reservations');?></th>
                            <td><select name="test_message[subscription_type]">
                                <optgroup label="<?php _e('Trainings', 'reservations');?>">
                                    <?=Utils\Html::getSelect($subscriptionTypesPairs)?>
                                </optgroup>
                                <optgroup label="<?php _e('Events', 'reservations');?>">
                                    <?=Utils\Html::getEventTypeSelect()?>
                                </optgroup>
                            </select></td>
                        </tr>
                        <tr>
                            <th scope="row"><?php _e('Recipient', 'reservations');?></th>
                            <td><input type="email" name="test_message[recipient]" size="35" value="<?=esc_attr($defaultRecipient)?>"></td>
                        </tr>
                    </tbody></table>

                    <button class="button" type="button" data-type="submit" name="submit" value="test_message"><?php _e('Save and send testing email', 'reservations');?></button>
                </div>
            <?php endif;?>
        </div>
        <?php
}

    public function sanitize($templates)
    {
        if (!is_array($templates)) {
            return [];
        }

        foreach ($templates as $i => &$template) {
            $template = Utils::defaults($template, [
                "body"        => "",
                "subject"     => "",
                "name"        => "",
                "attachments" => "",
            ]);

            if (is_array($template["attachments"])) {
                $attachments = $template["attachments"];
            } else {
                $attachments = explode(",", $template["attachments"]);
            }

            $template["attachments"] = array_keys(Utils::resolveAttachmentIds($attachments));

            $template["attach_application_form"] = !empty($template["attach_application_form"]);
            $template["attach_invoice_if_paid"] = !empty($template["attach_invoice_if_paid"]);

            if (empty($template["name"])) {
                $template["name"] = sprintf(__('Template #%s', 'reservations'), $i + 1);
            }
        }

        return $templates;
    }

    /** @action(admin_notices) */
    public function displayAdminNotice()
    {
        $status = $_SESSION['test_message_status'] ?? null;
        unset($_SESSION['test_message_status']);

        if ($status === 0) {
            ?>
            <div class="notice notice-success is-dismissible">
                <p><?php _e('Test email successfully sent', 'reservations');?></p>
            </div>
            <?php
} else if ($status === 1) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Invalid recipient', 'reservations');?></p>
            </div>
            <?php
} else if ($status === 2) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('No suitable objects found', 'reservations');?></p>
            </div>
            <?php
} else if ($status === 3) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Test email couldn\'t be sent', 'reservations');?></p>
            </div>
            <?php
}
    }

    public function afterSave($oldValue, $value)
    {
        if (!$this->plugin->isFeatureEnabled("test_message")) {
            return;
        }

        if (!isset($_POST['submit']) || $_POST['submit'] !== "test_message" || !isset($_POST['test_message'])) {
            return;
        }

        $values = (array) $_POST['test_message'];

        if (!Utils::allSet($values, [
            "recipient", "template", "subscription_type",
        ])) {
            return;
        }

        $templateId       = (int) sanitize_text_field($values["template"]);
        $recipient        = filter_var(sanitize_text_field($values["recipient"]), FILTER_VALIDATE_EMAIL);
        $subscriptionType = sanitize_text_field($values["subscription_type"]);

        $template = $value[$templateId] ?? null;

        if (!$recipient) {
            $_SESSION['test_message_status'] = 1;
            return;
        }

        if (!$template) {
            $_SESSION['test_message_status'] = 3;
            return;
        }

        if (in_array($subscriptionType, SubscriptionType::all())) {
            $objectType = ObjectType::TRAININGS;
            $eventType  = null;
        } else if (Models\Local\EventType::find($subscriptionType)) {
            $objectType       = ObjectType::EVENT;
            $eventType        = Models\Local\EventType::find($subscriptionType);
            $subscriptionType = SubscriptionType::SINGLE;
        } else {
            $_SESSION['test_message_status'] = 3;
            return;
        }

        if ($objectType === ObjectType::TRAININGS) {
            $object = Models\TrainingGroup::all()->first();
        } else if ($objectType === ObjectType::EVENT) {
            $object = Models\Event::eventType($eventType)->first();
        }

        if (!$object) {
            $_SESSION['test_message_status'] = 2;
            return;
        }

        $subscription = MessageTemplateTester::createMockSubscription($subscriptionType, $objectType, $object);

        if (MessageTemplateTester::sendTestMessage($recipient, $template, $subscription)) {
            $_SESSION['test_message_status'] = 0;
        } else {
            $_SESSION['test_message_status'] = 3;
        }
    }

}
