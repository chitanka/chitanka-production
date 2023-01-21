<?php
class SearchPage extends Page {
	public function __construct() {
		parent::__construct();
		$this->redirectLegacy('search?q='.urlencode($this->startwith));
	}
}
