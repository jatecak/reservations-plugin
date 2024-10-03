<?php

namespace Reservations\Admin;

use Reservations\Base;

class SettingsPage extends Base\AdminPage
{
    public function register()
    {
        $this->slug = $this->plugin->slug();

        return add_options_page(
            __('Reservations Settings', 'reservations'),
            __('Reservations', 'reservations'),
            "manage_options",
            $this->slug,
            [$this, "render"]
        );
    }

    public function prepare()
    {
        $settingsService = $this->plugin->settings;

        foreach ($settingsService->plugs as $plug) {
            $plug->prepare();
        }
    }

    public function assets()
    {
        wp_enqueue_editor();
    }

    public function render()
    {
        $settingsService = $this->plugin->settings;

        ?>
        <div class="wrap" id="res-settings">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        <form action="options.php" method="post">
        <?php
settings_fields($this->plugin->slug());
        do_settings_sections($this->plugin->slug());

        foreach ($settingsService->plugs as $plug) {
            $plug->render();
        }

        submit_button(__('Save Settings', 'reservations'));
        ?>
        </form>
        </div>
        <?php
}
}
