<?php

namespace Reservations\Admin\Events;

use Reservations\Admin;
use Reservations\PostTypes;

class Subscriptions extends Admin\Subscriptions
{
    /** @action(admin_menu)  */
    public function registerPage()
    {
        $this->slug = add_submenu_page("edit.php?post_type=" . PostTypes\Event::NAME, _x('Subscriptions', 'event', 'reservations'), _x('Subscriptions', 'event', 'reservations'), "edit_posts", $this->plugin->slug("-subscriptions-event"), [$this, "render"]);

        add_action("load-" . $this->slug, [$this, "prepare"]);
    }

    protected function filterUnpaidSubscriptionsQuery($subscriptions)
    {
        $subscriptions->forEvents();
    }

    protected function supportsEdit()
    {
        return true;
    }

    public function renderList()
    {

        ?>
        <div class="wrap" id="res-subscriptions">
        <h1 class="wp-heading-inline"><?php _ex('Subscriptions', 'event', 'reservations');?></h1>
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
}
