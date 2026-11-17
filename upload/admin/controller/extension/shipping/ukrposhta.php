<?php
class ControllerExtensionShippingUkrposhta extends Controller {
	private $lang = array();

	private function svc() {
		require_once DIR_SYSTEM . 'library/ukrposhta/service.php';
		return new UkrposhtaService($this->registry);
	}

	private function tokenKey() {
		return isset($this->session->data['user_token']) ? 'user_token' : 'token';
	}

	private function token() {
		$key = $this->tokenKey();
		return isset($this->session->data[$key]) ? $this->session->data[$key] : '';
	}

	private function tq() {
		return $this->tokenKey() . '=' . $this->token();
	}

	private function uiLang() {
		if (isset($this->request->get['lang']) && in_array($this->request->get['lang'], array('uk', 'ru'), true)) {
			$this->session->data['ukrposhta_lang'] = $this->request->get['lang'];
		}
		if (!empty($this->session->data['ukrposhta_lang'])) {
			return $this->session->data['ukrposhta_lang'];
		}
		$code = (string)$this->config->get('config_admin_language');
		return strpos($code, 'ru') === 0 ? 'ru' : 'uk';
	}

	private function loadUi() {
		$lang = $this->uiLang();
		$files = $lang === 'ru'
			? array('ru-ru', 'russian')
			: array('uk-ua', 'ukrainian', 'ua-ua');
		$_ = array();
		foreach ($files as $base) {
			$file = DIR_LANGUAGE . $base . '/extension/shipping/ukrposhta.php';
			if (is_file($file)) {
				require $file;
				break;
			}
		}
		$this->lang = $_;
		return $_;
	}

	private function t($key) {
		return isset($this->lang[$key]) ? $this->lang[$key] : $key;
	}

	private function link($route, $extra = '') {
		$query = $this->tq() . '&lang=' . $this->uiLang();
		if ($extra !== '') {
			$query .= '&' . ltrim($extra, '&');
		}
		return $this->url->link($route, $query, true);
	}

	private function guard($modify = false) {
		if (!$this->user->isLogged()) {
			$this->response->redirect($this->url->link('common/login', '', true));
			return false;
		}
		$perm = $modify ? 'modify' : 'access';
		if (!$this->user->hasPermission($perm, 'extension/shipping/ukrposhta')) {
			return false;
		}
		return true;
	}

	private function json($data) {
		$this->response->addHeader('Content-Type: application/json; charset=utf-8');
		$this->response->setOutput(json_encode($data, JSON_UNESCAPED_UNICODE));
	}

	private function human($code) {
		$map = array('city' => 'error_city', 'office' => 'error_office', 'door' => 'error_door');
		if (isset($map[$code])) {
			return $this->t($map[$code]);
		}
		return $code;
	}

	private function upgrade() {
		if ($this->config->get('shipping_ukrposhta_version') === '2.0.0') {
			return;
		}
		$svc = $this->svc();
		$svc->install();
		$defaults = $svc->defaults();
		$defaults['shipping_ukrposhta_version'] = '2.0.0';
		foreach ($defaults as $key => $value) {
			if ($key === 'shipping_ukrposhta_cron_key' && $value === '') {
				$value = bin2hex(random_bytes(16));
			}
			$exists = $this->db->query("SELECT setting_id FROM `" . DB_PREFIX . "setting` WHERE store_id = 0 AND `key` = '" . $this->db->escape($key) . "'");
			if (!$exists->num_rows) {
				$this->db->query("INSERT INTO `" . DB_PREFIX . "setting` SET store_id = 0, `code` = 'shipping_ukrposhta', `key` = '" . $this->db->escape($key) . "', `value` = '" . $this->db->escape((string)$value) . "', serialized = 0");
			}
		}
		$this->config->set('shipping_ukrposhta_version', '2.0.0');
	}

	private function layout($data) {
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		return $data;
	}

