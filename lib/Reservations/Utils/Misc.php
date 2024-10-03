<?php

namespace Reservations\Utils;

use Reservations;
use Reservations\Base\Service;
use Reservations\Utils;

class Misc extends Service
{
    /** @action(admin_enqueue_scripts) */
    public function enqueueAdminStyle()
    {
        wp_enqueue_style("res-admin-css", $this->plugin->url("admin/style.css"), [], Utils::getFileVersion($this->plugin->path("admin/style.css")));
        wp_enqueue_script("res-admin-js", $this->plugin->url("admin/script.js"), ["jquery", "jquery-ui-core", "jquery-ui-sortable"], Utils::getFileVersion($this->plugin->path("admin/script.js")));

        if (Reservations::MODE === "lead") {
            wp_enqueue_style("res-lead-admin-css", $this->plugin->url("admin/style-lead.css"), ["res-admin-css"], Utils::getFileVersion($this->plugin->path("admin/style-lead.css")));
        }

        wp_enqueue_media();
    }

    public function updateDatabaseSchema()
    {
        global $wpdb;

        $sql = str_replace("{PREFIX}", $wpdb->prefix, file_get_contents($this->plugin->path("db.sql")));

        $stmts = explode(";", $sql);

        foreach ($stmts as $stmt) {
            $stmt = trim($stmt);

            if (!$stmt) {
                continue;
            }

            $wpdb->query($stmt);
        }
    }
}
