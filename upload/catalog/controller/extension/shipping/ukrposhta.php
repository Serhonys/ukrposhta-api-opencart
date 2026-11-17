<?php
class ControllerExtensionShippingUkrposhta extends Controller {
	private function svc() {
		require_once DIR_SYSTEM . 'library/ukrposhta/service.php';
		return new UkrposhtaService($this->registry);
	}

	private function json($data) {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
	}

	public function cities() {
		$this->load->language('extension/shipping/ukrposhta');
		$svc = $this->svc();
		if (!$svc->allowPublicLookup()) {
			return $this->json(array());
		}
		$term = isset($this->request->get['term']) ? $this->request->get['term'] : '';
		$this->json($svc->searchCities($term));
	}

	public function offices() {
		$svc = $this->svc();
		if (!$svc->allowPublicLookup()) {
			return $this->json(array());
		}
		$city_id = isset($this->request->get['city_id']) ? $this->request->get['city_id'] : '';
		$this->json($city_id ? $svc->getOffices($city_id) : array());
	}

	public function streets() {
		$svc = $this->svc();
		if (!$svc->allowPublicLookup()) {
			return $this->json(array());
		}
		$city_id = isset($this->request->get['city_id']) ? $this->request->get['city_id'] : '';
		$term = isset($this->request->get['term']) ? $this->request->get['term'] : '';
		$this->json($svc->searchStreets($city_id, $term));
	}

	public function save() {
		$json = array('success' => false);
		if ($this->request->server['REQUEST_METHOD'] == 'POST') {
			$data = $this->selectionFromPost();
			$this->session->data['ukrposhta'] = $data;
			$json['success'] = true;
			$json['data'] = $data;
		}
		$this->json($json);
	}

	public function recalc() {
		$this->load->language('extension/shipping/ukrposhta');
		$postcode = isset($this->request->get['postcode']) ? preg_replace('/\D+/', '', $this->request->get['postcode']) : '';
		if (!isset($this->session->data['ukrposhta']) || !is_array($this->session->data['ukrposhta'])) {
			$this->session->data['ukrposhta'] = array();
		}
		if ($postcode !== '') {
			$this->session->data['ukrposhta']['postcode'] = $postcode;
		}
		$address = isset($this->session->data['shipping_address']) ? $this->session->data['shipping_address'] : array('country_id' => 0, 'zone_id' => 0, 'postcode' => $postcode);
		$this->load->model('extension/shipping/ukrposhta');
		$quote = $this->model_extension_shipping_ukrposhta->getQuote($address);
		$out = array();
		if (!empty($quote['quote'])) {
			foreach ($quote['quote'] as $code => $row) {
				$out[$code] = array('text' => $row['text'], 'cost' => $row['cost'], 'title' => $row['title']);
			}
		}
		$this->json(array('quotes' => $out));
	}

