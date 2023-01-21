<?php

class SandboxPage extends Page
{
	public function __construct() {
		$this->redirectLegacy('sandbox');
	}
}
