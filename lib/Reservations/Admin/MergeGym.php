<?php

namespace Reservations\Admin;

use Reservations\Base;
use Reservations\Models;
use Reservations\PostTypes;
use Reservations\Taxonomies;
use Reservations\Utils;

class MergeGym extends Base\AdminPage
{
    protected $gym;

    public function register()
    {
        $this->slug = $this->plugin->slug("-merge-gym");

        return add_submenu_page(
            "edit-tags.php?taxonomy=" . Taxonomies\Gym::NAME . "&post_type=" . PostTypes\Training::NAME,
            __('Merge', 'reservations'),
            __('Merge', 'reservations'),
            "manage_categories",
            $this->slug,
            [$this, "render"]);
    }

    public function prepare()
    {
        $gymList = admin_url("edit-tags.php?taxonomy=" . Taxonomies\Gym::NAME . "&post_type=" . PostTypes\Training::NAME);

        if (!isset($_GET['gym_id'])) {
            wp_redirect($gymList . "&merge_error=" . urlencode(__('Invalid gym ID', 'reservations')));
            exit;
        }

        $this->gym = Models\Gym::find((int) $_GET['gym_id']);

        if (!$this->gym) {
            wp_redirect($gymList . "&merge_error=" . urlencode(__('Gym not found', 'reservations')));
            exit;
        }

        if (!current_user_can("manage_categories")) {
            wp_redirect($gymList . "&merge_error=" . urlencode(__('You are not allowed to edit this gym', 'reservations')));
            exit;
        }

        if (isset($_POST['merge'])) {
            $this->submit();
        }
    }

    public function submit()
    {
        $gymList = admin_url("edit-tags.php?taxonomy=" . Taxonomies\Gym::NAME . "&post_type=" . PostTypes\Training::NAME);

        if (!Utils::allSet($_POST, [
            "target_gym_id", "_wpnonce",
        ])) {
            wp_redirect($gymList);
            exit;
        }

        if (!wp_verify_nonce($_POST['_wpnonce'], $this->plugin->prefix("merge_gym_" . $this->gym->id))) {
            wp_redirect($gymList);
            exit;
        }

        $targetGym = Models\Gym::accessible()->where("id", (int) $_POST['target_gym_id'])->first();

        if (!$targetGym) {
            wp_redirect($gymList . "&merge_error=" . urlencode(__('Target gym not found', 'reservations')));
            exit;
        }

        if ($this->gym->id === $targetGym->id) {
            wp_redirect($gymList . "&merge_error=" . urlencode(__('Cannot merge gym with itself', 'reservations')));
            exit;
        }

        $targetCity = $targetGym->city;
        $trainings  = $this->gym->trainings();

        foreach ($trainings as $training) {
            wp_set_object_terms($training->id, [$targetGym->id], Taxonomies\Gym::NAME);
            wp_set_object_terms($training->id, [$targetCity->id], Taxonomies\City::NAME);
        }

        $subscriptions = $this->gym->subscriptions()->withTrashed();

        foreach ($subscriptions as $subscription) {
            $subscription->gym_id = $targetGym->id;
            $subscription->save();
        }

        wp_delete_term($this->gym->id, Taxonomies\Gym::NAME);

        wp_redirect($gymList . "&merge_ok=" . urlencode(sprintf(__('Gym %s was merged with gym %s', 'reservations'), $this->gym->name, $targetGym->name)));
        exit;
    }

    public function render()
    {
        $gyms      = Models\Gym::accessible()->where("term_id", "!=", $this->gym->id)->sortByName()->get();
        $gymSelect = Utils\Html::getGymTreeSelect($gyms);

        $trainings = $this->gym->trainings();

        foreach ($trainings as $training) {
            $training->tgroup        = $training->trainingGroup();
            $training->ageGroupLabel = Utils::getAgeGroupPath($training->ageGroup);
        }

        $city = $this->gym->city;

        ?>
        <div class="wrap res-wrap" id="res-merge-gym">
        <h1 class="wp-heading-inline"><?php _e('Merge Gym', 'reservations');?></h1>
        <hr class="wp-header-end">
        <form method="post">
            <?php wp_nonce_field($this->plugin->prefix("merge_gym_" . $this->gym->id));?>

            <table class="form-table">
                <tr class="form-field form-required">
                    <th scope="row"><label>Zdrojová tělocvična</label></th>
                    <td><p style="font-weight: bold;margin-bottom:3px"><?=esc_html($this->gym->name)?></p>
                        <?php if ($city): ?><?=esc_html($this->gym->city->name)?><?php endif;?></td>
                </tr>
                <tr class="form-field">
                    <th scope="row"><label for="target_gym_id">Cílová tělocvična</label></th>
                    <td><select name="target_gym_id" id="target_gym_id"><?=$gymSelect?></select></td>
                </tr>
            </table>

            <h2><?php _e('Summary', 'reservations');?></h2>

            <p><?=sprintf(__('These trainings will be moved from %s to the selected target gym. Training groups won\'t be affected.', 'reservations'), '<strong>' . esc_html($this->gym->name) . '</strong>')?></p>

            <ul class="res-trainings-list">
                <?php foreach ($trainings as $training): ?>
                    <li><a href="<?=esc_attr($training->editLink)?>"><?=esc_html($training->title)?></a><?php if ($training->tgroup): ?> &ndash; <?php _e('training group:', 'reservations');?> <a href="<?=esc_attr($training->tgroup->editLink)?>"><?=esc_html($training->tgroup->name)?></a><?php endif;?> &ndash; <?php _e('age group:', 'reservations');?> <?=esc_html($training->ageGroupLabel)?></li>
                <?php endforeach;?>
            </ul>

            <p><?=sprintf(__('Gym %s will then be permanently deleted.', 'reservations'), '<strong>' . esc_html($this->gym->name) . '</strong>')?></p>

            <p class="submit">
                <input type="submit" name="merge" class="button button-primary" value="<?php esc_attr_e('Merge gyms', 'reservations');?>">
            </p>
        </form>
        <?php
}

    /** @action(admin_notices) */
    public function displayAdminNotices()
    {
        $ok    = $_GET['merge_ok'] ?? "";
        $error = $_GET['merge_error'] ?? "";

        ?>
        <?php if ($ok): ?>
            <div class="res-notice notice-success">
                <p><?=esc_html($ok)?></p>
            </div>
        <?php endif;?>

        <?php if ($error): ?>
            <div class="res-notice notice-error">
                <p><?=esc_html($error)?></p>
            </div>
        <?php endif;?>
    <?php
}
}
