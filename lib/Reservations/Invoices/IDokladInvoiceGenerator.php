<?php

declare(strict_types=1);

namespace Reservations\Invoices;

use Carbon\Carbon;
use DateTimeInterface;
use Illuminate\Support\Arr;
use Nette\InvalidStateException;
use Nette\Utils\Strings;
use Reservations\Base\Service;
use Reservations\Models\Subscriber;
use Reservations\Models\Subscription;
use Reservations\Utils;

final class IDokladInvoiceGenerator extends Service implements IInvoiceGenerator
{

	const DOCUMENT_TYPE_ISSUED_INVOICE = 0;

	/** @var IDokladClient */
	private $client = null;

	public function init()
	{
		parent::init();

		$clientId = $this->plugin->getOption("idoklad_client_id");
		$clientSecret = $this->plugin->getOption("idoklad_client_secret");

		if ($clientId && $clientSecret)
			$this->client = new IDokladClient($clientId, $clientSecret);
	}

	public function generateInvoicePdfForSubscription(Subscription $subscription)
	{
		if (!$this->client || !$this->plugin->getOption("invoices_enable"))
			return null;

		if (!$subscription->invoice_id) {
			$invoice = $this->createInvoiceForSubscription($subscription);

			$subscription->invoice_id = $invoice->Id;

			if ($subscription->id) // don't save if testing
				$subscription->save();
		}

		return $this->exportInvoiceToPdf($subscription->invoice_id);
	}

	private function exportInvoiceToPdf($invoiceId)
	{
		$pdf = $this->client->secureJsonRequest("GET", "Reports/IssuedInvoice/{$invoiceId}/Pdf", [
			"query" => [
				"compressed" => false,
				"language" => 1
			]
		])->Data;

		return base64_decode($pdf);
	}

	private function createInvoiceForSubscription(Subscription $subscription)
	{
		/** @var Subscriber */
		$subscriber = $subscription->subscriber;

		$country = $this->getCountryByCode("CZ");
		$contact = $this->getOrCreateContactForSubscriber($subscriber, $country);

		$price = $subscription->paid ? $subscription->paidAmount : $subscription->paymentAmount;

		$defaultInvoice = $this->client->secureJsonRequest("GET", "IssuedInvoices/Default")->Data;
		$defaultMaturityPeriodDays = Carbon::parse($defaultInvoice->DateOfMaturity)->diffInDays(Carbon::parse($defaultInvoice->DateOfIssue));

		$issueDate = $subscription->paidAt ?: Utils::today();

		$invoiceData = (clone $defaultInvoice);

		$numericSequenceName = $this->plugin->getOption("idoklad_numeric_sequence_name");
		if ($numericSequenceName) {
			$numericSequence = $this->getNumericSequenceByName(Strings::trim($numericSequenceName));

			if (!$numericSequence)
				throw new InvalidStateException("Numeric sequence {$numericSequenceName} not found.");

			$invoiceData->NumericSequenceId = $numericSequence->Id;
		}

		$invoiceData->OrderNumber = $subscription->id;

		$invoiceData->Description = $subscription->invoiceDescription;
		$invoiceData->Note = $subscription->invoiceDescription;
		$invoiceData->PartnerId = $contact->Id;
		$invoiceData->DateOfIssue = IDokladClient::formatDate($issueDate);
		$invoiceData->DateOfTaxing = IDokladClient::formatDate($issueDate);
		$invoiceData->DateOfMaturity = IDokladClient::formatDate((clone $issueDate)->addDays($defaultMaturityPeriodDays));
		// $invoiceData->PaymentStatus = $subscription->paid ? 1 : 0;

		$itemData = $invoiceData->Items[0];
		$itemData->Amount = 1;
		$itemData->Name = $subscription->invoiceDescription;
		$itemData->UnitPrice = $price;

		$invoice = $this->client->secureJsonRequest("POST", "IssuedInvoices", [
			"json" => $invoiceData
		])->Data;

		if ($subscription->paid) {
			$this->client->secureJsonRequest("PUT", "IssuedDocumentPayments/FullyPay/{$invoice->Id}", []);
		}

		return $invoice;
	}

	private function getOrCreateContactForSubscriber(Subscriber $subscriber, $country)
	{
		$contacts = $this->client->secureJsonRequest("GET", "Contacts", [
			"query" => [
				"filter" => "Email~eq~{$subscriber->contact_email}",
				"pagesize" => 200
			]
		])->Data->Items;

		$contact = collect($contacts)->first(function ($c) use ($subscriber) {
			return $c->Firstname === $subscriber->rep_first_name
				&& $c->Surname === $subscriber->rep_last_name
				&& $c->Email === $subscriber->contact_email
				&& $c->Phone === $subscriber->contact_phone
				&& $c->Street === $subscriber->rep_address;
		});

		if ($contact)
			return $contact;

		return $this->createContactForSubscriber($subscriber, $country);
	}

	private function createContactForSubscriber(Subscriber $subscriber, $country)
	{
		$contact = $this->client->secureJsonRequest("POST", "Contacts", [
			"json" => [
				"CompanyName" => $subscriber->rep_first_name . " " . $subscriber->rep_last_name,
				"CountryId" => $country->Id,
				"Firstname" => $subscriber->rep_first_name,
				"Surname" => $subscriber->rep_last_name,
				"Email" => $subscriber->contact_email,
				"Phone" => $subscriber->contact_phone,
				"Street" => $subscriber->rep_address,
			]
		])->Data;

		return $contact;
	}

	private function getCountryByCode($code)
	{
		$countries = $this->client->secureJsonRequest("GET", "Countries", [
			"query" => [
				"filter" => "Code~eq~{$code}",
				"pagesize" => 1
			]
		])->Data->Items;

		return Arr::first($countries);
	}

	private function getNumericSequenceByName($name)
	{
		$numericSequences = $this->client->secureJsonRequest("GET", "NumericSequences", [
			"query" => "filter=(DocumentType~eq~" . self::DOCUMENT_TYPE_ISSUED_INVOICE . ")",
		])->Data->Items;

		return Arr::first($numericSequences, function ($seq) use ($name) {
			return Strings::lower($name) === Strings::lower($seq->Name);
		});
	}
}
