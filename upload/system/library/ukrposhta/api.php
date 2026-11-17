<?php
/**
 * Ukrposhta eCom / Classifier / StatusTracking / Forms client.
 * OpenCart 3 / OcStore 3, PHP 7.2+.
 */
class UkrposhtaApi {
	const URL_ECOM_PROD = 'https://www.ukrposhta.ua/ecom/0.0.1/';
	const URL_FORMS_PROD = 'https://www.ukrposhta.ua/forms/ecom/0.0.1/';
	const URL_TRACK_PROD = 'https://www.ukrposhta.ua/status-tracking/0.0.1/';
	const URL_CLASSIFIER = 'https://www.ukrposhta.ua/address-classifier-ws/';
	const URL_ECOM_SANDBOX = 'https://dev.ukrposhta.ua/ecom/0.0.1/';
	const URL_FORMS_SANDBOX = 'https://dev.ukrposhta.ua/forms/ecom/0.0.1/';
	const URL_TRACK_SANDBOX = 'https://dev.ukrposhta.ua/status-tracking/0.0.1/';

	private $bearer_ecom;
	private $bearer_tracking;
	private $token;
	private $sandbox;
	private $timeout;
	private $last_error = '';
	private $last_http_code = 0;
	private $last_raw = '';
	private $debug = false;
	private $log;

	public function __construct($config = array()) {
		$this->bearer_ecom = isset($config['bearer_ecom']) ? $config['bearer_ecom'] : '';
		$this->bearer_tracking = isset($config['bearer_tracking']) ? $config['bearer_tracking'] : $this->bearer_ecom;
		$this->token = isset($config['token']) ? $config['token'] : '';
		$this->sandbox = !empty($config['sandbox']);
		$this->timeout = isset($config['timeout']) ? (int)$config['timeout'] : 20;
		$this->debug = !empty($config['debug']);
		$this->log = isset($config['log']) ? $config['log'] : null;
	}

	public function getLastError() {
		return $this->last_error;
	}

	public function getLastHttpCode() {
		return $this->last_http_code;
	}

	public function getLastRaw() {
		return $this->last_raw;
	}

	private function ecomUrl() {
		return $this->sandbox ? self::URL_ECOM_SANDBOX : self::URL_ECOM_PROD;
	}

	private function formsUrl() {
		return $this->sandbox ? self::URL_FORMS_SANDBOX : self::URL_FORMS_PROD;
	}

	private function trackUrl() {
		return $this->sandbox ? self::URL_TRACK_SANDBOX : self::URL_TRACK_PROD;
	}

	private function withToken($path) {
		$sep = (strpos($path, '?') === false) ? '?' : '&';

		return $path . $sep . 'token=' . rawurlencode($this->token);
	}