	private function settingsKeys() {
		return array(
			'shipping_ukrposhta_status', 'shipping_ukrposhta_sort_order', 'shipping_ukrposhta_geo_zone_id', 'shipping_ukrposhta_tax_class_id',
			'shipping_ukrposhta_sandbox', 'shipping_ukrposhta_debug', 'shipping_ukrposhta_timeout', 'shipping_ukrposhta_cron_key', 'shipping_ukrposhta_status_on_create',
			'shipping_ukrposhta_api_calc', 'shipping_ukrposhta_fallback_cost', 'shipping_ukrposhta_hide_without_price', 'shipping_ukrposhta_free_total',
			'shipping_ukrposhta_min_order', 'shipping_ukrposhta_max_order', 'shipping_ukrposhta_cache_min',
			'shipping_ukrposhta_default_weight', 'shipping_ukrposhta_min_weight', 'shipping_ukrposhta_default_length', 'shipping_ukrposhta_default_width', 'shipping_ukrposhta_default_height',
			'shipping_ukrposhta_bearer_ecom', 'shipping_ukrposhta_bearer_tracking', 'shipping_ukrposhta_token', 'shipping_ukrposhta_sender_uuid', 'shipping_ukrposhta_sender_postcode',
			'shipping_ukrposhta_description', 'shipping_ukrposhta_default_middlename', 'shipping_ukrposhta_paid_by_recipient', 'shipping_ukrposhta_postpay_enabled',
			'shipping_ukrposhta_cod_payment_codes', 'shipping_ukrposhta_sms', 'shipping_ukrposhta_check_on_delivery',
			'shipping_ukrposhta_method_express_w2w', 'shipping_ukrposhta_method_express_w2d', 'shipping_ukrposhta_method_standard_w2w', 'shipping_ukrposhta_method_standard_w2d',
			'shipping_ukrposhta_title_express_w2w_uk', 'shipping_ukrposhta_title_express_w2w_ru',
			'shipping_ukrposhta_title_express_w2d_uk', 'shipping_ukrposhta_title_express_w2d_ru',
			'shipping_ukrposhta_title_standard_w2w_uk', 'shipping_ukrposhta_title_standard_w2w_ru',
			'shipping_ukrposhta_title_standard_w2d_uk', 'shipping_ukrposhta_title_standard_w2d_ru',
			'shipping_ukrposhta_customer_notify', 'shipping_ukrposhta_mail_subject_uk', 'shipping_ukrposhta_mail_body_uk',
			'shipping_ukrposhta_mail_subject_ru', 'shipping_ukrposhta_mail_body_ru',
			'shipping_ukrposhta_seller_notify', 'shipping_ukrposhta_seller_email', 'shipping_ukrposhta_seller_lang', 'shipping_ukrposhta_seller_on_create',
			'shipping_ukrposhta_telegram_token', 'shipping_ukrposhta_telegram_chat'
		);
	}

