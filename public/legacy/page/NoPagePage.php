<?php
class NoPagePage extends Page {
	public function __construct() {
		$this->redirectLegacy('');
	}
}
