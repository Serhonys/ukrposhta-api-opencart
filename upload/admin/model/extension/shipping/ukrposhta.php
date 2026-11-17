<?php
class ModelExtensionShippingUkrposhta extends Model {
	private function svc() {
		require_once DIR_SYSTEM . 'library/ukrposhta/service.php';
		return new UkrposhtaService($this->registry);
	}

	public function install() {
		$this->svc()->install();
	}

	public function uninstall() {
	}
}
