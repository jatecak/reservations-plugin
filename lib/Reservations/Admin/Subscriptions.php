<?php

namespace Reservations\Admin;

use Reservations;
use Reservations\Models;
use Reservations\Models\Local\TranslatableEnums;
use Reservations\PostTypes;
use Reservations\Utils;

class Subscriptions extends Subscriptions\SubscriptionsBase
{
    protected $subscription;
    protected $edit;

    /** @action(admin_menu)  */
    public function registerPage()
    {
        $this->slug = add_submenu_page("edit.php?post_type=" . PostTypes\Training::NAME, __('Subscriptions', 'reservations'), __('Subscriptions', 'reservations'), "edit_posts", $this->plugin->slug("-subscriptions"), [$this, "render"]);

        add_action("load-" . $this->slug, [$this, "prepare"]);
    }

    protected function filterUnpaidSubscriptionsQuery($subscriptions)
    {
        $subscriptions->forTrainings();
    }

    protected function supportsEdit()
    {
        return true;
    }

    public function renderList()
    {
        ?>
        <div class="wrap" id="res-subscriptions">
        <h1 class="wp-heading-inline"><?php _e('Subscriptions', 'reservations');?></h1>
        <a href="<?=esc_url(add_query_arg([
            "remove_unpaid" => 1,
            "_removenonce"  => wp_create_nonce($this->plugin->prefix("remove_nonce")),
            "editok"        => false,
        ]))?>" class="page-title-action"><?php _e('Remove unpaid', 'reservations');?></a>
        <hr class="wp-header-end">
        <?php
$this->handleConfirmForm();
        $this->handleDelete();
        $this->handleRemoveUnpaid();
        $this->displayEditMessage();

        $this->renderTable(new SubscriptionsListTable($this->plugin));
        ?>
        </div>
        <?php
}

    public function addScreenOptions()
    {
        add_screen_option("per_page", [
            "label"   => __('Number of items per page:', 'reservations'),
            "default" => 20,
            "option"  => $this->plugin->prefix("subscriptions_per_page"),
        ]);
    }

    /** @filter(set-screen-option) */
    public function saveScreenOptions($status, $option, $value)
    {
        if ($option === $this->plugin->prefix("subscriptions_per_page")) {
            $value = (int) $value;
            return !is_nan($value) && $value > 0 ? $value : 20;
        }
    }

    public function handleConfirmForm()
    {
        $subscription = $this->getSubscription("confirm_form", "_confirmnonce", "confirm_nonce_");

        if (!$subscription) {
            return;
        }

        $subscription->applicationFormReceived = true;
        $subscription->save();

        echo '<div class="notice notice-success is-dismissible">
            <p>' . __('Application form was confirmed.', 'reservations') . '</p>
        </div>';
    }

    public function handleDelete()
    {
        $subscription = $this->getSubscription("delete", "_deletenonce", "delete_nonce_");

        if (!$subscription) {
            return;
        }

        if (!$subscription->isReplacement) {
            $subscription->delete();
        }

        echo '<div class="notice notice-success is-dismissible">
            <p>' . __('Subscription was deleted.', 'reservations') . '</p>
        </div>';
    }

    public function handleRemoveUnpaid()
    {
        if (!isset($_GET['remove_unpaid'])) {
            return;
        }

        if (!current_user_can("edit_posts")) {
            return;
        }

        if (!isset($_GET['_removenonce']) || !wp_verify_nonce($_GET['_removenonce'], $this->plugin->prefix("remove_nonce"))) {
            return;
        }

        $subscriptionsQuery = Models\Subscription::accessible()->active()->where("is_replacement", false)->where("application_form_received", false);
        $this->filterUnpaidSubscriptionsQuery($subscriptionsQuery);
        $subscriptions = $subscriptionsQuery->get();

        $numRemoved = 0;
        foreach ($subscriptions as $subscription) {
            if ($subscription->paidAmount > 0) {
                continue;
            }

            $subscription->updatePaidStatus();

            if ($subscription->paidAmount > 0) {
                continue;
            }

            $subscription->delete();
            $numRemoved++;
        }

        echo '<div class="notice notice-success is-dismissible">
            <p>' . sprintf(_n('Removed %d unpaid subscription.', 'Removed %d unpaid subscriptions.', $numRemoved, 'reservations'), $numRemoved) . '</p>
        </div>';
    }
}
