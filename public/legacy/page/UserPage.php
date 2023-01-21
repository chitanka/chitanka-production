<?php
class UserPage extends Page {
	public function __construct() {
		parent::__construct();
		$username = $this->request->value('username', null, 1);
		if ($username) {
			$this->redirectLegacy('user/'.urlencode($username));
		}
	}
}
