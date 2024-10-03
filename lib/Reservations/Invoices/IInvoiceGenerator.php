<?php

declare (strict_types = 1);

namespace Reservations\Invoices;

use Reservations\Models\Subscription;

interface IInvoiceGenerator {
	public function generateInvoicePdfForSubscription(Subscription $subscription);
}
