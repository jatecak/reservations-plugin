<?php

namespace Reservations\Admin\Subscriptions;

use Reservations;
use Reservations\Base;
use Reservations\Models;
use Reservations\PostTypes;

class SubscriptionsBase extends Base\Service
{
    public $slug;

    protected $editScreen;

    protected $isEvent;
    protected $isReplacement;

    protected function getSubscription($idKey, $nonceKey, $noncePrefix)
    {
        if (!isset($_GET[$idKey])) {
            return null;
        }

        $subscription = Models\Subscription::find((int) $_GET[$idKey]);

        if (!$subscription) {
            return null;
        }

        if (!current_user_can("edit_posts")) {
            return null;
        }

        if (!isset($_GET[$nonceKey]) || !wp_verify_nonce($_GET[$nonceKey], $this->plugin->prefix($noncePrefix . $subscription->subscription_id))) {
            return null;
        }

        if (!Models\User::current()->canAccess($subscription)) {
            return null;
        }

        return $subscription;
    }

    protected function supportsEdit()
    {
        return false;
    }

    public function prepare()
    {
        $screen = get_current_screen();

        if ($screen->post_type === PostTypes\Event::NAME) {
            $this->isEvent = true;
        }

        if (current_user_can("edit_posts") && isset($_GET['edit'])) {
            $subscription = Models\Subscription::find((int) $_GET['edit']);

            if ($subscription && Models\User::current()->canAccess($subscription)) {
                $this->subscription = $subscription;

                if ($this->supportsEdit()) {
                    $this->editScreen = new EditScreen($this->plugin, $this->subscription);

                    if (isset($_POST['action']) && $_POST['action'] === "update" && wp_verify_nonce($_POST['_wpnonce'], "subscription_edit")) {
                        $this->editScreen->handleSubmit();
                        exit;
                    }
                }
            }
        }

        $this->addScreenOptions();
    }

    public function render()
    {
        if ($this->editScreen) {
            $this->editScreen->render();
            return;
        }

        $this->renderList();
    }

    public function renderTable($table)
    {
        $table->prepare_items();
        ?>
        <form method="get">
        <input type="hidden" name="post_type" value="<?=esc_attr($_GET['post_type'])?>">
        <input type="hidden" name="page" value="<?=esc_attr($_GET['page'])?>">
        <?php $table->display();?>
        </form>
        <?php
}

    protected function addScreenOptions()
    {

    }

    protected function displayEditMessage()
    {
        if (isset($_GET['editok'])) {
            echo '<div class="notice notice-success is-dismissible">
                <p>' . __('Subscription updated.', 'reservations') . '</p>
            </div>';
        }
    }
}