	public function index() {
		if (!$this->guard()) {
			$this->loadUi();
			$this->session->data['error'] = $this->t('error_permission');
			$this->response->redirect($this->link('marketplace/extension', 'type=shipping'));
			return;
		}
		$this->upgrade();
		$data = $this->loadUi();
		$this->load->model('setting/setting');

		if ($this->request->server['REQUEST_METHOD'] == 'POST' && $this->user->hasPermission('modify', 'extension/shipping/ukrposhta')) {
			$post = $this->request->post;
			foreach (array('express_w2w', 'express_w2d', 'standard_w2w', 'standard_w2d') as $code) {
				$key = 'shipping_ukrposhta_method_' . $code;
				$post[$key] = empty($post[$key]) ? 0 : 1;
			}
			if (!empty($post['regen_cron'])) {
				$post['shipping_ukrposhta_cron_key'] = bin2hex(random_bytes(16));
			}
			unset($post['regen_cron']);
			$map = array();
			if (!empty($post['status_map']) && is_array($post['status_map'])) {
				foreach ($post['status_map'] as $code => $rule) {
					$map[preg_replace('/\D+/', '', (string)$code)] = array(
						'order_status_id' => isset($rule['order_status_id']) ? (int)$rule['order_status_id'] : 0,
						'notify_customer' => !empty($rule['notify_customer']) ? 1 : 0,
						'notify_seller' => !empty($rule['notify_seller']) ? 1 : 0
					);
				}
			}
			unset($post['status_map']);
			$post['shipping_ukrposhta_status_map'] = json_encode($map, JSON_UNESCAPED_UNICODE);
			$post['shipping_ukrposhta_version'] = '2.0.0';
			$this->model_setting_setting->editSetting('shipping_ukrposhta', $post);
			$this->session->data['success'] = $this->t('text_success');
			$this->response->redirect($this->link('extension/shipping/ukrposhta'));
			return;
		}

		$data['error_warning'] = '';
		if (!$this->user->hasPermission('modify', 'extension/shipping/ukrposhta')) {
			$data['error_warning'] = $this->t('error_permission');
		}
		$data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
		unset($this->session->data['success']);

		foreach ($this->settingsKeys() as $key) {
			$data[$key] = isset($this->request->post[$key]) ? $this->request->post[$key] : $this->config->get($key);
		}
		$svc = $this->svc();
		$data['status_map'] = $svc->statusMap();
		$data['events'] = $svc->trackingEvents();
		$data['ui_lang'] = $this->uiLang();

		$this->load->model('localisation/tax_class');
		$this->load->model('localisation/geo_zone');
		$this->load->model('localisation/order_status');
		$data['tax_classes'] = $this->model_localisation_tax_class->getTaxClasses();
		$data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();
		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
		$data['logs'] = $svc->getLogs(80);
		$data['cache_stats'] = $svc->cacheStats();
		$data['pending'] = $svc->pendingCount();

		$catalog = $this->config->get('config_secure') ? HTTPS_CATALOG : HTTP_CATALOG;
		$key = (string)$data['shipping_ukrposhta_cron_key'];
		$data['cron_track'] = $catalog . 'index.php?route=extension/shipping/ukrposhta/cron&task=track&key=' . $key;
		$data['cron_queue'] = $catalog . 'index.php?route=extension/shipping/ukrposhta/cron&task=queue&key=' . $key;

		$data['action'] = $this->link('extension/shipping/ukrposhta');
		$data['cancel'] = $this->extensionsLink();
		$data['shipments'] = $this->link('extension/shipping/ukrposhta/shipments');
		$data['lang_uk'] = $this->url->link('extension/shipping/ukrposhta', $this->tq() . '&lang=uk', true);
		$data['lang_ru'] = $this->url->link('extension/shipping/ukrposhta', $this->tq() . '&lang=ru', true);
		$data['test_url'] = $this->link('extension/shipping/ukrposhta/testApi');
		$data['cache_url'] = $this->link('extension/shipping/ukrposhta/clearCache');
		$data['log_url'] = $this->link('extension/shipping/ukrposhta/clearLog');
		$data['purge_url'] = $this->link('extension/shipping/ukrposhta/purge');
		$data['breadcrumbs'] = array(
			array('text' => $this->t('text_home'), 'href' => $this->url->link('common/dashboard', $this->tq(), true)),
			array('text' => $this->t('text_extension'), 'href' => $data['cancel']),
			array('text' => $this->t('heading_title') . ' ' . $this->t('text_version'), 'href' => $data['action'])
		);

		$this->response->setOutput($this->load->view('extension/shipping/ukrposhta', $this->layout($data)));
	}

	private function extensionsLink() {
		$route = isset($this->session->data['user_token']) ? 'marketplace/extension' : 'extension/extension';
		return $this->url->link($route, $this->tq() . '&type=shipping', true);
	}

