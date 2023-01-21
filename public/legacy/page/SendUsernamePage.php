<?php
class SendUsernamePage extends Page {
	public function __construct() {
		$this->redirectLegacy('request-username');
	}
}
