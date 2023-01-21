<?php
class InfoPage extends Page {
	public function __construct() {
		parent::__construct();
		$term = $this->request->value('term', NULL, 1);
		$this->redirectLegacy('person/'.urlencode($term));
	}
}