	public function install() {
		$this->load->model('extension/shipping/ukrposhta');
		$this->model_extension_shipping_ukrposhta->install();
		$this->upgrade();
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/shipping/ukrposhta');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/shipping/ukrposhta');
	}

	public function uninstall() {
	}

	public function testApi() {
		$this->loadUi();
		if (!$this->guard()) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$result = $this->svc()->testConnection();
		if (!empty($result['success'])) {
			return $this->json(array('success' => $result['message']));
		}
		return $this->json(array('error' => isset($result['error']) ? $result['error'] : 'Error'));
	}

	public function clearCache() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$this->svc()->cacheClear();
		return $this->json(array('success' => true));
	}

	public function clearLog() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$this->svc()->clearLog();
		return $this->json(array('success' => true));
	}

	public function purge() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$this->svc()->purge();
		$this->db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'shipping_ukrposhta_version'");
		return $this->json(array('success' => true));
	}

	public function cities() {
		if (!$this->guard()) {
			return $this->json(array());
		}
		$term = isset($this->request->get['term']) ? $this->request->get['term'] : '';
		$this->json($this->svc()->searchCities($term));
	}

	public function offices() {
		if (!$this->guard()) {
			return $this->json(array());
		}
		$city_id = isset($this->request->get['city_id']) ? $this->request->get['city_id'] : '';
		$this->json($city_id ? $this->svc()->getOffices($city_id) : array());
	}

	public function streets() {
		if (!$this->guard()) {
			return $this->json(array());
		}
		$city_id = isset($this->request->get['city_id']) ? $this->request->get['city_id'] : '';
		$term = isset($this->request->get['term']) ? $this->request->get['term'] : '';
		$this->json($this->svc()->searchStreets($city_id, $term));
	}

	public function order() {
		$this->loadUi();
		if (!$this->user->isLogged()) {
			return '';
		}
		$svc = $this->svc();
		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
		$order = $svc->getOrder($order_id);
		if (!$order) {
			return '';
		}
		$order_data = $svc->getOrderData($order_id);
		$shipments = $svc->getShipmentsByOrder($order_id);
		$is = (strpos((string)$order['shipping_code'], 'ukrposhta') === 0) || $order_data || $shipments;
		if (!$is) {
			return '';
		}
		$data = $this->lang;
		$data['order_id'] = $order_id;
		$data['order_data'] = $order_data;
		$data['shipments'] = $shipments;
		$data['open'] = $this->link('extension/shipping/ukrposhta/shipment', 'order_id=' . $order_id);
		$data['create_url'] = $this->link('extension/shipping/ukrposhta/quickCreate', 'order_id=' . $order_id);
		$data['delete_url'] = $this->link('extension/shipping/ukrposhta/deleteShipment');
		$data['sticker_url'] = $this->link('extension/shipping/ukrposhta/sticker');
		$data['track_url'] = $this->link('extension/shipping/ukrposhta/track');
		$data['form_base'] = $this->link('extension/shipping/ukrposhta/shipment');
		return $this->load->view('extension/shipping/ukrposhta_order', $data);
	}

	public function shipments() {
		if (!$this->guard()) {
			$this->loadUi();
			$this->response->redirect($this->extensionsLink());
			return;
		}
		$this->upgrade();
		$data = $this->loadUi();
		$svc = $this->svc();
		$src = array_merge($this->request->get, $this->request->post);
		$filter = array(
			'filter_order_id' => !empty($src['filter_order_id']) ? (int)$src['filter_order_id'] : 0,
			'filter_barcode' => isset($src['filter_barcode']) ? $src['filter_barcode'] : '',
			'filter_status' => isset($src['filter_status']) ? $src['filter_status'] : ''
		);
		$page = isset($this->request->get['page']) ? max(1, (int)$this->request->get['page']) : 1;
		$limit = 20;
		$filter['start'] = ($page - 1) * $limit;
		$filter['limit'] = $limit;
		$total = $svc->countShipments($filter);
		$data['shipments'] = $svc->getShipments($filter);
		$data['groups'] = $svc->getGroups();
		$data['filter'] = $filter;
		$data['pages'] = (int)ceil($total / $limit);
		$data['page'] = $page;
		$data['settings'] = $this->link('extension/shipping/ukrposhta');
		$data['action'] = $this->link('extension/shipping/ukrposhta/shipments');
		$data['create'] = $this->link('extension/shipping/ukrposhta/shipment');
		$data['group_url'] = $this->link('extension/shipping/ukrposhta/groupCreate');
		$data['attach_url'] = $this->link('extension/shipping/ukrposhta/groupAttach');
		$data['form_url'] = $this->link('extension/shipping/ukrposhta/groupForm');
		$data['gsticker_url'] = $this->link('extension/shipping/ukrposhta/groupSticker');
		$data['sticker_url'] = $this->link('extension/shipping/ukrposhta/sticker');
		$data['track_url'] = $this->link('extension/shipping/ukrposhta/track');
		$data['delete_url'] = $this->link('extension/shipping/ukrposhta/deleteShipment');
		$data['edit_base'] = $this->link('extension/shipping/ukrposhta/shipment');
		$data['ui_lang'] = $this->uiLang();
		$data['lang_uk'] = $this->url->link('extension/shipping/ukrposhta/shipments', $this->tq() . '&lang=uk', true);
		$data['lang_ru'] = $this->url->link('extension/shipping/ukrposhta/shipments', $this->tq() . '&lang=ru', true);
		$data['pagination'] = array();
		for ($i = 1; $i <= $data['pages']; $i++) {
			$data['pagination'][] = array('n' => $i, 'href' => $this->link('extension/shipping/ukrposhta/shipments', 'page=' . $i));
		}
		$this->response->setOutput($this->load->view('extension/shipping/ukrposhta_cn_list', $this->layout($data)));
	}

	public function shipment() {
		if (!$this->guard()) {
			$this->response->redirect($this->extensionsLink());
			return;
		}
		$this->upgrade();
		$data = $this->loadUi();
		$svc = $this->svc();
		$id = isset($this->request->get['shipment_id']) ? (int)$this->request->get['shipment_id'] : 0;
		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
		$row = $id ? $svc->getShipment($id) : array();
		if (!$row && $order_id) {
			$prepared = $svc->prepareFromOrder($order_id);
			if (empty($prepared['error'])) {
				$row = $prepared;
			} else {
				$data['error_warning'] = $prepared['error'];
				$row = array('order_id' => $order_id);
			}
		}
		if (!$row) {
			$dims_w = (int)$this->config->get('shipping_ukrposhta_default_weight');
			$row = array(
				'order_id' => 0,
				'type' => 'EXPRESS',
				'delivery_type' => 'W2W',
				'weight' => $dims_w ? $dims_w : 500,
				'length' => (int)$this->config->get('shipping_ukrposhta_default_length') ?: 30,
				'width' => (int)$this->config->get('shipping_ukrposhta_default_width') ?: 20,
				'height' => (int)$this->config->get('shipping_ukrposhta_default_height') ?: 10,
				'declared_price' => 0,
				'postpay' => 0,
				'sms' => $this->config->get('shipping_ukrposhta_sms') ? 1 : 0,
				'check_on_delivery' => $this->config->get('shipping_ukrposhta_check_on_delivery') ? 1 : 0,
				'paid_by_recipient' => $this->config->get('shipping_ukrposhta_paid_by_recipient') ? 1 : 0,
				'is_draft' => 1
			);
		}
		$data['cn'] = $row;
		$data['locked'] = !empty($row['uuid']);
		$data['error_warning'] = isset($data['error_warning']) ? $data['error_warning'] : '';
		if (!empty($this->session->data['error_warning'])) {
			$data['error_warning'] = $this->human($this->session->data['error_warning']);
			unset($this->session->data['error_warning']);
		}
		$data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
		unset($this->session->data['success']);
		$data['action'] = $this->link('extension/shipping/ukrposhta/saveShipment', $id ? 'shipment_id=' . $id : '');
		$data['back'] = $this->link('extension/shipping/ukrposhta/shipments');
		$data['settings'] = $this->link('extension/shipping/ukrposhta');
		$data['cities_url'] = $this->link('extension/shipping/ukrposhta/cities');
		$data['offices_url'] = $this->link('extension/shipping/ukrposhta/offices');
		$data['streets_url'] = $this->link('extension/shipping/ukrposhta/streets');
		$data['fill_url'] = $this->link('extension/shipping/ukrposhta/shipment');
		$data['sticker_url'] = $this->link('extension/shipping/ukrposhta/sticker', 'shipment_id=' . (int)$id);
		$data['track_url'] = $this->link('extension/shipping/ukrposhta/track', 'shipment_id=' . (int)$id);
		$data['ui_lang'] = $this->uiLang();
		$data['lang_uk'] = $this->url->link('extension/shipping/ukrposhta/shipment', $this->tq() . '&lang=uk' . ($id ? '&shipment_id=' . $id : '') . ($order_id ? '&order_id=' . $order_id : ''), true);
		$data['lang_ru'] = $this->url->link('extension/shipping/ukrposhta/shipment', $this->tq() . '&lang=ru' . ($id ? '&shipment_id=' . $id : '') . ($order_id ? '&order_id=' . $order_id : ''), true);
		$this->response->setOutput($this->load->view('extension/shipping/ukrposhta_cn_form', $this->layout($data)));
	}

	public function saveShipment() {
		$this->loadUi();
		if (!$this->guard(true)) {
			$this->response->redirect($this->extensionsLink());
			return;
		}
		$svc = $this->svc();
		$id = isset($this->request->get['shipment_id']) ? (int)$this->request->get['shipment_id'] : 0;
		$data = $this->shipmentFromPost();
		$saved = $svc->saveShipment($data, $id);
		if (!empty($this->request->post['create'])) {
			$result = $svc->pushShipment($saved);
			if (empty($result['success'])) {
				$this->session->data['success'] = '';
				$this->session->data['error_warning'] = $this->human(isset($result['error']) ? $result['error'] : 'Error');
				$this->response->redirect($this->link('extension/shipping/ukrposhta/shipment', 'shipment_id=' . $saved));
				return;
			}
			$this->session->data['success'] = $result['barcode'];
			$this->response->redirect($this->link('extension/shipping/ukrposhta/shipment', 'shipment_id=' . $saved));
			return;
		}
		$this->session->data['success'] = $this->t('text_draft');
		$this->response->redirect($this->link('extension/shipping/ukrposhta/shipment', 'shipment_id=' . $saved));
	}

	private function shipmentFromPost() {
		$p = $this->request->post;
		$places = array();
		if (!empty($p['places']) && is_array($p['places'])) {
			foreach ($p['places'] as $place) {
				$places[] = array(
					'weight' => isset($place['weight']) ? (int)$place['weight'] : 0,
					'length' => isset($place['length']) ? (int)$place['length'] : 0,
					'width' => isset($place['width']) ? (int)$place['width'] : 0,
					'height' => isset($place['height']) ? (int)$place['height'] : 0,
					'declared' => isset($place['declared']) ? (float)$place['declared'] : 0
				);
			}
		}
		return array(
			'order_id' => isset($p['order_id']) ? (int)$p['order_id'] : 0,
			'type' => isset($p['type']) ? $p['type'] : 'EXPRESS',
			'delivery_type' => isset($p['delivery_type']) ? $p['delivery_type'] : 'W2W',
			'first_name' => isset($p['first_name']) ? $p['first_name'] : '',
			'last_name' => isset($p['last_name']) ? $p['last_name'] : '',
			'middle_name' => isset($p['middle_name']) ? $p['middle_name'] : '',
			'recipient_phone' => isset($p['recipient_phone']) ? $p['recipient_phone'] : '',
			'recipient_email' => isset($p['recipient_email']) ? $p['recipient_email'] : '',
			'city_id' => isset($p['city_id']) ? $p['city_id'] : '',
			'city_name' => isset($p['city_name']) ? $p['city_name'] : '',
			'region_name' => isset($p['region_name']) ? $p['region_name'] : '',
			'postcode' => isset($p['postcode']) ? preg_replace('/\D+/', '', $p['postcode']) : '',
			'office_id' => isset($p['office_id']) ? $p['office_id'] : '',
			'office_name' => isset($p['office_name']) ? $p['office_name'] : '',
			'street' => isset($p['street']) ? $p['street'] : '',
			'house' => isset($p['house']) ? $p['house'] : '',
			'apartment' => isset($p['apartment']) ? $p['apartment'] : '',
			'weight' => isset($p['weight']) ? (int)$p['weight'] : 0,
			'length' => isset($p['length']) ? (int)$p['length'] : 0,
			'width' => isset($p['width']) ? (int)$p['width'] : 0,
			'height' => isset($p['height']) ? (int)$p['height'] : 0,
			'declared_price' => isset($p['declared_price']) ? (float)$p['declared_price'] : 0,
			'postpay' => isset($p['postpay']) ? (float)$p['postpay'] : 0,
			'paid_by_recipient' => !empty($p['paid_by_recipient']),
			'sms' => !empty($p['sms']),
			'check_on_delivery' => !empty($p['check_on_delivery']),
			'comment' => isset($p['comment']) ? $p['comment'] : '',
			'places' => $places
		);
	}

	public function quickCreate() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$order_id = isset($this->request->get['order_id']) ? (int)$this->request->get['order_id'] : 0;
		$result = $this->svc()->quickCreate($order_id, !empty($this->request->post['force']));
		if (!empty($result['success'])) {
			return $this->json(array('success' => $result['barcode'], 'barcode' => $result['barcode']));
		}
		return $this->json(array('error' => $this->human(isset($result['error']) ? $result['error'] : 'Error')));
	}

	public function deleteShipment() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$id = isset($this->request->get['shipment_id']) ? (int)$this->request->get['shipment_id'] : 0;
		$result = $this->svc()->deleteShipment($id);
		if (!empty($result['success'])) {
			return $this->json(array('success' => $this->t('button_delete')));
		}
		return $this->json(array('error' => isset($result['error']) ? $result['error'] : 'Error'));
	}

	public function sticker() {
		$this->loadUi();
		if (!$this->guard()) {
			$this->response->setOutput($this->t('error_permission'));
			return;
		}
		$id = isset($this->request->get['shipment_id']) ? (int)$this->request->get['shipment_id'] : 0;
		$row = $this->svc()->getShipment($id);
		if (!$row || ($row['uuid'] === '' && $row['barcode'] === '')) {
			$this->response->setOutput('Shipment not found');
			return;
		}
		$api = $this->svc()->getApi();
		$key = $row['uuid'] ? $row['uuid'] : $row['barcode'];
		$size = isset($this->request->get['size']) ? $this->request->get['size'] : '';
		$pdf = $api->getSticker($key, $size);
		if ($pdf === false) {
			$this->response->setOutput('Sticker: ' . $api->getLastError());
			return;
		}
		$name = preg_replace('/\W+/', '', $row['barcode'] ? $row['barcode'] : $key);
		$this->response->addHeader('Content-Type: application/pdf');
		$this->response->addHeader('Content-Disposition: inline; filename="ukrposhta-' . $name . '.pdf"');
		$this->response->setOutput($pdf);
	}

	public function track() {
		$this->loadUi();
		if (!$this->guard()) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$id = isset($this->request->get['shipment_id']) ? (int)$this->request->get['shipment_id'] : 0;
		$result = $this->svc()->trackShipment($id);
		$ajax = !empty($this->request->get['ajax']) || !empty($this->request->server['HTTP_X_REQUESTED_WITH']);
		if (!$ajax) {
			$this->session->data[!empty($result['success']) ? 'success' : 'error_warning'] = !empty($result['success']) ? $result['status'] : (isset($result['error']) ? $result['error'] : 'Error');
			$this->response->redirect($this->link('extension/shipping/ukrposhta/shipment', 'shipment_id=' . $id));
			return;
		}
		if (!empty($result['success'])) {
			return $this->json(array('success' => $result['status']));
		}
		return $this->json(array('error' => isset($result['error']) ? $result['error'] : 'Error'));
	}

	public function groupCreate() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$name = isset($this->request->post['name']) ? $this->request->post['name'] : '';
		$type = isset($this->request->post['type']) ? $this->request->post['type'] : 'EXPRESS';
		$result = $this->svc()->createGroup($name, $type);
		if (!empty($result['success'])) {
			return $this->json(array('success' => $result['uuid']));
		}
		return $this->json(array('error' => isset($result['error']) ? $result['error'] : 'Error'));
	}

	public function groupAttach() {
		$this->loadUi();
		if (!$this->guard(true)) {
			return $this->json(array('error' => $this->t('error_permission')));
		}
		$group_id = isset($this->request->post['group_id']) ? (int)$this->request->post['group_id'] : 0;
		$ids = isset($this->request->post['selected']) ? (array)$this->request->post['selected'] : array();
		$result = $this->svc()->attachToGroup($group_id, $ids);
		if (!empty($result['success'])) {
			return $this->json(array('success' => $result['count']));
		}
		return $this->json(array('error' => isset($result['error']) ? $result['error'] : 'Error'));
	}

	public function groupForm() {
		$this->pdfGroup('form');
	}

	public function groupSticker() {
		$this->pdfGroup('sticker');
	}

	private function pdfGroup($kind) {
		$this->loadUi();
		if (!$this->guard()) {
			$this->response->setOutput($this->t('error_permission'));
			return;
		}
		$group = $this->svc()->getGroup(isset($this->request->get['group_id']) ? (int)$this->request->get['group_id'] : 0);
		if (!$group || $group['uuid'] === '') {
			$this->response->setOutput('Group not found');
			return;
		}
		$api = $this->svc()->getApi();
		$pdf = $kind === 'form' ? $api->getGroupForm103a($group['uuid']) : $api->getGroupSticker($group['uuid']);
		if ($pdf === false) {
			$this->response->setOutput($api->getLastError());
			return;
		}
		$this->response->addHeader('Content-Type: application/pdf');
		$this->response->addHeader('Content-Disposition: inline; filename="ukrposhta-group-' . (int)$group['group_id'] . '.pdf"');
		$this->response->setOutput($pdf);
	}
}
