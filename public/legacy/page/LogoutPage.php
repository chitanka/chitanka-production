<?php
class LogoutPage extends Page {
	public function __construct() {
		$this->redirectLegacy('signout');
	}
}
