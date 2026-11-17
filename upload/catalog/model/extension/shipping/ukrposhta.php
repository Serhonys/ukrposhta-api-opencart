<?php
class ModelExtensionShippingUkrposhta extends Model {
	public function getQuote($address) {
		require_once DIR_SYSTEM . 'library/ukrposhta/service.php';
		$service = new UkrposhtaService($this->registry);
		return $service->getQuote($address);
	}
}
