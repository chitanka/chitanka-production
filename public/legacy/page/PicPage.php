<?php
class PicPage extends Page {

	protected $idOffset = 700;

	protected function buildContent() {
		$textId = $this->request->value('textId', 0, 1);

		if ( empty($textId) ) {
			$this->redirectLegacy('books');
		}

		$textId += $this->idOffset;
		$this->redirectLegacy('book/' . $textId);
	}
}
