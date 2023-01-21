<?php
class EmailUserPage extends Page {
	public function __construct() {
		parent::__construct();
		$username = $this->request->value('username', '', 1);
		$this->redirectLegacy('user/'.urlencode($username).'/email');
	}
}
