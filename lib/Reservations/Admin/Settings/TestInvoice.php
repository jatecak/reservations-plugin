<?php

declare (strict_types = 1);

namespace Reservations\Admin\Settings;

use Reservations\Mail\MessageTemplateTester;
use Reservations\Models\Local\ObjectType;
use Reservations\Models;
use Reservations\Models\Local\SubscriptionType;

class TestInvoice extends AbstractPlug {
    public $name = "test_invoice";

    public function prepare()
    {
        if (!session_id()) {
            session_start();
        }
    }

    public function render() {
    	?>
    	<button class="button" type="button" data-type="submit" name="submit" value="test_invoice"><?php _e('Save and generate sample invoice', 'reservations');?></button>
    	<?php
    }

    /** @action(admin_notices) */
    public function displayAdminNotice()
    {
        $status = $_SESSION['test_invoice_status'] ?? null;
        unset($_SESSION['test_invoice_status']);

        if ($status === 1) {
            ?>
            <div class="notice notice-error is-dismissible">
                <p><?php _e('Test email couldn\'t be sent', 'reservations');?></p>
            </div>
            <?php
}
    }

    public function afterSave($oldValue, $value)
    {
        if (!isset($_POST['submit']) || $_POST['submit'] !== "test_invoice") {
            return;
        }

        $subscriptionType = "biannual";

        if (in_array($subscriptionType, SubscriptionType::all())) {
            $objectType = ObjectType::TRAININGS;
            $eventType  = null;
        } else if (Models\Local\EventType::find($subscriptionType)) {
            $objectType       = ObjectType::EVENT;
            $eventType        = Models\Local\EventType::find($subscriptionType);
            $subscriptionType = SubscriptionType::SINGLE;
        } else {
            throw new \Exception("invalid subscriptionType");
            return;
        }

        if ($objectType === ObjectType::TRAININGS) {
            $object = Models\TrainingGroup::all()->first();
        } else if ($objectType === ObjectType::EVENT) {
            $object = Models\Event::eventType($eventType)->first();
        }

        if (!$object) {
            $_SESSION['test_invoice_status'] = 1;
            return;
        }

        $subscription = MessageTemplateTester::createMockSubscription($subscriptionType, $objectType, $object);

        $pdf = $this->plugin->invoiceGenerator->generateInvoicePdfForSubscription($subscription);
        if(!$pdf) {
            $_SESSION['test_invoice_status'] = 1;
            return;
        }

        header("Content-Type: application/pdf");
        echo $pdf;
        exit;
    }
}
