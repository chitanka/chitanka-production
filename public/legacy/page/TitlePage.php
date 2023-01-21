<?php
class TitlePage extends ViewPage {
	public function __construct() {
		parent::__construct();
		$type = $this->request->value('type', '');
		if ( ! empty($type) ) {
			$this->redirectLegacy('texts/type/' . $type);
		}

		$this->redirectLegacy('texts');
	}

	public function makeSimpleList() {}
	public function makeExtendedList() {}
}
