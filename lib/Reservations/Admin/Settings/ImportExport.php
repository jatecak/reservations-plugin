<?php

namespace Reservations\Admin\Settings;

use Reservations\Models;
use Reservations\Models\Local\ObjectType;
use Reservations\Utils;

class ImportExport extends AbstractPlug
{
    public function register()
    {
        $this->plugin->addHooks($this); // do not register any settings
    }

    public function render()
    {
        if (!$this->plugin->isFeatureEnabled("import_export") || !current_user_can("administrator")) {
            return;
        }

        $pageUrl = admin_url("options-general.php?page=" . $this->plugin->slug("-import-export"));
        ?>
        <h2><?php _e('Import & Export', 'reservations');?></h2>

        <p><?php _e('Import and export all data managed by the plugin.', 'reservations');?></p>

        <a href="<?php echo esc_attr($pageUrl); ?>" class="button"><?php _e('Open Tool', 'reservations');?></a>
        <?php
}
}
