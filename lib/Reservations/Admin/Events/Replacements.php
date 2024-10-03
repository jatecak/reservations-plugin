<?php

namespace Reservations\Admin\Events;

use Reservations\Admin;
use Reservations\PostTypes;

class Replacements extends Admin\Replacements
{
    /** @action(admin_menu)  */
    public function registerPage()
    {
        $this->slug = add_submenu_page("edit.php?post_type=" . PostTypes\Event::NAME, _x('Replacements', 'event', 'reservations'), _x('Replacements', 'event', 'reservations'), "edit_posts", $this->plugin->slug("-replacements-event"), [$this, "render"]);

        add_action("load-" . $this->slug, [$this, "prepare"]);
    }

    protected function supportsEdit()
    {
        return true;
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
}
