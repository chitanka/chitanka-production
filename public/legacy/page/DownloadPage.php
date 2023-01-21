<?php

class DownloadPage extends Page {
	public function __construct() {
		parent::__construct();

		$textId = $this->request->value('textId', null, 1);
		$id .= is_array($textId) ? implode(',', $textId) : $textId;
		$this->redirectLegacy("text/$id.sfb.zip");

	}
}
