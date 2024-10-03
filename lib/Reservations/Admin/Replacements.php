<?php

namespace Reservations\Admin;

use Reservations;
use Reservations\PostTypes;

class Replacements extends Subscriptions\SubscriptionsBase
{
    /** @action(admin_menu)  */
    public function registerPage()
    {
        if (!$this->plugin->isFeatureEnabled("replacements")) {
            return;
        }

        $this->slug = add_submenu_page("edit.php?post_type=" . PostTypes\Training::NAME, __('Replacements', 'reservations'), __('Replacements', 'reservations'), "edit_posts", $this->plugin->slug("-replacements"), [$this, "render"]);

        add_action("load-" . $this->slug, [$this, "prepare"]);
    }

    protected function renderList()
    {
        ?>
        <div class="wrap" id="res-subscriptions">
        <h1 class="wp-heading-inline"><?php _e('Replacements', 'reservations');?></h1>
        <hr class="wp-header-end">
        <?php
$this->handleDelete();

        $this->renderTable(new ReplacementsListTable($this->plugin));
        ?>
        </div>
        <?php
}

    protected function supportsEdit()
    {
        return true;
    }

    public function addScreenOptions()
    {
        add_screen_option("per_page", [
            "label"   => __('Number of items per page:', 'reservations'),
            "default" => 20,
            "option"  => $this->plugin->prefix("replacements_per_page"),
        ]);
    }

    /** @filter(set-screen-option) */
    public function saveScreenOptions($status, $option, $value)
    {
        if ($option === $this->plugin->prefix("replacements_per_page")) {
            $value = (int) $value;
            return !is_nan($value) && $value > 0 ? $value : 20;
        }
    }

    public function handleDelete()
    {
        $subscription = $this->getSubscription("delete", "_deletenonce", "delete_nonce_");

        if (!$subscription || Reservations::MODE !== "lubo") {
            return;
        }

        if ($subscription->isReplacement) {
            $subscription->delete();
        }

        echo '<div class="notice notice-success is-dismissible">
            <p>' . __('Replacement was deleted.', 'reservations') . '</p>
        </div>';
    }
}