	public function fields() {
		$this->load->language('extension/shipping/ukrposhta');
		$data = array();
		foreach (array('text_city', 'text_office', 'text_postcode', 'text_street', 'text_house', 'text_apartment', 'text_select_city', 'text_select_office', 'text_select_street', 'error_city', 'error_office', 'error_door') as $key) {
			$data[$key] = $this->language->get($key);
		}
		$ukr = isset($this->session->data['ukrposhta']) ? $this->session->data['ukrposhta'] : array();
		$js = '';
		$file = DIR_APPLICATION . 'view/javascript/ukrposhta/checkout.js';
		if (is_file($file)) {
			$js = file_get_contents($file);
		}
		$data['checkout_js'] = $js;
		$data['cfg_json'] = json_encode(array(
			'citiesUrl' => str_replace('&', '&', $this->url->link('extension/shipping/ukrposhta/cities', '', true)),
			'officesUrl' => str_replace('&', '&', $this->url->link('extension/shipping/ukrposhta/offices', '', true)),
			'streetsUrl' => str_replace('&', '&', $this->url->link('extension/shipping/ukrposhta/streets', '', true)),
			'saveUrl' => str_replace('&', '&', $this->url->link('extension/shipping/ukrposhta/save', '', true)),
			'recalcUrl' => str_replace('&', '&', $this->url->link('extension/shipping/ukrposhta/recalc', '', true)),
			'selectOffice' => $data['text_select_office'],
			'errorCity' => $data['error_city'],
			'errorOffice' => $data['error_office'],
			'errorDoor' => $data['error_door'],
			'selected' => $ukr
		), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
		return $this->load->view('extension/shipping/ukrposhta_fields', $data);
	}

	public function validateShipping() {
		$this->load->language('extension/shipping/ukrposhta');
		$code = isset($this->request->post['shipping_method']) ? $this->request->post['shipping_method'] : '';
		if (strpos($code, 'ukrposhta.') !== 0) {
			return true;
		}
		$data = isset($this->session->data['ukrposhta']) ? $this->session->data['ukrposhta'] : array();
		if ($this->request->server['REQUEST_METHOD'] == 'POST' && !empty($this->request->post['ukrposhta_city_id'])) {
			$data = $this->selectionFromPost();
			$this->session->data['ukrposhta'] = $data;
		}
		$result = $this->svc()->validateSelection($data, $code);
		if ($result === true) {
			return true;
		}
		$key = 'error_' . $result;
		$text = $this->language->get($key);
		return ($text && $text !== $key) ? $text : $result;
	}

	public function persistOrder($order_id = 0) {
		if (!$order_id && isset($this->session->data['order_id'])) {
			$order_id = (int)$this->session->data['order_id'];
		}
		$code = '';
		if (!empty($this->session->data['shipping_method']['code'])) {
			$code = $this->session->data['shipping_method']['code'];
		}
		if (!$order_id || strpos($code, 'ukrposhta.') !== 0 || empty($this->session->data['ukrposhta'])) {
			return;
		}
		$data = $this->session->data['ukrposhta'];
		$data['method_code'] = $code;
		if ($this->svc()->validateSelection($data, $code) !== true) {
			return;
		}
		$this->svc()->saveCheckout($order_id, $data);
	}

	public function cron() {
		$expected = (string)$this->config->get('shipping_ukrposhta_cron_key');
		$key = isset($this->request->get['key']) ? (string)$this->request->get['key'] : '';
		if ($expected === '' || !hash_equals($expected, $key)) {
			$this->response->addHeader('HTTP/1.1 403 Forbidden');
			$this->response->setOutput('Forbidden');
			return;
		}
		$svc = $this->svc();
		$task = isset($this->request->get['task']) ? $this->request->get['task'] : 'track';
		if ($task === 'queue') {
			$result = $svc->cronQueue();
			$result['done'] = true;
		} elseif ($task === 'cache') {
			$svc->cacheClear();
			$result = array('cleared' => true, 'done' => true);
		} else {
			$result = $svc->cronTrack();
			$result['done'] = empty($result['checked']) || (int)$result['checked'] < 40;
		}
		$this->json($result);
	}

	private function selectionFromPost() {
		$src = $this->request->post;
		return array(
			'method_code' => isset($src['method_code']) ? $src['method_code'] : (isset($src['shipping_method']) ? $src['shipping_method'] : ''),
			'city_id' => isset($src['city_id']) ? $src['city_id'] : (isset($src['ukrposhta_city_id']) ? $src['ukrposhta_city_id'] : ''),
			'city_name' => isset($src['city_name']) ? $src['city_name'] : (isset($src['ukrposhta_city']) ? $src['ukrposhta_city'] : ''),
			'region_name' => isset($src['region_name']) ? $src['region_name'] : '',
			'postcode' => isset($src['postcode']) ? preg_replace('/\D+/', '', $src['postcode']) : '',
			'office_id' => isset($src['office_id']) ? $src['office_id'] : '',
			'office_name' => isset($src['office_name']) ? $src['office_name'] : '',
			'street' => isset($src['street']) ? $src['street'] : '',
			'house' => isset($src['house']) ? $src['house'] : '',
			'apartment' => isset($src['apartment']) ? $src['apartment'] : ''
		);
	}
}
