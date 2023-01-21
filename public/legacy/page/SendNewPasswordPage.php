<?php
class SendNewPasswordPage extends Page {
	public function __construct() {
		$this->redirectLegacy('request-password');
	}
}
