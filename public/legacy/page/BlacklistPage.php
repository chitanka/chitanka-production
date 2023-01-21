<?php

class BlacklistPage extends Page
{
	public function __construct() {
		$this->redirectLegacy('blacklist');
	}
}
