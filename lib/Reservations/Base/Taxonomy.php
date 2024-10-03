<?php

namespace Reservations\Base;

abstract class Taxonomy extends Service
{
    public function init()
    {
        parent::init();

        add_filter("bulk_actions-edit-" . static::NAME, [$this, "_displayFilters"]);
        add_filter("bulk_actions-edit-" . static::NAME, [$this, "registerBulkActions"]);
        add_filter("handle_bulk_actions-edit-" . static::NAME, [$this, "handleBulkActions"], 10, 3);
        add_filter(static::NAME . "_row_actions", [$this, "registerRowActions"], 10, 2);
    }

    public function _displayFilters($actions)
    {
        echo '<div class="res-term-filters res-pull-right">';
        $this->displayFilters();
        echo '</div>';

        return $actions;
    }

    /**
     * @filter(get_terms_args)
     */
    public function _applyFilters($args, $taxonomies)
    {
        if (!is_admin() || !function_exists("get_current_screen")) {
            return $args;
        }

        $screen = get_current_screen();

        if (is_null($screen) || $screen->base !== "edit-tags" || $screen->taxonomy !== static::NAME || !in_array(static::NAME, $taxonomies)) {
            return $args;
        }

        return $this->applyFilters($args);
    }

    /**
     * @action(pre_delete_term)
     */
    public function _preDeleteTerm($termId, $taxonomy)
    {
        if ($taxonomy !== static::NAME) {
            return;
        }

        $this->beforeDelete($termId);
    }

    public function displayFilters()
    {

    }

    public function applyFilters($args)
    {
        return $args;
    }

    public function registerBulkActions($bulkActions)
    {
        return $bulkActions;
    }

    public function handleBulkActions($redirectTo, $action, $objectIds)
    {
        return $redirectTo;
    }

    public function beforeDelete($termId)
    {

    }

    public function registerRowActions($actions, $term)
    {
        return $actions;
    }
}
