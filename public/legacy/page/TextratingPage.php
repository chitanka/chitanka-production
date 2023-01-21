<?php
class TextratingPage extends Page {
	public function __construct() {
		parent::__construct();
		$username = $this->request->value('user');
		if ( ! empty( $username ) ) {
			$this->redirectLegacy('user/'.urlencode($username).'/ratings');
		}
		$textId = (int) $this->request->value('textId', 0, 1);
		$this->redirectLegacy('text/'.$textId.'/ratings');
	}
}
