<?php

namespace Reservations\Invoices;

use DateTimeInterface;
use GuzzleHttp;

final class IDokladClient {
	const ENDPOINT = "https://api.idoklad.cz/v3/";
	const IDENTITY_ENDPOINT = "https://identity.idoklad.cz/";

	private $clientId;
	private $clientSecret;

	/** @var GuzzleHttp\Client */
	private $client;

	/** @var GuzzleHttp\Client */
	private $apiClient;

	public function __construct($clientId, $clientSecret) {
		$this->clientId = $clientId;
		$this->clientSecret = $clientSecret;

		$this->client = new GuzzleHttp\Client();
	}

	static function formatDate(DateTimeInterface $date) {
		return $date->format("Y-m-d");
	}

	private function getResponseBody($res) {
		return json_decode($res->getBody());
	}

	private function authorize() {
		if($this->apiClient)
			return;

		$res = $this->client->post(self::IDENTITY_ENDPOINT . "server/connect/token", [
			"form_params" => [
				"grant_type" => "client_credentials",
				"client_id" => $this->clientId,
				"client_secret" => $this->clientSecret,
				"scope" => "idoklad_api"
			]
		]);
		$body = $this->getResponseBody($res);

		$this->apiClient = new GuzzleHttp\Client([
			"base_uri" => self::ENDPOINT,
			"headers" => [
				"Authorization" => "Bearer {$body->access_token}",
			]
		]);
	}

	public function secureJsonRequest($method, $uri = "", $options = []) {
		$this->authorize();

		$res = $this->apiClient->request($method, $uri, $options);
		return $this->getResponseBody($res);
	}

}