	private function request($method, $url, $bearer, $body = null, $raw_binary = false) {
		$this->last_error = '';
		$this->last_http_code = 0;
		$this->last_raw = '';

		if (!$bearer) {
			$this->last_error = 'Bearer token is empty';
			return false;
		}

		if (!function_exists('curl_init')) {
			$this->last_error = 'cURL extension is not installed';
			return false;
		}

		$headers = array(
			'Authorization: Bearer ' . $bearer,
			'Accept: application/json'
		);

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, max(5, $this->timeout));
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, min(10, max(5, $this->timeout)));
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, strtoupper($method));

		if ($body !== null) {
			$payload = is_string($body) ? $body : json_encode($body, JSON_UNESCAPED_UNICODE);
			$headers[] = 'Content-Type: application/json';
			curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
		}

		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		$response = curl_exec($ch);
		$this->last_http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);

		if ($response === false) {
			$this->last_error = curl_error($ch);
			curl_close($ch);
			$this->writeLog($method, $url, $body, null);
			return false;
		}

		curl_close($ch);
		$this->last_raw = $response;
		$this->writeLog($method, $url, $body, $raw_binary ? '[binary ' . strlen($response) . ']' : $response);

		if ($raw_binary) {
			if ($this->last_http_code >= 200 && $this->last_http_code < 300) {
				return $response;
			}
			$this->last_error = 'HTTP ' . $this->last_http_code;
			return false;
		}

		$data = json_decode($response, true);

		if (!is_array($data) && is_string($response) && strpos(ltrim($response), '<') === 0) {
			$xml = @simplexml_load_string($response);
			if ($xml) {
				$data = json_decode(json_encode($xml), true);
			}
		}

		if ($this->last_http_code < 200 || $this->last_http_code >= 300) {
			$this->last_error = $this->extractError($data, $response);
			return false;
		}

		if ($data === null && $response !== '' && $response !== 'null') {
			if ($response === '') {
				return true;
			}
			$this->last_error = 'Invalid JSON response';
			return false;
		}

		return $data === null ? true : $data;
	}

	private function extractError($data, $raw) {
		if (is_array($data)) {
			if (!empty($data['message'])) {
				return (string)$data['message'];
			}
			if (!empty($data['error']['message'])) {
				return (string)$data['error']['message'];
			}
			if (!empty($data['errors']) && is_array($data['errors'])) {
				$parts = array();
				foreach ($data['errors'] as $err) {
					if (is_string($err)) {
						$parts[] = $err;
					} elseif (is_array($err) && isset($err['message'])) {
						$parts[] = $err['message'];
					}
				}
				if ($parts) {
					return implode('; ', $parts);
				}
			}
		}

		return 'HTTP ' . $this->last_http_code . ': ' . substr(trim(strip_tags((string)$raw)), 0, 300);
	}

	private function writeLog($method, $url, $request, $response) {
		if (!$this->debug || !$this->log || !method_exists($this->log, 'write')) {
			return;
		}

		$safe_url = preg_replace('/token=[^&]+/i', 'token=***', $url);
		$req = is_string($request) ? $request : json_encode($request, JSON_UNESCAPED_UNICODE);
		$msg = $method . ' ' . $safe_url
			. "\nREQ: " . substr((string)$req, 0, 2000)
			. "\nHTTP: " . $this->last_http_code
			. "\nRES: " . substr((string)$response, 0, 2000);
		$this->log->write('[Ukrposhta] ' . $msg);
	}

	private function normalizeEntries($data) {
		if (!$data || !is_array($data)) {
			return array();
		}

		if (isset($data['Entries']['Entry'])) {
			$entries = $data['Entries']['Entry'];
			if ($entries === array() || $entries === null) {
				return array();
			}
			if (isset($entries[0])) {
				return $entries;
			}
			return array($entries);
		}

		if (isset($data['Entry'])) {
			$entries = $data['Entry'];
			if (isset($entries[0])) {
				return $entries;
			}
			return array($entries);
		}

		if (isset($data[0])) {
			return $data;
		}

		return array();
	}

	public function getCities($city_ua, $region_id = '', $district_id = '') {
		$query = array('city_ua' => $city_ua);
		if ($region_id !== '') {
			$query['region_id'] = $region_id;
		}
		if ($district_id !== '') {
			$query['district_id'] = $district_id;
		}
		$url = self::URL_CLASSIFIER . 'get_city_by_region_id_and_district_id_and_city_ua?' . http_build_query($query);
		return $this->normalizeEntries($this->request('GET', $url, $this->bearer_ecom));
	}

	public function getPostOfficesByCityId($city_id) {
		$url = self::URL_CLASSIFIER . 'get_postoffices_by_postcode_cityid_cityvpzid?' . http_build_query(array('city_id' => $city_id));
		return $this->normalizeEntries($this->request('GET', $url, $this->bearer_ecom));
	}

	public function getStreets($city_id, $street_ua) {
		$url = self::URL_CLASSIFIER . 'get_street_by_region_id_and_district_id_and_city_id_and_street_ua?' . http_build_query(array(
			'city_id' => $city_id,
			'street_ua' => $street_ua
		));
		return $this->normalizeEntries($this->request('GET', $url, $this->bearer_ecom));
	}

	public function createAddress($address) {
		return $this->request('POST', $this->ecomUrl() . 'addresses', $this->bearer_ecom, $address);
	}

	public function createClient($client) {
		return $this->request('POST', $this->ecomUrl() . $this->withToken('clients'), $this->bearer_ecom, $client);
	}

	public function updateClient($uuid, $client) {
		return $this->request('PUT', $this->ecomUrl() . $this->withToken('clients/' . rawurlencode($uuid)), $this->bearer_ecom, $client);
	}

	public function getClient($uuid) {
		return $this->request('GET', $this->ecomUrl() . $this->withToken('clients/' . rawurlencode($uuid)), $this->bearer_ecom);
	}

	public function getClientByExternalId($external_id) {
		return $this->request('GET', $this->ecomUrl() . $this->withToken('clients/external-id/' . rawurlencode($external_id)), $this->bearer_ecom);
	}

	public function getClientByPhone($phone) {
		$path = 'clients/phone?' . http_build_query(array(
			'token' => $this->token,
			'countryISO3166' => 'UA',
			'phoneNumber' => $phone
		));
		return $this->request('GET', $this->ecomUrl() . $path, $this->bearer_ecom);
	}

	public function ensureClient($client) {
		$existing = null;

		if (!empty($client['externalId'])) {
			$found = $this->getClientByExternalId($client['externalId']);
			if (is_array($found) && !empty($found['uuid'])) {
				$existing = $found;
			}
		}

		if (!$existing && !empty($client['phoneNumber'])) {
			$found = $this->getClientByPhone($client['phoneNumber']);
			if (is_array($found) && !empty($found['uuid'])) {
				$existing = $found;
			} elseif (is_array($found) && isset($found[0]['uuid'])) {
				$existing = $found[0];
			}
		}

		if ($existing && !empty($existing['uuid'])) {
			$update = $client;
			unset($update['externalId']);
			$updated = $this->updateClient($existing['uuid'], $update);
			if ($updated === true) {
				$existing['addressId'] = isset($client['addressId']) ? $client['addressId'] : (isset($existing['addressId']) ? $existing['addressId'] : '');
				return $existing;
			}
			if (is_array($updated) && !empty($updated['uuid'])) {
				return $updated;
			}
			$update_error = $this->last_error;
			$created = $this->createClient($client);
			if (is_array($created) && !empty($created['uuid'])) {
				return $created;
			}
			$this->last_error = $update_error ? $update_error : $this->last_error;
			return false;
		}

		return $this->createClient($client);
	}

	public function normalizePhone($phone) {
		$digits = preg_replace('/\D+/', '', (string)$phone);
		if (strpos($digits, '380') === 0 && strlen($digits) >= 12) {
			return '0' . substr($digits, -9);
		}
		if (strlen($digits) === 9) {
			return '0' . $digits;
		}
		if (strlen($digits) === 10 && isset($digits[0]) && $digits[0] === '0') {
			return $digits;
		}
		return $digits;
	}

	public function createShipment($shipment) {
		return $this->request('POST', $this->ecomUrl() . $this->withToken('shipments'), $this->bearer_ecom, $shipment);
	}

	public function getShipment($uuid_or_barcode) {
		return $this->request('GET', $this->ecomUrl() . $this->withToken('shipments/' . rawurlencode($uuid_or_barcode)), $this->bearer_ecom);
	}

	public function deleteShipment($uuid) {
		return $this->request('DELETE', $this->ecomUrl() . $this->withToken('shipments/' . rawurlencode($uuid)), $this->bearer_ecom);
	}

	public function calculateDomestic($payload) {
		return $this->request('POST', $this->ecomUrl() . 'domestic/delivery-price', $this->bearer_ecom, $payload);
	}

	public function getSticker($uuid_or_barcode, $size = '') {
		$query = array('token' => $this->token);
		if ($size) {
			$query['size'] = $size;
		}
		$url = $this->formsUrl() . 'shipments/' . rawurlencode($uuid_or_barcode) . '/sticker?' . http_build_query($query);
		return $this->request('GET', $url, $this->bearer_ecom, null, true);
	}

	public function createShipmentGroup($payload) {
		return $this->request('POST', $this->ecomUrl() . $this->withToken('shipment-groups'), $this->bearer_ecom, $payload);
	}

	public function addShipmentsToGroup($group_uuid, $shipment_uuids) {
		return $this->request('PUT', $this->ecomUrl() . $this->withToken('shipment-groups/' . rawurlencode($group_uuid) . '/shipments'), $this->bearer_ecom, array_values($shipment_uuids));
	}

	public function getGroupForm103a($group_uuid) {
		$url = $this->formsUrl() . 'shipment-groups/' . rawurlencode($group_uuid) . '/form103a?' . http_build_query(array('token' => $this->token));
		return $this->request('GET', $url, $this->bearer_ecom, null, true);
	}

	public function getGroupSticker($group_uuid) {
		$url = $this->formsUrl() . 'shipment-groups/' . rawurlencode($group_uuid) . '/sticker?' . http_build_query(array('token' => $this->token));
		return $this->request('GET', $url, $this->bearer_ecom, null, true);
	}

	public function getLastStatus($barcode) {
		$url = $this->trackUrl() . 'statuses/last?' . http_build_query(array('barcode' => $barcode));
		return $this->request('GET', $url, $this->bearer_tracking);
	}

	public function getStatuses($barcode) {
		$url = $this->trackUrl() . 'statuses?' . http_build_query(array('barcode' => $barcode));
		return $this->request('GET', $url, $this->bearer_tracking);
	}
}
